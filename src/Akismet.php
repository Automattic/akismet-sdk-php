<?php
/**
 * Main Akismet client facade.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet;

use Automattic\Akismet\Client\HttpClient;
use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\ServerException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Main entry point for the Akismet SDK.
 */
final class Akismet implements AkismetInterface {

	private Configuration $config;
	private HttpClient $httpClient;

	/**
	 * Create a new Akismet client.
	 *
	 * @param string                       $apiKey         Your Akismet API key.
	 * @param string                       $blog           Your site's homepage URL.
	 * @param bool                         $isTest         Enable test mode.
	 * @param ClientInterface|null         $httpClient     Custom PSR-18 HTTP client.
	 * @param RequestFactoryInterface|null $requestFactory Custom PSR-17 request factory.
	 * @param StreamFactoryInterface|null  $streamFactory  Custom PSR-17 stream factory.
	 */
	public function __construct(
		string $apiKey,
		string $blog,
		bool $isTest = false,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	) {
		$this->config     = new Configuration( $apiKey, $blog, isTest: $isTest );
		$this->httpClient = new HttpClient(
			$this->config,
			$httpClient,
			$requestFactory,
			$streamFactory,
		);
	}

	/**
	 * Create a client from an existing Configuration object.
	 */
	public static function fromConfiguration(
		Configuration $config,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	): self {
		$instance = new self(
			$config->apiKey,
			$config->blog,
			$config->isTest,
			$httpClient,
			$requestFactory,
			$streamFactory,
		);

		// Overwrite to preserve custom baseUrl from Configuration
		$instance->config     = $config;
		$instance->httpClient = new HttpClient(
			$config,
			$httpClient,
			$requestFactory,
			$streamFactory,
		);

		return $instance;
	}

	/**
	 * @inheritDoc
	 */
	public function verifyKey(): bool {
		$response = $this->httpClient->post(
			'/1.1/verify-key',
			[
				'key' => $this->config->apiKey,
			]
		);

		$body = HttpClient::getBody( $response );

		if ( $body === 'valid' ) {
			return true;
		}

		if ( $body === 'invalid' ) {
			$headers   = array_change_key_case( HttpClient::getHeaders( $response ), CASE_LOWER );
			$debugHelp = $headers['x-akismet-debug-help'] ?? null;
			throw InvalidApiKeyException::verificationFailed( $debugHelp );
		}

		throw ServerException::unexpectedResponse( $body );
	}

	/**
	 * @inheritDoc
	 */
	public function check( Comment $comment ): CheckResult {
		$response = $this->httpClient->post( '/1.1/comment-check', $comment->toArray() );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			$headers   = array_change_key_case( HttpClient::getHeaders( $response ), CASE_LOWER );
			$debugHelp = $headers['x-akismet-debug-help'] ?? null;
			throw InvalidApiKeyException::verificationFailed( $debugHelp );
		}

		return CheckResult::fromResponse(
			$body,
			HttpClient::getHeaders( $response ),
		);
	}

	/**
	 * @inheritDoc
	 */
	public function submitSpam( Comment $comment ): void {
		$response = $this->httpClient->post( '/1.1/submit-spam', $comment->toArray() );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			throw InvalidApiKeyException::verificationFailed();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function submitHam( Comment $comment ): void {
		$response = $this->httpClient->post( '/1.1/submit-ham', $comment->toArray() );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			throw InvalidApiKeyException::verificationFailed();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getUsageLimit(): UsageLimit {
		$response = $this->httpClient->get( '/1.2/usage-limit' );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			throw InvalidApiKeyException::verificationFailed();
		}

		try {
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			throw ServerException::unexpectedResponse( $body );
		}

		/** @var array{limit: int|string, usage: int, percentage: string, throttled: bool} $data */
		return UsageLimit::fromResponse( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getKeySites(
		?string $month = null,
		?string $filter = null,
		int $limit = 500,
		int $offset = 0,
		?string $order = null,
	): KeySitesResponse {
		$params = [
			'limit'  => (string) $limit,
			'offset' => (string) $offset,
		];

		if ( $month !== null ) {
			$params['month'] = $month;
		}

		if ( $filter !== null ) {
			$params['filter'] = $filter;
		}

		if ( $order !== null ) {
			$params['order'] = $order;
		}

		$response = $this->httpClient->get( '/1.2/key-sites', $params );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			throw InvalidApiKeyException::verificationFailed();
		}

		try {
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			throw ServerException::unexpectedResponse( $body );
		}

		/** @var array<string, mixed> $data */
		return KeySitesResponse::fromResponse( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getAccessToken(): string {
		$response = $this->httpClient->post( '/1.1/token', [] );

		$token = HttpClient::getBody( $response );

		if ( $token === '' || $token === 'invalid' ) {
			throw new InvalidApiKeyException( 'Failed to exchange API key for access token.' );
		}

		return $token;
	}

	/**
	 * Get the current configuration.
	 */
	public function getConfiguration(): Configuration {
		return $this->config;
	}
}
