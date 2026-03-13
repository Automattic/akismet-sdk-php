<?php
/**
 * Tests for the Akismet client facade.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit;

use Automattic\Akismet\Akismet;
use Automattic\Akismet\Client\HttpClient;
use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\DTO\AlertMetadata;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\SiteStats;
use Automattic\Akismet\DTO\Stats;
use Automattic\Akismet\DTO\StatsBreakdownEntry;
use Automattic\Akismet\DTO\Subscription;
use Automattic\Akismet\DTO\UpgradeRecommendation;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Enum\KeySitesOrder;
use Automattic\Akismet\Enum\SpamVerdict;
use Automattic\Akismet\Enum\StatsInterval;
use Automattic\Akismet\Enum\SubscriptionStatus;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass( Akismet::class )]
#[UsesClass( Configuration::class )]
#[UsesClass( HttpClient::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( InvalidApiKeyException::class )]
#[UsesClass( ServerException::class )]
#[UsesClass( AlertMetadata::class )]
#[UsesClass( CheckResult::class )]
#[UsesClass( KeySitesOrder::class )]
#[UsesClass( SpamVerdict::class )]
#[UsesClass( Content::class )]
#[UsesClass( Subscription::class )]
#[UsesClass( SubscriptionStatus::class )]
#[UsesClass( UpgradeRecommendation::class )]
#[UsesClass( UsageLimit::class )]
#[UsesClass( KeySitesResponse::class )]
#[UsesClass( SiteStats::class )]
#[UsesClass( Stats::class )]
#[UsesClass( StatsBreakdownEntry::class )]
#[UsesClass( StatsInterval::class )]
#[UsesClass( ValidationException::class )]
final class AkismetTest extends TestCase {

	// =========================================================================
	// verifyKey Tests
	// =========================================================================

	public function testVerifyKeyReturnsForValid(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'valid' )
		);

		$akismet->verifyKey();

		$this->addToAssertionCount( 1 );
	}

	public function testVerifyKeyThrowsOnInvalid(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Key not found' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Key not found' );

		$akismet->verifyKey();
	}

	public function testVerifyKeyThrowsOnUnexpectedBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->verifyKey();
	}

	// =========================================================================
	// check Tests
	// =========================================================================

	public function testCheckReturnsHamResult(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'false' )
		);

		$result = $akismet->check( $this->createContent() );

		$this->assertFalse( $result->isSpam() );
	}

	public function testCheckReturnsSpamResult(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'true' )
		);

		$result = $akismet->check( $this->createContent() );

		$this->assertTrue( $result->isSpam() );
	}

	public function testCheckReturnsDiscardForBlatantSpam(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-pro-tip' => 'discard' ], 'true' )
		);

		$result = $akismet->check( $this->createContent() );

		$this->assertTrue( $result->shouldDiscard() );
	}

	public function testCheckThrowsOnUnexpectedBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->check( $this->createContent() );
	}

	public function testCheckThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Invalid key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Invalid key' );

		$akismet->check( $this->createContent() );
	}

	// =========================================================================
	// submitSpam / submitHam Tests
	// =========================================================================

	/**
	 * @return array<string, array{string}>
	 */
	public static function submitMethodProvider(): array {
		return [
			'submitSpam' => [ 'submitSpam' ],
			'submitHam'  => [ 'submitHam' ],
		];
	}

	#[DataProvider( 'submitMethodProvider' )]
	public function testSubmitFeedbackSucceeds( string $method ): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'Thanks for making the web a better place.' )
		);

		$akismet->$method( $this->createContent() );

		$this->addToAssertionCount( 1 );
	}

	#[DataProvider( 'submitMethodProvider' )]
	public function testSubmitFeedbackThrowsOnUnexpectedBody( string $method ): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->$method( $this->createContent() );
	}

	#[DataProvider( 'submitMethodProvider' )]
	public function testSubmitFeedbackThrowsOnInvalidBody( string $method ): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Key revoked' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Key revoked' );

		$akismet->$method( $this->createContent() );
	}

	// =========================================================================
	// getUsageLimit Tests
	// =========================================================================

	public function testGetUsageLimitReturnsDto(): void {
		$json    = json_encode(
			[
				'limit'      => 10000,
				'usage'      => 500,
				'percentage' => '5.0',
				'throttled'  => false,
			]
		);
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], $json )
		);

		$result = $akismet->getUsageLimit();

		$this->assertSame( 10000, $result->limit );
		$this->assertSame( 500, $result->usage );
		$this->assertFalse( $result->throttled );
	}

	public function testGetUsageLimitThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Expired key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Expired key' );

		$akismet->getUsageLimit();
	}

	public function testGetUsageLimitThrowsOnJsonScalar(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'null' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getUsageLimit();
	}

	public function testGetUsageLimitThrowsOnMalformedJson(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'not-json{' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getUsageLimit();
	}

	// =========================================================================
	// getExtendedUsageLimit Tests
	// =========================================================================

	public function testGetExtendedUsageLimitReturnsDto(): void {
		$json    = json_encode(
			[
				'limit'        => 10000,
				'usage'        => 9500,
				'percentage'   => '95.0',
				'throttled'    => false,
				'notice_level' => 'NOTICE_FIRST_MONTH_OVER_LIMIT',
				'upgrade'      => [
					'plan' => 'plus',
					'name' => 'Plus',
					'url'  => 'https://akismet.com/upgrade/plus',
				],
			]
		);
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], $json )
		);

		$result = $akismet->getExtendedUsageLimit();

		$this->assertSame( 'NOTICE_FIRST_MONTH_OVER_LIMIT', $result->noticeLevel );
		$this->assertNotNull( $result->upgrade );
		$this->assertSame( 'plus', $result->upgrade->plan );
		$this->assertSame( 'Plus', $result->upgrade->name );
		$this->assertSame( 'https://akismet.com/upgrade/plus', $result->upgrade->url );
	}

	public function testGetExtendedUsageLimitPassesQueryParam(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'limit'        => 10000,
				'usage'        => 500,
				'percentage'   => '5.0',
				'throttled'    => false,
				'notice_level' => 'NOTICE_NONE',
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getExtendedUsageLimit();

		$this->assertNotNull( $capturedRequest );
		$this->assertStringContainsString( 'extended=true', (string) $capturedRequest->getUri() );
	}

	public function testGetUsageLimitOmitsExtendedQueryParam(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'limit'      => 10000,
				'usage'      => 500,
				'percentage' => '5.0',
				'throttled'  => false,
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getUsageLimit();

		$this->assertNotNull( $capturedRequest );
		$this->assertStringNotContainsString( 'extended', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// getKeySites Tests
	// =========================================================================

	public function testGetKeySitesReturnsDto(): void {
		$json    = json_encode(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 1,
				'site1'  => [
					'site'            => 'https://example.com',
					'api_calls'       => 100,
					'spam'            => 10,
					'ham'             => 90,
					'missed_spam'     => 1,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
			]
		);
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], $json )
		);

		$result = $akismet->getKeySites();

		$this->assertSame( 1, $result->total );
		$this->assertCount( 1, $result->sites );
	}

	public function testGetKeySitesThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Suspended key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Suspended key' );

		$akismet->getKeySites();
	}

	public function testGetKeySitesThrowsOnJsonScalar(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], '42' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getKeySites();
	}

	public function testGetKeySitesThrowsOnMalformedJson(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], '{broken' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getKeySites();
	}

	public function testGetKeySitesPassesOrderParam(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 0,
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getKeySites( order: KeySitesOrder::Spam );

		$this->assertNotNull( $capturedRequest );
		$this->assertStringContainsString( 'order=spam', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// getKeySites Validation Tests
	// =========================================================================

	#[DataProvider( 'invalidMonthProvider' )]
	public function testGetKeySitesRejectsInvalidMonth( string $month ): void {
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], '{}' ) );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'month' );

		$akismet->getKeySites( month: $month );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidMonthProvider(): array {
		return [
			'no dash'         => [ '202401' ],
			'wrong separator' => [ '2024/01' ],
			'just a year'     => [ '2024' ],
			'day included'    => [ '2024-01-15' ],
			'letters'         => [ 'January' ],
			'month 00'        => [ '2024-00' ],
			'month 13'        => [ '2024-13' ],
			'month 99'        => [ '2024-99' ],
		];
	}

	public function testGetKeySitesAcceptsValidMonth(): void {
		$json    = json_encode(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 0,
			]
		);
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], $json ) );

		$result = $akismet->getKeySites( month: '2024-01' );

		$this->assertSame( 0, $result->total );
	}

	#[DataProvider( 'invalidLimitProvider' )]
	public function testGetKeySitesRejectsInvalidLimit( int $limit ): void {
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], '{}' ) );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'limit' );

		$akismet->getKeySites( limit: $limit );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function invalidLimitProvider(): array {
		return [
			'zero'     => [ 0 ],
			'negative' => [ -1 ],
		];
	}

	public function testGetKeySitesRejectsNegativeOffset(): void {
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], '{}' ) );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'offset' );

		$akismet->getKeySites( offset: -1 );
	}

	public function testGetKeySitesAcceptsZeroOffset(): void {
		$json    = json_encode(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 0,
			]
		);
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], $json ) );

		$result = $akismet->getKeySites( offset: 0 );

		$this->assertSame( 0, $result->total );
	}

	// =========================================================================
	// getAccessToken Tests
	// =========================================================================

	public function testGetAccessTokenReturnsToken(): void {
		$expectedToken = 'abc123-opaque-token-value';
		$akismet       = $this->createAkismetWithResponse(
			new Response( 200, [], $expectedToken )
		);

		$token = $akismet->getAccessToken();

		$this->assertSame( $expectedToken, $token );
	}

	public function testGetAccessTokenThrowsOnEmptyResponse(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], '' )
		);

		$this->expectException( InvalidApiKeyException::class );

		$akismet->getAccessToken();
	}

	public function testGetAccessTokenThrowsOnInvalidResponse(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );

		$akismet->getAccessToken();
	}

	public function testGetAccessTokenPostsToTokenEndpoint(): void {
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], 'valid-token' ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getAccessToken();

		$this->assertNotNull( $capturedRequest );
		$this->assertSame( 'POST', $capturedRequest->getMethod() );
		$this->assertStringContainsString( '/1.1/token', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// getSubscription Tests
	// =========================================================================

	public function testGetSubscriptionReturnsDto(): void {
		$json    = json_encode(
			[
				'account_id'        => 123,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			]
		);
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], $json )
		);

		$result = $akismet->getSubscription();

		$this->assertSame( 123, $result->accountId );
		$this->assertSame( 'pro', $result->slug );
		$this->assertSame( 'Professional', $result->displayName );
		$this->assertSame( SubscriptionStatus::Active, $result->status );
		$this->assertSame( 1741824000, $result->nextBillingDate );
		$this->assertFalse( $result->limitReached );
		$this->assertTrue( $result->isActive() );
	}

	public function testGetSubscriptionThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Bad key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Bad key' );

		$akismet->getSubscription();
	}

	public function testGetSubscriptionThrowsOnMalformedJson(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'not-json{' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getSubscription();
	}

	public function testGetSubscriptionThrowsOnJsonScalar(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'null' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getSubscription();
	}

	public function testGetSubscriptionPostsToCorrectEndpoint(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'account_id'        => 1,
				'account_type'      => 'free-api-key',
				'account_name'      => 'Free',
				'status'            => 'active',
				'next_billing_date' => false,
				'limit_reached'     => false,
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getSubscription();

		$this->assertNotNull( $capturedRequest );
		$this->assertSame( 'POST', $capturedRequest->getMethod() );
		$this->assertStringContainsString( '/1.1/get-subscription', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// getStats Tests
	// =========================================================================

	public function testGetStatsReturnsDto(): void {
		$json    = json_encode(
			[
				'spam'            => 149,
				'ham'             => 242,
				'missed_spam'     => 10,
				'false_positives' => 10,
				'accuracy'        => '94.88',
				'time_saved'      => 7455,
				'breakdown'       => [
					'2026-01' => [
						'spam'            => '5',
						'ham'             => '5',
						'missed_spam'     => '0',
						'false_positives' => '0',
						'blogs'           => '1',
						'da'              => '2026-01-01',
					],
				],
			]
		);
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], $json )
		);

		$result = $akismet->getStats();

		$this->assertSame( 149, $result->spam );
		$this->assertSame( 242, $result->ham );
		$this->assertSame( '94.88', $result->accuracy );
		$this->assertSame( 7455, $result->timeSaved );
		$this->assertCount( 1, $result->breakdown );
		$this->assertSame( 5, $result->breakdown['2026-01']->spam );
	}

	public function testGetStatsThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Bad key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Bad key' );

		$akismet->getStats();
	}

	public function testGetStatsThrowsOnMalformedJson(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'not-json{' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getStats();
	}

	public function testGetStatsThrowsOnJsonScalar(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'null' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->getStats();
	}

	public function testGetStatsPostsToCorrectEndpoint(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => 0,
				'time_saved'      => 0,
				'breakdown'       => [],
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getStats();

		$this->assertNotNull( $capturedRequest );
		$this->assertSame( 'POST', $capturedRequest->getMethod() );
		$this->assertStringContainsString( '/1.2/get-key-stats', (string) $capturedRequest->getUri() );
	}

	public function testGetStatsPassesIntervalParameter(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => 0,
				'time_saved'      => 0,
				'breakdown'       => [],
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getStats( StatsInterval::All );

		$this->assertNotNull( $capturedRequest );
		$body = (string) $capturedRequest->getBody();
		$this->assertStringContainsString( 'from=all', $body );
	}

	public function testGetStatsDefaultsToSixMonths(): void {
		$capturedRequest = null;
		$json            = json_encode(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => 0,
				'time_saved'      => 0,
				'breakdown'       => [],
			]
		);
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], $json ),
			$capturedRequest
		);

		$akismet = new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getStats();

		$this->assertNotNull( $capturedRequest );
		$body = (string) $capturedRequest->getBody();
		$this->assertStringContainsString( 'from=6-months', $body );
	}

	// =========================================================================
	// Constructor Tests
	// =========================================================================

	public function testConstructorPreservesCustomBaseUrl(): void {
		$config = new Configuration(
			apiKey: 'test-key',
			site: 'https://example.com',
			baseUrl: 'https://custom-api.example.com',
		);

		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn(
			new Response( 200, [], 'valid' )
		);

		$akismet = new Akismet( $config, httpClient: $mockClient );

		$this->assertSame( $config, $akismet->getConfiguration() );
	}

	public function testCreateConvenienceFactory(): void {
		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn(
			new Response( 200, [], 'valid' )
		);

		$akismet = Akismet::create(
			apiKey: 'test-key',
			site: 'https://example.com',
			httpClient: $mockClient,
		);

		$akismet->verifyKey();
		$this->assertSame( 'test-key', $akismet->getConfiguration()->apiKey );
	}

	public function testCreateConvenienceFactoryPropagatesIsTestAndUserAgent(): void {
		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn(
			new Response( 200, [], 'valid' )
		);

		$akismet = Akismet::create(
			apiKey: 'test-key',
			site: 'https://example.com',
			isTest: true,
			applicationUserAgent: 'MyApp/1.0',
			httpClient: $mockClient,
		);

		$config = $akismet->getConfiguration();
		$this->assertTrue( $config->isTest );
		$this->assertSame( 'MyApp/1.0', $config->applicationUserAgent );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	private function createContent(): Content {
		return new Content(
			userIp: '127.0.0.1',
			userAgent: 'TestAgent/1.0',
		);
	}

	private function createAkismetWithResponse( ResponseInterface $response ): Akismet {
		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn( $response );

		return new Akismet(
			new Configuration( apiKey: 'test-key', site: 'https://example.com' ),
			httpClient: $mockClient,
		);
	}

	private function createMockClientCapturingRequest(
		ResponseInterface $response,
		?RequestInterface &$capturedRequest,
	): ClientInterface {
		$mock = $this->createMock( ClientInterface::class );
		$mock->method( 'sendRequest' )
			->willReturnCallback(
				function ( RequestInterface $request ) use ( $response, &$capturedRequest ) {
					$capturedRequest = $request;
					return $response;
				}
			);
		return $mock;
	}
}
