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
	 * Returns null if there are no calls to calculate from.
	 */
	public function getAccuracy(): ?float {
		if ( $this->totalCalls === 0 ) {
			return null;
		}

		$errors = $this->missedSpam + $this->falsePositives;
		return round( ( 1 - ( $errors / $this->totalCalls ) ) * 100, 2 );
	}

	/**
	 * Create from API response data.
	 *
	 * @param array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool} $data
	 */
	public static function fromResponse( array $data ): self {
		// API may return 'api_calls' or 'total' depending on context
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
