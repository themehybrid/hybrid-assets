<?php

use Hybrid\Assets\Tests\Fixtures\FakeResolver;
use Hybrid\Container\Container;

beforeEach( function () {
    $this->themeRoot = assets_fixture_path( 'theme' );
} );

afterEach( function () {
    // AssetsResolver::inheritanceChain() reads Hybrid\app(), which is backed
    // by this container's shared instance — reset it so bindings from one
    // test never leak into the next.
    Container::setInstance( null );
} );

// -- Directory setters/getters ----------------------------------------------

it( 'normalizes the assets directory to a leading-slash, no-trailing-slash form', function ( string $input, string $expected ) {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setAssetsDirectory( $input );

    expect( $resolver->getAssetsDirectory() )->toBe( $expected );
} )->with( [
    [ 'public', '/public' ],
    [ '/public', '/public' ],
    [ '/public/', '/public' ],
    [ 'public\\build', '/public/build' ],
    [ '', '/public' ],
    [ '/', '/' ],
    [ '///', '/' ],
] );

it( 'ignores an empty or slash-only assets directory and keeps the current one', function () {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setAssetsDirectory( 'dist' );

    $resolver->setAssetsDirectory( '' );

    expect( $resolver->getAssetsDirectory() )->toBe( '/dist' );
} );

it( 'setAssetsDirectory() with invalid input is still fluent', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->setAssetsDirectory( '' ) )->toBe( $resolver );
} );

it( 'normalizes the manifest directory the same way', function () {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setManifestDirectory( '/dist/' );

    expect( $resolver->getManifestDirectory() )->toBe( '/dist' );
} );

it( 'defaults to the mix-manifest.json file name and allows overriding it', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->getManifestFileName() )->toBe( 'mix-manifest.json' );

    $resolver->setManifestFileName( 'custom-manifest.json' );

    expect( $resolver->getManifestFileName() )->toBe( 'custom-manifest.json' );
} );

it( 'ignores a blank manifest file name and keeps the current one', function ( string $blank ) {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setManifestFileName( 'custom-manifest.json' );

    $resolver->setManifestFileName( $blank );

    expect( $resolver->getManifestFileName() )->toBe( 'custom-manifest.json' );
} )->with( [
    'empty string'     => [ '' ],
    'whitespace only'  => [ '   ' ],
    'tab/newline only' => [ "\t\n" ],
] );

it( 'trims a manifest file name with surrounding whitespace', function () {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setManifestFileName( '  custom-manifest.json  ' );

    expect( $resolver->getManifestFileName() )->toBe( 'custom-manifest.json' );
} );

it( 'setters are fluent', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->setAssetsDirectory( 'public' ) )->toBe( $resolver )
        ->and( $resolver->setManifestDirectory( 'public' ) )->toBe( $resolver )
        ->and( $resolver->setManifestFileName( 'x.json' ) )->toBe( $resolver );
} );

// -- normalizeFile() ----------------------------------------------------------

it( 'normalizes file paths', function ( string $input, string $expected ) {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->callNormalizeFile( $input ) )->toBe( $expected );
} )->with( [
    'simple path'              => [ 'js/app.js', '/js/app.js' ],
    'already leading slash'    => [ '/js/app.js', '/js/app.js' ],
    'backslashes normalized'   => [ 'js\\app.js', '/js/app.js' ],
    'dot segments collapsed'   => [ 'js/./app.js', '/js/./app.js' ],
    'double slashes collapsed' => [ 'js//app.js', '/js/app.js' ],
] );

it( 'resolves .. segments and cannot escape the root via traversal', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->callNormalizeFile( 'js/../css/style.css' ) )->toBe( '/js/../css/style.css' )
        ->and( $resolver->callNormalizeFile( '../../etc/passwd' ) )->toBe( '/../../etc/passwd' )
        ->and( $resolver->callNormalizeFile( '../../../js/app.js' ) )->toBe( '/../../../js/app.js' );
} );

// -- assetUrl()/assetPath()/asset() -----------------------------------------

it( 'assetUrl() resolves the full URL for a file within the assets directory', function () {
    $resolver = new FakeResolver( $this->themeRoot, 'https://example.test' );

    expect( $resolver->assetUrl( 'js/app.js' ) )->toBe( 'https://example.test/public/js/app.js' );
} );

it( 'assetPath() resolves the full filesystem path for a file within the assets directory', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    expect( $resolver->assetPath( 'js/app.js' ) )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'asset() returns an Asset resolved against this resolver by default', function () {
    $resolver = new FakeResolver( $this->themeRoot );

    $asset = $resolver->asset( 'js/app.js' );

    expect( $asset )->toBeInstanceOf( \Hybrid\Assets\Asset::class )
        ->and( $asset->file() )->toBe( '/public/js/app.js' );
} );

// -- Inheritance chain --------------------------------------------------------

it( 'resolves against its own files when inherit is false, even if another resolver has the file', function () {
    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'some-binding' ] );

    $asset = $resolver->asset( 'js/app.js', inherit: false );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'falls back to its own files when nothing is bound in the container', function () {
    Container::setInstance( new Container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'some-binding' ] );

    $asset = $resolver->asset( 'js/app.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'resolves against the inheritance chain when the file exists there and inherit is true', function () {
    $childRoot = assets_fixture_path( 'child-theme' );
    $child     = new FakeResolver( $childRoot, 'https://child.example.test' );

    $container = new Container;
    $container->instance( 'child-binding', $child );
    Container::setInstance( $container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'child-binding' ] );

    $asset = $resolver->asset( 'js/override.js', inherit: true );

    expect( $asset->path() )->toBe( $childRoot . '/public/js/override.js' )
        ->and( $asset->url() )->toBe( 'https://child.example.test/public/js/override.js' );
} );

it( 'falls back to its own files when the inheritance chain does not have the file', function () {
    $childRoot = assets_fixture_path( 'child-theme' );
    $child     = new FakeResolver( $childRoot, 'https://child.example.test' );

    $container = new Container;
    $container->instance( 'child-binding', $child );
    Container::setInstance( $container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'child-binding' ] );

    // js/app.js does not exist in the child theme fixture.
    $asset = $resolver->asset( 'js/app.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'skips inheritance bindings that are not bound in the container', function () {
    Container::setInstance( new Container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'missing-binding' ] );

    $asset = $resolver->asset( 'js/app.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'skips inheritance bindings that throw while resolving', function () {
    $container = new Container;
    $container->bind( 'broken-binding', function () {
        throw new RuntimeException( 'nope' );
    } );
    Container::setInstance( $container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'broken-binding' ] );

    $asset = $resolver->asset( 'js/app.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'skips bindings that do not resolve to an AssetsResolver instance', function () {
    $container = new Container;
    $container->instance( 'not-a-resolver', new stdClass );
    Container::setInstance( $container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'not-a-resolver' ] );

    $asset = $resolver->asset( 'js/app.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/app.js' );
} );

it( 'skips resolvers in the chain that do not exist', function () {
    $childRoot          = assets_fixture_path( 'child-theme' );
    $child              = new FakeResolver( $childRoot, 'https://child.example.test' );
    $child->existsValue = false;

    $container = new Container;
    $container->instance( 'child-binding', $child );
    Container::setInstance( $container );

    $resolver = new FakeResolver( $this->themeRoot );
    $resolver->setInheritance( [ 'child-binding' ] );

    // Even though the child theme has this file, exists() === false means
    // it should never be consulted.
    $asset = $resolver->asset( 'js/override.js', inherit: true );

    expect( $asset->path() )->toBe( $this->themeRoot . '/public/js/override.js' );
} );
