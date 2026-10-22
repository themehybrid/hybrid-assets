<?php

use Hybrid\Assets\AssetsServiceProvider;
use Hybrid\Assets\ChildTheme;
use Hybrid\Assets\ParentTheme;
use Hybrid\Assets\Plugin;
use Hybrid\Container\Container;

beforeEach( function () {
    $this->container = new Container;
} );

afterEach( function () {
    Container::setInstance( null );
} );

it( 'registers ParentTheme and ChildTheme as singletons', function () {
    ( new AssetsServiceProvider( $this->container ) )->register();

    expect( $this->container->make( ParentTheme::class ) )
        ->toBeInstanceOf( ParentTheme::class )
        ->and( $this->container->make( ParentTheme::class ) )
        ->toBe( $this->container->make( ParentTheme::class ) );

    expect( $this->container->make( ChildTheme::class ) )
        ->toBeInstanceOf( ChildTheme::class )
        ->and( $this->container->make( ChildTheme::class ) )
        ->toBe( $this->container->make( ChildTheme::class ) );
} );

it( 'binds Plugin as a fresh instance every time, since it carries per-consumer state', function () {
    ( new AssetsServiceProvider( $this->container ) )->register();

    $first  = $this->container->make( Plugin::class );
    $second = $this->container->make( Plugin::class );

    expect( $first )->toBeInstanceOf( Plugin::class )
        ->and( $second )->toBeInstanceOf( Plugin::class )
        ->and( $first )->not->toBe( $second );
} );

it( 'each Plugin instance keeps its own configuration', function () {
    ( new AssetsServiceProvider( $this->container ) )->register();

    $pluginA = $this->container->make( Plugin::class )->setPluginFile( '/plugins/a/a.php' );
    $pluginB = $this->container->make( Plugin::class )->setPluginFile( '/plugins/b/b.php' );

    expect( $pluginA->pluginFile() )->toBe( '/plugins/a/a.php' )
        ->and( $pluginB->pluginFile() )->toBe( '/plugins/b/b.php' );
} );

it( 'marks all three resolvers as bound after registering', function () {
    ( new AssetsServiceProvider( $this->container ) )->register();

    expect( $this->container->bound( ParentTheme::class ) )->toBeTrue()
        ->and( $this->container->bound( ChildTheme::class ) )->toBeTrue()
        ->and( $this->container->bound( Plugin::class ) )->toBeTrue();
} );
