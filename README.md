# JDZ Menu

A framework-agnostic PHP library for building, activating, and rendering hierarchical menu trees.

You feed it a flat (or nested) list of items; it assembles a tree of nodes, flags the active branch for the current route, and renders a template-ready nested array. Where the items *come from* (database, config file, hardcoded array) is entirely up to you — this package is intentionally **tree-only** and has no persistence layer.

## Features

- 🌳 **Tree builder** — turns a flat/nested item list into a parent/child `MenuNode` tree
- 🎯 **Active-state resolution** — marks the matching node and propagates `active` up its ancestors
- 🧩 **Template-ready output** — `toTemplate()` returns a plain nested array your view layer can loop over
- 🪝 **Pluggable callbacks** — customise active detection, titles, routes, per-component parsing
- 🎨 **Attribute hooks** — override link/container attribute formatting for your markup
- 🚫 **Class filtering** — include/ignore nodes by CSS class (e.g. hide `guest`/`logged` items)
- ✅ **Type-safe, zero coupling** — PHP 8.2+, depends only on `jdz/data`; no DB, no framework

## Installation

```bash
composer require jdz/menu
```

## Requirements

- PHP 8.2 or higher
- [`jdz/data`](https://jdz.joffreydemetz.com/data) (pulled automatically — used by `MenuConfig`)

## How it works

`AbstractMenu` drives a three-stage pipeline:

```
prepareItems()  →  appendItems()  →  setActiveItems()  →  toTemplate()
   (your data)      (build tree)     (flag active)        (render array)
```

You extend `AbstractMenu` and implement `prepareItems()` to supply the items. Everything else (tree assembly, active resolution, rendering) is handled for you.

## Quick Start

```php
use JDZ\Menu\Tree\AbstractMenu;

/** Supplies its items from a plain array. */
final class ArrayMenu extends AbstractMenu
{
    public function __construct(private array $items) {}

    protected function prepareItems(): array
    {
        return $this->items;
    }
}

// Build the item list (see "The item contract" below for the required fields).
$item = fn (array $o) => (object) array_merge([
    'id' => 0, 'title' => '', 'link' => '', 'component' => '',
    'class' => '', 'icon' => '', 'target' => '', 'slug' => '',
    'modal' => '', 'params' => [], 'separator' => false, 'children' => [],
], $o);

$menu = new ArrayMenu([
    $item(['id' => 1, 'title' => 'Home', 'link' => '/']),
    $item(['id' => 2, 'title' => 'Blog', 'link' => '/blog/', 'children' => [
        $item(['id' => 3, 'title' => 'News', 'link' => '/blog/news/']),
    ]]),
]);

$menu->activeRoute = '/blog/news/';

$tree = $menu->setMenu()->toTemplate();
// $tree is a nested array; the "Blog" and "News" nodes are marked active.
```

## The item contract

`prepareItems()` must return an array of objects (e.g. `stdClass`) exposing these public properties — `appendItems()` reads them directly:

| Property      | Type     | Notes                                              |
|---------------|----------|----------------------------------------------------|
| `id`          | int\|string |                                                 |
| `title`       | string   |                                                    |
| `link`        | string   | `#` and absolute `http(s)://` URLs are treated as external |
| `component`   | string   | used to dispatch per-component parser callbacks    |
| `class`       | string   | space-separated CSS classes                        |
| `icon`        | string   |                                                    |
| `target`      | string   | `blank` / `self` / `parent` → rendered as `_blank` etc. |
| `slug`        | string   |                                                    |
| `modal`       | string   | non-empty marks the node as a modal trigger        |
| `params`      | array    |                                                    |
| `separator`   | bool     | `true` renders a divider (title/class only)        |
| `children`    | array    | nested items, same shape                           |

## Rendered output

`toTemplate()` returns an array of items, each shaped like:

```php
[
    'route'     => '/blog/',   // null when link is '#' or empty
    'separator' => false,
    'active'    => true,
    'modal'     => false,
    'slug'      => '',
    'icon'      => '',
    'target'    => '',
    'id'        => 2,
    'class'     => '',
    'title'     => 'Blog',
    // 'containerAttrs' => [...]  // present if formatContainerAttributes() returns any
    // 'linkAttrs'      => [...]  // present if formatLinkAttributes() returns any
    'children'  => [ /* same shape, only if the node has rendered children */ ],
]
```

Add your own keys per node by overriding `extraNodeData(MenuNode $node): array`.

## MenuNode

`JDZ\Menu\Tree\MenuNode` is the tree node. Useful methods when writing callbacks or attribute formatters:

- `getId() / getTitle() / getLink() / getClass() / getIcon() / getTarget() / getSlug() / getComponent() / getModal() / getParams() / getRoute()`
- `isActive() / isSeparator() / isModal() / isRoot() / isInternal() / hasChildren() / hasClass($classes)`
- `getChildren() / getParent()`
- `addClass(string $value, bool $merge = false)` / `setActive()` (propagates to ancestors) / `setAnchorAttribute($key, $value)`

## Callbacks

Register closures before calling `setMenu()`:

```php
$menu
    // Decide active state yourself (return bool). Runs when the default
    // link === activeRoute check doesn't already match.
    ->setNodeActiveCallback(fn (MenuNode $n) => str_starts_with($n->getLink(), '/blog/'))

    // Rewrite the displayed title (return string).
    ->setNodeTitleCallback(fn (MenuNode $n) => strtoupper($n->getTitle()))

    // Post-process the route just before render.
    ->setNodeRouteCallback(fn (MenuNode $n) => $n)

    // Per-component item parsing ('_' is the fallback for any component).
    ->setNodeParserCallback('blog', fn (object $item) => $item)

    // Inject extra children onto a node as it's appended.
    ->setNodeAppendChildrenCallback(fn (MenuNode $n) => null);
```

Public tuning properties: `$activeRoute`, `$showChildren`, `$dropdown`, `$dropdownClass`, `$ignoreClasses` (nodes carrying any listed class are skipped during render).

## Configuration

`JDZ\Menu\Config\MenuConfig` (extends `JDZ\Utils\Data`) carries render-time context to your subclass via `setConfig()`:

```php
use JDZ\Menu\Config\MenuConfig;

$config = (new MenuConfig())
    ->set('publicPath', '/var/www/site/public')
    ->set('language', 'fr');

$menu->setConfig($config);
// inside your subclass: $this->config->getPublicPath(), getLanguage(), getCacheThumbs(), getBaseUrl()...
```

## Not included

This package builds and renders the **tree only**. It deliberately ships no database access, entities, or repositories — provide your items however you like by implementing `prepareItems()`.

## Testing

```bash
composer test
# or
vendor/bin/phpunit
```

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.