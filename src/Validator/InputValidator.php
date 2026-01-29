<?php
/**
 * Input validation utility.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Validator;

use Automattic\Akismet\Exception\ValidationException;

/**
 * Validates URLs, IP addresses, and email addresses.
 */
final class InputValidator {

	/**
	 * Validate a URL.
	 *
	 * @throws ValidationException If the URL is invalid.
	 */
	public static function validateUrl( string $url, string $fieldName = 'url' ): void {
		if ( $url === '' ) {
			throw ValidationException::invalidValue( $fieldName, 'cannot be empty' );
		}

		$parsed = parse_url( $url );

		if ( $parsed === false ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid URL' );
		}

		// Check scheme first - if it's present but invalid, report that specifically
		if ( isset( $parsed['scheme'] ) && ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must use http or https scheme' );
		}

		// Now check for required components (scheme and host)
		if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) || $parsed['host'] === '' ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid URL' );
		}
	}

	/**
	 * Validate an IP address (IPv4 or IPv6).
	 *
	 * @throws ValidationException If the IP address is invalid.
	 */
	public static function validateIp( string $ip, string $fieldName = 'ip' ): void {
		if ( $ip === '' ) {
			throw ValidationException::invalidValue( $fieldName, 'cannot be empty' );
		}

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid IP address' );
		}
	}

	/**
	 * Validate an email address.
	 *
	 * @throws ValidationException If the email address is invalid.
	 */
	public static function validateEmail( string $email, string $fieldName = 'email' ): void {
		if ( $email === '' ) {
			throw ValidationException::invalidValue( $fieldName, 'cannot be empty' );
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid email address' );
		}
	}
}
