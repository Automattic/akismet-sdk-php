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
final class KeySitesResponse {

	/**
	 * @param array<SiteStats> $sites  List of sites with their statistics.
	 * @param int              $limit  Maximum number of results returned.
	 * @param int              $offset Offset of the results.
	 * @param int              $total  Total number of sites available.
	 */
	public function __construct(
		public readonly array $sites,
		public readonly int $limit,
		public readonly int $offset,
		public readonly int $total,
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
	 * @throws \Automattic\Akismet\Exception\ServerException If required pagination keys are missing or non-numeric.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'limit', 'offset', 'total' ] as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_numeric( $data[ $key ] ) ) {
				throw \Automattic\Akismet\Exception\ServerException::unexpectedResponse(
					sprintf( 'Missing or non-numeric "%s" in key-sites response', $key )
				);
			}
		}

		/** @var numeric $rawLimit */
		$rawLimit = $data['limit'];
		/** @var numeric $rawOffset */
		$rawOffset = $data['offset'];
		/** @var numeric $rawTotal */
		$rawTotal = $data['total'];

		$limit  = (int) $rawLimit;
		$offset = (int) $rawOffset;
		$total  = (int) $rawTotal;

		// Remove pagination keys to get site data
		unset( $data['limit'], $data['offset'], $data['total'] );

		$sites = [];
		foreach ( $data as $siteData ) {
			// Skip non-site entries (the API may include additional metadata keys).
			if ( ! is_array( $siteData ) || ! isset( $siteData['site'] ) ) {
				continue;
			}

			/** @var array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool} $siteData */
			$sites[] = SiteStats::fromResponse( $siteData );
		}

		return new self( $sites, $limit, $offset, $total );
	}
}
