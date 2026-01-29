<?php
/**
 * Exception for server errors (5xx).
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;

/**
 * Thrown when Akismet API returns 5xx status.
 */
final class ServerException extends RuntimeException implements AkismetException {

	/**
	 * Create exception from HTTP status code.
	 */
	public static function fromStatusCode( int $statusCode, string $body = '' ): self {
		$message = sprintf(
			'Akismet API returned server error: %d',
			$statusCode
		);

		if ( $body !== '' ) {
			$message .= sprintf( ' - %s', $body );
		}

		return new self( $message );
	}
}
