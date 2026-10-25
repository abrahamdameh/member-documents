<?php
/**
 * Issued-document storage contract.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes issued-document records.
 */
interface DocumentRepositoryInterface {

	/**
	 * Inserts a record.
	 *
	 * @param array<string, int|string> $data Columns: user_id, template_id, version_id, title, file, sha256, issued_at.
	 * @return int Document ID.
	 */
	public function insert( array $data ): int;

	/**
	 * Finds a document.
	 *
	 * @param int $id Document ID.
	 */
	public function find( int $id ): ?Document;

	/**
	 * A member's documents, newest first.
	 *
	 * @param int $user_id Member.
	 * @return Document[]
	 */
	public function for_user( int $user_id ): array;

	/**
	 * Recent documents across all members, newest first.
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Offset.
	 * @return Document[]
	 */
	public function recent( int $limit, int $offset = 0 ): array;

	/**
	 * Every document, in ID order, one batch at a time (for integrity checks).
	 *
	 * @param int $after_id Return documents with a larger ID.
	 * @param int $limit    Batch size.
	 * @return Document[]
	 */
	public function batch( int $after_id, int $limit ): array;

	/**
	 * Records acceptance. Only a not-yet-accepted document is updated.
	 *
	 * @param int    $id         Document ID.
	 * @param string $at         UTC datetime.
	 * @param string $ip         IP address.
	 * @param string $user_agent Browser.
	 * @return bool True when this call recorded the acceptance.
	 */
	public function mark_accepted( int $id, string $at, string $ip, string $user_agent ): bool;

	/**
	 * Records that the document was emailed.
	 *
	 * @param int    $id Document ID.
	 * @param string $at UTC datetime.
	 */
	public function mark_emailed( int $id, string $at ): void;
}
