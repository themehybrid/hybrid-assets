<?php

use Hybrid\Assets\Contracts\AssetsException;
use Hybrid\Assets\Exceptions\InvalidAssetFileException;
use Hybrid\Assets\Exceptions\PathOutsideBaseException;
use Hybrid\Assets\Exceptions\PluginFileNotSetException;
use Hybrid\Assets\Exceptions\UnresolvableBaseDirectoryException;
use Hybrid\Assets\Plugin;
use Hybrid\Assets\Tests\Fixtures\FakeResolver;

afterEach( function () {
    if ( is_dir( assets_fixture_path( 'exceptions' ) ) ) {
        delete_directory( assets_fixture_path( 'exceptions' ) );
    }
} );

// -- InvalidAssetFileException -------------------------------------------------

it( 'throws InvalidAssetFileException for a blank file', function () {
    ( new FakeResolver( '/does/not/matter' ) )->asset( '   ' );
} )->throws( InvalidAssetFileException::class, 'Asset file path must not be blank.' );

it( 'InvalidAssetFileException implements the package exception contract', function () {
    expect( InvalidAssetFileException::blank() )->toBeInstanceOf( AssetsException::class )
        ->and( InvalidAssetFileException::blank() )->toBeInstanceOf( InvalidArgumentException::class );
} );

// -- PathOutsideBaseException ---------------------------------------------------

it( 'throws PathOutsideBaseException, with a correct message, when the resolved .asset.php escapes the base directory', function () {
    // Regression test: the original implementation referenced an undefined
    // `$realBaseDirectory` variable when building this exception's message,
    // instead of the `realpath( $basePath )` value it actually computed.
    $root    = assets_fixture_path( 'exceptions/base/public' );
    $outside = assets_fixture_path( 'exceptions/base/outside' );

    mkdir( $root, recursive: true, permissions: 0755 );
    mkdir( $outside, recursive: true, permissions: 0755 );
    file_put_contents( $outside . '/evil.asset.php', '<?php return [];' );

    $resolver = new FakeResolver( $root );

    // `/../outside/evil.js` resolves (relative to $root) to a `.asset.php`
    // file that lives one directory above the resolver's own base.
    $asset = $resolver->resolve( '/../outside/evil.js' );

    $expectedAssetPath = realpath( $outside . '/evil.asset.php' );
    $expectedBasePath  = realpath( $root );

    try {
        $asset->resolveAssetData();

        $this->fail( 'Expected PathOutsideBaseException was not thrown.' );
    } catch ( PathOutsideBaseException $e ) {
        expect( $e )->toBeInstanceOf( AssetsException::class )
            ->and( $e->getMessage() )->toBe( sprintf(
                'Path "%s" is not within the resolver base "%s".',
                $expectedAssetPath,
                $expectedBasePath
            ) )
            // The old buggy message always collapsed the base directory to
            // an empty string — assert directly against that regression too.
            ->and( $e->getMessage() )->not->toContain( 'base ""' );
    }
} );

// -- Path traversal protection (.asset.php lookups) -----------------------------

it( 'refuses to load a .asset.php that traversal escapes the base directory, at any depth', function ( string $traversalFile, int $upCount ) {
    // A base deep enough to have $upCount real ancestors to escape into:
    // .../traversal/a/b/c/theme/public
    $root = assets_fixture_path( 'exceptions/traversal/a/b/c/theme/public' );
    mkdir( $root, recursive: true, permissions: 0755 );

    // If the payload walks through a named subdirectory (e.g. `foo/../..`),
    // that directory must physically exist — realpath() needs every path
    // component to resolve, it does not do purely lexical '..' collapsing.
    if ( str_contains( $traversalFile, 'foo/' ) ) {
        mkdir( $root . '/foo', recursive: true, permissions: 0755 );
    }

    // dirname( $root, $upCount ) is exactly the directory $upCount `../`
    // segments from $root resolve to — i.e. where the payload should land.
    $ancestor = dirname( $root, $upCount );
    file_put_contents( $ancestor . '/evil.asset.php', '<?php return [ "version" => "leaked" ];' );

    $resolver = new FakeResolver( $root );
    $asset    = $resolver->resolve( $traversalFile );

    // Confirm the traversal really does land on the planted file — otherwise
    // this test would trivially pass for the wrong reason.
    expect( realpath( $ancestor . '/evil.asset.php' ) )->not->toBeFalse();

    $asset->resolveAssetData();
} )->with( [
    'one level up    (../evil.js)'                  => [ '/../evil.js', 1 ],
    'two levels up   (../../evil.js)'               => [ '/../../evil.js', 2 ],
    'three levels up (../../../evil.js)'            => [ '/../../../evil.js', 3 ],
    'traversal buried mid-path (foo/../../evil.js)' => [ '/foo/../../evil.js', 1 ],
] )->throws( PathOutsideBaseException::class );

it( 'rejects a sibling directory that merely shares the base path as a string prefix', function () {
    // Regression test for the containment check's directory-boundary anchor.
    // Unlike the traversal cases above (which escape to an *ancestor*, and so
    // fail a naive prefix check anyway), this is the one shape that a bare
    // `str_starts_with( $path, $base )` lets through: "site-evil" textually
    // starts with "site", despite being an entirely different directory.
    // If the `. DIRECTORY_SEPARATOR` anchor is ever dropped, only this test
    // goes red.
    $root = assets_fixture_path( 'exceptions/sibling/site' );
    $evil = assets_fixture_path( 'exceptions/sibling/site-evil' );

    mkdir( $root, recursive: true, permissions: 0755 );
    mkdir( $evil, recursive: true, permissions: 0755 );
    file_put_contents( $evil . '/evil.asset.php', '<?php return [ "version" => "leaked" ];' );

    $resolver = new FakeResolver( $root );
    $asset    = $resolver->resolve( '/../site-evil/evil.js' );

    // Confirm the payload really lands on the planted file, so this can't
    // pass for the wrong reason.
    expect( realpath( $evil . '/evil.asset.php' ) )->not->toBeFalse();

    $asset->resolveAssetData();
} )->throws( PathOutsideBaseException::class );

it( 'ignores a directory that happens to be named like a .asset.php file', function () {
    // realpath() succeeds for a directory, so only the is_file() guard stops
    // this reaching `include` — which would emit a warning and return false.
    $root = assets_fixture_path( 'exceptions/is-dir/public' );

    mkdir( $root . '/js/app.asset.php', recursive: true, permissions: 0755 );

    $resolver = new FakeResolver( $root );

    expect( $resolver->resolve( '/js/app.js' )->resolveAssetData() )->toBeNull();
} );

it( 'never leaks or reads a traversal target that does not exist — it just fails safe', function () {
    // realpath() on a nonexistent path returns false, so assetPhpPath()
    // returns null *before* the containment check ever runs. No exception,
    // no file read — the request simply yields "no meta data for this file".
    $root = assets_fixture_path( 'exceptions/no-target/theme/public' );
    mkdir( $root, recursive: true, permissions: 0755 );

    $resolver = new FakeResolver( $root );
    $asset    = $resolver->resolve( '/../../../../../../etc/passwd.js' );

    expect( $asset->resolveAssetData() )->toBeNull();
} );

it( 'resolves a .asset.php for a file that has no extension', function () {
    // The `'' !== $extension` branch: nothing to strip, so the meta file name
    // is the asset name with `.asset.php` appended wholesale.
    $root = assets_fixture_path( 'exceptions/no-ext/public' );

    mkdir( $root . '/bin', recursive: true, permissions: 0755 );
    file_put_contents( $root . '/bin/runner.asset.php', '<?php return [ "version" => "no-ext" ];' );

    $resolver = new FakeResolver( $root );

    expect( $resolver->resolve( '/bin/runner' )->resolveAssetData() )
        ->toBe( [
            'dependencies' => [],
            'version'      => 'no-ext',
        ] );
} );

it( 'refuses to include a .asset.php that is a symlink to a differently-named file', function () {
    // assetPhpPath() returns the *canonical* path, so a symlink pointing at a
    // non-meta file resolves to its real name and is rejected by the
    // `.asset.php` suffix guard in readAssetPhpFile() rather than included.
    $root = assets_fixture_path( 'exceptions/symlink/public' );

    mkdir( $root . '/js', recursive: true, permissions: 0755 );
    file_put_contents( $root . '/js/payload.php', '<?php return [ "version" => "leaked" ];' );
    // Windows refuses symlinks without Developer Mode or admin rights. Without
    // the link the file is simply missing, which would pass for the wrong reason.
    // PHPUnit records the warning even under @, so silence it with a handler.
    set_error_handler( static fn() => true );
    $linked = symlink( $root . '/js/payload.php', $root . '/js/app.asset.php' );
    restore_error_handler();

    if ( ! $linked ) {
        $this->markTestSkipped( 'This system cannot create symlinks.' );
    }

    $resolver = new FakeResolver( $root );

    expect( $resolver->resolve( '/js/app.js' )->resolveAssetData() )->toBeNull();
} );

it( 'still resolves a legitimately nested .asset.php inside the base directory', function () {
    // Sanity/control case: the containment check must not be so strict that
    // it rejects ordinary nested asset paths that never leave the base.
    $root = assets_fixture_path( 'exceptions/legit/theme/public' );
    mkdir( $root . '/vendor/widgets', recursive: true, permissions: 0755 );
    file_put_contents(
        $root . '/vendor/widgets/tabs.asset.php',
        '<?php return [ "dependencies" => [ "jquery" ], "version" => "abc123" ];'
    );

    $resolver = new FakeResolver( $root );
    $asset    = $resolver->resolve( '/vendor/widgets/tabs.js' );

    expect( $asset->resolveAssetData() )->toBe( [
        'dependencies' => [ 'jquery' ],
        'version'      => 'abc123',
    ] );
} );

// -- UnresolvableBaseDirectoryException ------------------------------------------

it( 'throws UnresolvableBaseDirectoryException instead of silently bypassing the containment check', function () {
    // If the resolver's base directory can't be resolved via realpath()
    // (returns false), str_starts_with() would coerce that to an empty
    // string and treat every path as "contained" — silently defeating the
    // check. This resolver simulates that split: the individual file
    // resolves to a real path, but the base directory does not.
    $fileRoot = assets_fixture_path( 'exceptions/unresolvable' );
    mkdir( $fileRoot, recursive: true, permissions: 0755 );
    file_put_contents( $fileRoot . '/app.asset.php', '<?php return [];' );

    $resolver = new class( $fileRoot ) extends FakeResolver {
        public function path( string $file = '' ): string {
            return '' === $file
                ? '/definitely/does/not/exist/' . uniqid()
                : parent::path( $file );
        }
    };

    $asset = $resolver->resolve( '/app.js' );

    $asset->resolveAssetData();
} )->throws( UnresolvableBaseDirectoryException::class );

it( 'UnresolvableBaseDirectoryException implements the package exception contract', function () {
    expect( UnresolvableBaseDirectoryException::forPath( '/nope' ) )->toBeInstanceOf( AssetsException::class )
        ->and( UnresolvableBaseDirectoryException::forPath( '/nope' ) )->toBeInstanceOf( RuntimeException::class )
        ->and( UnresolvableBaseDirectoryException::forPath( '/nope' )->getMessage() )
        ->toBe( 'Resolver base directory "/nope" could not be resolved to a real path.' );
} );

// -- PluginFileNotSetException ---------------------------------------------------

it( 'throws PluginFileNotSetException when no plugin file has been set', function () {
    ( new Plugin )->pluginFile();
} )->throws( PluginFileNotSetException::class, 'No plugin file set on Hybrid\Assets\Plugin.' );

it( 'PluginFileNotSetException implements the package exception contract and remains a LogicException', function () {
    $exception = PluginFileNotSetException::forResolver( Plugin::class );

    expect( $exception )->toBeInstanceOf( AssetsException::class )
        ->and( $exception )->toBeInstanceOf( LogicException::class );
} );
