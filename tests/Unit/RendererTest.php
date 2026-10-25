<?php
/**
 * Renderer tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Unit;

use MemberDocuments\Templates\Renderer;
use MemberDocuments\Templates\TemplateVersion;
use MemberDocuments\Tests\TestCase;

/**
 * @covers \MemberDocuments\Templates\Renderer
 */
final class RendererTest extends TestCase {

	private Renderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		$this->renderer = new Renderer();
	}

	public function test_fills_placeholders_with_or_without_spaces(): void {
		$out = $this->renderer->fill( 'Dear {{member_name}}, welcome to {{ site_name }}.', array( 'member_name' => 'Ada', 'site_name' => 'Club' ) );
		$this->assertSame( 'Dear Ada, welcome to Club.', $out );
	}

	public function test_placeholder_names_are_case_insensitive(): void {
		$this->assertSame( 'Hi Ada', $this->renderer->fill( 'Hi {{ Member_Name }}', array( 'member_name' => 'Ada' ) ) );
	}

	public function test_unknown_placeholders_become_empty(): void {
		$this->assertSame( 'Plan: .', $this->renderer->fill( 'Plan: {{plan}}.', array() ) );
	}

	public function test_member_values_are_escaped(): void {
		$out = $this->renderer->fill( '{{member_name}}', array( 'member_name' => '<script>alert(1)</script> & "Co"' ) );
		$this->assertStringNotContainsString( '<script>', $out );
		$this->assertSame( '&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Co&quot;', $out );
	}

	public function test_lists_placeholders_once_in_order(): void {
		$this->assertSame(
			array( 'member_name', 'date' ),
			$this->renderer->placeholders( '{{member_name}} on {{ date }} — {{MEMBER_NAME}}' )
		);
	}

	public function test_renders_a_complete_document_with_title_and_version(): void {
		$version = new TemplateVersion( 7, 2, 3, 'Agreement for {{member_name}}', '<p>Body for {{member_name}}</p>', '2026-01-01 00:00:00' );
		$html    = $this->renderer->render( $version, array( 'member_name' => 'Ada' ) );

		$this->assertStringStartsWith( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '<title>Agreement for Ada</title>', $html );
		$this->assertStringContainsString( '<p>Body for Ada</p>', $html );
		$this->assertStringContainsString( 'Version 3', $html );
	}
}
