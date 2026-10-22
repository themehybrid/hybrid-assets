<?php

namespace Hybrid\Assets;

use Hybrid\Assets\Contracts\Svg as SvgContract;
use Hybrid\Assets\Support\SvgSanitizer;

/**
 * Resolves, reads, and (by default) sanitizes an SVG file located via the
 * standard asset resolution/inheritance chain.
 *
 * Deliberately does not extend or implement `Asset`: an SVG's meaning is its
 * markup, not a URL + version + dependency list, so none of `Asset`'s
 * version-hash or `.asset.php`/manifest machinery applies here. Composition
 * over `Asset` for path resolution only, keeps that machinery out of reach.
 */
final class Svg implements SvgContract {
    private ?string $content = null;

    private bool $sanitize = true;

    public function __construct( private readonly Asset $asset ) {}

    public function sanitize( bool $sanitize = true ): self {
        if ( $sanitize !== $this->sanitize ) {
            // Invalidate the cache: content differs depending on this flag.
            $this->content = null;
        }

        $this->sanitize = $sanitize;

        return $this;
    }

    public function render(): string {
        if ( null !== $this->content ) {
            return $this->content;
        }

        $path = $this->asset->path();

        if ( ! is_file( $path ) || ! is_readable( $path ) ) {
            return $this->content = '';
        }

        $raw = file_get_contents( $path );

        if ( false === $raw ) {
            return $this->content = '';
        }

        return $this->content = $this->sanitize
            ? SvgSanitizer::sanitize( $raw )
            : $raw;
    }

    public function display(): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG markup, sanitized above by default; raw output is opt-in via sanitize(false).
        echo $this->render();
    }
}
