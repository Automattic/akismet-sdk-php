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
 *
 * Recommended handling steps:
 * 1. Catch this exception in your request handling code
 * 2. Check retryAfter value to determine wait time
 * 3. Implement exponential backoff for retries (e.g., 1s, 2s, 4s, 8s)
 * 4. Use request caching to reduce duplicate checks
 * 5. Queue non-urgent checks for later processing
 * 6. Monitor rate limit exceptions to identify usage patterns
 * 7. Consider upgrading plan if limits are consistently exceeded
 *
 * Example:
 * try {
 *     $result = $akismet->check($comment);
 * } catch (RateLimitException $e) {
 *     $wait = $e->getRetryAfter() ?? 60;
 *     // Queue for retry after $wait seconds
 * }
 */
final class RateLimitException extends RuntimeException implements AkismetException {

	private ?int $retryAfter;

	/**
	 * @param string   $message    Exception message.
	 * @param int|null $retryAfter Seconds to wait before retrying.
	 */
	public function __construct( string $message = '', ?int $retryAfter = null ) {
		parent::__construct( $message );
		$this->retryAfter = $retryAfter;
	}

	/**
	 * Create exception for HTTP 429 response.
	 *
	 * @param int|null $retryAfter Seconds to wait before retrying.
	 * @return self
	 */
	public static function fromResponse( ?int $retryAfter = null ): self {
		return new self( 'Akismet API rate limit exceeded', $retryAfter );
	}

	/**
	 * Create exception for throttled account.
	 *
	 * @return self
	 */
	public static function throttled(): self {
		return new self( 'Akismet API requests are being throttled due to exceeding usage limits' );
	}

	/**
	 * Get the number of seconds to wait before retrying.
	 *
	 * @return int|null
	 */
	public function getRetryAfter(): ?int {
		return $this->retryAfter;
	}
}
