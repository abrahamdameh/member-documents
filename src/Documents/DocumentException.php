<?php
/**
 * Errors raised while issuing or accepting documents.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * A document operation that cannot go ahead. The message is safe to show.
 */
final class DocumentException extends RuntimeException {

	/**
	 * Template has no current version.
	 *
	 * @param int $template_id Template ID.
	 */
	public static function template_not_found( int $template_id ): self {
		return new self( sprintf( 'Template %d was not found.', $template_id ) );
	}

	/**
	 * Document does not exist.
	 *
	 * @param int $id Document ID.
	 */
	public static function not_found( int $id ): self {
		return new self( sprintf( 'Document %d was not found.', $id ) );
	}

	/**
	 * Document belongs to someone else.
	 */
	public static function not_owner(): self {
		return new self( 'This document belongs to another member.' );
	}

	/**
	 * Stored file no longer matches the hash recorded at issue.
	 *
	 * @param int $id Document ID.
	 */
	public static function integrity( int $id ): self {
		return new self( sprintf( 'Document %d failed its integrity check: the stored file has changed since it was issued.', $id ) );
	}
}
