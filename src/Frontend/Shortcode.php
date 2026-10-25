<?php
/**
 * The [member_documents] page.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Frontend;

use MemberDocuments\Documents\DocumentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the signed-in member's documents with download and accept actions.
 */
final class Shortcode {

	/**
	 * Constructor.
	 *
	 * @param DocumentRepositoryInterface $documents Issued documents.
	 */
	public function __construct( private DocumentRepositoryInterface $documents ) {}

	/**
	 * Registers the shortcode.
	 */
	public function register(): void {
		add_shortcode( 'member_documents', array( $this, 'render' ) );
	}

	/**
	 * Renders the page.
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="mdocs-login">' . sprintf(
				/* translators: %s: login URL. */
				wp_kses_post( __( 'Please <a href="%s">log in</a> to see your documents.', 'member-documents' ) ),
				esc_url( wp_login_url( get_permalink() ? get_permalink() : home_url( '/' ) ) )
			) . '</p>';
		}

		$notice    = isset( $_GET['mdocs_notice'] ) ? sanitize_key( wp_unslash( $_GET['mdocs_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$documents = $this->documents->for_user( get_current_user_id() );

		ob_start();
		$this->load_template(
			'documents-list.php',
			array(
				'documents' => $documents,
				'notice'    => $notice,
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Includes a template. A theme can override it by copying it to
	 * your-theme/member-documents/{name}.
	 *
	 * @param string               $name Template file name.
	 * @param array<string, mixed> $vars Variables for the template.
	 */
	private function load_template( string $name, array $vars ): void {
		$theme_file = locate_template( 'member-documents/' . $name );
		$file       = $theme_file ? $theme_file : MDOCS_DIR . 'templates/' . $name;
		( static function ( string $mdocs_file, array $mdocs_vars ): void {
			extract( $mdocs_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template variables, documented in the template header.
			include $mdocs_file;
		} )( $file, $vars );
	}
}
