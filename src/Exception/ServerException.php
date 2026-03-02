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
	 *
	 * @param int    $statusCode HTTP status code.
	 * @param string $body       Response body.
	 * @return self
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

	/**
	 * Create exception for an unexpected API response body.
	 *
	 * @param string $body The unexpected response body.
	 * @return self
	 */
	public static function unexpectedResponse( string $body ): self {
		$truncated = strlen( $body ) > 200 ? substr( $body, 0, 200 ) . '...' : $body;

		return new self( sprintf( 'Unexpected Akismet API response: %s', $truncated ) );
	}
}
