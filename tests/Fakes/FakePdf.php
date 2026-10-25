<?php
/**
 * Predictable PDF engine for tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Fakes;

use MemberDocuments\Pdf\PdfRendererInterface;

/**
 * Returns a fake "PDF" that embeds the HTML, so tests can see what was rendered.
 */
final class FakePdf implements PdfRendererInterface {

	/**
	 * Last HTML rendered.
	 *
	 * @var string
	 */
	public string $last_html = '';

	/**
	 * {@inheritDoc}
	 *
	 * @param string $html HTML.
	 */
	public function render( string $html ): string {
		$this->last_html = $html;
		return "%PDF-FAKE\n" . $html;
	}
}
