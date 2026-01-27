<?php
/**
 * HTTP client wrapper for Akismet API.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Client;

use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\Exception\NetworkException;
use Automattic\Akismet\Exception\RateLimitException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP client wrapper for making Akismet API requests.
 */
final class HttpClient {

	private const USER_AGENT = 'Automattic-Akismet-SDK/1.0';

	private ClientInterface $client;
	private RequestFactoryInterface $requestFactory;
	private StreamFactoryInterface $streamFactory;
	private Configuration $config;

	public function __construct(
		Configuration $config,
		?ClientInterface $client = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	) {
		$this->config         = $config;
		$this->client         = $client ?? Psr18ClientDiscovery::find();
		$this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
		$this->streamFactory  = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
	}

	/**
	 * Send a POST request with form data.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, string> $data    Form data to send.
	 * @return ResponseInterface
	 * @throws NetworkException
	 * @throws RateLimitException
	 */
	public function post( string $endpoint, array $data ): ResponseInterface {
		$url = $this->config->baseUrl . $endpoint;

		// Add API key and blog to all requests
		$data['api_key'] = $this->config->apiKey;
		$data['blog']    = $this->config->blog;

		// Add test flag if enabled
		if ( $this->config->isTest ) {
			$data['is_test'] = '1';
		}

		$body = http_build_query( $data, '', '&' );

		$request = $this->requestFactory
			->createRequest( 'POST', $url )
			->withHeader( 'Content-Type', 'application/x-www-form-urlencoded' )
			->withHeader( 'User-Agent', self::USER_AGENT )
			->withBody( $this->streamFactory->createStream( $body ) );

		return $this->send( $request );
	}

	/**
	 * Send a GET request with query parameters.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, string> $params  Query parameters.
	 * @return ResponseInterface
	 * @throws NetworkException
	 * @throws RateLimitException
	 */
	public function get( string $endpoint, array $params = [] ): ResponseInterface {
		// Add API key to query params
		$params['api_key'] = $this->config->apiKey;

		$url = $this->config->baseUrl . $endpoint . '?' . http_build_query( $params, '', '&' );

		$request = $this->requestFactory
			->createRequest( 'GET', $url )
			->withHeader( 'User-Agent', self::USER_AGENT );

		return $this->send( $request );
	}

	/**
	 * Send a request and handle errors.
	 *
	 * @throws NetworkException
	 * @throws RateLimitException
	 */
	private function send( \Psr\Http\Message\RequestInterface $request ): ResponseInterface {
		try {
			$response = $this->client->sendRequest( $request );
		} catch ( ClientExceptionInterface $e ) {
			throw NetworkException::fromClientException( $e );
		}

		$statusCode = $response->getStatusCode();

		if ( $statusCode === 429 ) {
			$retryAfter = $response->hasHeader( 'Retry-After' )
				? (int) $response->getHeaderLine( 'Retry-After' )
				: null;
			throw RateLimitException::fromResponse( $retryAfter );
		}

		return $response;
	}

	/**
	 * Get response body as string.
	 */
	public static function getBody( ResponseInterface $response ): string {
		return (string) $response->getBody();
	}

	/**
	 * Get response headers as associative array.
	 *
	 * @return array<string, string>
	 */
	public static function getHeaders( ResponseInterface $response ): array {
		$headers = [];
		foreach ( $response->getHeaders() as $name => $values ) {
			$headers[ (string) $name ] = implode( ', ', $values );
		}
		return $headers;
	}
}
