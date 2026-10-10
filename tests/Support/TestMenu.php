<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Support;

use JDZ\Menu\Tree\AbstractMenu;
use JDZ\Menu\Tree\MenuNode;

/**
 * Concrete AbstractMenu fed from a fixed item list, with its protected hooks
 * (extra node data, attribute formatters, onlyClasses) driven by closures.
 */
final class TestMenu extends AbstractMenu
{
    private ?\Closure $extraData = null;
    private ?\Closure $linkAttributes = null;
    private ?\Closure $containerAttributes = null;

    /** @param list<\stdClass> $items */
    public function __construct(private array $items = [])
    {
    }

    /**
     * An item exposing every property appendItems() reads, defaulted.
     *
     * @param array<string, mixed> $props
     */
    public static function item(array $props = []): \stdClass
    {
        return (object) array_merge([
            'id' => 0,
            'title' => '',
            'link' => '',
            'component' => '',
            'class' => '',
            'icon' => '',
            'target' => '',
            'slug' => '',
            'modal' => '',
            'params' => [],
            'separator' => false,
            'children' => [],
        ], $props);
    }

    /** @param \Closure(MenuNode): array<string, mixed> $callback */
    public function withExtraData(\Closure $callback): static
    {
        $this->extraData = $callback;
        return $this;
    }

    /** @param \Closure(MenuNode): array<string, string> $callback */
    public function withLinkAttributes(\Closure $callback): static
    {
        $this->linkAttributes = $callback;
        return $this;
    }

    /** @param \Closure(MenuNode): array<string, string> $callback */
    public function withContainerAttributes(\Closure $callback): static
    {
        $this->containerAttributes = $callback;
        return $this;
    }

    /** @param list<string> $classes */
    public function withOnlyClasses(array $classes): static
    {
        $this->onlyClasses = $classes;
        return $this;
    }

    protected function prepareItems(): array
    {
        return $this->items;
    }

    protected function extraNodeData(MenuNode $node): array
    {
        return null !== $this->extraData ? ($this->extraData)($node) : parent::extraNodeData($node);
    }

    protected function formatLinkAttributes(MenuNode $node): array
    {
        return null !== $this->linkAttributes ? ($this->linkAttributes)($node) : parent::formatLinkAttributes($node);
    }

    protected function formatContainerAttributes(MenuNode $node): array
    {
        return null !== $this->containerAttributes ? ($this->containerAttributes)($node) : parent::formatContainerAttributes($node);
    }
}
