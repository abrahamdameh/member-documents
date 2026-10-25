<?php
/**
 * Turns a template version into a complete HTML document.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Fills {{placeholders}} and wraps the body in a printable HTML page.
 *
 * Member values are always escaped, so a name like "<b>Ann</b>" prints as
 * text and can never inject markup into a legal document. The template body
 * itself is trusted HTML: it is filtered with wp_kses_post() when an admin
 * saves it.
 */
final class Renderer {

	/**
	 * Renders a version with the given values.
	 *
	 * @param TemplateVersion       $version Version to render.
	 * @param array<string, scalar> $values  Placeholder values, keyed by name.
	 */
	public function render( TemplateVersion $version, array $values ): string {
		$body  = $this->fill( $version->body, $values );
		$title = $this->escape( $this->fill( $version->title, $values ) );

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . '</title>'
			. '<style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;line-height:1.5;color:#111;margin:36px}'
			. 'h1{font-size:20px;margin:0 0 16px}.mdocs-meta{margin-top:32px;font-size:10px;color:#555}</style>'
			. '</head><body><h1>' . $title . '</h1>' . $body
			. '<p class="mdocs-meta">' . $this->escape( sprintf( 'Version %d', $version->number ) ) . '</p>'
			. '</body></html>';
	}

	/**
	 * Replaces {{ name }} placeholders. Unknown placeholders become empty.
	 *
	 * @param string                $text   Text containing placeholders.
	 * @param array<string, scalar> $values Values keyed by name.
	 */
	public function fill( string $text, array $values ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			function ( array $m ) use ( $values ): string {
				$key = strtolower( $m[1] );
				return array_key_exists( $key, $values ) ? $this->escape( (string) $values[ $key ] ) : '';
			},
			$text
		);
	}

	/**
	 * Placeholder names used in a text, in order of first use.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public function placeholders( string $text ): array {
		preg_match_all( '/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $m );
		return array_values( array_unique( array_map( 'strtolower', $m[1] ) ) );
	}

	/**
	 * HTML-escapes a value.
	 *
	 * @param string $value Value.
	 */
	private function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
