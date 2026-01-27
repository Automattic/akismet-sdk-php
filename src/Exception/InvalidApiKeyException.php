<?php
/**
 * Exception for invalid API key errors.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use RuntimeException;

/**
 * Thrown when the API key is invalid or verification fails.
 */
final class InvalidApiKeyException extends RuntimeException implements AkismetException {

	/**
	 * Create exception for an invalid API key.
	 */
	public static function forKey( string $apiKey ): self {
		$maskedKey = substr( $apiKey, 0, 4 ) . str_repeat( '*', max( 0, strlen( $apiKey ) - 4 ) );
		return new self( sprintf( 'Invalid Akismet API key: %s', $maskedKey ) );
	}

	/**
	 * Create exception for key verification failure.
	 */
	public static function verificationFailed( ?string $debugHelp = null ): self {
		$message = 'Akismet API key verification failed';
		if ( $debugHelp !== null ) {
			$message .= ': ' . $debugHelp;
		}
		return new self( $message );
	}
}
