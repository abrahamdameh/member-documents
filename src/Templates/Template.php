<?php
/**
 * A document template.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * A template and a pointer to its current version.
 */
final class Template {

	/**
	 * Constructor.
	 *
	 * @param int    $id                 Template ID.
	 * @param string $title              Title of the current version.
	 * @param int    $current_version_id Current version ID.
	 * @param string $created_at         UTC datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly int $current_version_id,
		public readonly string $created_at
	) {}

	/**
	 * Builds a template from a database row.
	 *
	 * @param object $row Row from the templates table.
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(string) $row->title,
			(int) $row->current_version_id,
			(string) $row->created_at
		);
	}
}
