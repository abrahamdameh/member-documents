<?php
/**
 * Member documents list.
 *
 * Override by copying to your-theme/member-documents/documents-list.php.
 *
 * @package MemberDocuments
 *
 * @var \MemberDocuments\Documents\Document[] $documents The member's documents, newest first.
 * @var string                                $notice    'accepted', 'error' or ''.
 */

use MemberDocuments\Frontend\RequestHandler;

defined( 'ABSPATH' ) || exit;
?>
<div class="mdocs">
	<?php if ( 'accepted' === $notice ) : ?>
		<p class="mdocs-notice mdocs-notice-success" role="status"><?php esc_html_e( 'Thank you. Your acceptance has been recorded.', 'member-documents' ); ?></p>
	<?php elseif ( 'error' === $notice ) : ?>
		<p class="mdocs-notice mdocs-notice-error" role="alert"><?php esc_html_e( 'That document could not be accepted. Please contact us.', 'member-documents' ); ?></p>
	<?php endif; ?>

	<?php if ( empty( $documents ) ) : ?>
		<p class="mdocs-empty"><?php esc_html_e( 'You have no documents yet.', 'member-documents' ); ?></p>
	<?php else : ?>
		<table class="mdocs-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Document', 'member-documents' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Issued', 'member-documents' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'member-documents' ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'member-documents' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $documents as $mdocs_document ) : ?>
				<tr>
					<td><?php echo esc_html( $mdocs_document->title ); ?></td>
					<td><?php echo esc_html( get_date_from_gmt( $mdocs_document->issued_at, get_option( 'date_format' ) ) ); ?></td>
					<td>
						<?php if ( $mdocs_document->is_accepted() ) : ?>
							<?php
							/* translators: %s: acceptance date. */
							echo esc_html( sprintf( __( 'Accepted %s', 'member-documents' ), get_date_from_gmt( (string) $mdocs_document->accepted_at, get_option( 'date_format' ) ) ) );
							?>
						<?php else : ?>
							<?php esc_html_e( 'Awaiting acceptance', 'member-documents' ); ?>
						<?php endif; ?>
					</td>
					<td class="mdocs-actions">
						<a href="<?php echo esc_url( RequestHandler::download_url( $mdocs_document->id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View PDF', 'member-documents' ); ?></a>
						<?php if ( ! $mdocs_document->is_accepted() ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="mdocs_accept">
								<input type="hidden" name="id" value="<?php echo esc_attr( (string) $mdocs_document->id ); ?>">
								<?php wp_nonce_field( 'mdocs_accept_' . $mdocs_document->id ); ?>
								<button type="submit"><?php esc_html_e( 'I accept', 'member-documents' ); ?></button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
