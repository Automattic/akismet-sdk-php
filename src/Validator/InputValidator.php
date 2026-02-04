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
	 * @param string $url       The URL to validate.
	 * @param string $fieldName The field name for error messages.
	 * @return void
	 * @throws ValidationException If the URL is invalid.
	 */
	public static function validateUrl( string $url, string $fieldName = 'url' ): void {
		$parsed = parse_url( $url );

		if ( $parsed === false || ! isset( $parsed['scheme'], $parsed['host'] ) || $parsed['host'] === '' ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid URL' );
		}

		if ( ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must use http or https scheme' );
		}
	}

	/**
	 * Validate an IP address (IPv4 or IPv6).
	 *
	 * @param string $ip        The IP address to validate.
	 * @param string $fieldName The field name for error messages.
	 * @return void
	 * @throws ValidationException If the IP address is invalid.
	 */
	public static function validateIp( string $ip, string $fieldName = 'ip' ): void {
		if ( $ip === '' || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid IP address' );
		}
	}

	/**
	 * Validate an email address.
	 *
	 * @param string $email     The email address to validate.
	 * @param string $fieldName The field name for error messages.
	 * @return void
	 * @throws ValidationException If the email address is invalid.
	 */
	public static function validateEmail( string $email, string $fieldName = 'email' ): void {
		if ( $email === '' || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw ValidationException::invalidValue( $fieldName, 'must be a valid email address' );
		}
	}
}
