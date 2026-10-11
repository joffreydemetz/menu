<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Unit\Tree;

use JDZ\Menu\Tests\Support\TestMenu;
use JDZ\Menu\Tree\MenuNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MenuNodeTest extends TestCase
{
    public function testSetLinkEncodesBareAmpersandIdempotently(): void
    {
        $node = new MenuNode();

        $node->setLink('a&b');
        $this->assertSame('a&amp;b', $node->getLink());

        // already-encoded entity must survive a second pass unchanged
        $node->setLink('a&amp;b');
        $this->assertSame('a&amp;b', $node->getLink());
    }

    public function testAddClassDeduplicates(): void
    {
        $node = new MenuNode();

        $node->addClass('nav');
        $node->addClass('nav active', true);

        $this->assertSame('nav active', $node->getClass());
    }

    public function testAddClassWithoutMergeReplaces(): void
    {
        $node = new MenuNode();

        $node->addClass('a b');
        $node->addClass('c');

        $this->assertSame('c', $node->getClass());
    }

    public function testHasClass(): void
    {
        $node = (new MenuNode())->addClass('a b');

        $this->assertTrue($node->hasClass('b'));
        $this->assertTrue($node->hasClass(['x', 'a']));
        $this->assertFalse($node->hasClass('z'));
        $this->assertTrue($node->hasClass([]), 'an empty filter matches every node');
    }

    public function testIsInternal(): void
    {
        $this->assertTrue((new MenuNode())->setLink('/blog/')->isInternal());
        $this->assertFalse((new MenuNode())->setLink('#')->isInternal());
        $this->assertFalse((new MenuNode())->setLink('https://example.com')->isInternal());
        $this->assertFalse((new MenuNode())->setLink('http://example.com')->isInternal());
    }

    public function testAttrTarget(): void
    {
        $this->assertSame('_blank', (new MenuNode())->setTarget('blank')->getAttrTarget());
        $this->assertSame('_self', (new MenuNode())->setTarget('self')->getAttrTarget());
        $this->assertNull((new MenuNode())->setTarget('whatever')->getAttrTarget());
    }

    public function testAddChildLinksParentAndChild(): void
    {
        $root = (new MenuNode())->setRoot();
        $child = (new MenuNode())->setId(1);

        $root->addChild($child);

        $this->assertTrue($root->hasChildren());
        $this->assertContains($child, $root->getChildren());
        $this->assertSame($root, $child->getParent());
    }

    public function testSetActivePropagatesToParentButNotRoot(): void
    {
        $root = (new MenuNode())->setRoot();
        $child = new MenuNode();
        $grandchild = new MenuNode();

        $root->addChild($child);
        $child->addChild($grandchild);

        $grandchild->setActive();

        $this->assertTrue($grandchild->isActive());
        $this->assertTrue($child->isActive());
        $this->assertFalse($root->isActive());
    }

    public function testDefRouteUsesLinkButNotHash(): void
    {
        $this->assertSame('/blog/', (new MenuNode())->setLink('/blog/')->defRoute()->getRoute());
        $this->assertNull((new MenuNode())->setLink('#')->defRoute()->getRoute());
    }

    /** 1.1.0: getRoute() defines the route itself when defRoute() was not called (its lazy branch was unreachable) */
    public function testGetRouteWithoutDefRouteReadsTheLink(): void
    {
        $this->assertSame('/blog/', (new MenuNode())->setLink('/blog/')->getRoute());
        $this->assertNull((new MenuNode())->setLink('#')->getRoute());
        $this->assertNull((new MenuNode())->getRoute());
    }

    // -- effect on the rendered menu ----------------------------------------

    public function testSeparatorFlagRendersTheNodeAsAnInactiveSeparator(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Home', 'link' => '']),
            TestMenu::item(['id' => 2, 'title' => 'Spacer', 'link' => '']),
        ]))->setNodeAppendChildrenCallback(function (MenuNode $n): void {
            if (2 === $n->getId()) {
                $n->setSeparator(true);
            }
        });
        $menu->activeRoute = '/';

        $this->assertSame([
            ['title' => 'Home', 'separator' => false, 'active' => true],
            ['title' => 'Spacer', 'separator' => true, 'active' => false],
        ], self::pick($menu->setMenu()->toTemplate(), 'title', 'separator', 'active'));
    }

    public function testIgnoredNodesAreNotRendered(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Home']),
            TestMenu::item(['id' => 2, 'title' => 'Hidden', 'children' => [TestMenu::item(['id' => 5, 'title' => 'Inner'])]]),
            TestMenu::item(['id' => 3, 'title' => 'Restored']),
            TestMenu::item(['id' => 4, 'title' => 'Legacy']),
        ]))->setNodeAppendChildrenCallback(function (MenuNode $n): void {
            match ($n->getId()) {
                2 => $n->setIgnore(true),
                3 => $n->setIgnore(true)->setIgnore(false),
                4 => $n->set('ignore', 1),
                default => null,
            };
        });

        $this->assertSame(
            [['title' => 'Home'], ['title' => 'Restored']],
            self::pick($menu->setMenu()->toTemplate(), 'title')
        );
    }

    public function testSetClassReplacesTheClassesUsedForRenderingAndFiltering(): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['id' => 1, 'title' => 'Login', 'class' => 'guest nav']),
            TestMenu::item(['id' => 2, 'title' => 'Admin', 'class' => 'nav']),
        ]))->setNodeAppendChildrenCallback(function (MenuNode $n): void {
            $n->setClass(1 === $n->getId() ? 'nav public' : 'nav admin');
        });
        $menu->ignoreClasses = ['guest', 'admin'];

        $this->assertSame(
            [['title' => 'Login', 'class' => 'nav public']],
            self::pick($menu->setMenu()->toTemplate(), 'title', 'class')
        );
    }

    public function testAnchorAttributesOverrideAndExtendTheFormattedLinkAttrs(): void
    {
        $menu = (new TestMenu([TestMenu::item(['title' => 'Docs', 'link' => '/docs/'])]))
            ->withLinkAttributes(fn (MenuNode $n): array => ['href' => $n->getLink(), 'class' => 'nav-link'])
            ->setNodeAppendChildrenCallback(function (MenuNode $n): void {
                $n->setAnchorAttribute('class', 'nav-link external')->setAnchorAttribute('rel', 'nofollow');
            });

        $this->assertSame(
            ['href' => '/docs/', 'class' => 'nav-link external', 'rel' => 'nofollow'],
            $menu->setMenu()->toTemplate()[0]['linkAttrs']
        );
    }

    public function testAnchorAttributesAloneProduceLinkAttrs(): void
    {
        $menu = (new TestMenu([TestMenu::item(['id' => 3, 'title' => 'Docs'])]))
            ->setNodeAppendChildrenCallback(function (MenuNode $n): void {
                $n->setAnchorAttribute('data-id', '3');
            });

        $this->assertSame([[
            'linkAttrs' => ['data-id' => '3'],
            'route' => null,
            'separator' => false,
            'active' => false,
            'modal' => false,
            'slug' => '',
            'icon' => '',
            'target' => '',
            'id' => 3,
            'class' => '',
            'title' => 'Docs',
        ]], $menu->setMenu()->toTemplate());
    }

    public function testGetParamReadsOneItemParam(): void
    {
        $menu = (new TestMenu([TestMenu::item(['title' => 'Blog', 'params' => ['lang' => 'fr', 'count' => 3]])]))
            ->withExtraData(fn (MenuNode $n): array => [
                'lang' => $n->getParam('lang'),
                'count' => $n->getParam('count'),
                'missing' => $n->getParam('missing'),
            ]);

        $this->assertSame(
            [['lang' => 'fr', 'count' => 3, 'missing' => null]],
            self::pick($menu->setMenu()->toTemplate(), 'lang', 'count', 'missing')
        );
    }

    public static function deprecatedSetProvider(): array
    {
        return [
            'id' => ['id', 7, 'id', 7],
            'title is cast to string' => ['title', 12, 'title', '12'],
            'icon' => ['icon', 'star', 'icon', 'star'],
            'target' => ['target', 'blank', 'target', 'blank'],
            'slug' => ['slug', 'news', 'slug', 'news'],
            'component' => ['component', 'blog', 'component', 'blog'],
            'modal' => ['modal', 'login', 'modal', true],
            'link is encoded' => ['link', '/a?x=1&y=2', 'route', '/a?x=1&amp;y=2'],
            'class replaces the item classes' => ['class', 'b c', 'class', 'b c'],
            'params merge into the item params' => ['params', ['b' => 3, 'c' => 4], 'params', ['a' => 1, 'b' => 3, 'c' => 4]],
            'separator' => ['separator', true, 'separator', true],
            'active' => ['active', true, 'active', true],
            'separator from a database row is cast to bool' => ['separator', 1, 'separator', true],
            'active from a database row is cast to bool' => ['active', '0', 'active', false],
            'link is cast to string' => ['link', 5, 'route', '5'],
            'class is cast to string' => ['class', 5, 'class', '5'],
        ];
    }

    #[DataProvider('deprecatedSetProvider')]
    public function testDeprecatedSetWritesTheNamedField(string $name, mixed $value, string $key, mixed $expected): void
    {
        $menu = (new TestMenu([
            TestMenu::item(['title' => 'Item', 'link' => '/item/', 'class' => 'a', 'params' => ['a' => 1, 'b' => 2]]),
        ]))
            ->setNodeAppendChildrenCallback(function (MenuNode $n) use ($name, $value): void {
                $n->set($name, $value);
            })
            ->withExtraData(fn (MenuNode $n): array => ['component' => $n->getComponent(), 'params' => $n->getParams()]);

        $this->assertSame($expected, $menu->setMenu()->toTemplate()[0][$key]);
    }

    public function testDeprecatedSetIgnoresOtherNames(): void
    {
        $items = [TestMenu::item(['title' => 'Item', 'link' => '/item/'])];
        $expected = (new TestMenu($items))->setMenu()->toTemplate();

        $menu = (new TestMenu($items))->setNodeAppendChildrenCallback(function (MenuNode $n): void {
            $n->set('anchorAttributes', ['rel' => 'nofollow']);
            $n->set('children', [(new MenuNode())->setTitle('Injected')]);
            $n->set('route', '/hijacked/');
            $n->set('nope', 'x');
        });

        $this->assertSame($expected, $menu->setMenu()->toTemplate());
    }

    /**
     * Each rendered top-level item reduced to the given keys, in that order.
     *
     * @return list<array<string, mixed>>
     */
    private static function pick(array $items, string ...$keys): array
    {
        return array_map(
            fn (array $item): array => array_combine($keys, array_map(fn (string $k): mixed => $item[$k], $keys)),
            $items
        );
    }
}
