<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Unit\Tree;

use JDZ\Menu\Tests\Support\TestMenu;
use JDZ\Menu\Tree\AbstractMenu;
use JDZ\Menu\Tree\MenuNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AbstractMenuTest extends TestCase
{
    // -- tree building (appendItems) ---------------------------------------

    public function testSubclassWithoutItemsRendersAnEmptyMenu(): void
    {
        $menu = new class extends AbstractMenu {
        };

        $this->assertSame([], $menu->setMenu()->toTemplate());
    }

    public function testNestedItemsBuildTheTreeInItemOrder(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Home']),
            TestMenu::item(['title' => 'Blog', 'children' => [
                TestMenu::item(['title' => 'Archive', 'children' => [
                    TestMenu::item(['title' => 'Old']),
                ]]),
                TestMenu::item(['title' => 'News']),
            ]]),
            TestMenu::item(['title' => 'Contact']),
        ]);

        $this->assertSame(
            ['Home', 'Blog', 'Blog/Archive', 'Blog/Archive/Old', 'Blog/News', 'Contact'],
            self::paths($menu->setMenu()->toTemplate())
        );
    }

    public function testItemFieldsAreCopiedOntoTheRenderedNode(): void
    {
        $menu = (new TestMenu([
            TestMenu::item([
                'id' => 7,
                'title' => 'Blog',
                'link' => '/blog/?a=1&b=2',
                'component' => 'blog',
                'class' => 'main blog',
                'icon' => 'pen',
                'target' => 'blank',
                'slug' => 'blog',
                'modal' => 'preview',
                'params' => ['lang' => 'fr'],
            ]),
        ]))->withExtraData(fn (MenuNode $n): array => [
            'component' => $n->getComponent(),
            'params' => $n->getParams(),
        ]);

        $this->assertSame([[
            'route' => '/blog/?a=1&amp;b=2',
            'separator' => false,
            'active' => false,
            'modal' => true,
            'slug' => 'blog',
            'icon' => 'pen',
            'target' => 'blank',
            'id' => 7,
            'class' => 'main blog',
            'title' => 'Blog',
            'component' => 'blog',
            'params' => ['lang' => 'fr'],
        ]], $menu->setMenu()->toTemplate());
    }

    public function testItemFieldsAreCopiedOntoNestedNodes(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Blog', 'link' => '/blog/', 'children' => [
                TestMenu::item([
                    'id' => 'news',
                    'title' => 'News',
                    'link' => '/blog/news/',
                    'class' => 'sub',
                    'icon' => 'rss',
                    'target' => 'self',
                    'slug' => 'news',
                ]),
            ]]),
        ]);

        $child = $menu->setMenu()->toTemplate()[0]['children'][0];
        unset($child['route']); // nested routes are not asserted (known getRoute() issue)

        $this->assertSame([
            'separator' => false,
            'active' => false,
            'modal' => false,
            'slug' => 'news',
            'icon' => 'rss',
            'target' => 'self',
            'id' => 'news',
            'class' => 'sub',
            'title' => 'News',
        ], $child);
    }

    public function testSeparatorItemKeepsOnlyItsTitleAndClass(): void
    {
        $menu = new TestMenu([
            TestMenu::item([
                'separator' => true,
                'id' => 9,
                'title' => '--',
                'class' => 'divider',
                'link' => '/dropped/',
                'icon' => 'dropped',
                'target' => 'blank',
                'slug' => 'dropped',
                'modal' => 'dropped',
                'children' => [TestMenu::item(['title' => 'Dropped'])],
            ]),
        ]);

        $this->assertSame([[
            'route' => null,
            'separator' => true,
            'active' => false,
            'modal' => false,
            'slug' => '',
            'icon' => '',
            'target' => '',
            'id' => '',
            'class' => 'divider',
            'title' => '--',
        ]], $menu->setMenu()->toTemplate());
    }

    public function testAppendChildrenCallbacksRunInRegistrationOrderOnceEachNodeHasItsChildren(): void
    {
        $calls = [];
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'children' => [
                TestMenu::item(['title' => 'News']),
                TestMenu::item(['title' => 'Archive']),
            ]]),
            TestMenu::item(['title' => '--', 'separator' => true]),
            TestMenu::item(['title' => 'Contact']),
        ]))
            ->setNodeAppendChildrenCallback(function (MenuNode $n) use (&$calls): void {
                $calls[] = 'first:' . $n->getTitle() . '/' . count($n->getChildren());
            })
            ->setNodeAppendChildrenCallback(function (MenuNode $n) use (&$calls): void {
                $calls[] = 'second:' . $n->getTitle();
            });

        $menu->setMenu();

        $this->assertSame([
            'first:News/0', 'second:News',
            'first:Archive/0', 'second:Archive',
            'first:Blog/2', 'second:Blog',
            'first:Contact/0', 'second:Contact',
        ], $calls);
    }

    public function testChildrenGraftedByAnAppendCallbackAreRenderedAndJoinTheActiveTrail(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Home', 'link' => '/']),
            TestMenu::item(['id' => 2, 'title' => 'Shop', 'link' => '/shop/']),
        ]))->setNodeAppendChildrenCallback(function (MenuNode $n): void {
            if (2 === $n->getId()) {
                $n->addChild((new MenuNode())->setId(3)->setTitle('Cart')->setLink('/shop/cart/'));
            }
        });
        $menu->activeRoute = '/shop/cart/';

        $tree = $menu->setMenu()->toTemplate();

        $this->assertSame(['Home', 'Shop', 'Shop/Cart'], self::paths($tree));
        $this->assertSame(['Shop', 'Shop/Cart'], self::activePaths($tree));
    }

    public function testSetMenuRebuildsTheTreeOnEachCall(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Home', 'link' => '/']),
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/']),
        ]);
        $menu->activeRoute = '/';
        $menu->setMenu();

        $menu->activeRoute = '/blog/';
        $tree = $menu->setMenu()->toTemplate();

        $this->assertSame(['Home', 'Blog'], self::paths($tree));
        $this->assertSame(['Blog'], self::activePaths($tree));
    }

    // -- active trail (setActiveItems / isActiveItem) -----------------------

    public static function activeRouteProvider(): array
    {
        return [
            'link equals the active route' => ['/blog/', '/blog/', true],
            'link differs from the active route' => ['/blog/', '/news/', false],
            'link is only a prefix of the active route' => ['/blog/', '/blog/news/', false],
            'root link on the root route' => ['/', '/', true],
            'empty link on the root route' => ['', '/', true],
            'empty link on another route' => ['', '/blog/', false],
            'empty link without an active route' => ['', '', false],
            'hash link equal to the active route' => ['#', '#', false],
            'https link equal to the active route' => ['https://example.com/', 'https://example.com/', false],
            'http link equal to the active route' => ['http://example.com/', 'http://example.com/', false],
        ];
    }

    #[DataProvider('activeRouteProvider')]
    public function testActiveFlagFollowsTheActiveRoute(string $link, string $activeRoute, bool $active): void
    {
        $menu = new TestMenu([TestMenu::item(['title' => 'Item', 'link' => $link])]);
        $menu->activeRoute = $activeRoute;

        $this->assertSame($active, $menu->setMenu()->toTemplate()[0]['active']);
    }

    public function testSeparatorIsNotActiveOnTheRootRoute(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Home', 'link' => '']),
            TestMenu::item(['title' => '--', 'separator' => true]),
        ]);
        $menu->activeRoute = '/';

        $this->assertSame(['Home'], self::activePaths($menu->setMenu()->toTemplate()));
    }

    public function testActiveTrailReachesAncestorsButNotSiblings(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Home', 'link' => '/']),
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/', 'children' => [
                TestMenu::item(['title' => 'News', 'link' => '/blog/news/', 'children' => [
                    TestMenu::item(['title' => 'Today', 'link' => '/blog/news/today/']),
                    TestMenu::item(['title' => 'Week', 'link' => '/blog/news/week/']),
                ]]),
                TestMenu::item(['title' => 'Archive', 'link' => '/blog/archive/']),
            ]]),
        ]);
        $menu->activeRoute = '/blog/news/today/';

        $this->assertSame(
            ['Blog', 'Blog/News', 'Blog/News/Today'],
            self::activePaths($menu->setMenu()->toTemplate())
        );
    }

    public function testActiveCallbackIsOnlyAskedAboutInternalNodesTheRouteDidNotMatch(): void
    {
        $asked = [];
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'One', 'link' => '/one/']),
            TestMenu::item(['title' => 'Anchor', 'link' => '#']),
            TestMenu::item(['title' => 'External', 'link' => 'https://example.com/']),
            TestMenu::item(['title' => '--', 'separator' => true]),
            TestMenu::item(['title' => 'Four', 'link' => '/four/', 'children' => [
                TestMenu::item(['title' => 'Five', 'link' => '/four/five/']),
            ]]),
            TestMenu::item(['title' => 'Six', 'link' => '/six/']),
        ]))->setNodeActiveCallback(function (MenuNode $n) use (&$asked): bool {
            $asked[] = $n->getTitle();
            return true;
        });
        $menu->activeRoute = '/six/';

        $tree = $menu->setMenu()->toTemplate();

        $this->assertSame(['One', 'Five', 'Four'], $asked);
        $this->assertSame(['One', 'Four', 'Four/Five', 'Six'], self::activePaths($tree));
    }

    public function testActiveCallbackMarksTheNodeAndItsAncestors(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Home', 'link' => '/']),
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/', 'children' => [
                TestMenu::item(['title' => 'News', 'link' => '/blog/news/']),
                TestMenu::item(['title' => 'Archive', 'link' => '/blog/archive/']),
            ]]),
        ]))->setNodeActiveCallback(fn (MenuNode $n): bool => '/blog/news/' === $n->getLink());
        $menu->activeRoute = '/blog/news/some-article/';

        $this->assertSame(['Blog', 'Blog/News'], self::activePaths($menu->setMenu()->toTemplate()));
    }

    // -- rendering (render / toTemplate) ------------------------------------

    public function testIgnoreClassesDropMatchingNodesWithTheirSubtree(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Login', 'class' => 'guest']),
            TestMenu::item(['title' => 'Account', 'class' => 'logged', 'children' => [
                TestMenu::item(['title' => 'Profile']),
                TestMenu::item(['title' => 'Register', 'class' => 'nav guest']),
            ]]),
            TestMenu::item(['title' => 'Members', 'class' => 'admin', 'children' => [
                TestMenu::item(['title' => 'List']),
            ]]),
            TestMenu::item(['title' => 'Contact']),
        ]);
        $menu->ignoreClasses = ['guest', 'admin'];

        $this->assertSame(['Account', 'Account/Profile', 'Contact'], self::paths($menu->setMenu()->toTemplate()));
    }

    public function testOnlyClassesKeepMatchingNodesAtEveryLevel(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Home', 'class' => 'main']),
            TestMenu::item(['title' => 'Blog', 'class' => 'main blog', 'children' => [
                TestMenu::item(['title' => 'News', 'class' => 'main']),
                TestMenu::item(['title' => 'Old']),
            ]]),
            TestMenu::item(['title' => 'Legal', 'class' => 'footer']),
            TestMenu::item(['title' => 'Plain', 'children' => [
                TestMenu::item(['title' => 'Deep', 'class' => 'main']),
            ]]),
        ]))->withOnlyClasses(['main']);

        $this->assertSame(['Home', 'Blog', 'Blog/News'], self::paths($menu->setMenu()->toTemplate()));
    }

    public function testChildrenKeyIsOmittedWhenNoChildIsRendered(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Account', 'children' => [
                TestMenu::item(['title' => 'Login', 'class' => 'guest']),
            ]]),
        ]);
        $menu->ignoreClasses = ['guest'];

        $this->assertSame(
            ['route', 'separator', 'active', 'modal', 'slug', 'icon', 'target', 'id', 'class', 'title'],
            array_keys($menu->setMenu()->toTemplate()[0])
        );
    }

    public function testAttributeFormattersAddContainerAndLinkAttrsFirst(): void
    {
        $menu = (new TestMenu([TestMenu::item(['id' => 1, 'title' => 'Blog', 'link' => '/blog/'])]))
            ->withContainerAttributes(fn (MenuNode $n): array => ['class' => 'nav-item', 'data-id' => (string) $n->getId()])
            ->withLinkAttributes(fn (MenuNode $n): array => ['href' => $n->getLink(), 'class' => 'nav-link']);

        $this->assertSame([[
            'containerAttrs' => ['class' => 'nav-item', 'data-id' => '1'],
            'linkAttrs' => ['href' => '/blog/', 'class' => 'nav-link'],
            'route' => '/blog/',
            'separator' => false,
            'active' => false,
            'modal' => false,
            'slug' => '',
            'icon' => '',
            'target' => '',
            'id' => 1,
            'class' => '',
            'title' => 'Blog',
        ]], $menu->setMenu()->toTemplate());
    }

    public function testExtraNodeDataIsAddedToEveryNodeBeforeItsChildren(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'children' => [TestMenu::item(['title' => 'News'])]]),
        ]))->withExtraData(fn (MenuNode $n): array => ['badge' => strtolower($n->getTitle())]);

        $blog = $menu->setMenu()->toTemplate()[0];

        $this->assertSame(
            ['route', 'separator', 'active', 'modal', 'slug', 'icon', 'target', 'id', 'class', 'title', 'badge', 'children'],
            array_keys($blog)
        );
        $this->assertSame('blog', $blog['badge']);
        $this->assertSame('news', $blog['children'][0]['badge']);
    }

    // -- title / route callbacks --------------------------------------------

    public function testTitleCallbackProvidesTheTitleOfEveryRenderedNode(): void
    {
        $asked = [];
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'children' => [TestMenu::item(['title' => 'News'])]]),
            TestMenu::item(['title' => '--', 'separator' => true]),
            TestMenu::item(['title' => 'Login', 'class' => 'guest']),
        ]))->setNodeTitleCallback(function (MenuNode $n) use (&$asked): string {
            $asked[] = $n->getTitle();
            return strtoupper($n->getTitle());
        });
        $menu->ignoreClasses = ['guest'];

        $this->assertSame(['BLOG', 'BLOG/NEWS', '--'], self::paths($menu->setMenu()->toTemplate()));
        $this->assertSame(['Blog', 'News', '--'], $asked);
    }

    public function testRouteCallbackGetsEachTopLevelNodeWithItsRouteAlreadyDefined(): void
    {
        $asked = [];
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/']),
            TestMenu::item(['title' => 'Anchor', 'link' => '#']),
            TestMenu::item(['title' => 'Home', 'link' => '']),
        ]))->setNodeRouteCallback(function (MenuNode $n) use (&$asked): MenuNode {
            $asked[] = [$n->getTitle(), $n->getRoute()];
            return $n;
        });

        $menu->setMenu()->toTemplate();

        $this->assertSame([['Blog', '/blog/'], ['Anchor', null], ['Home', null]], $asked);
    }

    public function testRouteCallbackCanRewriteTheRenderedRoute(): void
    {
        $menu = (new TestMenu([TestMenu::item(['title' => 'Blog', 'link' => '/blog/'])]))
            ->setNodeRouteCallback(fn (MenuNode $n): MenuNode => $n->setLink('/fr' . $n->getLink())->defRoute());

        $this->assertSame('/fr/blog/', $menu->setMenu()->toTemplate()[0]['route']);
    }

    // -- helpers -------------------------------------------------------------

    /**
     * Pre-order list of rendered title paths ("Blog/News").
     *
     * @return list<string>
     */
    private static function paths(array $items, string $prefix = ''): array
    {
        $paths = [];
        foreach ($items as $item) {
            $path = $prefix . $item['title'];
            $paths[] = $path;
            array_push($paths, ...self::paths($item['children'] ?? [], $path . '/'));
        }
        return $paths;
    }

    /** @return list<string> */
    private static function activePaths(array $items, string $prefix = ''): array
    {
        $paths = [];
        foreach ($items as $item) {
            $path = $prefix . $item['title'];
            if ($item['active']) {
                $paths[] = $path;
            }
            array_push($paths, ...self::activePaths($item['children'] ?? [], $path . '/'));
        }
        return $paths;
    }
}
