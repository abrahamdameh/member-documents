<?php
/**
 * In-memory document repository for tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Fakes;

use MemberDocuments\Documents\Document;
use MemberDocuments\Documents\DocumentRepositoryInterface;

/**
 * Stores document rows in an array, with the same write-once acceptance
 * rule as the database repository.
 */
final class InMemoryDocuments implements DocumentRepositoryInterface {

	/**
	 * Rows by ID.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, int|string> $data Columns.
	 */
	public function insert( array $data ): int {
		$id                = count( $this->rows ) + 1;
		$this->rows[ $id ] = $data + array(
			'id'                  => $id,
			'accepted_at'         => null,
			'accepted_ip'         => null,
			'accepted_user_agent' => null,
			'emailed_at'          => null,
		);
		return $id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Document.
	 */
	public function find( int $id ): ?Document {
		return isset( $this->rows[ $id ] ) ? Document::from_row( (object) $this->rows[ $id ] ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $user_id Member.
	 */
	public function for_user( int $user_id ): array {
		$mine = array_filter( $this->rows, static fn( array $r ): bool => (int) $r['user_id'] === $user_id );
		return array_map( static fn( array $r ): Document => Document::from_row( (object) $r ), array_reverse( $mine ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $limit  Page size.
	 * @param int $offset Offset.
	 */
	public function recent( int $limit, int $offset = 0 ): array {
		$rows = array_slice( array_reverse( $this->rows ), $offset, $limit );
		return array_map( static fn( array $r ): Document => Document::from_row( (object) $r ), $rows );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $after_id After this ID.
	 * @param int $limit    Batch size.
	 */
	public function batch( int $after_id, int $limit ): array {
		$rows = array_filter( $this->rows, static fn( array $r ): bool => (int) $r['id'] > $after_id );
		return array_map( static fn( array $r ): Document => Document::from_row( (object) $r ), array_slice( $rows, 0, $limit ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $id         Document.
	 * @param string $at         Time.
	 * @param string $ip         IP.
	 * @param string $user_agent Browser.
	 */
	public function mark_accepted( int $id, string $at, string $ip, string $user_agent ): bool {
		if ( ! isset( $this->rows[ $id ] ) || null !== $this->rows[ $id ]['accepted_at'] ) {
			return false;
		}
		$this->rows[ $id ]['accepted_at']         = $at;
		$this->rows[ $id ]['accepted_ip']         = $ip;
		$this->rows[ $id ]['accepted_user_agent'] = $user_agent;
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $id Document.
	 * @param string $at Time.
	 */
	public function mark_emailed( int $id, string $at ): void {
		$this->rows[ $id ]['emailed_at'] = $at;
	}
}
