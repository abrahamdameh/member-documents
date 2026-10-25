<?php
/**
 * File storage contract.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Stores PDF files, one folder per member.
 */
interface DocumentStoreInterface {

	/**
	 * Writes a file.
	 *
	 * @param int    $user_id  Member.
	 * @param string $filename File name (no directories).
	 * @param string $bytes    Contents.
	 * @return string Path relative to the store.
	 */
	public function put( int $user_id, string $filename, string $bytes ): string;

	/**
	 * Reads a file.
	 *
	 * @param string $relative_path Path returned by put().
	 * @return string|null Contents, or null when missing.
	 */
	public function read( string $relative_path ): ?string;

	/**
	 * Absolute path of a stored file (for attachments and downloads).
	 *
	 * @param string $relative_path Path returned by put().
	 */
	public function absolute( string $relative_path ): string;
}
