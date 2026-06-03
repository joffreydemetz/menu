<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Unit\Tree;

use JDZ\Menu\Tree\MenuNode;
use PHPUnit\Framework\TestCase;

class MenuNodeTest extends TestCase
{
    public function testFluentSettersReturnSelf(): void
    {
        $node = new MenuNode();

        $result = $node->setId(1)
            ->setTitle('Home')
            ->setIcon('home')
            ->setTarget('blank')
            ->setSlug('home')
            ->setComponent('page')
            ->setModal('login')
            ->setParams(['a' => 1]);

        $this->assertSame($node, $result);
        $this->assertSame(1, $node->getId());
        $this->assertSame('Home', $node->getTitle());
        $this->assertSame('home', $node->getIcon());
        $this->assertSame('blank', $node->getTarget());
        $this->assertSame('home', $node->getSlug());
        $this->assertSame('page', $node->getComponent());
        $this->assertSame('login', $node->getModal());
        $this->assertTrue($node->isModal());
        $this->assertSame(['a' => 1], $node->getParams());
    }

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
}
