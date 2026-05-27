<?php
/**
 * KeySitesResponse DTO for Akismet API 1.2 key-sites response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents the response from the key-sites endpoint.
 */
final class KeySitesResponse {

	private const MONTH_PATTERN      = '/^\d{4}-(0[1-9]|1[0-2])$/';
	private const MONTH_LIKE_PATTERN = '/^\d{4}-\d{1,2}$/';

	/**
	 * @param array<SiteStats> $sites  List of sites with their statistics.
	 * @param int              $limit  Maximum number of results returned.
	 * @param int              $offset Offset of the results.
	 * @param int              $total  Total number of sites available.
	 * @param string|null      $month  Month bucket returned by the API, if present.
	 */
	public function __construct(
		public readonly array $sites,
		public readonly int $limit,
		public readonly int $offset,
		public readonly int $total,
		public readonly ?string $month = null,
	) {
	}

	/**
	 * Convert to an array matching the API response format.
	 *
	 * @return array{sites: array<int, array{site: string, api_calls: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool}>, limit: int, offset: int, total: int, month?: string}
	 */
	public function toArray(): array {
		$sites = [];
		foreach ( $this->sites as $site ) {
			$sites[] = $site->toArray();
		}

		$data = [
			'sites'  => $sites,
			'limit'  => $this->limit,
			'offset' => $this->offset,
			'total'  => $this->total,
		];

		if ( $this->month !== null ) {
			$data['month'] = $this->month;
		}

		return $data;
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
	 * @throws ServerException If required pagination keys are missing or non-numeric.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'limit', 'offset', 'total' ] as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_numeric( $data[ $key ] ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing or non-numeric "%s" in key-sites response', $key )
				);
			}
		}

		/** @var int|float|numeric-string $rawLimit */
		$rawLimit = $data['limit'];
		/** @var int|float|numeric-string $rawOffset */
		$rawOffset = $data['offset'];
		/** @var int|float|numeric-string $rawTotal */
		$rawTotal = $data['total'];

		$limit  = (int) $rawLimit;
		$offset = (int) $rawOffset;
		$total  = (int) $rawTotal;

		// Remove pagination keys to get site data.
		unset( $data['limit'], $data['offset'], $data['total'] );

		$topLevelMonth        = null;
		$bucketMonth          = null;
		$bucketSites          = null;
		$hasTopLevelSitesKey  = false;
		$hasLegacyFlatEntries = false;
		$legacySites          = [];

		if ( isset( $data['month'] ) && is_string( $data['month'] ) ) {
			if ( ! preg_match( self::MONTH_PATTERN, $data['month'] ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Invalid month "%s" in key-sites response', $data['month'] )
				);
			}

			$topLevelMonth = $data['month'];
			unset( $data['month'] );
		}

		foreach ( $data as $key => $value ) {
			$keyString = (string) $key;

			if ( preg_match( self::MONTH_LIKE_PATTERN, $keyString ) ) {
				if ( ! preg_match( self::MONTH_PATTERN, $keyString ) ) {
					throw ServerException::unexpectedResponse(
						sprintf( 'Invalid month bucket "%s" in key-sites response', $keyString )
					);
				}

				if ( ! is_array( $value ) ) {
					throw ServerException::unexpectedResponse(
						sprintf( 'Month bucket "%s" is not an array in key-sites response', $keyString )
					);
				}

				if ( $bucketMonth !== null ) {
					throw ServerException::unexpectedResponse(
						sprintf(
							'Multiple site buckets found in key-sites response ("%s" and "%s")',
							$bucketMonth,
							$keyString
						)
					);
				}

				if ( $topLevelMonth !== null && $topLevelMonth !== $keyString ) {
					throw ServerException::unexpectedResponse(
						sprintf(
							'Top-level month "%s" does not match bucket "%s" in key-sites response',
							$topLevelMonth,
							$keyString
						)
					);
				}

				$bucketMonth = $keyString;
				$bucketSites = $value;
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( $keyString === 'sites' ) {
				$hasTopLevelSitesKey = true;
				$legacySites         = array_merge( $legacySites, $value );
				continue;
			}

			// Legacy fallback: require enough stat fields to be confident this is a site entry,
			// not a future top-level metadata block. The production backend no longer emits this shape.
			if ( isset( $value['site'], $value['spam'], $value['ham'] ) ) {
				$legacySites[]        = $value;
				$hasLegacyFlatEntries = true;
			}
		}

		if ( $bucketSites !== null && $legacySites !== [] ) {
			throw ServerException::unexpectedResponse(
				'Mixed month bucket and legacy site entries in key-sites response'
			);
		}

		if ( $hasTopLevelSitesKey && $hasLegacyFlatEntries ) {
			throw ServerException::unexpectedResponse(
				'Mixed "sites" key and legacy flat site entries in key-sites response'
			);
		}

		if ( $total > 0 && $bucketSites === null && ! $hasTopLevelSitesKey && $legacySites === [] ) {
			throw ServerException::unexpectedResponse(
				'Missing site bucket in key-sites response'
			);
		}

		$rawSites = $bucketSites ?? $legacySites;

		$sites = [];
		foreach ( $rawSites as $siteData ) {
			if ( ! is_array( $siteData ) ) {
				throw ServerException::unexpectedResponse(
					'Expected site object in key-sites response'
				);
			}

			/** @var array{site: string, api_calls?: int, total?: int, spam: int, ham: int, missed_spam: int, false_positives: int, is_revoked: bool} $siteData */
			$sites[] = SiteStats::fromResponse( $siteData );
		}

		return new self( $sites, $limit, $offset, $total, $bucketMonth ?? $topLevelMonth );
	}
}
