<?php
/**
 * SiteStats DTO for individual site statistics.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

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
		public readonly ?string $hash = null,
		public readonly ?bool $eligibleForRevoke = null,
	) {
	}

	/**
	 * Convert to an array matching the API response format.
	 *
	 * @return array{site: string, api_calls: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool, hash?: string|null, eligible_for_revoke?: bool|null}
	 */
	public function toArray(): array {
		$data = [
			'site'            => $this->site,
			'api_calls'       => $this->totalCalls,
			'spam'            => $this->spam,
			'ham'             => $this->ham,
			'missed_spam'     => $this->missedSpam,
			'false_positives' => $this->falsePositives,
			'is_revoked'      => $this->isRevoked,
		];

		if ( $this->hash !== null ) {
			$data['hash'] = $this->hash;
		}
		if ( $this->eligibleForRevoke !== null ) {
			$data['eligible_for_revoke'] = $this->eligibleForRevoke;
		}

		return $data;
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
	 * @param array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool, hash?: mixed, eligible_for_revoke?: mixed} $data
	 * @throws ServerException If required keys are missing.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'site', 'spam', 'ham', 'missed_spam', 'false_positives', 'is_revoked' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in site stats response', $key )
				);
			}
		}

		if ( ! isset( $data['api_calls'] ) && ! isset( $data['total'] ) ) {
			throw ServerException::unexpectedResponse(
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
			self::parseOptionalString( $data['hash'] ?? null ),
			self::parseOptionalBool( $data['eligible_for_revoke'] ?? null ),
		);
	}

	private static function parseOptionalString( mixed $value ): ?string {
		return is_string( $value ) ? $value : null;
	}

	private static function parseOptionalBool( mixed $value ): ?bool {
		if ( $value === null ) {
			return null;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return match ( $value ) {
				1 => true,
				0 => false,
				default => null,
			};
		}
		if ( is_string( $value ) ) {
			return match ( strtolower( $value ) ) {
				'1', 'true' => true,
				'0', 'false' => false,
				default => null,
			};
		}
		return null;
	}
}
