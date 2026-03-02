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
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Validator\InputValidator;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
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
final class AkismetTest extends TestCase {

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
			apiKey: 'test-key',
			blog: 'https://example.com',
			httpClient: $mockClient,
		);

		$akismet->getAccessToken();

		$this->assertNotNull( $capturedRequest );
		$this->assertSame( 'POST', $capturedRequest->getMethod() );
		$this->assertStringContainsString( '/1.1/token', (string) $capturedRequest->getUri() );
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	private function createAkismetWithResponse( ResponseInterface $response ): Akismet {
		$mockClient = $this->createMock( ClientInterface::class );
		$mockClient->method( 'sendRequest' )->willReturn( $response );

		return new Akismet(
			apiKey: 'test-key',
			blog: 'https://example.com',
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
