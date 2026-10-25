<?php
/**
 * One immutable version of a document template.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * A saved template version. Versions are never edited: a change to a
 * template creates a new version, so every issued document can always be
 * traced to the exact wording it was generated from.
 */
final class TemplateVersion {

	/**
	 * Constructor.
	 *
	 * @param int    $id          Version ID.
	 * @param int    $template_id Parent template ID.
	 * @param int    $number      Version number within the template (1, 2, 3…).
	 * @param string $title       Document title.
	 * @param string $body        Body HTML with {{placeholders}}.
	 * @param string $created_at  UTC datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $template_id,
		public readonly int $number,
		public readonly string $title,
		public readonly string $body,
		public readonly string $created_at
	) {}

	/**
	 * Builds a version from a database row.
	 *
	 * @param object $row Row from the versions table.
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->template_id,
			(int) $row->number,
			(string) $row->title,
			(string) $row->body,
			(string) $row->created_at
		);
	}
}
