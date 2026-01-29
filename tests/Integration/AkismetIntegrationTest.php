<?php
/**
 * Integration tests for Akismet API.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Integration;

use Automattic\Akismet\Akismet;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
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

		if ( false === $apiKey ) {
			$this->fail( 'AKISMET_API_KEY environment variable is required' );
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

	public function testCheckHam(): void {
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'This is a legitimate comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertFalse( $result->isSpam() );
		$this->assertFalse( $result->shouldDiscard() );
	}

	public function testCheckSpam(): void {
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'viagra-test-123',
			authorEmail: 'akismet-guaranteed-spam@example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertTrue( $result->isSpam() );
	}

	public function testSubmitSpam(): void {
		$this->expectNotToPerformAssertions();

		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'spam content',
			type: CommentType::Comment
		);

		$this->akismet->submitSpam( $comment );
	}

	public function testSubmitHam(): void {
		$this->expectNotToPerformAssertions();

		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'legitimate content',
			type: CommentType::Comment
		);

		$this->akismet->submitHam( $comment );
	}

	public function testGetUsageLimit(): void {
		$usage = $this->akismet->getUsageLimit();

		$this->assertGreaterThanOrEqual( 0, $usage->usage );
		$this->assertTrue( is_int( $usage->limit ) || $usage->limit === null );
		$this->assertIsString( $usage->percentage );
		$this->assertIsBool( $usage->throttled );
	}

	public function testGetKeySites(): void {
		$response = $this->akismet->getKeySites( limit: 10 );

		$this->assertIsArray( $response->sites );
		$this->assertLessThanOrEqual( 10, count( $response->sites ) );
	}

	public function testGetKeySitesWithFilter(): void {
		$response = $this->akismet->getKeySites(
			filter: 'example.com',
			limit: 5
		);

		$this->assertIsArray( $response->sites );
	}
}
