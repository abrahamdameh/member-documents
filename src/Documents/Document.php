<?php
/**
 * An issued document.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * A PDF issued to one member from one template version.
 */
final class Document {

	/**
	 * Constructor.
	 *
	 * @param int         $id                  Document ID.
	 * @param int         $user_id             Member.
	 * @param int         $template_id         Template.
	 * @param int         $version_id          Template version it was generated from.
	 * @param string      $title               Title at issue time.
	 * @param string      $file                Path relative to the document store.
	 * @param string      $sha256              Hash of the PDF bytes at issue time.
	 * @param string      $issued_at           UTC datetime.
	 * @param string|null $accepted_at         UTC datetime, or null when not accepted.
	 * @param string|null $accepted_ip         IP address recorded on acceptance.
	 * @param string|null $accepted_user_agent Browser recorded on acceptance.
	 * @param string|null $emailed_at          UTC datetime of the last email.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $user_id,
		public readonly int $template_id,
		public readonly int $version_id,
		public readonly string $title,
		public readonly string $file,
		public readonly string $sha256,
		public readonly string $issued_at,
		public readonly ?string $accepted_at = null,
		public readonly ?string $accepted_ip = null,
		public readonly ?string $accepted_user_agent = null,
		public readonly ?string $emailed_at = null
	) {}

	/**
	 * Whether the member has accepted this document.
	 */
	public function is_accepted(): bool {
		return null !== $this->accepted_at;
	}

	/**
	 * Builds a document from a database row.
	 *
	 * @param object $row Row from the documents table.
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->user_id,
			(int) $row->template_id,
			(int) $row->version_id,
			(string) $row->title,
			(string) $row->file,
			(string) $row->sha256,
			(string) $row->issued_at,
			null === $row->accepted_at ? null : (string) $row->accepted_at,
			null === $row->accepted_ip ? null : (string) $row->accepted_ip,
			null === $row->accepted_user_agent ? null : (string) $row->accepted_user_agent,
			null === $row->emailed_at ? null : (string) $row->emailed_at
		);
	}
}
