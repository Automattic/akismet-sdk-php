<?php
/**
 * SiteStats DTO for individual site statistics.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

/**
 * Represents usage statistics for a single site.
 */
final class SiteStats {

	public function __construct(
		public readonly string $site,
		public readonly int $totalCalls,
		public readonly int $spam,
		public readonly int $ham,
		public readonly int $missedSpam,
		public readonly int $falsePositives,
		public readonly bool $isRevoked,
	) {
	}

	/**
	 * Calculate the spam detection accuracy percentage.
	 *
	 * Returns null if there are no calls to calculate from. The result is
	 * clamped to [0.0, 100.0] — values outside this range indicate a data
	 * inconsistency from the API (e.g., errors exceeding total calls).
	 */
	public function getAccuracy(): ?float {
		if ( $this->totalCalls === 0 ) {
			return null;
		}

		$errors   = $this->missedSpam + $this->falsePositives;
		$accuracy = round( ( 1 - ( $errors / $this->totalCalls ) ) * 100, 2 );
		return max( 0.0, min( 100.0, $accuracy ) );
	}

	/**
	 * Create from API response data.
	 *
	 * @param array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool} $data
	 * @throws \Automattic\Akismet\Exception\ServerException If required keys are missing.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'site', 'spam', 'ham', 'missed_spam', 'false_positives', 'is_revoked' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw \Automattic\Akismet\Exception\ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in site stats response', $key )
				);
			}
		}

		if ( ! isset( $data['api_calls'] ) && ! isset( $data['total'] ) ) {
			throw \Automattic\Akismet\Exception\ServerException::unexpectedResponse(
				'Missing required key "api_calls" or "total" in site stats response'
			);
		}

		$totalCalls = $data['api_calls'] ?? $data['total'] ?? 0;

		return new self(
			$data['site'],
			(int) $totalCalls,
			(int) $data['spam'],
			(int) $data['ham'],
			(int) $data['missed_spam'],
			(int) $data['false_positives'],
			(bool) $data['is_revoked'],
		);
	}
}
