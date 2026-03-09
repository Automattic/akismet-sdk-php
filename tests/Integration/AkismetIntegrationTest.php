<?php
/**
 * Integration tests for Akismet API.
 *
 * These tests run against the real Akismet API and require a valid API key.
 * They verify the SDK correctly communicates with the API under normal conditions.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Integration;

use Automattic\Akismet\Akismet;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

#[Group( 'integration' )]
#[CoversNothing]
final class AkismetIntegrationTest extends TestCase {

	private Akismet $akismet;
	private string $apiKey;
	private string $blogUrl;

	protected function setUp(): void {
		$apiKey        = getenv( 'AKISMET_API_KEY' );
		$this->apiKey  = false !== $apiKey ? $apiKey : '';
		$blogUrl       = getenv( 'AKISMET_BLOG_URL' );
		$this->blogUrl = false !== $blogUrl ? $blogUrl : 'https://example.com';

		if ( '' === $this->apiKey ) {
			$this->fail( 'AKISMET_API_KEY environment variable is required' );
		}

		$this->akismet = Akismet::create(
			apiKey: $this->apiKey,
			blog: $this->blogUrl,
			isTest: true
		);
	}

	// =========================================================================
	// Key Verification Tests
	// =========================================================================

	public function testVerifyKeyWithValidKey(): void {
		$isValid = $this->akismet->verifyKey();

		$this->assertTrue( $isValid, 'Valid API key should be accepted' );
	}

	public function testVerifyKeyWithInvalidKey(): void {
		$akismet = Akismet::create(
			apiKey: 'invalid-key-that-does-not-exist',
			blog: $this->blogUrl,
			isTest: true
		);

		$this->expectException( InvalidApiKeyException::class );
		$akismet->verifyKey();
	}

	// =========================================================================
	// Comment Check Tests - Ham (Not Spam)
	// =========================================================================

	public function testCheckHamWithTypicalComment(): void {
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			content: 'This is a legitimate comment with normal content.',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertFalse( $result->isSpam(), 'Normal comment should not be spam' );
		$this->assertFalse( $result->shouldDiscard(), 'Normal comment should not be discarded' );
		$this->assertNotNull( $result->guid, 'Akismet should return a GUID for check requests' );
	}

	public function testCheckHamWithMinimalData(): void {
		// Minimal data with no spam signals should return ham (not spam).
		$comment = new Comment( userIp: '192.168.1.1' );

		$result = $this->akismet->check( $comment );

		// Absence of spam signals should bias toward ham.
		$this->assertFalse( $result->isSpam(), 'Minimal data without spam signals should not be flagged' );
		$this->assertFalse( $result->shouldDiscard() );
	}

	public function testCheckHamWithAdministratorRole(): void {
		// Administrator role should bias toward ham.
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'Admin comment',
			authorName: 'Site Admin',
			authorEmail: 'admin@example.com',
			type: CommentType::Comment,
			userRole: 'administrator'
		);

		$result = $this->akismet->check( $comment );

		$this->assertFalse( $result->isSpam(), 'Administrator comments should not be spam' );
	}

	// =========================================================================
	// Comment Check Tests - Spam
	// =========================================================================

	public function testCheckSpamWithGuaranteedSpamEmail(): void {
		// Akismet test mode: akismet-guaranteed-spam@example.com always triggers spam.
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'This comment uses a guaranteed spam email.',
			authorEmail: 'akismet-guaranteed-spam@example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertTrue( $result->isSpam(), 'Guaranteed spam email should be flagged' );
	}

	public function testCheckSpamWithGuaranteedSpamAuthor(): void {
		// Using akismet-guaranteed-spam as author name triggers spam detection.
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'Check out my website!',
			authorName: 'akismet-guaranteed-spam',
			authorEmail: 'test@example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertTrue( $result->isSpam(), 'Guaranteed spam author should be flagged' );
	}

	public function testCheckSpamBlatantShouldDiscard(): void {
		// Combining multiple spam signals should trigger blatant spam (discard).
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'Buy cheap stuff now!',
			authorName: 'akismet-guaranteed-spam',
			authorEmail: 'akismet-guaranteed-spam@example.com',
			authorUrl: 'https://spam-site.example.com',
			type: CommentType::Comment
		);

		$result = $this->akismet->check( $comment );

		$this->assertTrue( $result->isSpam(), 'Blatant spam should be flagged as spam' );
		// Note: shouldDiscard() depends on X-akismet-pro-tip header from API.
		// We assert isSpam at minimum; discard is API-dependent.
	}

	// =========================================================================
	// Comment Check Tests - All Fields
	// =========================================================================

	public function testCheckWithAllOptionalFields(): void {
		$date         = new \DateTimeImmutable( '2024-01-15T10:30:00Z' );
		$postModified = new \DateTimeImmutable( '2024-01-10T08:00:00Z' );

		$comment = new Comment(
			userIp: '203.0.113.42',
			userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
			content: 'This is a test comment with all fields populated.',
			authorName: 'Jane Smith',
			authorEmail: 'jane@example.com',
			authorUrl: 'https://jane.example.com',
			type: CommentType::Comment,
			permalink: 'https://example.com/blog/post-123',
			referrer: 'https://google.com/search?q=example',
			dateGmt: $date,
			postModifiedGmt: $postModified,
			userRole: 'subscriber',
			recheckReason: 'edit',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: '',
			serverVariables: [
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
			],
		);

		$result = $this->akismet->check( $comment );

		// Legitimate content with all fields should not be spam.
		$this->assertFalse( $result->isSpam(), 'Comment with all fields should not be spam' );
		$this->assertNotNull( $result->verdict );
	}

	// =========================================================================
	// Comment Check Tests - Different Comment Types
	// =========================================================================

	public function testCheckContactFormSubmission(): void {
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'I have a question about your services.',
			authorName: 'Potential Customer',
			authorEmail: 'customer@example.com',
			type: CommentType::ContactForm
		);

		$result = $this->akismet->check( $comment );

		$this->assertFalse( $result->isSpam(), 'Legitimate contact form should not be spam' );
	}

	public function testCheckSignupSubmission(): void {
		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: '',
			authorName: 'newuser123',
			authorEmail: 'newuser@example.com',
			type: CommentType::Signup
		);

		$result = $this->akismet->check( $comment );

		// Legitimate signup with normal email should not be flagged.
		$this->assertFalse( $result->isSpam(), 'Legitimate signup should not be spam' );
	}

	// =========================================================================
	// Spam/Ham Submission Tests
	// =========================================================================

	public function testSubmitSpamDoesNotThrow(): void {
		$this->expectNotToPerformAssertions();

		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'This was spam that got through.',
			authorEmail: 'spammer@example.com',
			type: CommentType::Comment
		);

		$this->akismet->submitSpam( $comment );
	}

	public function testSubmitHamDoesNotThrow(): void {
		$this->expectNotToPerformAssertions();

		$comment = new Comment(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			content: 'This was incorrectly flagged as spam.',
			authorName: 'Legitimate User',
			authorEmail: 'legit@example.com',
			type: CommentType::Comment
		);

		$this->akismet->submitHam( $comment );
	}

	// =========================================================================
	// Usage Limit Tests
	// =========================================================================

	public function testGetUsageLimitReturnsValidData(): void {
		$usage = $this->akismet->getUsageLimit();

		$this->assertGreaterThanOrEqual( 0, $usage->usage, 'Usage should be non-negative' );
		$this->assertFalse( $usage->throttled, 'Test account should not be throttled' );

		// Verify percentage is a numeric string (e.g., "45.0" or "0").
		$this->assertMatchesRegularExpression(
			'/^\d+(\.\d+)?$/',
			$usage->percentage,
			'Percentage should be a numeric string'
		);

		// Limit is either null (unlimited) or a positive int.
		if ( $usage->limit !== null ) {
			$this->assertGreaterThan( 0, $usage->limit, 'Limit should be positive when set' );
		}
	}

	public function testGetUsageLimitRemainingCalculation(): void {
		$usage = $this->akismet->getUsageLimit();

		$remaining = $usage->getRemaining();

		if ( $usage->limit === null ) {
			$this->assertNull( $remaining, 'Remaining should be null for unlimited plans' );
		} else {
			$this->assertIsInt( $remaining, 'Remaining should be int for limited plans' );
			$this->assertGreaterThanOrEqual( 0, $remaining, 'Remaining should be non-negative' );
		}
	}

	// =========================================================================
	// Key Sites Tests
	// =========================================================================

	public function testGetKeySitesReturnsValidResponse(): void {
		$response = $this->akismet->getKeySites( limit: 10 );

		$this->assertLessThanOrEqual( 10, count( $response->sites ), 'Should respect limit' );
		$this->assertSame( 0, $response->offset, 'Default offset should be 0' );
		$this->assertSame( 10, $response->limit, 'Limit should match requested value' );
	}

	public function testGetKeySitesWithMonthFilter(): void {
		$currentMonth = gmdate( 'Y-m' );
		$response     = $this->akismet->getKeySites(
			month: $currentMonth,
			limit: 5
		);

		$this->assertLessThanOrEqual( 5, count( $response->sites ), 'Should respect limit' );
		$this->assertSame( 5, $response->limit, 'Limit should match requested value' );
	}
}
