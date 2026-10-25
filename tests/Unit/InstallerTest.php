<?php
/**
 * Schema tests.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests\Unit;

use Brain\Monkey\Functions;
use MemberDocuments\Schema\Installer;
use MemberDocuments\Tests\TestCase;

/**
 * @covers \MemberDocuments\Schema\Installer
 */
final class InstallerTest extends TestCase {

	public function test_schema_creates_three_prefixed_tables(): void {
		$sql = Installer::schema( 'wp_', 'DEFAULT CHARSET=utf8mb4' );

		$this->assertCount( 3, $sql );
		$this->assertStringContainsString( 'CREATE TABLE wp_mdocs_templates', $sql[0] );
		$this->assertStringContainsString( 'CREATE TABLE wp_mdocs_template_versions', $sql[1] );
		$this->assertStringContainsString( 'CREATE TABLE wp_mdocs_documents', $sql[2] );
	}

	public function test_schema_is_written_for_dbdelta(): void {
		foreach ( Installer::schema( 'wp_', '' ) as $statement ) {
			// dbDelta needs exactly two spaces between PRIMARY KEY and its definition.
			$this->assertMatchesRegularExpression( '/PRIMARY KEY  \(id\)/', $statement );
		}
	}

	public function test_version_numbers_are_unique_per_template(): void {
		$this->assertStringContainsString( 'UNIQUE KEY template_number (template_id,number)', Installer::schema( 'wp_', '' )[1] );
	}

	public function test_upgrade_is_skipped_when_the_schema_is_current(): void {
		Functions\expect( 'get_option' )->once()->with( Installer::VERSION_OPTION )->andReturn( Installer::SCHEMA_VERSION );
		Functions\expect( 'update_option' )->never();

		Installer::maybe_upgrade();
		$this->addToAssertionCount( 1 );
	}
}
