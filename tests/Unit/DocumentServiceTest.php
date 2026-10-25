<?php
/**
 * Document lifecycle tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Unit;

use MemberDocuments\Documents\DocumentException;
use MemberDocuments\Documents\DocumentService;
use MemberDocuments\Templates\Renderer;
use MemberDocuments\Tests\Fakes\FakePdf;
use MemberDocuments\Tests\Fakes\InMemoryDocuments;
use MemberDocuments\Tests\Fakes\InMemoryStore;
use MemberDocuments\Tests\Fakes\InMemoryTemplates;
use MemberDocuments\Tests\TestCase;

/**
 * @covers \MemberDocuments\Documents\DocumentService
 */
final class DocumentServiceTest extends TestCase {

	private InMemoryTemplates $templates;
	private InMemoryDocuments $documents;
	private InMemoryStore $store;
	private FakePdf $pdf;
	private DocumentService $service;
	private string $now = '2026-10-26 09:00:00';

	protected function setUp(): void {
		parent::setUp();
		$this->templates = new InMemoryTemplates();
		$this->documents = new InMemoryDocuments();
		$this->store     = new InMemoryStore();
		$this->pdf       = new FakePdf();
		$this->service   = new DocumentService(
			$this->templates,
			$this->documents,
			new Renderer(),
			$this->pdf,
			$this->store,
			fn(): string => $this->now
		);
	}

	public function test_issue_renders_current_version_and_records_hash(): void {
		$template = $this->templates->create( 'Agreement', '<p>v1 for {{member_name}}</p>', 1 );
		$this->templates->add_version( $template, 'Agreement', '<p>v2 for {{member_name}}</p>', 1 );

		$document = $this->service->issue( 42, $template, array( 'member_name' => 'Ada' ) );

		$this->assertStringContainsString( 'v2 for Ada', $this->pdf->last_html );
		$this->assertSame( 42, $document->user_id );
		$this->assertSame( 2, $document->version_id );
		$this->assertSame( $this->now, $document->issued_at );
		$this->assertSame( hash( 'sha256', (string) $this->store->read( $document->file ) ), $document->sha256 );
		$this->assertStringStartsWith( '42/t1-v2-', $document->file );
		$this->assertFalse( $document->is_accepted() );
	}

	public function test_issue_fills_placeholders_in_the_stored_title(): void {
		$template = $this->templates->create( 'Notice for {{member_name}}', '<p>Body</p>', 1 );
		$this->assertSame( 'Notice for Ada', $this->service->issue( 1, $template, array( 'member_name' => 'Ada' ) )->title );
	}

	public function test_issue_fails_for_a_missing_template(): void {
		$this->expectException( DocumentException::class );
		$this->service->issue( 1, 999, array() );
	}

	public function test_older_documents_keep_their_version_after_the_template_changes(): void {
		$template = $this->templates->create( 'Agreement', '<p>original</p>', 1 );
		$first    = $this->service->issue( 1, $template, array() );
		$this->templates->add_version( $template, 'Agreement', '<p>revised</p>', 1 );
		$second = $this->service->issue( 1, $template, array() );

		$this->assertSame( 1, $first->version_id );
		$this->assertSame( 2, $second->version_id );
		$this->assertStringContainsString( 'original', (string) $this->store->read( $first->file ) );
	}

	public function test_accept_records_time_ip_and_browser(): void {
		$document = $this->issue_one( 7 );

		$accepted = $this->service->accept( $document->id, 7, '203.0.113.9', 'Firefox' );

		$this->assertTrue( $accepted->is_accepted() );
		$this->assertSame( $this->now, $accepted->accepted_at );
		$this->assertSame( '203.0.113.9', $accepted->accepted_ip );
		$this->assertSame( 'Firefox', $accepted->accepted_user_agent );
	}

	public function test_acceptance_is_write_once(): void {
		$document = $this->issue_one( 7 );
		$this->service->accept( $document->id, 7, '203.0.113.9', 'Firefox' );

		$this->now = '2026-12-31 23:59:59';
		$again     = $this->service->accept( $document->id, 7, '198.51.100.1', 'Chrome' );

		$this->assertSame( '2026-10-26 09:00:00', $again->accepted_at );
		$this->assertSame( '203.0.113.9', $again->accepted_ip );
	}

	public function test_a_member_cannot_accept_someone_elses_document(): void {
		$document = $this->issue_one( 7 );
		$this->expectException( DocumentException::class );
		$this->service->accept( $document->id, 8, '203.0.113.9', 'Firefox' );
	}

	public function test_a_changed_file_fails_verification_and_cannot_be_accepted(): void {
		$document                             = $this->issue_one( 7 );
		$this->store->files[ $document->file ] .= 'tampered';

		$this->assertFalse( $this->service->verify( $document ) );
		$this->expectException( DocumentException::class );
		$this->service->accept( $document->id, 7, '203.0.113.9', 'Firefox' );
	}

	public function test_a_missing_file_fails_verification(): void {
		$document = $this->issue_one( 7 );
		unset( $this->store->files[ $document->file ] );
		$this->assertFalse( $this->service->verify( $document ) );
	}

	public function test_get_and_owned_report_missing_documents(): void {
		$this->expectException( DocumentException::class );
		$this->service->owned( 123, 1 );
	}

	/**
	 * Issues one document to a member.
	 *
	 * @param int $user_id Member.
	 */
	private function issue_one( int $user_id ): \MemberDocuments\Documents\Document {
		$template = $this->templates->create( 'Agreement', '<p>Terms</p>', 1 );
		return $this->service->issue( $user_id, $template, array() );
	}
}
