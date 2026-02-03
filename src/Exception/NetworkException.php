<?php
/**
 * Exception for network errors.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when a network error occurs during API communication.
 */
final class NetworkException extends RuntimeException implements AkismetException {

	/**
	 * Create exception for connection failure.
	 *
	 * @param Throwable|null $previous Previous exception.
	 * @return self
	 */
	public static function connectionFailed( ?Throwable $previous = null ): self {
		return new self( 'Failed to connect to Akismet API', 0, $previous );
	}

	/**
	 * Create exception for request timeout.
	 *
	 * @param Throwable|null $previous Previous exception.
	 * @return self
	 */
	public static function timeout( ?Throwable $previous = null ): self {
		return new self( 'Akismet API request timed out', 0, $previous );
	}

	/**
	 * Create exception with endpoint context.
	 *
	 * @param string         $endpoint API endpoint path.
	 * @param string         $message  Error message.
	 * @param Throwable|null $previous Previous exception.
	 * @return self
	 */
	public static function fromEndpoint(
		string $endpoint,
		string $message,
		?Throwable $previous = null
	): self {
		return new self(
			sprintf( 'Akismet API request to %s failed: %s', $endpoint, $message ),
			0,
			$previous
		);
	}
}
