<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The suite runs against a real WordPress install, provisioned by PestWP
| (SQLite, no MySQL). There is no function mocking: WordPress functions
| called by the resolvers are the real ones.
|
| Consequence worth knowing: assertions are about RETURN VALUES, not about
| which WordPress function was called. Under the previous Brain Monkey setup
| a test could assert `get_parent_theme_file_path()` was called once with an
| exact argument. That is no longer expressible. Where that contract still
| matters, it is asserted structurally instead (see ParentThemeTest).
|
*/

use PestWP\Database\TransactionManager;

uses()
    ->beforeEach( fn() => TransactionManager::beginTransaction() )
    ->afterEach( fn() => TransactionManager::rollback() )
    ->in( 'Integration' );

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Path to a temporary fixture directory (mirroring a theme/plugin root).
 */
function assets_fixture_path( string $path = '' ): string {
    $base = dirname( __DIR__ ) . '/tests/Fixtures';

    return '' === $path ? $base : $base . '/' . ltrim( $path, '/' );
}

/**
 * Recursively delete a directory created during a test.
 */
function delete_directory( string $dir ): void {
    if ( ! is_dir( $dir ) ) {
        return;
    }

    foreach ( array_diff( scandir( $dir ), [ '.', '..' ] ) as $item ) {
        $path = $dir . '/' . $item;

        is_dir( $path ) && ! is_link( $path ) ? delete_directory( $path ) : unlink( $path );
    }

    rmdir( $dir );
}
