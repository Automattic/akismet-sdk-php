<?php
/**
 * Integration tests for Akismet API.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Integration;

use Automattic\Akismet\Akismet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

#[Group( 'integration' )]
final class AkismetIntegrationTest extends TestCase {

	private Akismet $akismet;

	protected function setUp(): void {
		$apiKey  = getenv( 'AKISMET_API_KEY' );
		$blogUrl = getenv( 'AKISMET_BLOG_URL' );

		if ( false === $blogUrl ) {
			$blogUrl = 'https://example.com';
		}

		$this->akismet = new Akismet(
			apiKey: $apiKey,
			blog: $blogUrl,
			isTest: true
		);
	}

	public function testVerifyKey(): void {
		$isValid = $this->akismet->verifyKey();
		$this->assertTrue( $isValid );
	}
}
