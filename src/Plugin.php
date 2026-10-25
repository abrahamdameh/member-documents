<?php
/**
 * Composition root.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments;

use MemberDocuments\Admin\AdminPage;
use MemberDocuments\Cli\Command;
use MemberDocuments\Documents\DocumentRepository;
use MemberDocuments\Documents\DocumentService;
use MemberDocuments\Documents\MemberValues;
use MemberDocuments\Frontend\RequestHandler;
use MemberDocuments\Frontend\Shortcode;
use MemberDocuments\Mail\DocumentMailer;
use MemberDocuments\Pdf\DompdfRenderer;
use MemberDocuments\Pdf\PdfRendererInterface;
use MemberDocuments\Schema\Installer;
use MemberDocuments\Storage\FilesystemStore;
use MemberDocuments\Templates\Renderer;
use MemberDocuments\Templates\TemplateRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the object graph once and registers hooks. Dependencies are passed
 * in through constructors, never looked up globally, which keeps every class
 * testable in isolation.
 */
final class Plugin {

	/**
	 * The document service, available to other code after boot.
	 *
	 * @var DocumentService|null
	 */
	private static ?DocumentService $service = null;

	/**
	 * Boots the plugin on plugins_loaded.
	 */
	public static function boot(): void {
		global $wpdb;

		Installer::maybe_upgrade();
		load_plugin_textdomain( 'member-documents', false, dirname( plugin_basename( MDOCS_FILE ) ) . '/languages' );

		$uploads   = wp_upload_dir( null, false );
		$store     = new FilesystemStore( $uploads['basedir'] . '/member-documents' );
		$templates = new TemplateRepository( $wpdb );
		$documents = new DocumentRepository( $wpdb );

		/**
		 * Filters the PDF engine, e.g. to use a headless-browser service.
		 *
		 * @param PdfRendererInterface $renderer Default Dompdf renderer.
		 */
		$pdf = apply_filters( 'mdocs_pdf_renderer', new DompdfRenderer() );
		if ( ! $pdf instanceof PdfRendererInterface ) {
			$pdf = new DompdfRenderer();
		}

		self::$service = new DocumentService( $templates, $documents, new Renderer(), $pdf, $store );
		$mailer        = new DocumentMailer( $store, $documents );

		( new Shortcode( $documents ) )->register();
		( new RequestHandler( self::$service, $store ) )->register();

		if ( is_admin() ) {
			( new AdminPage( $templates, $documents, self::$service, $mailer, new MemberValues() ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'member-documents', new Command( $documents, self::$service ) );
		}
	}

	/**
	 * The document service, for integrations (e.g. issuing a document when a
	 * subscription starts). Null before plugins_loaded.
	 */
	public static function service(): ?DocumentService {
		return self::$service;
	}

	/**
	 * Capability required to manage templates and view all documents.
	 */
	public static function capability(): string {
		return (string) apply_filters( 'mdocs_capability', 'manage_options' );
	}
}
