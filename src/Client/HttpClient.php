<?php
/**
 * HTTP client wrapper for Akismet API.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Client;

use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\Exception\ClientErrorException;
use Automattic\Akismet\Exception\NetworkException;
use Automattic\Akismet\Exception\RateLimitException;
use Automattic\Akismet\Exception\ServerException;
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

	/**
	 * @param Configuration                 $config         SDK configuration.
	 * @param ClientInterface|null          $client         PSR-18 HTTP client (auto-discovered if null).
	 * @param RequestFactoryInterface|null  $requestFactory PSR-17 request factory (auto-discovered if null).
	 * @param StreamFactoryInterface|null   $streamFactory  PSR-17 stream factory (auto-discovered if null).
	 */
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
	 * @throws ClientErrorException
	 * @throws NetworkException
	 * @throws RateLimitException
	 * @throws ServerException
	 */
	public function post( string $endpoint, array $data ): ResponseInterface {
		$url = $this->config->baseUrl . $endpoint;

		$data['api_key'] = $this->config->apiKey;
		$data['blog']    = $this->config->blog;

		if ( $this->config->isTest ) {
			$data['is_test'] = '1';
		}

		$body = http_build_query( $data, '', '&' );

		$request = $this->requestFactory
			->createRequest( 'POST', $url )
			->withHeader( 'Content-Type', 'application/x-www-form-urlencoded' )
			->withHeader( 'User-Agent', $this->getUserAgent() )
			->withBody( $this->streamFactory->createStream( $body ) );

		return $this->send( $request );
	}

	/**
	 * Send a GET request with query parameters.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, string> $params  Query parameters.
	 * @return ResponseInterface
	 * @throws ClientErrorException
	 * @throws NetworkException
	 * @throws RateLimitException
	 * @throws ServerException
	 */
	public function get( string $endpoint, array $params = [] ): ResponseInterface {
		$params['api_key'] = $this->config->apiKey;

		$url = $this->config->baseUrl . $endpoint . '?' . http_build_query( $params, '', '&' );

		$request = $this->requestFactory
			->createRequest( 'GET', $url )
			->withHeader( 'User-Agent', $this->getUserAgent() );

		return $this->send( $request );
	}

	/**
	 * Send a request and handle errors.
	 *
	 * @param \Psr\Http\Message\RequestInterface $request The request to send.
	 * @return ResponseInterface The response.
	 * @throws ClientErrorException If the API returns a 4xx error.
	 * @throws NetworkException If a network error occurs.
	 * @throws RateLimitException If rate limited (429).
	 * @throws ServerException If the API returns a 5xx error.
	 */
	private function send( \Psr\Http\Message\RequestInterface $request ): ResponseInterface {
		try {
			$response = $this->client->sendRequest( $request );
		} catch ( ClientExceptionInterface $e ) {
			$endpoint = parse_url( (string) $request->getUri(), PHP_URL_PATH );
			$message  = self::redactApiKey( $e->getMessage() );
			throw NetworkException::fromEndpoint( $endpoint ? $endpoint : 'unknown', $message, $e );
		}

		$statusCode = $response->getStatusCode();

		if ( $statusCode >= 500 ) {
			throw ServerException::fromStatusCode( $statusCode, self::getBody( $response ) );
		}

		if ( $statusCode === 429 ) {
			$retryAfter = $response->hasHeader( 'Retry-After' )
				? self::parseRetryAfter( $response->getHeaderLine( 'Retry-After' ) )
				: null;
			throw RateLimitException::fromResponse( $retryAfter );
		}

		if ( $statusCode >= 400 ) {
			throw ClientErrorException::fromStatusCode( $statusCode, self::getBody( $response ) );
		}

		return $response;
	}

	/**
	 * Parse a Retry-After header value (integer seconds or HTTP-date).
	 *
	 * @param string $value The header value.
	 * @return int|null Seconds to wait, or null if unparseable.
	 */
	private static function parseRetryAfter( string $value ): ?int {
		if ( ctype_digit( $value ) ) {
			return (int) $value;
		}

		$timestamp = strtotime( $value );
		if ( $timestamp !== false ) {
			return max( 0, $timestamp - time() );
		}

		return null;
	}

	/**
	 * Redact API key values from a string to prevent credential leakage in logs.
	 *
	 * Covers both `api_key` (comment-check, submit, GET endpoints) and `key`
	 * (verify-key), case-insensitively, in query strings and form bodies.
	 *
	 * @param string $message The string that may contain API key values.
	 * @return string The string with API key values replaced.
	 */
	private static function redactApiKey( string $message ): string {
		return preg_replace( '/\b(api_key|key)=[^&\s]+/i', '$1=***', $message ) ?? $message;
	}

	/**
	 * Build the User-Agent header value.
	 *
	 * If an application User-Agent is configured, it is prepended to the SDK identifier.
	 *
	 * @return string The User-Agent header value.
	 */
	private function getUserAgent(): string {
		if ( $this->config->applicationUserAgent !== null ) {
			return $this->config->applicationUserAgent . ' | ' . self::USER_AGENT;
		}

		return self::USER_AGENT;
	}

	/**
	 * Get response body as string.
	 *
	 * @param ResponseInterface $response The response.
	 * @return string The response body.
	 */
	public static function getBody( ResponseInterface $response ): string {
		return (string) $response->getBody();
	}

	/**
	 * Get response headers as associative array.
	 *
	 * @param ResponseInterface $response The response.
	 * @return array<string, string> Headers as key-value pairs.
	 */
	public static function getHeaders( ResponseInterface $response ): array {
		$headers = [];
		foreach ( $response->getHeaders() as $name => $values ) {
			$headers[ (string) $name ] = implode( ', ', $values );
		}
		return $headers;
	}
}
