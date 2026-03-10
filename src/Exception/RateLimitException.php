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
 * Use getRetryAfter() to determine how long to wait before retrying.
 * Implement exponential backoff if retryAfter is null.
 *
 * Example:
 * try {
 *     $result = $akismet->check($content);
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
