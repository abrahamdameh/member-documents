<?php
/**
 * Template storage contract.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes templates and their versions.
 */
interface TemplateRepositoryInterface {

	/**
	 * Creates a template with its first version.
	 *
	 * @param string $title   Title.
	 * @param string $body    Body HTML.
	 * @param int    $user_id Author.
	 * @return int Template ID.
	 */
	public function create( string $title, string $body, int $user_id ): int;

	/**
	 * Adds a new version and makes it current.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $title       Title.
	 * @param string $body        Body HTML.
	 * @param int    $user_id     Author.
	 * @return int Version ID.
	 */
	public function add_version( int $template_id, string $title, string $body, int $user_id ): int;

	/**
	 * Finds a template.
	 *
	 * @param int $template_id Template ID.
	 */
	public function find( int $template_id ): ?Template;

	/**
	 * All templates, newest first.
	 *
	 * @return Template[]
	 */
	public function all(): array;

	/**
	 * The current version of a template.
	 *
	 * @param int $template_id Template ID.
	 */
	public function current_version( int $template_id ): ?TemplateVersion;

	/**
	 * A specific version.
	 *
	 * @param int $version_id Version ID.
	 */
	public function version( int $version_id ): ?TemplateVersion;

	/**
	 * All versions of a template, newest first.
	 *
	 * @param int $template_id Template ID.
	 * @return TemplateVersion[]
	 */
	public function versions( int $template_id ): array;
}
