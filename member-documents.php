<?php
/**
 * Plugin Name:       Member Documents
 * Plugin URI:        https://github.com/abrahamdameh/member-documents
 * Description:       Versioned document templates, recorded member acceptance and per-member PDF archives.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Abraham Dameh
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       member-documents
 * Domain Path:       /languages
 *
 * @package MemberDocuments
 */

defined( 'ABSPATH' ) || exit;

define( 'MDOCS_VERSION', '1.0.0' );
define( 'MDOCS_FILE', __FILE__ );
define( 'MDOCS_DIR', plugin_dir_path( __FILE__ ) );

$mdocs_autoload = MDOCS_DIR . 'vendor/autoload.php';

if ( ! is_readable( $mdocs_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			esc_html_e( 'Member Documents is missing its dependencies. Run "composer install" in the plugin folder, or install a release build.', 'member-documents' );
			echo '</p></div>';
		}
	);
	return;
}

require $mdocs_autoload;

// Release builds ship Dompdf under the MemberDocuments\Vendor namespace (see
// the release workflow), so it cannot clash with another plugin's copy.
if ( is_readable( MDOCS_DIR . 'vendor-prefixed/autoload.php' ) ) {
	require MDOCS_DIR . 'vendor-prefixed/autoload.php';
}

register_activation_hook( __FILE__, array( \MemberDocuments\Schema\Installer::class, 'activate' ) );

add_action( 'plugins_loaded', array( \MemberDocuments\Plugin::class, 'boot' ) );
