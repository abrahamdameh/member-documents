<?php
/**
 * WP-CLI commands.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Cli;

use MemberDocuments\Documents\DocumentRepositoryInterface;
use MemberDocuments\Documents\DocumentService;

defined( 'ABSPATH' ) || exit;

/**
 * Manage member documents.
 */
final class Command {

	/**
	 * Constructor.
	 *
	 * @param DocumentRepositoryInterface $documents Issued documents.
	 * @param DocumentService             $service   Document service.
	 */
	public function __construct(
		private DocumentRepositoryInterface $documents,
		private DocumentService $service
	) {}

	/**
	 * Checks every stored PDF against the hash recorded when it was issued.
	 *
	 * Lists any document whose file is missing or has changed. Exits with a
	 * non-zero status when there are problems, so it can run from cron or CI.
	 *
	 * ## EXAMPLES
	 *
	 *     wp member-documents verify
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function verify( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		$checked = 0;
		$bad     = array();
		$after   = 0;

		do {
			$batch = $this->documents->batch( $after, 200 );
			$size  = count( $batch );
			foreach ( $batch as $document ) {
				++$checked;
				$after = $document->id;
				if ( ! $this->service->verify( $document ) ) {
					$bad[] = array(
						'id'     => $document->id,
						'member' => $document->user_id,
						'file'   => $document->file,
					);
				}
			}
		} while ( 200 === $size );

		if ( $bad ) {
			\WP_CLI\Utils\format_items( 'table', $bad, array( 'id', 'member', 'file' ) );
			\WP_CLI::error( sprintf( '%d of %d documents failed the integrity check.', count( $bad ), $checked ) );
		}
		\WP_CLI::success( sprintf( 'All %d documents match the hashes recorded at issue.', $checked ) );
	}
}
