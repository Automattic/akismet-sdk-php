<?php
/**
 * Exception for rate limit errors.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;

/**
 * Thrown when API requests are being rate limited or throttled.
 */
final class RateLimitException extends RuntimeException implements AkismetException {

	private ?int $retryAfter;

	public function __construct( string $message = '', ?int $retryAfter = null ) {
		parent::__construct( $message );
		$this->retryAfter = $retryAfter;
	}

	/**
	 * Create exception for HTTP 429 response.
	 */
	public static function fromResponse( ?int $retryAfter = null ): self {
		return new self( 'Akismet API rate limit exceeded', $retryAfter );
	}

	/**
	 * Create exception for throttled account.
	 */
	public static function throttled(): self {
		return new self( 'Akismet API requests are being throttled due to exceeding usage limits' );
	}

	/**
	 * Get the number of seconds to wait before retrying.
	 */
	public function getRetryAfter(): ?int {
		return $this->retryAfter;
	}
}
