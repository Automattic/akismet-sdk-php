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
final class Configuration {

	public const DEFAULT_BASE_URL = 'https://rest.akismet.com';

	public readonly string $apiKey;
	public readonly string $blog;
	public readonly string $baseUrl;
	public readonly bool $isTest;

	/**
	 * @param string $apiKey  Akismet API key.
	 * @param string $blog    Blog URL.
	 * @param string $baseUrl Base URL for API requests.
	 * @param bool   $isTest  Whether to enable test mode.
	 * @throws ValidationException If any parameter is invalid.
	 */
	public function __construct(
		string $apiKey,
		string $blog,
		string $baseUrl = self::DEFAULT_BASE_URL,
		bool $isTest = false,
	) {
		if ( $apiKey === '' ) {
			throw ValidationException::invalidValue( 'apiKey', 'cannot be empty' );
		}

		InputValidator::validateUrl( $blog, 'blog' );
		InputValidator::validateUrl( $baseUrl, 'baseUrl' );

		$this->apiKey  = $apiKey;
		$this->blog    = rtrim( $blog, '/' );
		$this->baseUrl = rtrim( $baseUrl, '/' );
		$this->isTest  = $isTest;
	}

	/**
	 * Create a new configuration with test mode enabled.
	 *
	 * @param bool $isTest Whether to enable test mode.
	 * @return self New configuration instance.
	 */
	public function withTestMode( bool $isTest = true ): self {
		return new self(
			$this->apiKey,
			$this->blog,
			$this->baseUrl,
			$isTest,
		);
	}
}
