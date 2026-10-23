<?php

namespace Hybrid\Assets\Contracts;

/**
 * A resolved SVG asset, ready to render.
 */
interface Svg {
    /**
     * Toggle sanitization. On by default.
     */
    public function sanitize( bool $sanitize = true ): self;

    /**
     * Get the (optionally sanitized) SVG markup.
     *
     * Returns an empty string if the file doesn't exist, isn't readable, or
     * — when sanitizing — contains no valid SVG. Never throws for a missing
     * or invalid file; that's an expected outcome for markup this method
     * treats as untrusted by default.
     */
    public function render(): string;

    /**
     * Echo the (optionally sanitized) SVG markup.
     */
    public function display(): void;
}
