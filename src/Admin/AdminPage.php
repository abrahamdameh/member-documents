<?php
/**
 * Admin screens: templates, issuing, recent documents.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Admin;

use MemberDocuments\Documents\DocumentException;
use MemberDocuments\Documents\DocumentRepositoryInterface;
use MemberDocuments\Documents\DocumentService;
use MemberDocuments\Documents\MemberValues;
use MemberDocuments\Frontend\RequestHandler;
use MemberDocuments\Mail\DocumentMailer;
use MemberDocuments\Plugin;
use MemberDocuments\Templates\TemplateRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * The "Member Documents" admin menu.
 */
final class AdminPage {

	private const SLUG = 'member-documents';

	/**
	 * Constructor.
	 *
	 * @param TemplateRepositoryInterface $templates Templates.
	 * @param DocumentRepositoryInterface $documents Issued documents.
	 * @param DocumentService             $service   Document service.
	 * @param DocumentMailer              $mailer    Mailer.
	 * @param MemberValues                $values    Placeholder values.
	 */
	public function __construct(
		private TemplateRepositoryInterface $templates,
		private DocumentRepositoryInterface $documents,
		private DocumentService $service,
		private DocumentMailer $mailer,
		private MemberValues $values
	) {}

	/**
	 * Registers the menu and form handlers.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_mdocs_save_template', array( $this, 'save_template' ) );
		add_action( 'admin_post_mdocs_issue', array( $this, 'issue' ) );
	}

	/**
	 * Adds the menu page.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Member Documents', 'member-documents' ),
			__( 'Member Documents', 'member-documents' ),
			Plugin::capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-media-document',
			58
		);
	}

	/**
	 * Renders the list screen or the template editor.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
		$template_id = isset( $_GET['template'] ) ? absint( $_GET['template'] ) : 0;
		$editing     = isset( $_GET['edit'] );
		$notice      = isset( $_GET['mdocs_notice'] ) ? sanitize_key( wp_unslash( $_GET['mdocs_notice'] ) ) : '';
		// phpcs:enable

		echo '<div class="wrap"><h1>' . esc_html__( 'Member Documents', 'member-documents' ) . '</h1>';
		$this->notice( $notice );

		if ( $editing ) {
			$this->render_editor( $template_id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * Saves a template. An unchanged save creates nothing; any change creates
	 * a new version, leaving earlier versions (and documents issued from them)
	 * untouched.
	 */
	public function save_template(): void {
		$this->authorize();
		check_admin_referer( 'mdocs_save_template' );

		$template_id = isset( $_POST['template'] ) ? absint( $_POST['template'] ) : 0;
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body        = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		if ( '' === $title || '' === trim( wp_strip_all_tags( $body ) ) ) {
			$this->back( 'missing', $template_id, true );
		}

		if ( 0 === $template_id ) {
			$template_id = $this->templates->create( $title, $body, get_current_user_id() );
			$this->back( 'created', $template_id, true );
		}

		$current = $this->templates->current_version( $template_id );
		if ( $current && $current->title === $title && $current->body === $body ) {
			$this->back( 'unchanged', $template_id, true );
		}
		$this->templates->add_version( $template_id, $title, $body, get_current_user_id() );
		$this->back( 'versioned', $template_id, true );
	}

	/**
	 * Issues a document to a member, optionally emailing it.
	 */
	public function issue(): void {
		$this->authorize();
		check_admin_referer( 'mdocs_issue' );

		$template_id = isset( $_POST['template'] ) ? absint( $_POST['template'] ) : 0;
		$who         = isset( $_POST['member'] ) ? sanitize_text_field( wp_unslash( $_POST['member'] ) ) : '';
		$user        = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );

		if ( ! $user ) {
			$this->back( 'no_member' );
		}

		try {
			$document = $this->service->issue( $user->ID, $template_id, $this->values->for_user( $user ) );
		} catch ( DocumentException $e ) {
			$this->back( 'no_template' );
		}

		$notice = 'issued';
		if ( ! empty( $_POST['email'] ) && ! $this->mailer->send( $document, $user ) ) {
			$notice = 'issued_not_emailed';
		}

		/**
		 * Fires after a document is issued from the admin screen.
		 *
		 * @param \MemberDocuments\Documents\Document $document Document.
		 * @param \WP_User                            $user     Member.
		 */
		do_action( 'mdocs_document_issued', $document, $user );
		$this->back( $notice );
	}

	/**
	 * Templates, issue form and recent documents.
	 */
	private function render_list(): void {
		$templates = $this->templates->all();
		$new_url   = add_query_arg(
			array(
				'page' => self::SLUG,
				'edit' => 1,
			),
			admin_url( 'admin.php' )
		);

		echo '<h2>' . esc_html__( 'Templates', 'member-documents' ) . ' <a class="page-title-action" href="' . esc_url( $new_url ) . '">' . esc_html__( 'Add template', 'member-documents' ) . '</a></h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'member-documents' ) . '</th><th>' . esc_html__( 'Versions', 'member-documents' ) . '</th><th>' . esc_html__( 'Created', 'member-documents' ) . '</th></tr></thead><tbody>';
		if ( empty( $templates ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No templates yet.', 'member-documents' ) . '</td></tr>';
		}
		foreach ( $templates as $template ) {
			$url = add_query_arg(
				array(
					'page'     => self::SLUG,
					'edit'     => 1,
					'template' => $template->id,
				),
				admin_url( 'admin.php' )
			);
			echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $template->title ) . '</a></td><td>' . esc_html( (string) count( $this->templates->versions( $template->id ) ) ) . '</td><td>' . esc_html( get_date_from_gmt( $template->created_at, get_option( 'date_format' ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		if ( $templates ) {
			echo '<h2>' . esc_html__( 'Issue a document', 'member-documents' ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mdocs_issue">';
			wp_nonce_field( 'mdocs_issue' );
			echo '<table class="form-table"><tr><th><label for="mdocs-template">' . esc_html__( 'Template', 'member-documents' ) . '</label></th><td><select id="mdocs-template" name="template">';
			foreach ( $templates as $template ) {
				echo '<option value="' . esc_attr( (string) $template->id ) . '">' . esc_html( $template->title ) . '</option>';
			}
			echo '</select></td></tr>';
			echo '<tr><th><label for="mdocs-member">' . esc_html__( 'Member', 'member-documents' ) . '</label></th><td><input id="mdocs-member" class="regular-text" name="member" placeholder="' . esc_attr__( 'Email or username', 'member-documents' ) . '" required></td></tr>';
			echo '<tr><th>' . esc_html__( 'Email', 'member-documents' ) . '</th><td><label><input type="checkbox" name="email" value="1" checked> ' . esc_html__( 'Email the PDF to the member', 'member-documents' ) . '</label></td></tr></table>';
			submit_button( __( 'Issue document', 'member-documents' ) );
			echo '</form>';
		}

		$recent = $this->documents->recent( 20 );
		echo '<h2>' . esc_html__( 'Recent documents', 'member-documents' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Document', 'member-documents' ) . '</th><th>' . esc_html__( 'Member', 'member-documents' ) . '</th><th>' . esc_html__( 'Issued', 'member-documents' ) . '</th><th>' . esc_html__( 'Accepted', 'member-documents' ) . '</th><th>' . esc_html__( 'Integrity', 'member-documents' ) . '</th></tr></thead><tbody>';
		if ( empty( $recent ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No documents issued yet.', 'member-documents' ) . '</td></tr>';
		}
		foreach ( $recent as $document ) {
			$member = get_userdata( $document->user_id );
			echo '<tr><td><a href="' . esc_url( RequestHandler::download_url( $document->id ) ) . '" target="_blank" rel="noopener">' . esc_html( $document->title ) . '</a></td>';
			echo '<td>' . esc_html( $member ? $member->user_email : '#' . $document->user_id ) . '</td>';
			echo '<td>' . esc_html( get_date_from_gmt( $document->issued_at ) ) . '</td>';
			echo '<td>' . esc_html( $document->accepted_at ? get_date_from_gmt( $document->accepted_at ) . ' · ' . $document->accepted_ip : '—' ) . '</td>';
			echo '<td>' . ( $this->service->verify( $document ) ? esc_html__( 'OK', 'member-documents' ) : '<strong>' . esc_html__( 'Changed', 'member-documents' ) . '</strong>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * The template editor with its version history.
	 *
	 * @param int $template_id Template, or 0 for a new one.
	 */
	private function render_editor( int $template_id ): void {
		$current  = $template_id ? $this->templates->current_version( $template_id ) : null;
		$versions = $template_id ? $this->templates->versions( $template_id ) : array();

		echo '<h2>' . esc_html( $current ? __( 'Edit template', 'member-documents' ) : __( 'New template', 'member-documents' ) ) . '</h2>';
		echo '<p>' . esc_html__( 'Saving a change creates a new version. Documents already issued keep the version they were generated from.', 'member-documents' ) . '</p>';
		echo '<p>' . esc_html__( 'Placeholders:', 'member-documents' ) . ' <code>' . esc_html( implode( '}}  {{', MemberValues::names() ) ) . '</code></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mdocs_save_template"><input type="hidden" name="template" value="' . esc_attr( (string) $template_id ) . '">';
		wp_nonce_field( 'mdocs_save_template' );
		echo '<p><label for="mdocs-title"><strong>' . esc_html__( 'Title', 'member-documents' ) . '</strong></label><br><input id="mdocs-title" class="large-text" name="title" value="' . esc_attr( $current ? $current->title : '' ) . '" required></p>';
		wp_editor(
			$current ? $current->body : '',
			'mdocs-body',
			array(
				'textarea_name' => 'body',
				'media_buttons' => false,
				'textarea_rows' => 18,
			)
		);
		submit_button( $current ? __( 'Save new version', 'member-documents' ) : __( 'Create template', 'member-documents' ) );
		echo '</form>';

		if ( $versions ) {
			echo '<h3>' . esc_html__( 'Version history', 'member-documents' ) . '</h3><ul>';
			foreach ( $versions as $version ) {
				/* translators: 1: version number, 2: date. */
				echo '<li>' . esc_html( sprintf( __( 'Version %1$d · %2$s', 'member-documents' ), $version->number, get_date_from_gmt( $version->created_at ) ) ) . '</li>';
			}
			echo '</ul>';
		}
	}

	/**
	 * Admin notice for a result code.
	 *
	 * @param string $code Result code.
	 */
	private function notice( string $code ): void {
		$messages = array(
			'created'            => array( 'success', __( 'Template created.', 'member-documents' ) ),
			'versioned'          => array( 'success', __( 'New version saved.', 'member-documents' ) ),
			'unchanged'          => array( 'info', __( 'Nothing changed, so no new version was created.', 'member-documents' ) ),
			'missing'            => array( 'error', __( 'A title and some content are required.', 'member-documents' ) ),
			'issued'             => array( 'success', __( 'Document issued.', 'member-documents' ) ),
			'issued_not_emailed' => array( 'warning', __( 'Document issued, but the email could not be sent.', 'member-documents' ) ),
			'no_member'          => array( 'error', __( 'No member was found with that email or username.', 'member-documents' ) ),
			'no_template'        => array( 'error', __( 'That template no longer exists.', 'member-documents' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
	}

	/**
	 * Stops the request unless the user may manage documents.
	 */
	private function authorize(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'member-documents' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirects back to the admin screen with a result code.
	 *
	 * @param string $notice      Result code.
	 * @param int    $template_id Template to reopen.
	 * @param bool   $edit        Whether to reopen the editor.
	 * @return never
	 */
	private function back( string $notice, int $template_id = 0, bool $edit = false ): never {
		$args = array(
			'page'         => self::SLUG,
			'mdocs_notice' => $notice,
		);
		if ( $edit ) {
			$args['edit'] = 1;
		}
		if ( $template_id ) {
			$args['template'] = $template_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
