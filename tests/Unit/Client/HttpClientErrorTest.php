<?php
/**
 * Tests for HTTP client error handling.
 *
 * These tests use mock HTTP responses to verify error handling without
 * requiring a live API connection.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Client;

use Automattic\Akismet\Client\HttpClient;
use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\Exception\ClientErrorException;
use Automattic\Akismet\Exception\NetworkException;
use Automattic\Akismet\Exception\RateLimitException;
use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Validator\InputValidator;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass( HttpClient::class )]
#[UsesClass( Configuration::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ServerException::class )]
#[UsesClass( ClientErrorException::class )]
#[UsesClass( RateLimitException::class )]
#[UsesClass( NetworkException::class )]
final class HttpClientErrorTest extends TestCase {

	private Configuration $config;
	private HttpFactory $httpFactory;

	protected function setUp(): void {
		$this->config      = new Configuration(
			apiKey: 'test-api-key',
			blog: 'https://example.com'
		);
		$this->httpFactory = new HttpFactory();
	}

	// =========================================================================
	// Server Error Tests (5xx)
	// =========================================================================

	#[DataProvider( 'serverErrorStatusCodes' )]
	public function testServerErrorThrowsServerException( int $statusCode ): void {
		$mockClient = $this->createMockClient(
			new Response( $statusCode, [], 'Internal Server Error' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/server error.*' . $statusCode . '/i' );

		$httpClient->post( '/1.1/comment-check', [] );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function serverErrorStatusCodes(): array {
		return [
			'500 Internal Server Error' => [ 500 ],
			'502 Bad Gateway'           => [ 502 ],
			'503 Service Unavailable'   => [ 503 ],
			'504 Gateway Timeout'       => [ 504 ],
		];
	}

	public function testServerErrorIncludesResponseBody(): void {
		$errorBody  = 'Database connection failed';
		$mockClient = $this->createMockClient(
			new Response( 500, [], $errorBody )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected ServerException' );
		} catch ( ServerException $e ) {
			$this->assertStringContainsString( $errorBody, $e->getMessage() );
		}
	}

	// =========================================================================
	// Client Error Tests (4xx)
	// =========================================================================

	#[DataProvider( 'clientErrorStatusCodes' )]
	public function testClientErrorThrowsClientErrorException( int $statusCode ): void {
		$mockClient = $this->createMockClient(
			new Response( $statusCode, [], 'Bad Request' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$this->expectException( ClientErrorException::class );
		$this->expectExceptionMessageMatches( '/client error.*' . $statusCode . '/i' );

		$httpClient->post( '/1.1/comment-check', [] );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function clientErrorStatusCodes(): array {
		return [
			'400 Bad Request'  => [ 400 ],
			'401 Unauthorized' => [ 401 ],
			'403 Forbidden'    => [ 403 ],
			'404 Not Found'    => [ 404 ],
		];
	}

	// =========================================================================
	// Rate Limit Tests (429)
	// =========================================================================

	public function testRateLimitIncludesRetryAfterHeader(): void {
		$retryAfter = 60;
		$mockClient = $this->createMockClient(
			new Response( 429, [ 'Retry-After' => (string) $retryAfter ], 'Too Many Requests' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$this->assertSame( $retryAfter, $e->getRetryAfter() );
		}
	}

	public function testRateLimitParsesHttpDateRetryAfter(): void {
		$futureDate = gmdate( 'D, d M Y H:i:s', time() + 120 ) . ' GMT';
		$mockClient = $this->createMockClient(
			new Response( 429, [ 'Retry-After' => $futureDate ], 'Too Many Requests' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$retryAfter = $e->getRetryAfter();
			$this->assertNotNull( $retryAfter );
			// Should be roughly 120 seconds (allow some tolerance for test execution time).
			$this->assertGreaterThan( 100, $retryAfter );
			$this->assertLessThanOrEqual( 120, $retryAfter );
		}
	}

	public function testRateLimitReturnsNullForUnparseableRetryAfter(): void {
		$mockClient = $this->createMockClient(
			new Response( 429, [ 'Retry-After' => 'not-a-date-or-number' ], 'Too Many Requests' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$this->assertNull( $e->getRetryAfter() );
		}
	}

	public function testRateLimitWithoutRetryAfterHeader(): void {
		$mockClient = $this->createMockClient(
			new Response( 429, [], 'Too Many Requests' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$this->assertNull( $e->getRetryAfter() );
		}
	}

	// =========================================================================
	// Network Error Tests
	// =========================================================================

	public function testNetworkErrorThrowsNetworkException(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class() extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$this->expectException( NetworkException::class );

		$httpClient->post( '/1.1/comment-check', [] );
	}

	public function testNetworkExceptionIncludesEndpoint(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class( 'Connection refused' ) extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertStringContainsString( '/1.1/comment-check', $e->getMessage() );
			$this->assertStringContainsString( 'Connection refused', $e->getMessage() );
		}
	}

	public function testNetworkExceptionPreservesPreviousException(): void {
		$originalException = new class( 'DNS resolution failed' ) extends \Exception implements ClientExceptionInterface {
		};

		$mockClient = $this->createMockClientThatThrows( $originalException );

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertSame( $originalException, $e->getPrevious() );
		}
	}

	// =========================================================================
	// API Key Redaction Tests
	// =========================================================================

	public function testNetworkExceptionRedactsApiKeyFromMessage(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class( 'Could not resolve host: rest.akismet.com?api_key=secret123&other=val' ) extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->get( '/1.2/usage-limit', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertStringContainsString( 'api_key=***', $e->getMessage() );
			$this->assertStringNotContainsString( 'secret123', $e->getMessage() );
			$this->assertStringNotContainsString( 'test-api-key', $e->getMessage() );
		}
	}

	public function testNetworkExceptionRedactsVerifyKeyParam(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class( 'Error sending key=my-secret&blog=https://example.com' ) extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/verify-key', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertStringContainsString( 'key=***', $e->getMessage() );
			$this->assertStringNotContainsString( 'my-secret', $e->getMessage() );
		}
	}

	public function testNetworkExceptionRedactsApiKeyFromPostError(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class( 'Error with api_key=my-secret-key in body' ) extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = $this->createHttpClient( $mockClient );

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertStringContainsString( 'api_key=***', $e->getMessage() );
			$this->assertStringNotContainsString( 'my-secret-key', $e->getMessage() );
		}
	}

	// =========================================================================
	// Success Response Tests
	// =========================================================================

	public function testSuccessfulResponseReturnsResponse(): void {
		$mockClient = $this->createMockClient(
			new Response( 200, [], 'true' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$response = $httpClient->post( '/1.1/comment-check', [] );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertSame( 'true', (string) $response->getBody() );
	}

	public function testRequestIncludesApiKeyAndBlog(): void {
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], 'true' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$httpClient->post( '/1.1/comment-check', [ 'user_ip' => '127.0.0.1' ] );

		$this->assertNotNull( $capturedRequest );
		$body = (string) $capturedRequest->getBody();
		$this->assertStringContainsString( 'api_key=test-api-key', $body );
		$this->assertStringContainsString( 'blog=https%3A%2F%2Fexample.com', $body );
	}

	public function testTestModeAddsIsTestParameter(): void {
		$config          = new Configuration(
			apiKey: 'test-api-key',
			blog: 'https://example.com',
			isTest: true
		);
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], 'true' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient, $config );

		$httpClient->post( '/1.1/comment-check', [] );

		$this->assertNotNull( $capturedRequest );
		$body = (string) $capturedRequest->getBody();
		$this->assertStringContainsString( 'is_test=1', $body );
	}

	// =========================================================================
	// GET Request Tests
	// =========================================================================

	public function testGetRequestIncludesApiKey(): void {
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], '{}' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$httpClient->get( '/1.2/usage-limit', [] );

		$this->assertNotNull( $capturedRequest );
		$uri = (string) $capturedRequest->getUri();
		$this->assertStringContainsString( 'api_key=test-api-key', $uri );
	}

	public function testGetRequestServerError(): void {
		$mockClient = $this->createMockClient(
			new Response( 503, [], 'Service Unavailable' )
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$this->expectException( ServerException::class );

		$httpClient->get( '/1.2/usage-limit', [] );
	}

	// =========================================================================
	// User-Agent Header Tests
	// =========================================================================

	public function testPostRequestSendsDefaultUserAgent(): void {
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], 'true' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$httpClient->post( '/1.1/comment-check', [] );

		$this->assertNotNull( $capturedRequest );
		$this->assertMatchesRegularExpression(
			'/^Automattic-Akismet-SDK\/.+$/',
			$capturedRequest->getHeaderLine( 'User-Agent' )
		);
	}

	public function testGetRequestSendsDefaultUserAgent(): void {
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], '{}' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient );

		$httpClient->get( '/1.2/usage-limit', [] );

		$this->assertNotNull( $capturedRequest );
		$this->assertMatchesRegularExpression(
			'/^Automattic-Akismet-SDK\/.+$/',
			$capturedRequest->getHeaderLine( 'User-Agent' )
		);
	}

	public function testPostRequestSendsCustomUserAgent(): void {
		$config          = new Configuration(
			apiKey: 'test-api-key',
			blog: 'https://example.com',
			applicationUserAgent: 'Akismet-Drupal/1.0 | Drupal/11.0',
		);
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], 'true' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient, $config );

		$httpClient->post( '/1.1/comment-check', [] );

		$this->assertNotNull( $capturedRequest );
		$this->assertMatchesRegularExpression(
			'/^Akismet-Drupal\/1\.0 \| Drupal\/11\.0 \| Automattic-Akismet-SDK\/.+$/',
			$capturedRequest->getHeaderLine( 'User-Agent' )
		);
	}

	public function testGetRequestSendsCustomUserAgent(): void {
		$config          = new Configuration(
			apiKey: 'test-api-key',
			blog: 'https://example.com',
			applicationUserAgent: 'MyApp/2.0',
		);
		$capturedRequest = null;
		$mockClient      = $this->createMockClientCapturingRequest(
			new Response( 200, [], '{}' ),
			$capturedRequest
		);

		$httpClient = $this->createHttpClient( $mockClient, $config );

		$httpClient->get( '/1.2/usage-limit', [] );

		$this->assertNotNull( $capturedRequest );
		$this->assertMatchesRegularExpression(
			'/^MyApp\/2\.0 \| Automattic-Akismet-SDK\/.+$/',
			$capturedRequest->getHeaderLine( 'User-Agent' )
		);
	}

	// =========================================================================
	// Header Normalization Tests
	// =========================================================================

	public function testGetHeadersNormalizesKeysToLowercase(): void {
		$response = new Response(
			200,
			[
				'X-Akismet-Debug-Help' => 'Some debug info',
				'X-AKISMET-PRO-TIP'    => 'discard',
				'Content-Type'         => 'text/plain',
			],
			'true'
		);

		$headers = HttpClient::getHeaders( $response );

		$this->assertArrayHasKey( 'x-akismet-debug-help', $headers );
		$this->assertArrayHasKey( 'x-akismet-pro-tip', $headers );
		$this->assertArrayHasKey( 'content-type', $headers );
		$this->assertSame( 'Some debug info', $headers['x-akismet-debug-help'] );
		$this->assertSame( 'discard', $headers['x-akismet-pro-tip'] );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	private function createHttpClient( ClientInterface $mockClient, ?Configuration $config = null ): HttpClient {
		return new HttpClient(
			$config ?? $this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);
	}

	private function createMockClient( ResponseInterface $response ): ClientInterface {
		$mock = $this->createMock( ClientInterface::class );
		$mock->method( 'sendRequest' )->willReturn( $response );
		return $mock;
	}

	private function createMockClientThatThrows( \Throwable $exception ): ClientInterface {
		$mock = $this->createMock( ClientInterface::class );
		$mock->method( 'sendRequest' )->willThrowException( $exception );
		return $mock;
	}

	private function createMockClientCapturingRequest(
		ResponseInterface $response,
		?RequestInterface &$capturedRequest
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
