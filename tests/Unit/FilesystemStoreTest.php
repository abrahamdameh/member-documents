<?php
/**
 * File storage tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Unit;

use InvalidArgumentException;
use MemberDocuments\Storage\FilesystemStore;
use MemberDocuments\Tests\TestCase;

/**
 * @covers \MemberDocuments\Storage\FilesystemStore
 */
final class FilesystemStoreTest extends TestCase {

	private string $dir;
	private FilesystemStore $store;

	protected function setUp(): void {
		parent::setUp();
		$this->dir   = sys_get_temp_dir() . '/mdocs-test-' . bin2hex( random_bytes( 4 ) );
		$this->store = new FilesystemStore( $this->dir );
	}

	protected function tearDown(): void {
		$this->remove( $this->dir );
		parent::tearDown();
	}

	public function test_writes_and_reads_back_a_file_in_the_members_folder(): void {
		$path = $this->store->put( 5, 'doc.pdf', 'PDF-BYTES' );

		$this->assertSame( '5/doc.pdf', $path );
		$this->assertSame( 'PDF-BYTES', $this->store->read( $path ) );
		$this->assertFileExists( $this->dir . '/5/doc.pdf' );
		$this->assertFileDoesNotExist( $this->dir . '/5/doc.pdf.tmp' );
	}

	public function test_closes_folders_to_direct_web_access(): void {
		$this->store->put( 5, 'doc.pdf', 'x' );
		foreach ( array( $this->dir, $this->dir . '/5' ) as $folder ) {
			$this->assertFileExists( $folder . '/.htaccess' );
			$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $folder . '/.htaccess' ) );
			$this->assertFileExists( $folder . '/index.php' );
		}
	}

	public function test_missing_file_reads_as_null(): void {
		$this->assertNull( $this->store->read( '5/none.pdf' ) );
	}

	/**
	 * @dataProvider unsafe_names
	 *
	 * @param string $name Unsafe file name.
	 */
	public function test_rejects_unsafe_file_names( string $name ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->store->put( 5, $name, 'x' );
	}

	/**
	 * Unsafe names.
	 *
	 * @return array<string, array{string}>
	 */
	public function unsafe_names(): array {
		return array(
			'traversal'   => array( '../evil.pdf' ),
			'subfolder'   => array( 'a/b.pdf' ),
			'hidden'      => array( '.htaccess' ),
			'backslash'   => array( 'a\\b.pdf' ),
			'double dots' => array( 'a..pdf' ),
		);
	}

	public function test_rejects_paths_that_leave_the_store(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->store->absolute( '5/../../wp-config.php' );
	}

	/**
	 * Deletes a folder tree.
	 *
	 * @param string $dir Folder.
	 */
	private function remove( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ) as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}
}
