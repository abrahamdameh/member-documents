<?php
/**
 * Issued documents in a custom table.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

use MemberDocuments\Schema\Installer;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom table; every query is prepared.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the only interpolated value is the table name from Installer.

/**
 * Issued-document records backed by $wpdb.
 */
final class DocumentRepository implements DocumentRepositoryInterface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb $db Database.
	 */
	public function __construct( private \wpdb $db ) {}

	/**
	 * Table name.
	 */
	private function table(): string {
		return Installer::table( 'documents' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, int|string> $data Columns.
	 * @throws RuntimeException When the insert fails.
	 */
	public function insert( array $data ): int {
		$inserted = $this->db->insert(
			$this->table(),
			array(
				'user_id'     => (int) $data['user_id'],
				'template_id' => (int) $data['template_id'],
				'version_id'  => (int) $data['version_id'],
				'title'       => (string) $data['title'],
				'file'        => (string) $data['file'],
				'sha256'      => (string) $data['sha256'],
				'issued_at'   => (string) $data['issued_at'],
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not record the document.' );
		}
		return (int) $this->db->insert_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Document ID.
	 */
	public function find( int $id ): ?Document {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE id = %d", $id ) );
		return $row ? Document::from_row( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $user_id Member.
	 */
	public function for_user( int $user_id ): array {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE user_id = %d ORDER BY id DESC", $user_id ) );
		return $this->map( $rows );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Offset.
	 */
	public function recent( int $limit, int $offset = 0 ): array {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ) );
		return $this->map( $rows );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $after_id Return documents with a larger ID.
	 * @param int $limit    Batch size.
	 */
	public function batch( int $after_id, int $limit ): array {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table()} WHERE id > %d ORDER BY id ASC LIMIT %d", $after_id, $limit ) );
		return $this->map( $rows );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The WHERE accepted_at IS NULL clause makes acceptance write-once: a
	 * repeated or concurrent request can never overwrite the first record.
	 *
	 * @param int    $id         Document ID.
	 * @param string $at         UTC datetime.
	 * @param string $ip         IP address.
	 * @param string $user_agent Browser.
	 */
	public function mark_accepted( int $id, string $at, string $ip, string $user_agent ): bool {
		$updated = $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table()} SET accepted_at = %s, accepted_ip = %s, accepted_user_agent = %s WHERE id = %d AND accepted_at IS NULL",
				$at,
				$ip,
				substr( $user_agent, 0, 255 ),
				$id
			)
		);
		return 1 === $updated;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $id Document ID.
	 * @param string $at UTC datetime.
	 */
	public function mark_emailed( int $id, string $at ): void {
		$this->db->update( $this->table(), array( 'emailed_at' => $at ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	/**
	 * Maps rows to documents.
	 *
	 * @param array<int, object>|null $rows Rows.
	 * @return Document[]
	 */
	private function map( ?array $rows ): array {
		return array_map( array( Document::class, 'from_row' ), $rows ? $rows : array() );
	}
}
