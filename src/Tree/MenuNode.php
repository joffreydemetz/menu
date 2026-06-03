<?php

declare(strict_types=1);

namespace JDZ\Menu\Tree;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MenuNode
{
    protected string|int $id = '';
    protected string $title = '';
    protected string $link = '';
    protected string $class = '';
    protected string $icon = '';
    protected string $target = '';
    protected string $slug = '';
    protected string $component = '';
    protected string $modal = '';
    protected array $params = [];
    protected array $anchorAttributes = [];
    protected array $children = [];
    protected bool $root = false;
    protected bool $ignore = false;
    protected bool $separator = false;
    protected bool $active = false;
    protected ?string $route = null;
    protected ?self $parent = null;

    // -- Getters ---------------------------------------------------------------

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getModal(): string
    {
        return $this->modal;
    }

    public function isModal(): bool
    {
        return $this->modal !== '';
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function isRoot(): bool
    {
        return $this->root;
    }

    public function isSeparator(): bool
    {
        return $this->separator;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isIgnored(): bool
    {
        return $this->ignore;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    // -- Setters ---------------------------------------------------------------

    public function setId(string|int $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;
        return $this;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function setComponent(string $component): static
    {
        $this->component = $component;
        return $this;
    }

    public function setModal(string $modal): static
    {
        $this->modal = $modal;
        return $this;
    }

    public function setSeparator(bool $separator): static
    {
        $this->separator = $separator;
        return $this;
    }

    public function setIgnore(bool $ignore): static
    {
        $this->ignore = $ignore;
        return $this;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;
        return $this;
    }

    // -- Existing methods ------------------------------------------------------

    /** @deprecated Use typed setters instead */
    public function set(string $name, mixed $value): void
    {
        if ('params' === $name) {
            foreach ($value as $k => $v) {
                $this->params[$k] = $v;
            }
            return;
        }

        if ('link' === $name) {
            $this->setLink($value);
            return;
        }

        if ('class' === $name) {
            $this->addClass($value);
            return;
        }

        switch ($name) {
            case 'title':
            case 'icon':
            case 'target':
            case 'slug':
            case 'component':
            case 'modal':
                $value = (string) $value;
                break;

            case 'ignore':
            case 'separator':
            case 'active':
                $value = ($value);
                break;

            case 'id':
                break;

            default:
                return;
        }

        $this->{$name} = $value;
    }

    public function ignoreIt(): bool
    {
        return true === $this->ignore;
    }

    public function addChild(MenuNode $child): void
    {
        $child->setParent($this);
    }

    public function setRoot(): static
    {
        $this->root = true;
        $this->id = 'root';
        $this->title = 'ROOT';
        return $this;
    }

    public function setLink(string $value): static
    {
        $value = str_replace('&&', '*--*', $value);
        $value = str_replace('&#', '*-*', $value);
        $value = str_replace('&amp;', '&', $value);
        $value = preg_replace('|&(?![\w]+;)|', '&amp;', $value);
        $value = str_replace('*-*', '&#', $value);
        $value = str_replace('*--*', '&&', $value);

        $this->link = $value;

        return $this;
    }

    public function addClass(string $value, bool $merge = false): static
    {
        $classes = [];
        if (true === $merge) {
            if ('' !== $this->class) {
                $classes = explode(' ', $this->class);
            }
        }

        if ('' !== $value) {
            $_classes = explode(' ', $value);
            $classes = array_merge($classes, $_classes);
        }
        $classes = array_unique($classes);
        $value = implode(' ', $classes);

        $this->class = $value;

        return $this;
    }

    public function setAnchorAttribute(string $key, string $value): static
    {
        $this->anchorAttributes[$key] = $value;
        return $this;
    }

    public function setActive(): static
    {
        $this->active = true;

        if ($this->hasParent() && false === $this->parent->root) {
            $this->parent->setActive();
        }

        return $this;
    }

    public function setParent(MenuNode $parent): static
    {
        $hash = \spl_object_hash($this);
        $parent->children[$hash] = $this;
        $this->parent = $parent;
        return $this;
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    public function hasParent(): bool
    {
        return null !== $this->parent;
    }

    public function getAnchorAttributes(): array
    {
        return $this->anchorAttributes;
    }

    public function getParam(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function isInternal(): bool
    {
        return ('#' !== $this->link && !preg_match("/https?:\/\//", $this->link));
    }

    public function hasClass(array|string $classes): bool
    {
        if (!$classes) {
            return true;
        }

        if (!is_array($classes)) {
            $classes = [$classes];
        }

        $list = $this->class ? explode(' ', $this->class) : [];

        foreach ($list as $class) {
            if (in_array($class, $classes)) {
                return true;
            }
        }

        return false;
    }

    public function getAttrTarget(): ?string
    {
        if (in_array($this->target, ['blank', 'parent', 'self'])) {
            $target = '_' . $this->target;
        } else {
            $target = null;
        }
        return $target;
    }

    public function defRoute(): static
    {
        if ($this->link && '#' !== $this->link) {
            $this->route = $this->link;
        } else {
            $this->route = null;
        }
        return $this;
    }

    public function getRoute(): ?string
    {
        if (!isset($this->route) && null !== $this->route) {
            if ($this->link && '#' !== $this->link) {
                $this->route = $this->link;
            } else {
                $this->route = null;
            }
        }
        return $this->route;
    }

    public function toAnchorAttributes(array &$attrs): void
    {
        foreach ($this->anchorAttributes as $key => $value) {
            $attrs[$key] = $value;
        }
    }
}
