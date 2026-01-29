<?php
/**
 * Exception for client errors (4xx).
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;

/**
 * Thrown when Akismet API returns 4xx status (excluding 429).
 */
final class BadRequestException extends RuntimeException implements AkismetException {

	/**
	 * Create exception from HTTP status code.
	 */
	public static function fromStatusCode( int $statusCode, string $body = '' ): self {
		$message = sprintf(
			'Akismet API returned client error: %d',
			$statusCode
		);

		if ( $body !== '' ) {
			$message .= sprintf( ' - %s', $body );
		}

		return new self( $message );
	}
}
