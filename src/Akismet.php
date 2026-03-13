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
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\Stats;
use Automattic\Akismet\DTO\Subscription;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Enum\KeySitesOrder;
use Automattic\Akismet\Enum\StatsInterval;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Exception\ValidationException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
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
	 * @param Configuration                $config         SDK configuration.
	 * @param ClientInterface|null         $httpClient     Custom PSR-18 HTTP client (auto-discovered if null).
	 * @param RequestFactoryInterface|null $requestFactory Custom PSR-17 request factory (auto-discovered if null).
	 * @param StreamFactoryInterface|null  $streamFactory  Custom PSR-17 stream factory (auto-discovered if null).
	 */
	public function __construct(
		Configuration $config,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	) {
		$this->config     = $config;
		$this->httpClient = new HttpClient(
			$config,
			$httpClient,
			$requestFactory,
			$streamFactory,
		);
	}

	/**
	 * Convenience factory for quick initialization from scalar values.
	 *
	 * @param string                       $apiKey               Your Akismet API key.
	 * @param string                       $site                 Your site's homepage URL.
	 * @param bool                         $isTest               Enable test mode.
	 * @param string|null                  $applicationUserAgent Integration identifier prepended to the SDK User-Agent header.
	 * @param ClientInterface|null         $httpClient           Custom PSR-18 HTTP client.
	 * @param RequestFactoryInterface|null $requestFactory       Custom PSR-17 request factory.
	 * @param StreamFactoryInterface|null  $streamFactory        Custom PSR-17 stream factory.
	 */
	public static function create(
		string $apiKey,
		string $site,
		bool $isTest = false,
		?string $applicationUserAgent = null,
		?ClientInterface $httpClient = null,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null,
	): self {
		$config = new Configuration( $apiKey, $site, isTest: $isTest, applicationUserAgent: $applicationUserAgent );
		return new self( $config, $httpClient, $requestFactory, $streamFactory );
	}

	/**
	 * @inheritDoc
	 */
	public function verifyKey(): void {
		$response = $this->httpClient->post(
			'/1.1/verify-key',
			[
				'key' => $this->config->apiKey,
			]
		);

		$body = HttpClient::getBody( $response );

		if ( $body === 'valid' ) {
			return;
		}

		if ( $body === 'invalid' ) {
			$this->throwInvalidKey( $response );
		}

		throw ServerException::unexpectedResponse( $body );
	}

	/**
	 * @inheritDoc
	 */
	public function check( Content $content ): CheckResult {
		$response = $this->httpClient->post( '/1.1/comment-check', $content->toArray() );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			$this->throwInvalidKey( $response );
		}

		if ( $body !== 'true' && $body !== 'false' ) {
			throw ServerException::unexpectedResponse( $body );
		}

		return CheckResult::fromResponse( $body, HttpClient::getHeaders( $response ) );
	}

	/**
	 * @inheritDoc
	 */
	public function submitSpam( Content $content ): void {
		$this->submitFeedback( '/1.1/submit-spam', $content );
	}

	/**
	 * @inheritDoc
	 */
	public function submitHam( Content $content ): void {
		$this->submitFeedback( '/1.1/submit-ham', $content );
	}

	/**
	 * @inheritDoc
	 */
	public function getUsageLimit(): UsageLimit {
		return $this->fetchUsageLimit();
	}

	/**
	 * @inheritDoc
	 */
	public function getExtendedUsageLimit(): UsageLimit {
		return $this->fetchUsageLimit( extended: true );
	}

	/**
	 * Fetch usage limit data from the API.
	 *
	 * @param bool $extended Whether to request extended fields.
	 */
	private function fetchUsageLimit( bool $extended = false ): UsageLimit {
		$params   = $extended ? [ 'extended' => 'true' ] : [];
		$response = $this->httpClient->get( '/1.2/usage-limit', $params );
		$data     = $this->decodeJsonResponse( $response );

		/** @var array{limit: int|string, usage: int|string, percentage: int|string, throttled: bool, notice_level?: mixed, upgrade?: mixed} $data */
		return UsageLimit::fromResponse( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getSubscription(): Subscription {
		$response = $this->httpClient->post( '/1.1/get-subscription', [] );
		$data     = $this->decodeJsonResponse( $response );

		/** @var array{account_id: mixed, account_type: mixed, account_name: mixed, status: mixed, next_billing_date: mixed, limit_reached: mixed} $data */
		return Subscription::fromResponse( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getStats( StatsInterval $interval = StatsInterval::SixMonths ): Stats {
		$response = $this->httpClient->post(
			'/1.2/get-key-stats',
			[
				// The get-key-stats endpoint requires the 'key' wire parameter (not 'api_key').
				'key'  => $this->config->apiKey,
				'from' => $interval->value,
			]
		);
		$data     = $this->decodeJsonResponse( $response );

		/** @var array<string, mixed> $data */
		return Stats::fromResponse( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function getKeySites(
		?string $month = null,
		?string $filter = null,
		int $limit = 500,
		int $offset = 0,
		?KeySitesOrder $order = null,
	): KeySitesResponse {
		if ( $month !== null && ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			throw ValidationException::invalidValue( 'month', 'must be in YYYY-MM format (01-12)' );
		}

		if ( $limit <= 0 ) {
			throw ValidationException::invalidValue( 'limit', 'must be a positive integer' );
		}

		if ( $offset < 0 ) {
			throw ValidationException::invalidValue( 'offset', 'must be a non-negative integer' );
		}

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
			$params['order'] = $order->value;
		}

		$response = $this->httpClient->get( '/1.2/key-sites', $params );
		$data     = $this->decodeJsonResponse( $response );

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

	/**
	 * Throw an InvalidApiKeyException, including any debug help from the response.
	 *
	 * @throws InvalidApiKeyException Always.
	 */
	private function throwInvalidKey( ResponseInterface $response ): never {
		$headers   = HttpClient::getHeaders( $response );
		$debugHelp = $headers['x-akismet-debug-help'] ?? null;
		throw InvalidApiKeyException::verificationFailed( $debugHelp );
	}

	private const FEEDBACK_SUCCESS_BODY = 'Thanks for making the web a better place.';

	/**
	 * Submit spam or ham feedback to the API.
	 *
	 * @param string  $endpoint API endpoint path.
	 * @param Content $content  The content to submit feedback for.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the response is unexpected.
	 */
	private function submitFeedback( string $endpoint, Content $content ): void {
		$response = $this->httpClient->post( $endpoint, $content->toArray() );
		$body     = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			$this->throwInvalidKey( $response );
		}

		if ( $body !== self::FEEDBACK_SUCCESS_BODY ) {
			throw ServerException::unexpectedResponse( $body );
		}
	}

	/**
	 * Decode a JSON response body into an array.
	 *
	 * @param ResponseInterface $response The HTTP response.
	 * @return array<mixed, mixed> The decoded data.
	 * @throws InvalidApiKeyException If the body is 'invalid'.
	 * @throws ServerException If the body is not valid JSON or not an array.
	 */
	private function decodeJsonResponse( ResponseInterface $response ): array {
		$body = HttpClient::getBody( $response );

		if ( $body === 'invalid' ) {
			$this->throwInvalidKey( $response );
		}

		try {
			$data = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			throw ServerException::unexpectedResponse( $body );
		}

		if ( ! is_array( $data ) ) {
			throw ServerException::unexpectedResponse( $body );
		}

		return $data;
	}
}
