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
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\SiteStats;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Enum\KeySitesOrder;
use Automattic\Akismet\Enum\SpamVerdict;
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
#[UsesClass( Comment::class )]
#[UsesClass( UsageLimit::class )]
#[UsesClass( KeySitesResponse::class )]
#[UsesClass( SiteStats::class )]
#[UsesClass( ValidationException::class )]
final class AkismetTest extends TestCase {

	// =========================================================================
	// verifyKey Tests
	// =========================================================================

	public function testVerifyKeyReturnsTrueForValid(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'valid' )
		);

		$this->assertTrue( $akismet->verifyKey() );
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

		$result = $akismet->check( $this->createComment() );

		$this->assertFalse( $result->isSpam() );
	}

	public function testCheckReturnsSpamResult(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'true' )
		);

		$result = $akismet->check( $this->createComment() );

		$this->assertTrue( $result->isSpam() );
	}

	public function testCheckReturnsDiscardForBlatantSpam(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-pro-tip' => 'discard' ], 'true' )
		);

		$result = $akismet->check( $this->createComment() );

		$this->assertTrue( $result->shouldDiscard() );
	}

	public function testCheckThrowsOnUnexpectedBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->check( $this->createComment() );
	}

	public function testCheckThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [ 'X-akismet-debug-help' => 'Invalid key' ], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );
		$this->expectExceptionMessage( 'Invalid key' );

		$akismet->check( $this->createComment() );
	}

	// =========================================================================
	// submitSpam Tests
	// =========================================================================

	public function testSubmitSpamSucceeds(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'Thanks for making the web a better place.' )
		);

		$akismet->submitSpam( $this->createComment() );

		$this->addToAssertionCount( 1 );
	}

	public function testSubmitSpamThrowsOnUnexpectedBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->submitSpam( $this->createComment() );
	}

	public function testSubmitSpamThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );

		$akismet->submitSpam( $this->createComment() );
	}

	// =========================================================================
	// submitHam Tests
	// =========================================================================

	public function testSubmitHamSucceeds(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'Thanks for making the web a better place.' )
		);

		$akismet->submitHam( $this->createComment() );

		$this->addToAssertionCount( 1 );
	}

	public function testSubmitHamThrowsOnUnexpectedBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'something-unexpected' )
		);

		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unexpected Akismet API response' );

		$akismet->submitHam( $this->createComment() );
	}

	public function testSubmitHamThrowsOnInvalidBody(): void {
		$akismet = $this->createAkismetWithResponse(
			new Response( 200, [], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );

		$akismet->submitHam( $this->createComment() );
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
			new Response( 200, [], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );

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
			new Response( 200, [], 'invalid' )
		);

		$this->expectException( InvalidApiKeyException::class );

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
			new Configuration( apiKey: 'test-key', blog: 'https://example.com' ),
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

	public function testGetKeySitesRejectsNonPositiveLimit(): void {
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], '{}' ) );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'limit' );

		$akismet->getKeySites( limit: 0 );
	}

	public function testGetKeySitesRejectsNegativeLimit(): void {
		$akismet = $this->createAkismetWithResponse( new Response( 200, [], '{}' ) );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'limit' );

		$akismet->getKeySites( limit: -1 );
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
			new Configuration( apiKey: 'test-key', blog: 'https://example.com' ),
			httpClient: $mockClient,
		);

		$akismet->getAccessToken();

		$this->assertNotNull( $capturedRequest );
		$this->assertSame( 'POST', $capturedRequest->getMethod() );
		$this->assertStringContainsString( '/1.1/token', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// Constructor Tests
	// =========================================================================

	public function testConstructorPreservesCustomBaseUrl(): void {
		$config = new Configuration(
			apiKey: 'test-key',
			blog: 'https://example.com',
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
			blog: 'https://example.com',
			httpClient: $mockClient,
		);

		$this->assertTrue( $akismet->verifyKey() );
		$this->assertSame( 'test-key', $akismet->getConfiguration()->apiKey );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	private function createComment(): Comment {
		return new Comment(
			userIp: '127.0.0.1',
			userAgent: 'TestAgent/1.0',
		);
	}

	private function createAkismetWithResponse( ResponseInterface $response ): Akismet {
		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn( $response );

		return new Akismet(
			new Configuration( apiKey: 'test-key', blog: 'https://example.com' ),
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
