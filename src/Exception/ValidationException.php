<?php
/**
 * Exception for validation errors.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Exception;

use InvalidArgumentException;

/**
 * Thrown when required parameters are missing or invalid.
 */
final class ValidationException extends InvalidArgumentException implements AkismetException {

	/**
	 * @var array<string>
	 */
	private array $missingFields;

	/**
	 * @param string        $message       Exception message.
	 * @param array<string> $missingFields List of missing field names.
	 */
	public function __construct( string $message, array $missingFields = [] ) {
		parent::__construct( $message );
		$this->missingFields = $missingFields;
	}

	/**
	 * Create exception for missing required fields.
	 *
	 * @param array<string> $fields List of missing field names.
	 * @return self
	 */
	public static function missingRequired( array $fields ): self {
		return new self(
			sprintf( 'Missing required fields: %s', implode( ', ', $fields ) ),
			$fields
		);
	}

	/**
	 * Create exception for an invalid field value.
	 *
	 * @param string $field  Field name.
	 * @param string $reason Reason the value is invalid.
	 * @return self
	 */
	public static function invalidValue( string $field, string $reason ): self {
		return new self( sprintf( 'Invalid value for %s: %s', $field, $reason ) );
	}

	/**
	 * Get the list of missing fields.
	 *
	 * @return array<string>
	 */
	public function getMissingFields(): array {
		return $this->missingFields;
	}
}
