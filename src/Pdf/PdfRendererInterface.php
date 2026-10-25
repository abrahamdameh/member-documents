<?php
/**
 * PDF engine contract.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Turns an HTML document into PDF bytes. Kept behind an interface so the
 * engine can be swapped (Dompdf, a headless-browser service…) and mocked in
 * tests.
 */
interface PdfRendererInterface {

	/**
	 * Renders HTML to PDF.
	 *
	 * @param string $html Complete HTML document.
	 * @return string PDF bytes.
	 */
	public function render( string $html ): string;
}
