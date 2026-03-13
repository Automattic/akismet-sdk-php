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
	 * Convert to an array matching the API response format.
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
	 * @throws ServerException If required keys are missing.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'spam', 'ham', 'missed_spam', 'false_positives', 'accuracy', 'time_saved' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in get-key-stats response', $key )
				);
			}
		}

		$breakdown    = [];
		$rawBreakdown = $data['breakdown'] ?? [];

		if ( is_array( $rawBreakdown ) ) {
			foreach ( $rawBreakdown as $period => $entry ) {
				if ( is_array( $entry ) ) {
					/** @var array<string, mixed> $entry */
					$breakdown[ (string) $period ] = StatsBreakdownEntry::fromResponse( (string) $period, $entry );
				}
			}
		}

		$spam           = $data['spam'];
		$ham            = $data['ham'];
		$missedSpam     = $data['missed_spam'];
		$falsePositives = $data['false_positives'];
		$accuracy       = $data['accuracy'];
		$timeSaved      = $data['time_saved'];

		if ( ! is_numeric( $spam ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "spam" in get-key-stats response' );
		}

		if ( ! is_numeric( $ham ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "ham" in get-key-stats response' );
		}

		if ( ! is_numeric( $missedSpam ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "missed_spam" in get-key-stats response' );
		}

		if ( ! is_numeric( $falsePositives ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "false_positives" in get-key-stats response' );
		}

		if ( ! is_numeric( $accuracy ) && ! is_string( $accuracy ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric or string "accuracy" in get-key-stats response' );
		}

		if ( ! is_numeric( $timeSaved ) ) {
			throw ServerException::unexpectedResponse( 'Expected numeric "time_saved" in get-key-stats response' );
		}

		return new self(
			(int) $spam,
			(int) $ham,
			(int) $missedSpam,
			(int) $falsePositives,
			(string) $accuracy,
			(int) $timeSaved,
			$breakdown,
		);
	}
}
