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
	 *
	 * @param string $apiKey The invalid API key.
	 * @return self
	 */
	public static function forKey( string $apiKey ): self {
		$length    = strlen( $apiKey );
		$visible   = min( 2, $length );
		$maskedKey = substr( $apiKey, 0, $visible ) . str_repeat( '*', max( 0, $length - $visible ) );
		return new self( sprintf( 'Invalid Akismet API key: %s', $maskedKey ) );
	}

	/**
	 * Create exception for key verification failure.
	 *
	 * @param string|null $debugHelp Debug help message from API.
	 * @return self
	 */
	public static function verificationFailed( ?string $debugHelp = null ): self {
		$message = 'Akismet API key verification failed';
		if ( $debugHelp !== null ) {
			$message .= ': ' . $debugHelp;
		}
		return new self( $message );
	}
}
