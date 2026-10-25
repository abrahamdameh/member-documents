<?php
/**
 * Base test case with Brain Monkey set up.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Base class for unit tests.
 */
abstract class TestCase extends PhpUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Sets up Brain Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tears down Brain Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
