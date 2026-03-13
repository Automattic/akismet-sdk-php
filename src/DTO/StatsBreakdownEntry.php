<?php
/**
 * StatsBreakdownEntry DTO for a single period in Akismet stats breakdown.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents a single time period in the stats breakdown.
 *
 * The API returns breakdown entries keyed by period (e.g., "2026-03" for monthly,
 * "2026" for yearly). Values may be null (inactive periods) or strings, which are
 * normalized to integers.
 */
final class StatsBreakdownEntry {

	/**
	 * @param string $period         The period key (e.g., "2026-03", "2026", "2026-03-15").
	 * @param int    $spam           Spam count for this period.
	 * @param int    $ham            Ham count for this period.
	 * @param int    $missedSpam     Missed spam count for this period.
	 * @param int    $falsePositives False positive count for this period.
	 * @param int    $blogs          Number of active blogs in this period.
	 * @param string $date           Full date string (YYYY-MM-DD) from the API "da" field.
	 */
	public function __construct(
		public readonly string $period,
		public readonly int $spam,
		public readonly int $ham,
		public readonly int $missedSpam,
		public readonly int $falsePositives,
		public readonly int $blogs,
		public readonly string $date,
	) {
	}

	/**
	 * Convert to an array.
	 *
	 * Note: includes a `period` key (the breakdown period string) which is SDK
	 * context not present in the wire format.
	 *
	 * @return array{period: string, spam: int, ham: int, missed_spam: int, false_positives: int, blogs: int, da: string}
	 */
	public function toArray(): array {
		return [
			'period'          => $this->period,
			'spam'            => $this->spam,
			'ham'             => $this->ham,
			'missed_spam'     => $this->missedSpam,
			'false_positives' => $this->falsePositives,
			'blogs'           => $this->blogs,
			'da'              => $this->date,
		];
	}

	/**
	 * Create from a single entry in the API breakdown response.
	 *
	 * @param string               $period The breakdown object key.
	 * @param array<string, mixed> $data   The entry data.
	 * @throws ServerException If required keys are missing or have invalid types.
	 */
	public static function fromResponse( string $period, array $data ): self {
		foreach ( [ 'spam', 'ham', 'missed_spam', 'false_positives', 'blogs', 'da' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in stats breakdown entry', $key )
				);
			}
		}

		if ( ! is_string( $data['da'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected string "da" in stats breakdown entry'
			);
		}

		return new self(
			$period,
			self::castToInt( $data['spam'], 'spam' ),
			self::castToInt( $data['ham'], 'ham' ),
			self::castToInt( $data['missed_spam'], 'missed_spam' ),
			self::castToInt( $data['false_positives'], 'false_positives' ),
			self::castToInt( $data['blogs'], 'blogs' ),
			$data['da'],
		);
	}

	/**
	 * Validate and cast a mixed API value to int.
	 *
	 * Accepts null (returns 0), int, float, or numeric string. Rejects non-numeric
	 * strings and other types to ensure we don't silently swallow malformed API responses.
	 *
	 * @param mixed  $value The raw value from the API response.
	 * @param string $field The field name, used in error messages.
	 * @throws ServerException If the value is not null, int, float, or a numeric string.
	 */
	private static function castToInt( mixed $value, string $field ): int {
		if ( $value === null ) {
			return 0;
		}

		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
			throw ServerException::unexpectedResponse(
				sprintf( 'Expected numeric or null "%s" in stats breakdown entry', $field )
			);
		}

		return (int) $value;
	}
}
