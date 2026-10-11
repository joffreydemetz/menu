<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Unit\Tree;

use JDZ\Menu\Config\MenuConfig;
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

        // 1.1.0: a nested node renders its route (it was always null: only the top level defined it)
        $this->assertSame([
            'route' => '/blog/news/',
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
            // 1.1.0: a node with rendered children is a dropdown, its attributes come first
            ['containerAttrs', 'linkAttrs', 'route', 'separator', 'active', 'modal', 'slug', 'icon', 'target', 'id', 'class', 'title', 'badge', 'children'],
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

    /** 1.1.0: the route callback sees every rendered node (it used to see the top level only) */
    public function testRouteCallbackGetsEachNodeWithItsRouteAlreadyDefined(): void
    {
        $asked = [];
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/', 'children' => [
                TestMenu::item(['title' => 'News', 'link' => '/blog/news/']),
            ]]),
            TestMenu::item(['title' => 'Anchor', 'link' => '#']),
            TestMenu::item(['title' => 'Home', 'link' => '']),
        ]))->setNodeRouteCallback(function (MenuNode $n) use (&$asked): MenuNode {
            $asked[] = [$n->getTitle(), $n->getRoute()];
            return $n;
        });

        $menu->setMenu()->toTemplate();

        $this->assertSame([['Blog', '/blog/'], ['News', '/blog/news/'], ['Anchor', null], ['Home', null]], $asked);
    }

    // -- 1.1.0: showChildren, dropdown, MenuConfig, lazy toTemplate() ----------

    private static function blogWithNews(): TestMenu
    {
        return new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Blog', 'link' => '/blog/', 'children' => [
                TestMenu::item(['id' => 2, 'title' => 'News', 'link' => '/blog/news/']),
            ]]),
        ]);
    }

    public function testShowChildrenFalseRendersTheTopLevelOnly(): void
    {
        $menu = self::blogWithNews();
        $menu->showChildren = false;

        $tree = $menu->setMenu()->toTemplate();

        $this->assertSame(['Blog'], self::paths($tree));
        $this->assertArrayNotHasKey('children', $tree[0]);
    }

    public static function dropdowns(): array
    {
        return [
            'default dropdown class' => ['dropdown', ['class' => 'dropdown'], ['class' => 'dropdown-toggle']],
            'custom dropdown class' => ['mm', ['class' => 'mm'], ['class' => 'mm-toggle']],
        ];
    }

    #[DataProvider('dropdowns')]
    public function testANodeWithRenderedChildrenIsADropdown(string $class, array $containerAttrs, array $linkAttrs): void
    {
        $menu = self::blogWithNews();
        $menu->dropdownClass = $class;

        $blog = $menu->setMenu()->toTemplate()[0];

        $this->assertSame($containerAttrs, $blog['containerAttrs']);
        $this->assertSame($linkAttrs, $blog['linkAttrs']);
        $this->assertSame(['containerAttrs', 'linkAttrs', 'route'], array_slice(array_keys($blog), 0, 3));
        $this->assertArrayNotHasKey('containerAttrs', $blog['children'][0]);
        $this->assertArrayNotHasKey('linkAttrs', $blog['children'][0]);
    }

    public function testDropdownOffLeavesTheAttributesAlone(): void
    {
        $menu = self::blogWithNews();
        $menu->dropdown = false;

        $blog = $menu->setMenu()->toTemplate()[0];

        $this->assertArrayNotHasKey('containerAttrs', $blog);
        $this->assertArrayNotHasKey('linkAttrs', $blog);
        $this->assertCount(1, $blog['children']);
    }

    public function testTheDropdownClassesJoinTheFormattersClassesOnce(): void
    {
        $menu = self::blogWithNews()
            ->withContainerAttributes(fn (MenuNode $n): array => ['class' => 'nav-item dropdown', 'data-id' => (string) $n->getId()])
            ->withLinkAttributes(fn (MenuNode $n): array => ['class' => 'nav-link']);

        $blog = $menu->setMenu()->toTemplate()[0];

        $this->assertSame(['class' => 'nav-item dropdown', 'data-id' => '1'], $blog['containerAttrs']);
        $this->assertSame(['class' => 'nav-link dropdown-toggle'], $blog['linkAttrs']);
    }

    public function testANodeWhoseChildrenAreAllIgnoredIsNotADropdown(): void
    {
        $menu = new TestMenu([
            TestMenu::item(['title' => 'Account', 'children' => [TestMenu::item(['title' => 'Login', 'class' => 'guest'])]]),
        ]);
        $menu->ignoreClasses = ['guest'];

        $account = $menu->setMenu()->toTemplate()[0];

        $this->assertArrayNotHasKey('containerAttrs', $account);
        $this->assertArrayNotHasKey('children', $account);
    }

    public function testTheConfigRequestUriIsTheDefaultActiveRoute(): void
    {
        $menu = self::blogWithNews()->setConfig((new MenuConfig())->set('requestUri', '/blog/news/'));

        $tree = $menu->setMenu()->toTemplate();

        $this->assertTrue($tree[0]['active']);
        $this->assertTrue($tree[0]['children'][0]['active']);
    }

    public function testAnExplicitActiveRouteWinsOverTheConfig(): void
    {
        $menu = self::blogWithNews()->setConfig((new MenuConfig())->set('requestUri', '/blog/'));
        $menu->activeRoute = '/elsewhere/';

        $this->assertFalse($menu->setMenu()->toTemplate()[0]['active']);
    }

    public function testTheConfigBaseUrlPrefixesTheInternalRoutes(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Blog', 'link' => '/blog/', 'children' => [TestMenu::item(['title' => 'News', 'link' => '/blog/news/'])]]),
            TestMenu::item(['title' => 'Elsewhere', 'link' => 'https://other.example/']),
            TestMenu::item(['title' => 'Anchor', 'link' => '#']),
        ]))->setConfig((new MenuConfig())->set('baseUrl', 'https://www.example.com/'));

        $tree = $menu->setMenu()->toTemplate();

        $this->assertSame('https://www.example.com/blog/', $tree[0]['route']);
        $this->assertSame('https://www.example.com/blog/news/', $tree[0]['children'][0]['route']);
        $this->assertSame('https://other.example/', $tree[1]['route']);
        $this->assertNull($tree[2]['route']);
    }

    /** toTemplate() before setMenu() was an uninitialised-property Error */
    public function testToTemplateBuildsTheMenuWhenSetMenuWasNotCalled(): void
    {
        $this->assertSame(['Blog', 'Blog/News'], self::paths(self::blogWithNews()->toTemplate()));
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
