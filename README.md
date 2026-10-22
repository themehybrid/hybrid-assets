# Hybrid Assets

Asset (CSS/JS etc.) resolution for [Hybrid Core](https://github.com/themehybrid/hybrid-core).

Hybrid Assets gives themes and plugins a single, consistent API for resolving asset **URLs**, **filesystem paths**, **dependencies**, and **cache-busting versions** — with automatic support for child-theme overrides, wp-scripts `.asset.php` metadata, and Laravel Mix manifests.

## Why

WordPress asset registration usually means hardcoding URLs, manually tracking `filemtime()` for cache busting, and writing bespoke logic every time a child theme needs to override a parent theme or plugin asset. Hybrid Assets handles all of that:

- **One API** for themes and plugins alike (`path()`, `url()`, `assetUrl()`, `assetPath()`, `asset()`)
- **Automatic inheritance** (when the `inherit` param is `true`) — child theme → parent theme → plugin fallback chain
- **Automatic versioning** — reads `wp-scripts` `.asset.php` files or Laravel Mix `mix-manifest.json`, falling back to a content hash
- **Dependency resolution** — reads `wp_enqueue_script`/`style` dependency arrays straight out of `.asset.php`

## Requirements

- PHP 8.2+
- [Hybrid Core](https://github.com/themehybrid/hybrid-core) ^7.0
- [Hybrid Tools](https://github.com/themehybrid/hybrid-tools) ^2.0
- WordPress 7.0+

## Installation

```bash
composer require themehybrid/hybrid-assets
```

Register the service provider on your container:

```php
$app->register( \Hybrid\Assets\AssetsServiceProvider::class );
```

This binds `ParentTheme` and `ChildTheme` as singletons, and `Plugin` as a fresh (non-shared) binding — each plugin carries its own config (plugin file, override directory, manifest settings), so instances can't be shared between consumers.

## Setting up a resolver

`AssetsServiceProvider` registers the underlying `ParentTheme` / `ChildTheme` / `Plugin` classes, but you still bind your **own** named instance — configured for your theme or plugin's specific asset directory — in your provider's `register()` method.

### In a theme

```php
use Hybrid\Assets\ParentTheme;

$this->app->singleton( 'my-theme/assets', static function ( $app ) {
    /** @var ParentTheme $theme */
    $theme = $app->make( ParentTheme::class );
    $theme->setAssetsDirectory( '/assets' );
    $theme->setManifestDirectory( '/assets' );

    return $theme;
} );
```

### In a plugin

```php
use Hybrid\Assets\Plugin as AssetsPlugin;

$this->app->singleton( 'my-plugin/assets', static function ( $app ) {
    /** @var AssetsPlugin $plugin */
    $plugin = $app->make( AssetsPlugin::class );
    $plugin->setPluginFile( MY_PLUGIN_FILE );

    // By default, plugin asset overrides are expected under `{theme}/public/my-plugin/...`.
    // Themes can override this slug to match their own asset structure, such as `dist/`,
    // `assets/`, or any other custom directory.
    $plugin->setOverrideAssetsDirectory(
        apply_filters( 'my-plugin/assets/override/path', '/public/my-plugin' )
    );

    return $plugin;
} );
```

Binding key names (`my-theme/assets`, `my-plugin/assets`) are your choice — just keep them unique across the container and reuse the same string in your Facade accessor (see below).

## Usage

### Direct container access

```php
$theme = app( 'my-theme/assets' );

$theme->url( '/js/app.js' );
$theme->path( '/js/app.js' );
```

### Via a Facade (recommended)

Wrapping your bound instance in a `Facade` gives you a clean, static-style call site without losing testability. Define one per theme/plugin:

```php
<?php

namespace MyTheme\Facades;

use Hybrid\Core\Facades\Facade;

/**
 * @method static string url(string $file)
 * @method static string path(string $file)
 * @method static string assetUrl(string $file, bool $inherit)
 * @method static string assetPath(string $file, bool $inherit)
 * @method static \Hybrid\Assets\Asset asset(string $file, bool $inherit, string $overrideManifestDirectory = '')
 * @method static \Hybrid\Assets\Svg svg(string $file, bool $inherit = false)
 */
class Assets extends Facade {

    protected static function getFacadeAccessor() {
        return 'my-theme/assets';
    }

}
```

Then enqueue assets with it directly:

```php
/** @var \Hybrid\Assets\Asset $asset */
$asset = Assets::asset( 'js/my-file.js' );

wp_enqueue_script(
    'my-file',
    $asset->url(),
    $asset->dependencies(), // read from js/app.asset.php, if present
    $asset->version(), // from .asset.php, mix-manifest.json, or a content hash
    true
);
```

The same pattern applies to plugins — just point `getFacadeAccessor()` at your plugin's binding key:

```php
<?php

namespace MyPlugin\Facades;

use Hybrid\Core\Facades\Facade;

/**
 * @see \Hybrid\Assets\Plugin
 *
 * @method static string url(string $file)
 * @method static string path(string $file)
 * @method static string assetUrl(string $file, bool $inherit)
 * @method static string assetPath(string $file, bool $inherit)
 * @method static \Hybrid\Assets\Asset asset(string $file, bool $inherit, string $overrideManifestDirectory = '')
 * @method static \Hybrid\Assets\Svg svg(string $file, bool $inherit = false)
 */
class Assets extends Facade {

    protected static function getFacadeAccessor() {
        return 'my-plugin/assets';
    }

}
```

```php
/** @var \Hybrid\Assets\Asset $asset */
$asset = Assets::asset( 'js/my-file.js', true );

wp_register_script(
    'my-file',
    $asset->url(),
    $asset->dependencies(), // read from js/my-file.asset.php, if present
    $asset->version(),      // from .asset.php, mix-manifest.json, or a content hash
    true
);
```

### Child-theme / plugin-override inheritance

Pass `inherit: true` as the second argument to check the inheritance chain first. This lets a child theme override a parent theme's asset, or a theme override a plugin's asset:

```php
// Checks the child theme first, then falls back to the parent theme itself.
Assets::asset( 'js/my-file.js', true );

// For a plugin: checks child theme, then parent theme (both under the
// plugin's override directory), before falling back to the plugin's own file.
Assets::asset( 'js/my-file.js', true );
```

### Laravel Mix support

If your build uses Laravel Mix instead of `@wordpress/scripts`, point Hybrid Assets at the manifest when you bind it:

```php
$theme->setManifestDirectory( '/assets' );          // where mix-manifest.json lives
$theme->setManifestFileName( 'mix-manifest.json' );  // default, rarely needs changing
```

Metadata resolution order is always: **`.asset.php` → `mix-manifest.json` → content hash fallback.**

### Inline SVG

`svg()` resolves a file through the same path and inheritance rules as `asset()`, then renders its markup inline — useful for icons you want to style with CSS or animate:

```php
Assets::svg( 'images/icons/arrow.svg' )->display();   // echo
$markup = Assets::svg( 'images/icons/arrow.svg' )->render();  // return
```

Markup is **sanitized by default** against an allow-list vendored from an open WordPress core pull request ([#12197](https://github.com/WordPress/wordpress-develop/pull/12197)). It strips scripts, event handlers, `javascript:` / `data:` URLs (in `href` and `xlink:href`), `<animate>` / `<set>`, and disallowed elements. Once core ships that sanitizer, this package will call core's instead. A file that is missing, unreadable, or contains no valid SVG renders as an empty string rather than throwing.

On WordPress 7.0, `wp_kses()` cannot keep attribute names that contain a colon, so `xmlns:xlink` is dropped and `xlink:href` is rewritten to `href`. Unsafe URLs are still removed. WordPress 7.1 keeps both.

Inheritance works as it does for `asset()`:

```php
Assets::svg( 'images/icons/arrow.svg', true );  // child theme first
```

Skip sanitization only for build-pipeline output you control:

```php
Assets::svg( 'images/icons/arrow.svg' )->sanitize( false )->render();
```

## How resolution works

```
asset( $file, inherit: true )
  │
  ├─ inherit? ─── walk inheritance chain (child theme → parent theme → plugin)
  │                 └─ first resolver where the file exists & is readable wins
  │
  └─ resolve( $file )
        └─ new Asset( resolver, file, path )
              ├─ url()          → resolver->url( $file )
              ├─ path()         → resolver->path( $file )
              ├─ dependencies() → from .asset.php, else []
              └─ version()      → .asset.php → mix-manifest.json → md5_file() hash
```

## Exceptions

All exceptions implement `Hybrid\Assets\Contracts\AssetsException`, so the whole package can be caught with one type.

| Exception | Thrown when |
|---|---|
| `InvalidAssetFileException` | `asset()` / `svg()` is called with a blank file path |
| `PluginFileNotSetException` | A `Plugin` resolver is used before `setPluginFile()` |
| `PathOutsideBaseException` | A resolved `.asset.php` escapes the resolver's base directory |
| `UnresolvableBaseDirectoryException` | The resolver's base directory can't be resolved via `realpath()` |

## Architecture

| Class | Role |
|---|---|
| `Contracts\AssetsResolver` | Contract: `path()`, `url()`, `asset()`, `svg()`, `assetUrl()`, `assetPath()` |
| `AssetsResolver` | Abstract base: resolution, inheritance chain, manifest handling — extended by all three resolvers below |
| `ParentTheme` | Resolves assets in the active parent theme |
| `ChildTheme` | Resolves assets in the active child theme, if one exists |
| `Plugin` | Resolves assets in a specific plugin; supports override directories |
| `Asset` | Immutable, fully-resolved asset (URL, path, dependencies, version) |
| `Svg` | Reads and sanitizes an SVG for inline rendering |
| `Support\SvgSanitizer` | Allow-list sanitizer, vendored from WordPress core PR #12197 |
| `Concerns\AssetMetaData` | Trait: reads `.asset.php` / `mix-manifest.json` metadata, path-traversal safe |
| `AssetsServiceProvider` | Registers the above with the Hybrid Core container |

## License

This project is licensed under the [GNU GPL](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

2008&thinsp;&ndash;&thinsp;2026 &copy; [Theme Hybrid](https://themehybrid.com).
