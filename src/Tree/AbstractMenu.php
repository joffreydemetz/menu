<?php

declare(strict_types=1);

namespace JDZ\Menu\Tree;

use JDZ\Menu\Contract\MenuConfigInterface;
use JDZ\Menu\Contract\MenuTreeInterface;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class AbstractMenu implements MenuTreeInterface
{
    protected ?MenuConfigInterface $config = null;

    public string $activeRoute = '';
    public bool $dropdown = true;
    public string $dropdownClass = 'dropdown';
    public bool $showChildren = true;
    protected MenuNode $_root;
    protected MenuNode $_current;
    protected ?array $onlyClasses = null;
    public ?array $ignoreClasses = null;
    protected array $nodeParserCallbacks = [];
    protected array $nodeAppendChildrenCallbacks = [];
    protected ?\Closure $nodeTitleCallback = null;
    protected ?\Closure $nodeActiveCallback = null;
    protected ?\Closure $nodeRouteCallback = null;

    public function setConfig(MenuConfigInterface $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function init(): void
    {
        $this->_root = $this->createNode();
        $this->_root->setRoot();
    }

    protected function createNode(): MenuNode
    {
        return new MenuNode();
    }

    protected function extraNodeData(MenuNode $node): array
    {
        return [];
    }

    public function setNodeParserCallback(string $component, \Closure $callback): static
    {
        $this->nodeParserCallbacks[$component] = $callback;
        return $this;
    }

    public function setNodeAppendChildrenCallback(\Closure $callback): static
    {
        $this->nodeAppendChildrenCallbacks[] = $callback;
        return $this;
    }

    public function setNodeActiveCallback(\Closure $callback): static
    {
        $this->nodeActiveCallback = $callback;
        return $this;
    }

    public function setNodeTitleCallback(\Closure $callback): static
    {
        $this->nodeTitleCallback = $callback;
        return $this;
    }

    public function setNodeRouteCallback(\Closure $callback): static
    {
        $this->nodeRouteCallback = $callback;
        return $this;
    }

    public function setMenu(): static
    {
        $this->init();
        $items = $this->prepareItems();

        $this->_current = $this->_root;
        $this->appendItems($items);

        $this->_current = $this->_root;
        $this->setActiveItems();

        return $this;
    }

    public function toTemplate(): array
    {
        $rootNode = $this->_root;

        $items = [];

        foreach ($rootNode->getChildren() as $node) {
            $node->defRoute();
            $this->nodeRoute($node);

            if ($item = $this->render($node)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    protected function prepareItems(): array
    {
        return [];
    }

    protected function appendItems(array $items = []): void
    {
        foreach ($items as $item) {
            if (true === $item->separator) {
                $node = $this->createNode();
                $node->addClass($item->class)
                     ->setTitle($item->title)
                     ->setSeparator(true);
                $this->_current->addChild($node);
                continue;
            }

            $node = $this->createNode();
            $node->setId($item->id)
                 ->setTitle($item->title)
                 ->setIcon($item->icon)
                 ->setTarget($item->target)
                 ->setSlug($item->slug)
                 ->setComponent($item->component)
                 ->setModal($item->modal)
                 ->setLink($item->link)
                 ->addClass($item->class)
                 ->setParams($item->params);

            $this->_current->addChild($node);

            if (count($item->children)) {
                $this->_current = $node;
                $this->appendItems($item->children);
                $this->_current = $this->_current->getParent();
            }

            $this->nodeChildrenAppend($node);
        }
    }

    protected function setActiveItems(): void
    {
        foreach ($this->_current->getChildren() as $node) {
            if ($node->hasChildren()) {
                $this->_current = $node;
                $this->setActiveItems();
                $this->_current = $node->getParent();
            }

            if ($this->isActiveItem($node)) {
                $node->setActive();
            }
        }
    }

    protected function isActiveItem(MenuNode $node): bool
    {
        if (!$node->isInternal()) {
            return false;
        }

        if ($node->getLink() && $node->getLink() === $this->activeRoute) {
            return true;
        }

        if ($node->isSeparator()) {
            return false;
        }

        if ('' === $node->getLink() && $this->activeRoute === '/') {
            return true;
        }

        if (isset($this->nodeActiveCallback) && is_callable($this->nodeActiveCallback)) {
            return call_user_func($this->nodeActiveCallback, $node);
        }

        return false;
    }

    protected function render(MenuNode $node): array|bool
    {
        if ($this->onlyClasses) {
            if (!$node->hasClass($this->onlyClasses)) {
                return false;
            }
        }

        if ($this->ignoreClasses) {
            if ($node->hasClass($this->ignoreClasses)) {
                return false;
            }
        }

        if ($this->ignoreClasses) {
            if (true === $node->ignoreIt()) {
                return false;
            }
        }

        list($containerAttrs, $linkAttrs) = $this->nodeParseAttributes($node);

        $node->toAnchorAttributes($linkAttrs);

        $item = [];

        if (!empty($containerAttrs)) {
            $item['containerAttrs'] = $containerAttrs;
        }

        if (!empty($linkAttrs)) {
            $item['linkAttrs'] = $linkAttrs;
        }

        $item['route']     = $node->getRoute();
        $item['separator'] = $node->isSeparator();
        $item['active']    = $node->isActive();
        $item['modal']     = $node->isModal();
        $item['slug']      = $node->getSlug();
        $item['icon']      = $node->getIcon();
        $item['target']    = $node->getTarget();
        $item['id']        = $node->getId();
        $item['class']     = $node->getClass();
        $item['title']     = $this->nodeTitle($node);

        $item = array_merge($item, $this->extraNodeData($node));

        if ($node->hasChildren()) {
            $item['children'] = [];

            foreach ($node->getChildren() as $child) {
                if ($_item = $this->render($child)) {
                    $item['children'][] = $_item;
                }
            }

            if (!$item['children']) {
                unset($item['children']);
            }
        }

        return $item;
    }

    protected function nodeParser(\stdClass $item): \stdClass
    {
        if (isset($this->nodeParserCallbacks[$item->component]) && \is_callable($this->nodeParserCallbacks[$item->component])) {
            $item = \call_user_func($this->nodeParserCallbacks[$item->component], $item);
        } elseif (isset($this->nodeParserCallbacks['_']) && \is_callable($this->nodeParserCallbacks['_'])) {
            $item = \call_user_func($this->nodeParserCallbacks['_'], $item);
        }

        return $item;
    }

    protected function nodeChildrenAppend(MenuNode $node): void
    {
        foreach ($this->nodeAppendChildrenCallbacks as $callback) {
            if (\is_callable($callback)) {
                \call_user_func($callback, $node);
            }
        }
    }

    protected function nodeTitle(MenuNode $node): string
    {
        if (\is_callable($this->nodeTitleCallback)) {
            $title = \call_user_func($this->nodeTitleCallback, $node);
        } else {
            $title = $node->getTitle();
        }
        return $title;
    }

    protected function nodeRoute(MenuNode $node): void
    {
        if (\is_callable($this->nodeRouteCallback)) {
            $node = \call_user_func($this->nodeRouteCallback, $node);
        }
    }

    protected function nodeParseAttributes(MenuNode $node): array
    {
        $linkAttrs = $this->formatLinkAttributes($node);
        $containerAttrs = $this->formatContainerAttributes($node);
        return [$containerAttrs, $linkAttrs];
    }

    protected function formatLinkAttributes(MenuNode $node): array
    {
        return [];
    }

    protected function formatContainerAttributes(MenuNode $node): array
    {
        return [];
    }
}
