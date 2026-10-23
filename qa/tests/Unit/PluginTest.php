<?php

use Hybrid\Assets\ChildTheme;
use Hybrid\Assets\ParentTheme;
use Hybrid\Assets\Plugin;

// -- pluginFile() -------------------------------------------------------------

it( 'throws when no plugin file has been set', function () {
    ( new Plugin )->pluginFile();
} )->throws( LogicException::class, 'No plugin file set on Hybrid\Assets\Plugin.' );

it( 'setPluginFile() is fluent and pluginFile() returns what was set', function () {
    $plugin = new Plugin;

    expect( $plugin->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' ) )->toBe( $plugin )
        ->and( $plugin->pluginFile() )->toBe( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );
} );

// -- getOverrideAssetsDirectory() ---------------------------------------------

it( 'defaults the override assets directory to the assets dir + plugin dir name', function () {
    $plugin = ( new Plugin )
        ->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    // Default assets directory is `/public`.
    expect( $plugin->getOverrideAssetsDirectory() )->toBe( '/public/my-plugin' );
} );

it( 'reflects a custom assets directory in the default override directory', function () {
    $plugin = ( new Plugin )
        ->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' )
        ->setAssetsDirectory( 'dist' );

    expect( $plugin->getOverrideAssetsDirectory() )->toBe( '/dist/my-plugin' );
} );

it( 'setOverrideAssetsDirectory() takes precedence over the default', function () {
    $plugin = ( new Plugin )
        ->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' )
        ->setOverrideAssetsDirectory( 'public/custom-name' );

    expect( $plugin->getOverrideAssetsDirectory() )->toBe( '/public/custom-name' );
} );

it( 'setOverrideAssetsDirectory() is fluent', function () {
    $plugin = new Plugin;

    expect( $plugin->setOverrideAssetsDirectory( 'public/x' ) )->toBe( $plugin );
} );

// -- path()/url() --------------------------------------------------------------

/*
 * Migrated off Brain Monkey. `plugin_dir_path()` and `plugin_dir_url()` are
 * pure string operations over the plugin file path, so the real functions
 * behave deterministically without the plugin existing on disk — but
 * `plugin_dir_url()` resolves relative to WP_PLUGIN_DIR, so the fixture path
 * is anchored there rather than at an arbitrary /var/www path.
 */

it( 'resolves path() via plugin_dir_path()', function () {
    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    expect( $plugin->path( '/public/js/app.js' ) )
        ->toBe( WP_PLUGIN_DIR . '/my-plugin/public/js/app.js' );
} );

it( 'path() returns the bare plugin directory for an empty file', function () {
    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    expect( $plugin->path() )->toBe( WP_PLUGIN_DIR . '/my-plugin/' );
} );

it( 'resolves url() via plugin_dir_url()', function () {
    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    expect( $plugin->url( '/public/js/app.js' ) )
        ->toBe( plugins_url( '', WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' ) . '/public/js/app.js' );
} );

it( 'url() returns the bare plugin URL for an empty file', function () {
    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    expect( $plugin->url() )->toEndWith( '/my-plugin/' )
        ->and( $plugin->url() )->toStartWith( WP_PLUGIN_URL );
} );

it( 'path()/url() strip a leading slash before appending the file', function () {
    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    // No accidental double slash between the plugin dir and the file.
    expect( $plugin->path( '/js/app.js' ) )->toBe( WP_PLUGIN_DIR . '/my-plugin/js/app.js' )
        ->and( $plugin->path( '/js/app.js' ) )->not->toContain( '//js' );
} );

// -- Inheritance ----------------------------------------------------------------

it( 'falls back to the child theme, then the parent theme', function () {
    $reflection = new ReflectionProperty( Plugin::class, 'inheritance' );
    $reflection->setAccessible( true );

    expect( $reflection->getValue( new Plugin ) )->toBe( [ ChildTheme::class, ParentTheme::class ] );
} );

it( 'looks up inherited files under its override assets directory, not its own', function () {
    // A theme overriding this plugin's assets is expected to host them at
    // `{theme}/public/my-plugin/...`, not `{theme}/public/...` — this is
    // the one piece of behaviour that's specific to Plugin (see
    // AssetsResolver::asset()'s `$this instanceof Plugin` branch).
    $themeRoot = assets_fixture_path( 'theme' );

    mkdir( assets_fixture_path( 'theme/public/my-plugin/js' ), recursive: true, permissions: 0755 );
    file_put_contents( assets_fixture_path( 'theme/public/my-plugin/js/override.js' ), "console.log('override');" );

    $themeResolver = new Hybrid\Assets\Tests\Fixtures\FakeResolver( $themeRoot, 'https://example.test' );

    $container = new Hybrid\Container\Container;
    $container->instance( ChildTheme::class, $themeResolver );
    Hybrid\Container\Container::setInstance( $container );

    $plugin = ( new Plugin )->setPluginFile( WP_PLUGIN_DIR . '/my-plugin/my-plugin.php' );

    $asset = $plugin->asset( 'js/override.js', inherit: true );

    expect( $asset->path() )->toBe( $themeRoot . '/public/my-plugin/js/override.js' );

    Hybrid\Container\Container::setInstance( null );
    delete_directory( assets_fixture_path( 'theme/public/my-plugin' ) );
} );
