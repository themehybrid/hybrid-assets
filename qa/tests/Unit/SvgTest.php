<?php

/*
|--------------------------------------------------------------------------
| Coverage scope
|--------------------------------------------------------------------------
|
| Svg's own behaviour: resolution, file reading, caching, and the sanitize()
| toggle. What the sanitizer actually strips is covered separately, against
| core's own dataset, in tests/Integration/SvgSanitizerTest.php.
|
*/

use Hybrid\Assets\Tests\Fixtures\FakeResolver;

/** Markup that survives sanitization unchanged, so raw and clean are comparable. */
const SVG_FIXTURE = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /></svg>';

beforeEach( function () {
    $this->themeRoot = assets_fixture_path( 'svg/theme' );
    $this->resolver  = new FakeResolver( $this->themeRoot, 'https://example.test' );

    mkdir( $this->themeRoot . '/public/images', recursive: true, permissions: 0755 );
    file_put_contents( $this->themeRoot . '/public/images/icon.svg', SVG_FIXTURE );
} );

afterEach( function () {
    if ( is_dir( assets_fixture_path( 'svg' ) ) ) {
        delete_directory( assets_fixture_path( 'svg' ) );
    }
} );

it( 'resolves via the same path/inheritance logic as asset()', function () {
    expect( $this->resolver->svg( 'images/icon.svg' ) )
        ->toBeInstanceOf( \Hybrid\Assets\Svg::class );
} );

it( 'returns an empty string for a missing file, rather than throwing', function () {
    expect( $this->resolver->svg( 'images/does-not-exist.svg' )->render() )->toBe( '' );
} );

it( 'sanitizes by default', function () {
    expect( $this->resolver->svg( 'images/icon.svg' )->render() )->toBe( SVG_FIXTURE );
} );

it( 'strips dangerous markup by default', function () {
    file_put_contents(
        $this->themeRoot . '/public/images/evil.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"><path d="M0 0h24v24H0z" onload="evil()" /></svg>'
    );

    expect( $this->resolver->svg( 'images/evil.svg' )->render() )
        ->not->toContain( 'onclick' )
        ->not->toContain( 'onload' )
        ->toContain( '<path d="M0 0h24v24H0z"' );
} );

it( 'returns raw markup unchanged when sanitize(false) is used', function () {
    file_put_contents(
        $this->themeRoot . '/public/images/evil.svg',
        '<svg onclick="alert(1)"></svg>'
    );

    expect( $this->resolver->svg( 'images/evil.svg' )->sanitize( false )->render() )
        ->toBe( '<svg onclick="alert(1)"></svg>' );
} );

it( 'caches render() so the file is not re-read on repeated calls', function () {
    $svg  = $this->resolver->svg( 'images/icon.svg' );
    $path = $this->themeRoot . '/public/images/icon.svg';

    $first = $svg->render();

    // Mutate on disk after the first render; a cached call returns the original.
    file_put_contents( $path, '<svg><circle r="1" /></svg>' );

    expect( $svg->render() )->toBe( $first );
} );

it( 'invalidates the cache when sanitize() is toggled after a render', function () {
    $path = $this->themeRoot . '/public/images/mixed.svg';
    file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"></svg>' );

    $svg = $this->resolver->svg( 'images/mixed.svg' );

    $raw = $svg->sanitize( false )->render();
    expect( $raw )->toContain( 'onclick' );

    // Toggling must not return the stale raw cache.
    expect( $svg->sanitize( true )->render() )->not->toContain( 'onclick' );
} );

it( 'display() echoes exactly what render() returns', function () {
    $svg = $this->resolver->svg( 'images/icon.svg' );

    ob_start();
    $svg->display();
    $output = ob_get_clean();

    expect( $output )->toBe( $svg->render() )
        ->and( $output )->not->toBe( '' );
} );
