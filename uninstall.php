<?php
/**
 * Uninstall.
 *
 * Issued documents and acceptance records can be legal evidence, so they are
 * kept by default. To remove everything, define MDOCS_REMOVE_ALL_DATA as true
 * in wp-config.php before deleting the plugin.
 *
 * @package MemberDocuments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MDOCS_REMOVE_ALL_DATA' ) || true !== MDOCS_REMOVE_ALL_DATA ) {
	return;
}

global $wpdb;

foreach ( array( 'mdocs_documents', 'mdocs_template_versions', 'mdocs_templates' ) as $mdocs_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . $mdocs_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
}
delete_option( 'mdocs_schema_version' );

$mdocs_uploads = wp_upload_dir( null, false );
$mdocs_dir     = $mdocs_uploads['basedir'] . '/member-documents';
if ( is_dir( $mdocs_dir ) ) {
	$mdocs_items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $mdocs_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $mdocs_items as $mdocs_item ) {
		$mdocs_item->isDir() ? rmdir( $mdocs_item->getPathname() ) : unlink( $mdocs_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- removing the plugin's own private folder.
	}
	rmdir( $mdocs_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- see above.
}
