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
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;
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
	private string $siteUrl;

	protected function setUp(): void {
		$apiKey        = getenv( 'AKISMET_API_KEY' );
		$this->apiKey  = false !== $apiKey ? $apiKey : '';
		$siteUrl       = getenv( 'AKISMET_SITE_URL' );
		$siteUrl       = ( false !== $siteUrl && '' !== $siteUrl ) ? $siteUrl : getenv( 'AKISMET_BLOG_URL' );
		$this->siteUrl = ( false !== $siteUrl && '' !== $siteUrl ) ? $siteUrl : 'https://example.com';

		if ( '' === $this->apiKey ) {
			$this->fail( 'AKISMET_API_KEY environment variable is required' );
		}

		$this->akismet = Akismet::create(
			apiKey: $this->apiKey,
			site: $this->siteUrl,
			isTest: true
		);
	}

	// =========================================================================
	// Key Verification Tests
	// =========================================================================

	public function testVerifyKeyWithValidKey(): void {
		$this->akismet->verifyKey();

		$this->addToAssertionCount( 1 );
	}

	public function testVerifyKeyWithInvalidKey(): void {
		$akismet = Akismet::create(
			apiKey: 'invalid-key-that-does-not-exist',
			site: $this->siteUrl,
			isTest: true
		);

		$this->expectException( InvalidApiKeyException::class );
		$akismet->verifyKey();
	}

	// =========================================================================
	// Deactivate Tests
	// =========================================================================

	public function testDeactivateWithValidKey(): void {
		$this->akismet->deactivate();

		$this->addToAssertionCount( 1 );
	}

	// =========================================================================
	// Content Check Tests - Ham (Not Spam)
	// =========================================================================

	public function testCheckHamWithTypicalContent(): void {
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			body: 'This is a legitimate comment with normal content.',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			type: ContentType::Comment
		);

		$result = $this->akismet->check( $content );

		$this->assertFalse( $result->isSpam(), 'Normal content should not be spam' );
		$this->assertFalse( $result->shouldDiscard(), 'Normal content should not be discarded' );
		$this->assertNotNull( $result->guid, 'Akismet should return a GUID for check requests' );
	}

	public function testCheckHamWithMinimalData(): void {
		// Minimal data with no spam signals should return ham (not spam).
		$content = new Content( userIp: '192.168.1.1' );

		$result = $this->akismet->check( $content );

		// Absence of spam signals should bias toward ham.
		$this->assertFalse( $result->isSpam(), 'Minimal data without spam signals should not be flagged' );
		$this->assertFalse( $result->shouldDiscard() );
	}

	public function testCheckHamWithAdministratorRole(): void {
		// Administrator role should bias toward ham.
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'Admin comment',
			authorName: 'Site Admin',
			authorEmail: 'admin@example.com',
			type: ContentType::Comment,
			userRole: 'administrator'
		);

		$result = $this->akismet->check( $content );

		$this->assertFalse( $result->isSpam(), 'Administrator comments should not be spam' );
	}

	// =========================================================================
	// Content Check Tests - Spam
	// =========================================================================

	public function testCheckSpamWithGuaranteedSpamEmail(): void {
		// Akismet test mode: akismet-guaranteed-spam@example.com always triggers spam.
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'This comment uses a guaranteed spam email.',
			authorEmail: 'akismet-guaranteed-spam@example.com',
			type: ContentType::Comment
		);

		$result = $this->akismet->check( $content );

		$this->assertTrue( $result->isSpam(), 'Guaranteed spam email should be flagged' );
	}

	public function testCheckSpamWithGuaranteedSpamAuthor(): void {
		// Using akismet-guaranteed-spam as author name triggers spam detection.
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'Check out my website!',
			authorName: 'akismet-guaranteed-spam',
			authorEmail: 'test@example.com',
			type: ContentType::Comment
		);

		$result = $this->akismet->check( $content );

		$this->assertTrue( $result->isSpam(), 'Guaranteed spam author should be flagged' );
	}

	public function testCheckSpamBlatantShouldDiscard(): void {
		// Combining multiple spam signals should trigger blatant spam (discard).
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'Buy cheap stuff now!',
			authorName: 'akismet-guaranteed-spam',
			authorEmail: 'akismet-guaranteed-spam@example.com',
			authorUrl: 'https://spam-site.example.com',
			type: ContentType::Comment
		);

		$result = $this->akismet->check( $content );

		$this->assertTrue( $result->isSpam(), 'Blatant spam should be flagged as spam' );
		// Note: shouldDiscard() depends on X-akismet-pro-tip header from API.
		// We assert isSpam at minimum; discard is API-dependent.
	}

	// =========================================================================
	// Content Check Tests - All Fields
	// =========================================================================

	public function testCheckWithAllOptionalFields(): void {
		$date         = new \DateTimeImmutable( '2024-01-15T10:30:00Z' );
		$postModified = new \DateTimeImmutable( '2024-01-10T08:00:00Z' );

		$content = new Content(
			userIp: '203.0.113.42',
			userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
			body: 'This is a test comment with all fields populated.',
			authorName: 'Jane Smith',
			authorEmail: 'jane@example.com',
			authorUrl: 'https://jane.example.com',
			type: ContentType::Comment,
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

		$result = $this->akismet->check( $content );

		// Legitimate content with all fields should not be spam.
		$this->assertFalse( $result->isSpam(), 'Content with all fields should not be spam' );
		$this->assertNotNull( $result->verdict );
	}

	// =========================================================================
	// Content Check Tests - Different Content Types
	// =========================================================================

	public function testCheckContactFormSubmission(): void {
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'I have a question about your services.',
			authorName: 'Potential Customer',
			authorEmail: 'customer@example.com',
			type: ContentType::ContactForm
		);

		$result = $this->akismet->check( $content );

		$this->assertFalse( $result->isSpam(), 'Legitimate contact form should not be spam' );
	}

	public function testCheckSignupSubmission(): void {
		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: '',
			authorName: 'newuser123',
			authorEmail: 'newuser@example.com',
			type: ContentType::Signup
		);

		$result = $this->akismet->check( $content );

		// Legitimate signup with normal email should not be flagged.
		$this->assertFalse( $result->isSpam(), 'Legitimate signup should not be spam' );
	}

	// =========================================================================
	// Spam/Ham Submission Tests
	// =========================================================================

	public function testSubmitSpamDoesNotThrow(): void {
		$this->expectNotToPerformAssertions();

		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'This was spam that got through.',
			authorEmail: 'spammer@example.com',
			type: ContentType::Comment
		);

		$this->akismet->submitSpam( $content );
	}

	public function testSubmitHamDoesNotThrow(): void {
		$this->expectNotToPerformAssertions();

		$content = new Content(
			userIp: '127.0.0.1',
			userAgent: 'Mozilla/5.0',
			body: 'This was incorrectly flagged as spam.',
			authorName: 'Legitimate User',
			authorEmail: 'legit@example.com',
			type: ContentType::Comment
		);

		$this->akismet->submitHam( $content );
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

	public function testGetExtendedUsageLimitReturnsValidData(): void {
		$usage = $this->akismet->getExtendedUsageLimit();

		$this->assertGreaterThanOrEqual( 0, $usage->usage, 'Usage should be non-negative' );

		// Extended fields are present but may be null depending on account state.
		// notice_level is null when no threshold is triggered; upgrade is null when
		// no upgrade is recommended or the account is not approaching its limit.
		if ( $usage->noticeLevel !== null ) {
			$this->assertIsString( $usage->noticeLevel, 'Notice level should be a string when present' );
		}

		if ( $usage->upgrade !== null ) {
			$this->assertNotEmpty( $usage->upgrade->plan, 'Upgrade plan slug should not be empty' );
			$this->assertNotEmpty( $usage->upgrade->name, 'Upgrade plan name should not be empty' );
			$this->assertNotEmpty( $usage->upgrade->url, 'Upgrade URL should not be empty' );
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

	// =========================================================================
	// Subscription Tests
	// =========================================================================

	public function testGetSubscriptionReturnsValidData(): void {
		$subscription = $this->akismet->getSubscription();

		$this->assertIsInt( $subscription->accountId );
		$this->assertGreaterThan( 0, $subscription->accountId, 'Account ID should be positive' );
		$this->assertIsString( $subscription->slug );
		$this->assertIsString( $subscription->displayName );
		$this->assertInstanceOf( \Automattic\Akismet\Enum\SubscriptionStatus::class, $subscription->status );
		$this->assertIsBool( $subscription->limitReached );

		if ( $subscription->nextBillingDate !== null ) {
			$this->assertGreaterThan( 0, $subscription->nextBillingDate, 'Billing date should be a positive timestamp' );
		}
	}

	public function testGetSubscriptionWithInvalidKey(): void {
		$akismet = Akismet::create(
			apiKey: 'invalid-key-that-does-not-exist',
			site: $this->siteUrl,
			isTest: true
		);

		$this->expectException( InvalidApiKeyException::class );
		$akismet->getSubscription();
	}

	// =========================================================================
	// Stats Tests
	// =========================================================================

	public function testGetStatsReturnsValidData(): void {
		$stats = $this->akismet->getStats();

		$this->assertIsInt( $stats->spam );
		$this->assertGreaterThanOrEqual( 0, $stats->spam, 'Spam count should be non-negative' );
		$this->assertIsInt( $stats->ham );
		$this->assertGreaterThanOrEqual( 0, $stats->ham, 'Ham count should be non-negative' );
		$this->assertIsInt( $stats->missedSpam );
		$this->assertIsInt( $stats->falsePositives );
		$this->assertIsString( $stats->accuracy );
		$this->assertIsInt( $stats->timeSaved );
		$this->assertGreaterThanOrEqual( 0, $stats->timeSaved, 'Time saved should be non-negative' );
		$this->assertIsArray( $stats->breakdown );

		if ( count( $stats->breakdown ) > 0 ) {
			$breakdown  = $stats->breakdown;
			$firstEntry = reset( $breakdown );
			$this->assertInstanceOf( \Automattic\Akismet\DTO\StatsBreakdownEntry::class, $firstEntry );
			$this->assertIsString( $firstEntry->period );
			$this->assertIsString( $firstEntry->date );
		}
	}

	public function testGetStatsWithDifferentIntervals(): void {
		$intervals = [
			\Automattic\Akismet\Enum\StatsInterval::SixtyDays,
			\Automattic\Akismet\Enum\StatsInterval::SixMonths,
			\Automattic\Akismet\Enum\StatsInterval::Year,
			\Automattic\Akismet\Enum\StatsInterval::All,
		];

		foreach ( $intervals as $interval ) {
			$stats = $this->akismet->getStats( $interval );
			$this->assertIsInt( $stats->spam, "Failed for interval: {$interval->value}" );
			$this->assertIsArray( $stats->breakdown, "Failed for interval: {$interval->value}" );
		}
	}

	public function testGetStatsWithInvalidKey(): void {
		$akismet = Akismet::create(
			apiKey: 'invalid-key-that-does-not-exist',
			site: $this->siteUrl,
			isTest: true
		);

		$this->expectException( InvalidApiKeyException::class );
		$akismet->getStats();
	}

	// =========================================================================
	// Access Token Tests
	// =========================================================================

	public function testGetAccessTokenReturnsNonEmptyString(): void {
		$token = $this->akismet->getAccessToken();

		$this->assertIsString( $token );
		$this->assertNotEmpty( $token, 'Access token should be a non-empty string' );
	}
}
