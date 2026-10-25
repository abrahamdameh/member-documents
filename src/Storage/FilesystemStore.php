<?php
/**
 * Private file storage under the uploads folder.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Storage;

use InvalidArgumentException;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Stores documents in uploads/member-documents/{user_id}/.
 *
 * The folder is closed to direct web access (deny rules plus an empty
 * index), so files are only ever served through the plugin's download
 * handler, which checks the member owns the document. On Nginx, which
 * ignores .htaccess, add the location rule shown in the README.
 */
final class FilesystemStore implements DocumentStoreInterface {

	/**
	 * Constructor.
	 *
	 * @param string $base_dir Absolute folder for all documents.
	 */
	public function __construct( private string $base_dir ) {
		$this->base_dir = rtrim( $base_dir, '/\\' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $user_id  Member.
	 * @param string $filename File name.
	 * @param string $bytes    Contents.
	 * @throws InvalidArgumentException For an unsafe file name.
	 * @throws RuntimeException         When the file cannot be written.
	 */
	public function put( int $user_id, string $filename, string $bytes ): string {
		if ( $user_id <= 0 || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,150}$/', $filename ) || str_contains( $filename, '..' ) ) {
			throw new InvalidArgumentException( 'Unsafe document file name.' );
		}

		$this->protect( $this->base_dir );
		$dir = $this->base_dir . '/' . $user_id;
		$this->protect( $dir );

		$relative = $user_id . '/' . $filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- private folder outside WP_Filesystem's scope; writes are atomic via rename.
		if ( false === file_put_contents( $dir . '/' . $filename . '.tmp', $bytes, LOCK_EX )
			|| ! rename( $dir . '/' . $filename . '.tmp', $dir . '/' . $filename ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- see above.
			throw new RuntimeException( 'Could not write the document file.' );
		}
		return $relative;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $relative_path Path returned by put().
	 */
	public function read( string $relative_path ): ?string {
		$path = $this->absolute( $relative_path );
		if ( ! is_file( $path ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local private file.
		$bytes = file_get_contents( $path );
		return false === $bytes ? null : $bytes;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $relative_path Path returned by put().
	 * @throws InvalidArgumentException For a path that leaves the store.
	 */
	public function absolute( string $relative_path ): string {
		if ( ! preg_match( '#^[0-9]+/[A-Za-z0-9][A-Za-z0-9._-]*$#', $relative_path ) || str_contains( $relative_path, '..' ) ) {
			throw new InvalidArgumentException( 'Invalid document path.' );
		}
		return $this->base_dir . '/' . $relative_path;
	}

	/**
	 * Creates a folder and closes it to direct web access.
	 *
	 * @param string $dir Folder.
	 * @throws RuntimeException When the folder cannot be created.
	 */
	private function protect( string $dir ): void {
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0750, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- see put().
			throw new RuntimeException( 'Could not create the documents folder.' );
		}
		if ( ! is_file( $dir . '/.htaccess' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- see put().
			file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
		if ( ! is_file( $dir . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- see put().
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}
}
