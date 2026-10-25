<?php
/**
 * PHPUnit bootstrap: unit tests run without WordPress; WordPress functions
 * are stubbed per test with Brain Monkey.
 *
 * @package MemberDocuments
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MDOCS_DIR', dirname( __DIR__ ) . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
