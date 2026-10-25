<?php
/**
 * In-memory file store for tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Fakes;

use MemberDocuments\Storage\DocumentStoreInterface;

/**
 * Keeps files in an array. Tests can edit $files to simulate tampering.
 */
final class InMemoryStore implements DocumentStoreInterface {

	/**
	 * Files by relative path.
	 *
	 * @var array<string, string>
	 */
	public array $files = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $user_id  Member.
	 * @param string $filename Name.
	 * @param string $bytes    Contents.
	 */
	public function put( int $user_id, string $filename, string $bytes ): string {
		$path                 = $user_id . '/' . $filename;
		$this->files[ $path ] = $bytes;
		return $path;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $relative_path Path.
	 */
	public function read( string $relative_path ): ?string {
		return $this->files[ $relative_path ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $relative_path Path.
	 */
	public function absolute( string $relative_path ): string {
		return '/memory/' . $relative_path;
	}
}
