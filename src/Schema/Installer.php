<?php
/**
 * Database schema: creation and self-upgrade.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's tables.
 *
 * The upgrade runs on every request until it has been verified, so a site
 * never needs the plugin deactivated and reactivated to pick up a schema
 * change. The stored version is written only after the tables are confirmed
 * to exist, which means a failed upgrade is simply retried next request.
 */
final class Installer {

	public const SCHEMA_VERSION = '1.0.0';
	public const VERSION_OPTION = 'mdocs_schema_version';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::install();
	}

	/**
	 * Installs or upgrades the schema when the stored version is out of date.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}
		self::install();
	}

	/**
	 * Runs dbDelta and records the version once the tables really exist.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::schema( $wpdb->prefix, $wpdb->get_charset_collate() ) as $sql ) {
			dbDelta( $sql );
		}

		foreach ( array_keys( self::tables() ) as $key ) {
			$table = self::table( $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check, runs only while upgrading.
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return;
			}
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Logical table names mapped to their unprefixed names.
	 *
	 * @return array<string, string>
	 */
	public static function tables(): array {
		return array(
			'templates' => 'mdocs_templates',
			'versions'  => 'mdocs_template_versions',
			'documents' => 'mdocs_documents',
		);
	}

	/**
	 * Full (prefixed) table name for a logical key.
	 *
	 * @param string $key One of the keys of tables().
	 */
	public static function table( string $key ): string {
		global $wpdb;
		return $wpdb->prefix . self::tables()[ $key ];
	}

	/**
	 * CREATE TABLE statements, written for dbDelta (two spaces before PRIMARY KEY).
	 *
	 * @param string $prefix  Table prefix.
	 * @param string $collate Charset/collation clause.
	 * @return string[]
	 */
	public static function schema( string $prefix, string $collate ): array {
		$templates = $prefix . 'mdocs_templates';
		$versions  = $prefix . 'mdocs_template_versions';
		$documents = $prefix . 'mdocs_documents';

		return array(
			"CREATE TABLE {$templates} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(200) NOT NULL,
  current_version_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id)
) {$collate};",
			"CREATE TABLE {$versions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  template_id bigint(20) unsigned NOT NULL,
  number int(10) unsigned NOT NULL,
  title varchar(200) NOT NULL,
  body longtext NOT NULL,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY template_number (template_id,number)
) {$collate};",
			"CREATE TABLE {$documents} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  template_id bigint(20) unsigned NOT NULL,
  version_id bigint(20) unsigned NOT NULL,
  title varchar(200) NOT NULL,
  file varchar(255) NOT NULL,
  sha256 char(64) NOT NULL,
  issued_at datetime NOT NULL,
  accepted_at datetime DEFAULT NULL,
  accepted_ip varchar(45) DEFAULT NULL,
  accepted_user_agent varchar(255) DEFAULT NULL,
  emailed_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY template_id (template_id)
) {$collate};",
		);
	}
}
