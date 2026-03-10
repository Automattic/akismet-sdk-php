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
	public readonly string $site;
	public readonly string $baseUrl;
	public readonly bool $isTest;
	public readonly ?string $applicationUserAgent;

	/**
	 * @param string      $apiKey               Akismet API key.
	 * @param string      $site                 Site URL.
	 * @param string      $baseUrl              Base URL for API requests.
	 * @param bool        $isTest               Whether to enable test mode.
	 * @param string|null $applicationUserAgent  Integration identifier prepended to the SDK User-Agent header.
	 * @throws ValidationException If any parameter is invalid.
	 */
	public function __construct(
		string $apiKey,
		string $site,
		string $baseUrl = self::DEFAULT_BASE_URL,
		bool $isTest = false,
		?string $applicationUserAgent = null,
	) {
		$apiKey = trim( $apiKey );
		if ( $apiKey === '' ) {
			throw ValidationException::invalidValue( 'apiKey', 'cannot be empty' );
		}

		InputValidator::validateUrl( $site, 'site' );
		InputValidator::validateUrl( $baseUrl, 'baseUrl' );

		$this->apiKey               = $apiKey;
		$this->site                 = rtrim( $site, '/' );
		$this->baseUrl              = rtrim( $baseUrl, '/' );
		$this->isTest               = $isTest;
		$this->applicationUserAgent = $applicationUserAgent;
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
			$this->site,
			$this->baseUrl,
			$isTest,
			$this->applicationUserAgent,
		);
	}
}
