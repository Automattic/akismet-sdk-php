<?php
/**
 * Configuration class for Akismet SDK.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Config;

use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;

/**
 * Immutable configuration for the Akismet client.
 */
final readonly class Configuration {

	public const DEFAULT_BASE_URL = 'https://rest.akismet.com';
	public const DEFAULT_TIMEOUT  = 10;

	public string $apiKey;
	public string $blog;
	public string $baseUrl;
	public int $timeout;
	public bool $isTest;

	public function __construct(
		string $apiKey,
		string $blog,
		string $baseUrl = self::DEFAULT_BASE_URL,
		int $timeout = self::DEFAULT_TIMEOUT,
		bool $isTest = false,
	) {
		if ( $apiKey === '' ) {
			throw ValidationException::invalidValue( 'apiKey', 'cannot be empty' );
		}

		InputValidator::validateUrl( $blog, 'blog' );
		InputValidator::validateUrl( $baseUrl, 'baseUrl' );

		if ( $timeout < 1 ) {
			throw ValidationException::invalidValue( 'timeout', 'must be at least 1 second' );
		}

		$this->apiKey  = $apiKey;
		$this->blog    = rtrim( $blog, '/' );
		$this->baseUrl = rtrim( $baseUrl, '/' );
		$this->timeout = $timeout;
		$this->isTest  = $isTest;
	}

	/**
	 * Create a new configuration with test mode enabled.
	 */
	public function withTestMode( bool $isTest = true ): self {
		return new self(
			$this->apiKey,
			$this->blog,
			$this->baseUrl,
			$this->timeout,
			$isTest,
		);
	}

	/**
	 * Create a new configuration with a different timeout.
	 */
	public function withTimeout( int $timeout ): self {
		return new self(
			$this->apiKey,
			$this->blog,
			$this->baseUrl,
			$timeout,
			$this->isTest,
		);
	}
}
