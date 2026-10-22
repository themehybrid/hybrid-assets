<?php

use Hybrid\Assets\ChildTheme;

/*
 * Migrated off Brain Monkey — with one acknowledged loss of coverage.
 *
 * `is_child_theme()` is implemented in core as `TEMPLATEPATH !== STYLESHEETPATH`.
 * Those are PHP constants, fixed for the lifetime of the process, so with a
 * real WordPress install it is impossible to exercise both the true and false
 * branches in a single test run. The previous Brain Monkey test did:
 *
 *     Functions\when( 'is_child_theme' )->justReturn( $isChildTheme );
 *     ...->with( [ true, false ] );
 *
 * That is not reproducible here. Rather than pretend otherwise with a
 * tautology, the delegation contract is pinned at the call site (below), and
 * the value branch is asserted against whatever the provisioned install
 * actually is. If the test environment ever gains a child theme, the second
 * test flips on its own rather than silently passing.
 */

it( 'exists() agrees with the active install', function () {
    expect( ( new ChildTheme )->exists() )->toBe( is_child_theme() );
} );

it( 'exists() delegates to is_child_theme()', function () {
    $reflection = new ReflectionMethod( ChildTheme::class, 'exists' );
    $lines      = file( $reflection->getFileName() );
    $source     = implode( '', array_slice(
        $lines,
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1
    ) );

    expect( $source )->toContain( 'is_child_theme(' );
} );

it( 'resolves path() via the stylesheet directory', function () {
    expect( ( new ChildTheme )->path( '/public/js/app.js' ) )
        ->toBe( get_stylesheet_directory() . '/public/js/app.js' );
} );

it( 'path() returns the bare stylesheet directory for an empty file', function () {
    expect( ( new ChildTheme )->path() )->toBe( get_stylesheet_directory() );
} );

it( 'resolves url() via the stylesheet directory URI', function () {
    expect( ( new ChildTheme )->url( '/public/js/app.js' ) )
        ->toBe( get_stylesheet_directory_uri() . '/public/js/app.js' );
} );

it( 'url() returns the bare stylesheet directory URI for an empty file', function () {
    expect( ( new ChildTheme )->url() )->toBe( get_stylesheet_directory_uri() );
} );

it( 'has no inheritance chain of its own', function () {
    $reflection = new ReflectionProperty( ChildTheme::class, 'inheritance' );
    $reflection->setAccessible( true );

    expect( $reflection->getValue( new ChildTheme ) )->toBe( [] );
} );
