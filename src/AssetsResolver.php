<?php

/**
 * Base implementation of AssetsResolver — manifest resolution, versioning, path prep.
 */

namespace Hybrid\Assets;

use Hybrid\Assets\Contracts\AssetsResolver as AssetsResolverContract;
use Hybrid\Assets\Exceptions\InvalidAssetFileException;
use Hybrid\Tools\Str;
use function Hybrid\app;
use function Hybrid\Tools\blank;

abstract class AssetsResolver implements AssetsResolverContract {
    /**
     * Assets directory path.
     */
    protected string $assetsDirectory = '/public';

    /**
     * The manifest directory path.
     */
    protected string $manifestDirectory = '';

    /**
     * File name of the Mix manifest.
     */
    protected string $manifestFileName = 'mix-manifest.json';

    /**
     * Container binding keys checked, in order, when resolving with
     * `$inherit = true`. Empty by default — a resolver with no chain of its
     * own (e.g. `ChildTheme`, which nothing overrides) simply resolves
     * against its own files.
     *
     * @var array<int, class-string<\Hybrid\Assets\Contracts\AssetsResolver>>
     */
    protected array $inheritance = [];

    /**
     * Whether this resolver's source (theme, plugin, etc.) is actually
     * available. Overridden by resolvers that can be inactive, e.g.
     * `ChildTheme`. Consulted when walking an inheritance chain.
     */
    public function exists(): bool {
        return true;
    }

    /**
     * Set the assets directory path.
     *
     * @param string $assetsDirectory Assets directory path.
     */
    public function setAssetsDirectory( string $assetsDirectory ): static {
        // Empty directory path is not allowed.
        if ( blank( $assetsDirectory ) ) {
            return $this;
        }

        $this->assetsDirectory = $this->normalizeDirectory( $assetsDirectory );

        return $this;
    }

    /**
     * Get the assets directory path.
     */
    public function getAssetsDirectory(): string {
        return $this->assetsDirectory;
    }

    /**
     * Set the manifest directory path.
     *
     * @param string $manifestDirectory Manifest directory path.
     */
    public function setManifestDirectory( string $manifestDirectory ): static {
        // Empty directory path is not allowed.
        if ( blank( $manifestDirectory ) ) {
            return $this;
        }

        $this->manifestDirectory = $this->normalizeDirectory( $manifestDirectory );

        return $this;
    }

    /**
     * Get the manifest directory path.
     */
    public function getManifestDirectory(): string {
        return $this->manifestDirectory;
    }

    /**
     * Set the Mix manifest file name.
     *
     * A blank value (empty string, or whitespace-only) is ignored, keeping
     * whatever file name (default or previously set) is already configured.
     *
     * @param string $manifestFileName Manifest file name.
     */
    public function setManifestFileName( string $manifestFileName ): static {
        if ( ! blank( $manifestFileName ) ) {
            $this->manifestFileName = Str::trim( $manifestFileName );
        }

        return $this;
    }

    /**
     * Get the Mix manifest file name.
     */
    public function getManifestFileName(): string {
        return $this->manifestFileName;
    }

    /**
     * Get the asset URL for a file.
     *
     * @param string $file Relative file path within the assets directory.
     * @param bool   $inherit Check child theme first, where applicable.
     */
    public function assetUrl( string $file, bool $inherit = false ): string {
        return $this->asset( $file, $inherit )->url();
    }

    /**
     * Get the absolute filesystem path for a file within the assets directory.
     *
     * @param string $file Relative file path within the assets directory.
     * @param bool   $inherit Check child theme first, where applicable.
     */
    public function assetPath( string $file, bool $inherit = false ): string {
        return $this->asset( $file, $inherit )->path();
    }

    /**
     * Resolve a fully-described `Asset` instance for a file.
     *
     * @param string $file Relative file path, e.g. `js/admin/tabs.js`.
     * @param bool   $inherit Whether to check the inheritance chain (e.g. child theme) first.
     * @param string $manifestDirectory Optional per-lookup manifest directory override.
     *
     * @throws InvalidAssetFileException
     */
    public function asset( string $file, bool $inherit = false, string $manifestDirectory = '' ): Asset {
        $file = $this->normalizeFile( $file );

        if ( $inherit ) {
            // Plugins are looked up under their override directory in the theme(s);
            // themes are looked up under their own assets directory as-is.
            $lookupFile = $this instanceof Plugin
                ? $this->prependOverrideAssetsDirectory( $file )
                : $this->prependAssetsDir( $file );

            foreach ( $this->inheritanceChain() as $resolver ) {
                if ( ! $resolver->exists() ) {
                    continue;
                }

                $candidate = $resolver->path( $lookupFile );

                if ( is_file( $candidate ) && is_readable( $candidate ) ) {
                    return $resolver->resolve( $lookupFile, $manifestDirectory );
                }
            }
        }

        return $this->resolve( $this->prependAssetsDir( $file ), $manifestDirectory );
    }

    /**
     * Resolve an `Svg` instance for a file, sanitized by default.
     *
     * @param string $file Relative file path, e.g. `images/icons/arrow.svg`.
     * @param bool   $inherit Whether to check the inheritance chain (e.g. child theme) first.
     */
    public function svg( string $file, bool $inherit = false ): Svg {
        return new Svg( $this->asset( $file, $inherit ) );
    }

    /**
     * Normalize a relative file path: guarantee a leading slash.
     *
     * @param string $file Relative file path.
     */
    protected function normalizeFile( string $file ): string {
        if ( blank( $file ) ) {
            throw InvalidAssetFileException::blank();
        }

        return $this->normalizeDirectory( $file );
    }

    /**
     * Normalize a directory path to a leading-slash, no-trailing-slash form
     * (or '/'), so path and manifest lookups can rely on its shape.
     */
    protected function normalizeDirectory( string $directory ): string {
        $directory = wp_normalize_path( $directory );
        $directory = trim( $directory, '/' );

        return Str::start( $directory, '/' );
    }

    /**
     * Prepend the assets directory to a file path.
     *
     * @param string $file Relative file path within the assets.
     */
    protected function prependAssetsDir( string $file ): string {
        return $this->getAssetsDirectory() . $file;
    }

    /**
     * Prepend this resolver's override assets directory to a file path, for use
     * when looking the file up in *other* resolvers (the inheritance chain).
     */
    protected function prependOverrideAssetsDirectory( string $file ): string {
        return $this->getOverrideAssetsDirectory() . $file;
    }

    /**
     * Directory name used by other resolvers when overriding this resolver's assets
     * (currently only meaningful for `Plugin`). Inheritance
     * lookups prefix the requested file with this value, so a theme can host
     * overrides for several plugins side by side, e.g.
     * `{theme}/public/{override-assets-directory}/js/tabs.js`.
     *
     * Only applies when *this* resolver looks outward into its inheritance
     * chain — resolving a file against this resolver's own files never uses it.
     */
    public function getOverrideAssetsDirectory(): string {
        return '';
    }

    /**
     * Resolve this resolver's inheritance chain to actual `AssetsResolver` instances,
     * silently skipping any binding that isn't registered or fails to resolve.
     *
     * @return array<int, self>
     */
    protected function inheritanceChain(): array {
        $app   = app();
        $chain = [];

        foreach ( $this->inheritance as $binding ) {
            if ( ! $app || ! $app->bound( $binding ) ) {
                continue;
            }

            try {
                $resolved = $app->make( $binding );
            } catch ( \Throwable ) {
                // Skip bindings that fail to resolve rather than breaking asset lookup.
                continue;
            }

            if ( $resolved instanceof self ) {
                $chain[] = $resolved;
            }
        }

        return $chain;
    }

    /**
     * Resolve an `Asset` against this resolver specifically, without consulting
     * the inheritance chain. Metadata is looked up via `.asset.php`, then
     * the Mix manifest, then a filemtime-based hash fallback.
     *
     * @param string $file Relative file path to look up, e.g. `public/js/tabs.js`.
     */
    public function resolve( string $file, string $manifestDirectory = '' ): Asset {
        return new Asset(
            assetResolver: $this,
            file: $file,
            manifestDirectory: $manifestDirectory
        );
    }
}
