<?php
/**
 * Member-facing actions: download and accept.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Frontend;

use MemberDocuments\Documents\DocumentException;
use MemberDocuments\Documents\DocumentService;
use MemberDocuments\Plugin;
use MemberDocuments\Storage\DocumentStoreInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin-post.php requests from the documents page. Every request is
 * nonce-checked and ownership-checked; documents are never served from a
 * public URL.
 */
final class RequestHandler {

	/**
	 * Constructor.
	 *
	 * @param DocumentService        $service Document service.
	 * @param DocumentStoreInterface $store   File storage.
	 */
	public function __construct(
		private DocumentService $service,
		private DocumentStoreInterface $store
	) {}

	/**
	 * Registers the handlers (logged-in users only).
	 */
	public function register(): void {
		add_action( 'admin_post_mdocs_download', array( $this, 'download' ) );
		add_action( 'admin_post_mdocs_accept', array( $this, 'accept' ) );
	}

	/**
	 * Download URL for a document.
	 *
	 * @param int $document_id Document.
	 */
	public static function download_url( int $document_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'mdocs_download',
					'id'     => $document_id,
				),
				admin_url( 'admin-post.php' )
			),
			'mdocs_download_' . $document_id
		);
	}

	/**
	 * Streams a PDF to its member (or to an administrator).
	 */
	public function download(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'mdocs_download_' . $id );

		try {
			$document = current_user_can( Plugin::capability() )
				? $this->service->get( $id )
				: $this->service->owned( $id, get_current_user_id() );
		} catch ( DocumentException $e ) {
			wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 403 ) );
		}

		$bytes = $this->store->read( $document->file );
		if ( null === $bytes ) {
			wp_die( esc_html__( 'The document file is missing.', 'member-documents' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $document->title ) . '.pdf"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
		exit;
	}

	/**
	 * Records the current member's acceptance, then returns to the page.
	 */
	public function accept(): void {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		check_admin_referer( 'mdocs_accept_' . $id );

		$notice = 'accepted';
		try {
			$this->service->accept( $id, get_current_user_id(), $this->client_ip(), $this->user_agent() );
		} catch ( DocumentException $e ) {
			$notice = 'error';
		}

		wp_safe_redirect( add_query_arg( 'mdocs_notice', $notice, wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
		exit;
	}

	/**
	 * The connecting IP address. Only REMOTE_ADDR is trusted: forwarding
	 * headers are set by the client and could be forged. Sites behind a proxy
	 * can supply the real address through the filter.
	 */
	private function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'mdocs_client_ip', $ip );
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * The request's browser string.
	 */
	private function user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}
}
