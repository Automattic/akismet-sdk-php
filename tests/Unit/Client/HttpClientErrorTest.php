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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

	public function testRateLimitThrowsRateLimitException(): void {
		$mockClient = $this->createMockClient(
			new Response( 429, [], 'Too Many Requests' )
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		$this->expectException( RateLimitException::class );

		$httpClient->post( '/1.1/comment-check', [] );
	}

	public function testRateLimitIncludesRetryAfterHeader(): void {
		$retryAfter = 60;
		$mockClient = $this->createMockClient(
			new Response( 429, [ 'Retry-After' => (string) $retryAfter ], 'Too Many Requests' )
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected RateLimitException' );
		} catch ( RateLimitException $e ) {
			$this->assertSame( $retryAfter, $e->getRetryAfter() );
		}
	}

	public function testRateLimitWithoutRetryAfterHeader(): void {
		$mockClient = $this->createMockClient(
			new Response( 429, [], 'Too Many Requests' )
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		$this->expectException( NetworkException::class );

		$httpClient->post( '/1.1/comment-check', [] );
	}

	public function testNetworkExceptionIncludesEndpoint(): void {
		$mockClient = $this->createMockClientThatThrows(
			new class( 'Connection refused' ) extends \Exception implements ClientExceptionInterface {
			}
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		try {
			$httpClient->post( '/1.1/comment-check', [] );
			$this->fail( 'Expected NetworkException' );
		} catch ( NetworkException $e ) {
			$this->assertSame( $originalException, $e->getPrevious() );
		}
	}

	// =========================================================================
	// Success Response Tests
	// =========================================================================

	public function testSuccessfulResponseReturnsResponse(): void {
		$mockClient = $this->createMockClient(
			new Response( 200, [], 'true' )
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

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

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		$httpClient->get( '/1.2/usage-limit', [] );

		$this->assertNotNull( $capturedRequest );
		$uri = (string) $capturedRequest->getUri();
		$this->assertStringContainsString( 'api_key=test-api-key', $uri );
	}

	public function testGetRequestServerError(): void {
		$mockClient = $this->createMockClient(
			new Response( 503, [], 'Service Unavailable' )
		);

		$httpClient = new HttpClient(
			$this->config,
			$mockClient,
			$this->httpFactory,
			$this->httpFactory
		);

		$this->expectException( ServerException::class );

		$httpClient->get( '/1.2/usage-limit', [] );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

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
