<?php
/**
 * Bootstrap for integration tests.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Verify required environment variables.
$apiKey = getenv( 'AKISMET_API_KEY' );
if ( empty( $apiKey ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Using STDERR for test bootstrap error output is appropriate.
	fwrite(
		STDERR,
		"AKISMET_API_KEY environment variable required for integration tests\n"
		. "Run: AKISMET_API_KEY=your-key composer test:integration\n"
	);
	exit( 1 );
}
