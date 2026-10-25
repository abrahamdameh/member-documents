<?php
/**
 * Issuing, accepting and verifying documents.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

use MemberDocuments\Pdf\PdfRendererInterface;
use MemberDocuments\Storage\DocumentStoreInterface;
use MemberDocuments\Templates\Renderer;
use MemberDocuments\Templates\TemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The document lifecycle. It holds no WordPress calls of its own, so every
 * rule here is covered by plain unit tests.
 */
final class DocumentService {

	/**
	 * Returns the current UTC time as 'Y-m-d H:i:s'.
	 *
	 * @var callable(): string
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param TemplateRepositoryInterface $templates Templates.
	 * @param DocumentRepositoryInterface $documents Issued documents.
	 * @param Renderer                    $renderer  HTML renderer.
	 * @param PdfRendererInterface        $pdf       PDF engine.
	 * @param DocumentStoreInterface      $store     File storage.
	 * @param callable|null               $clock     Current UTC time; defaults to gmdate().
	 */
	public function __construct(
		private TemplateRepositoryInterface $templates,
		private DocumentRepositoryInterface $documents,
		private Renderer $renderer,
		private PdfRendererInterface $pdf,
		private DocumentStoreInterface $store,
		?callable $clock = null
	) {
		$this->clock = $clock ?? static fn(): string => gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Generates a PDF from a template's current version and records it.
	 *
	 * The SHA-256 of the exact bytes is stored, so the archive can later prove
	 * the file a member accepted is the file that was issued.
	 *
	 * @param int                   $user_id     Member.
	 * @param int                   $template_id Template.
	 * @param array<string, scalar> $values      Placeholder values.
	 * @throws DocumentException When the template does not exist.
	 */
	public function issue( int $user_id, int $template_id, array $values ): Document {
		$version = $this->templates->current_version( $template_id );
		if ( null === $version ) {
			throw DocumentException::template_not_found( $template_id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
		}

		$bytes = $this->pdf->render( $this->renderer->render( $version, $values ) );
		$hash  = hash( 'sha256', $bytes );
		$file  = $this->store->put(
			$user_id,
			sprintf( 't%d-v%d-%s.pdf', $template_id, $version->number, substr( $hash, 0, 16 ) ),
			$bytes
		);

		$id = $this->documents->insert(
			array(
				'user_id'     => $user_id,
				'template_id' => $template_id,
				'version_id'  => $version->id,
				'title'       => $this->renderer->fill( $version->title, $values ),
				'file'        => $file,
				'sha256'      => $hash,
				'issued_at'   => ( $this->clock )(),
			)
		);

		return $this->documents->find( $id ) ?? throw DocumentException::not_found( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed. // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
	}

	/**
	 * Records a member's acceptance.
	 *
	 * Acceptance is write-once: accepting an already-accepted document returns
	 * it unchanged, keeping the original time, IP and browser. A document whose
	 * file no longer matches its issue-time hash cannot be accepted.
	 *
	 * @param int    $document_id Document.
	 * @param int    $user_id     The member accepting it.
	 * @param string $ip          Request IP address.
	 * @param string $user_agent  Request browser.
	 * @throws DocumentException When missing, not the member's, or tampered with.
	 */
	public function accept( int $document_id, int $user_id, string $ip, string $user_agent ): Document {
		$document = $this->owned( $document_id, $user_id );
		if ( $document->is_accepted() ) {
			return $document;
		}
		if ( ! $this->verify( $document ) ) {
			throw DocumentException::integrity( $document_id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
		}

		$this->documents->mark_accepted( $document_id, ( $this->clock )(), $ip, $user_agent );
		return $this->documents->find( $document_id ) ?? throw DocumentException::not_found( $document_id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed. // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
	}

	/**
	 * Whether the stored file still matches the hash recorded at issue.
	 *
	 * @param Document $document Document.
	 */
	public function verify( Document $document ): bool {
		$bytes = $this->store->read( $document->file );
		return null !== $bytes && hash_equals( $document->sha256, hash( 'sha256', $bytes ) );
	}

	/**
	 * Loads a document regardless of owner (for administrators).
	 *
	 * @param int $document_id Document.
	 * @throws DocumentException When missing.
	 */
	public function get( int $document_id ): Document {
		return $this->documents->find( $document_id ) ?? throw DocumentException::not_found( $document_id ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed. // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
	}

	/**
	 * Loads a document and checks who it belongs to.
	 *
	 * @param int $document_id Document.
	 * @param int $user_id     Member.
	 * @throws DocumentException When missing or not the member's.
	 */
	public function owned( int $document_id, int $user_id ): Document {
		$document = $this->get( $document_id );
		if ( $document->user_id !== $user_id ) {
			throw DocumentException::not_owner(); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is escaped where it is displayed.
		}
		return $document;
	}
}
