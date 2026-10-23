<?php

use Hybrid\Assets\Tests\Fixtures\FakeResolver;

beforeEach( function () {
    $this->themeRoot = assets_fixture_path( 'theme' );
    $this->resolver  = new FakeResolver( $this->themeRoot, 'https://example.test' );
} );

it( 'exposes the file it was resolved with', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->file() )->toBe( '/public/js/app.js' );
} );

it( 'delegates url() to the resolver', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->url() )->toBe( 'https://example.test/public/js/app.js' );
} );

it( 'delegates path() to the resolver', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'reads dependencies from an .asset.php meta file', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->dependencies() )->toBe( [ 'wp-element', 'wp-i18n' ] );
} );

it( 'merges and dedupes additional dependencies', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->dependencies( [ 'wp-i18n', 'jquery' ] ) )
        ->toBe( [ 'wp-element', 'wp-i18n', 'jquery' ] );
} );

it( 'reads the version from an .asset.php meta file', function () {
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->version() )->toBe( 'asset-php-version-123' );
} );

it( 'has no dependencies for a Mix-only asset', function () {
    $asset = $this->resolver->asset( 'js/mixed.js' );

    expect( $asset->dependencies() )->toBe( [] )
        ->and( $asset->dependencies( [ 'jquery' ] ) )->toBe( [ 'jquery' ] );
} );

it( 'reads a query-string style version from mix-manifest.json', function () {
    $asset = $this->resolver->asset( 'css/mixed.css' );

    expect( $asset->version() )->toBe( 'deadbeef1234' );
} );

it( 'reads a filename-hash style version from mix-manifest.json', function () {
    $asset = $this->resolver->asset( 'js/mixed.js' );

    expect( $asset->version() )->toBe( 'ab12cd34' );
} );

it( 'falls back to a content hash when no meta data source has an entry', function () {
    $asset = $this->resolver->asset( 'js/no-meta.js' );

    $expected = substr( md5_file( $this->themeRoot . '/public/js/no-meta.js' ), 0, 20 );

    expect( $asset->version() )->toBe( $expected )
        ->and( $asset->version() )->toHaveLength( 20 );
} );

it( 'returns a null version when the file does not exist and there is no meta data', function () {
    $asset = $this->resolver->asset( 'js/missing.js' );

    expect( $asset->version() )->toBeNull();
} );

it( 'prefers the .asset.php file over the Mix manifest when both could apply', function () {
    // app.js only has an .asset.php entry; confirm it never falls through
    // to a (nonexistent) manifest lookup that could hide the real value.
    $asset = $this->resolver->asset( 'js/app.js' );

    expect( $asset->version() )->toBe( 'asset-php-version-123' );
} );

it( 'lets a per-lookup manifest directory override the resolver defaults', function () {
    // `dist/mix-manifest.json` keys its entries relative to `/dist`, not the
    // resolver's `/public` assets directory — only passing the manifest
    // directory explicitly lets the key-stripping match up.
    $asset = $this->resolver->asset( 'dist/js/nested.js', false, 'public/dist' );

    expect( $asset->version() )->toBe( 'deadbeef99' );
} );

it( 'lets a resolver-level manifest directory override the assets directory', function () {
    $resolver = ( new FakeResolver( $this->themeRoot, 'https://example.test' ) )
        ->setManifestDirectory( 'public/dist' );

    $asset = $resolver->asset( 'dist/js/nested.js' );

    expect( $asset->version() )->toBe( 'deadbeef99' );
} );
