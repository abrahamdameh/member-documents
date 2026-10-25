<?php
/**
 * Template storage in custom tables.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Templates;

use MemberDocuments\Schema\Installer;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables; every query is prepared.

/**
 * Templates and versions backed by $wpdb.
 */
final class TemplateRepository implements TemplateRepositoryInterface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb $db Database.
	 */
	public function __construct( private \wpdb $db ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $title   Title.
	 * @param string $body    Body HTML.
	 * @param int    $user_id Author.
	 * @throws RuntimeException When the insert fails.
	 */
	public function create( string $title, string $body, int $user_id ): int {
		$inserted = $this->db->insert(
			Installer::table( 'templates' ),
			array(
				'title'      => $title,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s' )
		);
		if ( false === $inserted ) {
			throw new RuntimeException( 'Could not create the template.' );
		}
		$template_id = (int) $this->db->insert_id;
		$this->add_version( $template_id, $title, $body, $user_id );
		return $template_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The next number is taken inside a transaction with a locking read, and
	 * a UNIQUE (template_id, number) key backs it up, so two editors saving at
	 * the same moment can never produce the same version number.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $title       Title.
	 * @param string $body        Body HTML.
	 * @param int    $user_id     Author.
	 * @throws RuntimeException When the insert fails.
	 */
	public function add_version( int $template_id, string $title, string $body, int $user_id ): int {
		$versions  = Installer::table( 'versions' );
		$templates = Installer::table( 'templates' );

		$this->db->query( 'START TRANSACTION' );

		$next = 1 + (int) $this->db->get_var(
			$this->db->prepare( "SELECT MAX(number) FROM {$versions} WHERE template_id = %d FOR UPDATE", $template_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Installer.
		);

		$inserted = $this->db->insert(
			$versions,
			array(
				'template_id' => $template_id,
				'number'      => $next,
				'title'       => $title,
				'body'        => $body,
				'created_by'  => $user_id,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			$this->db->query( 'ROLLBACK' );
			throw new RuntimeException( 'Could not save the template version.' );
		}

		$version_id = (int) $this->db->insert_id;
		$this->db->update(
			$templates,
			array(
				'title'              => $title,
				'current_version_id' => $version_id,
			),
			array( 'id' => $template_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		$this->db->query( 'COMMIT' );
		return $version_id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template ID.
	 */
	public function find( int $template_id ): ?Template {
		$table = Installer::table( 'templates' );
		$row   = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $template_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Installer.
		return $row ? Template::from_row( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function all(): array {
		$table = Installer::table( 'templates' );
		$rows  = $this->db->get_results( "SELECT * FROM {$table} ORDER BY id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; table name from Installer.
		return array_map( array( Template::class, 'from_row' ), $rows ? $rows : array() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template ID.
	 */
	public function current_version( int $template_id ): ?TemplateVersion {
		$template = $this->find( $template_id );
		return $template ? $this->version( $template->current_version_id ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $version_id Version ID.
	 */
	public function version( int $version_id ): ?TemplateVersion {
		$table = Installer::table( 'versions' );
		$row   = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d", $version_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Installer.
		return $row ? TemplateVersion::from_row( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template ID.
	 */
	public function versions( int $template_id ): array {
		$table = Installer::table( 'versions' );
		$rows  = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$table} WHERE template_id = %d ORDER BY number DESC", $template_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from Installer.
		return array_map( array( TemplateVersion::class, 'from_row' ), $rows ? $rows : array() );
	}
}
