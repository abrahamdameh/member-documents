<?php
/**
 * In-memory template repository for tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Fakes;

use MemberDocuments\Templates\Template;
use MemberDocuments\Templates\TemplateRepositoryInterface;
use MemberDocuments\Templates\TemplateVersion;

/**
 * Stores templates and versions in arrays.
 */
final class InMemoryTemplates implements TemplateRepositoryInterface {

	/**
	 * Templates by ID.
	 *
	 * @var array<int, Template>
	 */
	private array $templates = array();

	/**
	 * Versions by ID.
	 *
	 * @var array<int, TemplateVersion>
	 */
	private array $versions = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string $title   Title.
	 * @param string $body    Body.
	 * @param int    $user_id Author.
	 */
	public function create( string $title, string $body, int $user_id ): int {
		$id                     = count( $this->templates ) + 1;
		$this->templates[ $id ] = new Template( $id, $title, 0, '2026-01-01 00:00:00' );
		$this->add_version( $id, $title, $body, $user_id );
		return $id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $template_id Template.
	 * @param string $title       Title.
	 * @param string $body        Body.
	 * @param int    $user_id     Author.
	 */
	public function add_version( int $template_id, string $title, string $body, int $user_id ): int {
		$id     = count( $this->versions ) + 1;
		$number = 1 + count( $this->versions( $template_id ) );

		$this->versions[ $id ]           = new TemplateVersion( $id, $template_id, $number, $title, $body, '2026-01-01 00:00:00' );
		$this->templates[ $template_id ] = new Template( $template_id, $title, $id, '2026-01-01 00:00:00' );
		return $id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template.
	 */
	public function find( int $template_id ): ?Template {
		return $this->templates[ $template_id ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function all(): array {
		return array_values( $this->templates );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template.
	 */
	public function current_version( int $template_id ): ?TemplateVersion {
		$template = $this->find( $template_id );
		return $template ? $this->version( $template->current_version_id ) : null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $version_id Version.
	 */
	public function version( int $version_id ): ?TemplateVersion {
		return $this->versions[ $version_id ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $template_id Template.
	 */
	public function versions( int $template_id ): array {
		return array_values( array_filter( $this->versions, static fn( TemplateVersion $v ): bool => $v->template_id === $template_id ) );
	}
}
