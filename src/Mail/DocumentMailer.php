<?php
/**
 * Emails a document to its member.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Mail;

use MemberDocuments\Documents\Document;
use MemberDocuments\Documents\DocumentRepositoryInterface;
use MemberDocuments\Storage\DocumentStoreInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the PDF as an attachment through wp_mail(), so any SMTP plugin the
 * site uses applies.
 */
final class DocumentMailer {

	/**
	 * Constructor.
	 *
	 * @param DocumentStoreInterface      $store     File storage.
	 * @param DocumentRepositoryInterface $documents Issued documents.
	 */
	public function __construct(
		private DocumentStoreInterface $store,
		private DocumentRepositoryInterface $documents
	) {}

	/**
	 * Emails the document and records when it was sent.
	 *
	 * @param Document $document Document.
	 * @param \WP_User $user     Recipient (the document's member).
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public function send( Document $document, \WP_User $user ): bool {
		/* translators: %s: document title. */
		$subject = sprintf( __( 'Your document: %s', 'member-documents' ), $document->title );
		$message = sprintf(
			/* translators: 1: member name, 2: document title, 3: URL of the documents page. */
			__( "Hello %1\$s,\n\nYour document \"%2\$s\" is attached. You can also view and accept it on your documents page:\n%3\$s\n", 'member-documents' ),
			$user->display_name,
			$document->title,
			(string) apply_filters( 'mdocs_documents_page_url', home_url( '/' ) )
		);

		$sent = wp_mail( $user->user_email, $subject, $message, array(), array( $this->store->absolute( $document->file ) ) );
		if ( $sent ) {
			$this->documents->mark_emailed( $document->id, gmdate( 'Y-m-d H:i:s' ) );
		}
		return $sent;
	}
}
