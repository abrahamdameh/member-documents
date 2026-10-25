<?php
/**
 * PDF rendering with Dompdf.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Pure-PHP renderer: works on any host, with no external service.
 */
final class DompdfRenderer implements PdfRendererInterface {

	/**
	 * Renders HTML to an A4 PDF.
	 *
	 * Remote resources are disabled, so a template can never make the server
	 * fetch arbitrary URLs while rendering.
	 *
	 * @param string $html Complete HTML document.
	 */
	public function render( string $html ): string {
		$options = new Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isPhpEnabled', false );
		$options->set( 'defaultFont', 'DejaVu Sans' );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4' );
		$dompdf->render();

		return (string) $dompdf->output();
	}
}
