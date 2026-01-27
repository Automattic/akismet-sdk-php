<?php
/**
 * KeySitesResponse DTO for Akismet API 1.2 key-sites response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

/**
 * Represents the response from the key-sites endpoint.
 */
final readonly class KeySitesResponse {

	/**
	 * @param array<SiteStats> $sites  List of sites with their statistics.
	 * @param int              $limit  Maximum number of results returned.
	 * @param int              $offset Offset of the results.
	 * @param int              $total  Total number of sites available.
	 */
	public function __construct(
		public array $sites,
		public int $limit,
		public int $offset,
		public int $total,
	) {
	}

	/**
	 * Check if there are more pages of results.
	 */
	public function hasMore(): bool {
		return ( $this->offset + count( $this->sites ) ) < $this->total;
	}

	/**
	 * Get the offset for the next page of results.
	 */
	public function getNextOffset(): int {
		return $this->offset + $this->limit;
	}

	/**
	 * Create from API JSON response.
	 *
	 * @param array<string, mixed> $data Raw API response data.
	 */
	public static function fromResponse( array $data ): self {
		$limit  = (int) ( $data['limit'] ?? 500 );
		$offset = (int) ( $data['offset'] ?? 0 );
		$total  = (int) ( $data['total'] ?? 0 );

		// Remove pagination keys to get site data
		unset( $data['limit'], $data['offset'], $data['total'] );

		$sites = [];
		foreach ( $data as $siteData ) {
			// Each remaining key is site data (keyed by month or identifier)
			if ( is_array( $siteData ) && isset( $siteData['site'] ) ) {
				/** @var array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool} $siteData */
				$sites[] = SiteStats::fromResponse( $siteData );
			}
		}

		return new self( $sites, $limit, $offset, $total );
	}
}
