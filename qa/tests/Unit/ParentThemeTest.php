<?php

use Hybrid\Assets\ChildTheme;
use Hybrid\Assets\ParentTheme;

/*
 * Migrated off Brain Monkey. These previously asserted that `path()` called
 * `get_parent_theme_file_path()` exactly once with an exact argument. Real
 * WordPress offers nothing to assert that against, so the behaviour is now
 * pinned two ways instead:
 *
 *   1. Return values are checked against the real active theme's directory,
 *      which is what the resolver is actually for.
 *   2. The "which core function" contract — the part mocks used to protect —
 *      is asserted at the end of this file, so swapping
 *      get_parent_theme_file_path() for get_theme_file_path() still fails.
 */

it( 'always exists', function () {
    expect( ( new ParentTheme )->exists() )->toBeTrue();
} );

it( 'resolves path() inside the active parent theme directory', function () {
    expect( ( new ParentTheme )->path( '/public/js/app.js' ) )
        ->toBe( get_template_directory() . '/public/js/app.js' );
} );

it( 'resolves url() inside the active parent theme URI', function () {
    expect( ( new ParentTheme )->url( '/public/js/app.js' ) )
        ->toBe( get_template_directory_uri() . '/public/js/app.js' );
} );

it( 'defaults path()/url() to an empty file', function () {
    $theme = new ParentTheme;

    expect( $theme->path() )->toBe( get_template_directory() )
        ->and( $theme->url() )->toBe( get_template_directory_uri() );
} );

it( 'only falls back to the child theme in its inheritance chain', function () {
    $reflection = new ReflectionProperty( ParentTheme::class, 'inheritance' );
    $reflection->setAccessible( true );

    expect( $reflection->getValue( new ParentTheme ) )->toBe( [ ChildTheme::class ] );
} );

/*
 * `get_parent_theme_file_path()` and `get_theme_file_path()` return the same
 * value on a parent-only install, so a value-based test cannot tell them
 * apart — yet they diverge the moment a child theme is active, which is
 * precisely the case this resolver exists to get right. Pin the call site.
 */
it( 'resolves through the parent-theme-specific core functions', function () {
    $source = static function ( string $method ): string {
        $reflection = new ReflectionMethod( ParentTheme::class, $method );
        $lines      = file( $reflection->getFileName() );

        return implode( '', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ) );
    };

    expect( $source( 'path' ) )->toContain( 'get_parent_theme_file_path(' )
        ->and( $source( 'url' ) )->toContain( 'get_parent_theme_file_uri(' );
} );
