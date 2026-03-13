<?php
/**
 * Stats DTO for Akismet get-key-stats response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents aggregate statistics from the Akismet get-key-stats endpoint.
 */
final class Stats {

	/**
	 * @param int                                $spam           Total spam count.
	 * @param int                                $ham            Total ham count.
	 * @param int                                $missedSpam     Total missed spam count.
	 * @param int                                $falsePositives Total false positive count.
	 * @param string                             $accuracy       Accuracy percentage (e.g., "94.88").
	 * @param int                                $timeSaved      Seconds of moderation time saved.
	 * @param array<string, StatsBreakdownEntry> $breakdown      Per-period breakdown keyed by period string.
	 */
	public function __construct(
		public readonly int $spam,
		public readonly int $ham,
		public readonly int $missedSpam,
		public readonly int $falsePositives,
		public readonly string $accuracy,
		public readonly int $timeSaved,
		public readonly array $breakdown,
	) {
	}

	/**
	 * Convert to an array.
	 *
	 * Note: breakdown entries include a `period` key (SDK context) not present in
	 * the wire format.
	 *
	 * @return array{spam: int, ham: int, missed_spam: int, false_positives: int, accuracy: string, time_saved: int, breakdown: array<string, array<string, mixed>>}
	 */
	public function toArray(): array {
		$breakdown = [];
		foreach ( $this->breakdown as $period => $entry ) {
			$breakdown[ $period ] = $entry->toArray();
		}

		return [
			'spam'            => $this->spam,
			'ham'             => $this->ham,
			'missed_spam'     => $this->missedSpam,
			'false_positives' => $this->falsePositives,
			'accuracy'        => $this->accuracy,
			'time_saved'      => $this->timeSaved,
			'breakdown'       => $breakdown,
		];
	}

	/**
	 * Create from API JSON response.
	 *
	 * @param array<string, mixed> $data
	 * @throws ServerException If required keys are missing or have invalid types.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'spam', 'ham', 'missed_spam', 'false_positives', 'accuracy', 'time_saved' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in get-key-stats response', $key )
				);
			}
		}

		$breakdown = [];

		if ( array_key_exists( 'breakdown', $data ) ) {
			$rawBreakdown = $data['breakdown'];

			if ( ! is_array( $rawBreakdown ) ) {
				throw ServerException::unexpectedResponse(
					'Expected array "breakdown" in get-key-stats response'
				);
			}

			foreach ( $rawBreakdown as $period => $entry ) {
				if ( ! is_array( $entry ) ) {
					throw ServerException::unexpectedResponse(
						sprintf( 'Expected array for breakdown period "%s" in get-key-stats response', (string) $period )
					);
				}
				/** @var array<string, mixed> $entry */
				$breakdown[ (string) $period ] = StatsBreakdownEntry::fromResponse( (string) $period, $entry );
			}
		}

		$accuracy = $data['accuracy'];

		if ( ! is_numeric( $accuracy ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "accuracy" in get-key-stats response' );
		}

		return new self(
			self::castToInt( $data['spam'], 'spam' ),
			self::castToInt( $data['ham'], 'ham' ),
			self::castToInt( $data['missed_spam'], 'missed_spam' ),
			self::castToInt( $data['false_positives'], 'false_positives' ),
			(string) $accuracy,
			self::castToInt( $data['time_saved'], 'time_saved' ),
			$breakdown,
		);
	}

	/**
	 * Validate and cast a mixed API value to int.
	 *
	 * @param mixed  $value The raw value from the API response.
	 * @param string $field The field name, used in error messages.
	 * @throws ServerException If the value is not numeric.
	 */
	private static function castToInt( mixed $value, string $field ): int {
		if ( ! is_numeric( $value ) ) {
			throw ServerException::unexpectedResponse(
				sprintf( 'Expected numeric "%s" in get-key-stats response', $field )
			);
		}

		return (int) $value;
	}
}
