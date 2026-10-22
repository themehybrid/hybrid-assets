<?php

namespace Hybrid\Assets\Tests\Fixtures;

use Hybrid\Assets\AssetsResolver;

/**
 * Minimal concrete AssetsResolver used to exercise the abstract base class'
 * behaviour directly, without needing WordPress theme/plugin functions.
 */
class FakeResolver extends AssetsResolver {
    /**
     * Controls what exists() returns; toggled in tests that walk the
     * inheritance chain.
     */
    public bool $existsValue = true;

    /** @var array<int, class-string<\Hybrid\Assets\Contracts\AssetsResolver>> */
    protected array $inheritance = [];

    public function __construct(
        protected string $root = '',
        protected string $baseUrl = 'https://example.test'
    ) {}

    public function path( string $file = '' ): string {
        return $this->root . $file;
    }

    public function url( string $file = '' ): string {
        return $this->baseUrl . $file;
    }

    public function exists(): bool {
        return $this->existsValue;
    }

    /**
     * @param array<int, class-string<\Hybrid\Assets\Contracts\AssetsResolver>> $inheritance
     */
    public function setInheritance( array $inheritance ): static {
        $this->inheritance = $inheritance;

        return $this;
    }

    public function setOverrideAssetsDirectoryValue( string $value ): static {
        $this->overrideAssetsDirectoryValue = $value;

        return $this;
    }

    protected string $overrideAssetsDirectoryValue = '';

    public function getOverrideAssetsDirectory(): string {
        return $this->overrideAssetsDirectoryValue;
    }

    // -- Expose protected internals for direct unit testing -----------------

    public function callNormalizeFile( string $file ): string {
        return $this->normalizeFile( $file );
    }

    public function callNormalizeDirectory( string $directory ): string {
        return $this->normalizeDirectory( $directory );
    }

    public function callPrependAssetsDir( string $file ): string {
        return $this->prependAssetsDir( $file );
    }
}
