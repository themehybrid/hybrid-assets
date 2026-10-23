# Change Log

You can see the changes made via the [commit log](https://github.com/themehybrid/hybrid-assets/commits/master) for the latest release.

## [1.0.0-alpha.6] - 2026-10-22

Adds inline SVG rendering. No breaking changes to existing APIs.

### Added

- `AssetsResolver::svg()` and `Contracts\Svg`: resolves an SVG through the
  same path and inheritance chain as `asset()`, then renders it inline via
  `render()` or `display()` ([#4])
- `Support\SvgSanitizer`: sanitization is on by default; opt out per call
  with `->sanitize( false )` when the file is known-trusted
- SVG markup is filtered through the sanitizer from the open core pull
  request [PR #12197] ([Trac #64651]), ported from its commit `c129d8c`.
  Scripts, event handlers, `javascript:`/`data:` URLs in `href` and
  `xlink:href`, `<animate>`/`<set>`, `<foreignObject>`, and disallowed
  elements are stripped. When core ships it, the package will call core's
- Integration test suite running the sanitizer against real `wp_kses()` and
  `WP_HTML_Processor`, using the pull request's own test expectations
  verbatim, so any drift from upstream fails loudly

### Changed

- Test suite migrated from Brain Monkey to a real WordPress install via
  `alvarodelera/pest-wp-plugin` (SQLite, no database server). It lives in
  `qa/`, a separate Composer project, because Pest 5 needs **PHP 8.4**; the
  package itself still supports PHP 8.2+. Run with `composer test`
- Resolver tests now assert real return values rather than mocked call
  expectations

### Known issues

- On WordPress 7.0, `wp_kses()` drops `xmlns:xlink` and rewrites
  `xlink:href` to `href`, so five conformance cases fail there. Unsafe URLs
  are still stripped. The suite targets the latest WordPress, 7.1
- `is_child_theme()` is constant-backed, so `ChildTheme::exists()` can only be
  exercised against whichever theme the test environment has active
- The symlinked `.asset.php` test is skipped on systems that cannot create
  symlinks, such as Windows without Developer Mode

[#4]: https://github.com/themehybrid/hybrid-assets/issues/4
[Trac #64651]: https://core.trac.wordpress.org/ticket/64651
[PR #12197]: https://github.com/WordPress/wordpress-develop/pull/12197

## [1.0.0-alpha.5] - 2026-08-01

Large internal restructure. The public method names are mostly unchanged, but
namespaces and base classes moved — see **Changed** before upgrading.

### Security

- `.asset.php` resolution is now containment-checked against the resolver's base directory. Paths that escape it (via `../` traversal, at any depth) throw `PathOutsideBaseException` instead of being included
- The containment check is anchored to a directory boundary, so a sibling directory sharing the base as a string prefix (`/site-evil` against a `/site` base) no longer passes as contained
- Fails closed when the base directory can't be resolved via `realpath()`, throwing `UnresolvableBaseDirectoryException` rather than treating an unresolvable base as "no restriction"
- `.asset.php` lookups resolve to a canonical path before inclusion, so a symlink pointing at a differently-named file is rejected by the `.asset.php` suffix guard

### Added

- `Contracts\AssetsException` and four typed exceptions — `InvalidAssetFileException`, `PluginFileNotSetException`, `PathOutsideBaseException`, `UnresolvableBaseDirectoryException`
- `Contracts\Asset`, now implemented by `Asset`
- `Asset::file()` — the relative path the asset resolved to
- `Asset::dependencies()` accepts an `$additional` array of handles to merge and dedupe (#3)
- `AssetsResolver::getAssetsDirectory()`, `setManifestDirectory()`, `getManifestDirectory()`, `setManifestFileName()`, `getManifestFileName()`
- `AssetsResolver::exists()` and a per-class `$inheritance` chain driving `$inherit` lookups
- `themehybrid/hybrid-core ^7.0` and `themehybrid/hybrid-tools ^2.0` as explicit runtime requirements — previously assumed but undeclared
- Pest test suite with Brain Monkey; `composer test` and `composer test:mutate`

### Changed

- `Contracts\AssetsAbstract` moved and renamed to `Hybrid\Assets\AssetsResolver`
- `Contracts\AssetsInterface` renamed to `Contracts\AssetsResolver`
- `Contracts\AssetMetaData` (trait) moved to `Concerns\AssetMetaData`
- `Plugin::overrideAssetsDirectory()` renamed to `getOverrideAssetsDirectory()`
- `Asset::__construct()` takes a `$manifestDirectory` override in place of the precomputed `$absolutePath`
- `setAssetsDirectory()` returns `static` instead of `void`, so directory setters are fluent
- Blank values passed to the directory and manifest-file-name setters are ignored, keeping the existing value, rather than being written through

### Fixed

- `dependencies()` had no way to merge caller-supplied handles, forcing consumers to re-merge at the call site (#3)
- Version hashing resolves the asset path through the resolver instead of a path captured at construction, and now checks readability and a failed `md5_file()` before returning

## [1.0.0-alpha.4] - 2026-07-15



### Added

- `Asset` value object — resolves a file's URL, path, dependencies, and version in one call via the new `asset()` method
- `.asset.php` (wp-scripts) metadata support, including dependency resolution
- Mix manifest (`mix-manifest.json`) fallback for version resolution when no `.asset.php` file exists
- `Plugin::setOverrideAssetsDirectory()` — lets themes override a plugin's assets under a configurable path
- `ChildTheme::exists()` and explicit per-class inheritance chains driving `$inherit` lookups

### Changed

- `assetUrl()` / `assetPath()` now accept `(string $file, bool $inherit = false)` instead of a manifest directory override
- `Plugin` is now a non-shared container binding instead of a singleton, so multiple plugins don't share state
- Minimum PHP requirement raised to 8.2
- Renamed `Provider` to `AssetsServiceProvider`

### Removed

- `prepareAsset()` manifest-based file rewriting, replaced by `AssetMetaData`'s dedicated `.asset.php` / Mix manifest resolution

## [1.0.0-alpha.3] - 2024-11-29

### Changed

- Add $inherit parameter to handle child theme asset fallback in ParentTheme class

## [1.0.0-alpha.2] - 2024-08-02

### Changed

- Add composer sort-packages configuration

## [1.0.0-alpha.1] - 2023-10-04

### Added

- Launch.  Everything's new!
