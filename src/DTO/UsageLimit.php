<?php
/**
 * UsageLimit DTO for Akismet API 1.2 usage-limit response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents API usage statistics and limits.
 *
 * The percentage field indicates how much of the monthly limit has been
 * consumed. When throttled is true, requests may fail or be delayed.
 *
 * @see RateLimitException For handling rate limit errors.
 */
final class UsageLimit {

	/**
	 * @param int|null                  $limit       Monthly API call limit, or null if unlimited.
	 * @param int                       $usage       Number of API calls this month.
	 * @param string                    $percentage  Percentage of limit used (e.g., "45.2%").
	 * @param bool                      $throttled   Whether requests are being throttled.
	 * @param string|null               $noticeLevel Usage threshold indicator (e.g., "NOTICE_NONE", "NOTICE_FIRST_MONTH_OVER_LIMIT"). Only present with extended=true. Integer 0 from the API is normalized to "0".
	 * @param UpgradeRecommendation|null $upgrade    Recommended plan upgrade. Only present with extended=true.
	 */
	public function __construct(
		public readonly ?int $limit,
		public readonly int $usage,
		public readonly string $percentage,
		public readonly bool $throttled,
		public readonly ?string $noticeLevel = null,
		public readonly ?UpgradeRecommendation $upgrade = null,
	) {
	}

	/**
	 * Check if the API key has unlimited usage.
	 */
	public function isUnlimited(): bool {
		return $this->limit === null;
	}

	/**
	 * Get the remaining API calls for this month.
	 *
	 * Returns null if unlimited.
	 */
	public function getRemaining(): ?int {
		if ( $this->limit === null ) {
			return null;
		}

		return max( 0, $this->limit - $this->usage );
	}

	/**
	 * Create from API JSON response.
	 *
	 * @param array{limit: int|string, usage: int|string, percentage: int|string, throttled: bool, notice_level?: mixed, upgrade?: mixed} $data
	 * @throws ServerException If required keys are missing or extended fields have unexpected types.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'limit', 'usage', 'percentage', 'throttled' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in usage-limit response', $key )
				);
			}
		}

		// limit can be an integer or "none" for unlimited
		$limit = $data['limit'] === 'none' ? null : (int) $data['limit'];

		// notice_level can be a string (e.g., "NOTICE_FIRST_MONTH_OVER_LIMIT"), an int
		// (0 for no notice), or absent. Normalize to string or null.
		$noticeLevel = null;
		if ( array_key_exists( 'notice_level', $data ) && $data['notice_level'] !== null ) {
			if ( ! is_string( $data['notice_level'] ) && ! is_int( $data['notice_level'] ) ) {
				throw ServerException::unexpectedResponse(
					'Expected string or int "notice_level" in usage-limit response'
				);
			}
			$noticeLevel = (string) $data['notice_level'];
		}

		// The API returns false when no upgrade is recommended, or an array otherwise.
		$upgrade = null;
		if ( array_key_exists( 'upgrade', $data ) && $data['upgrade'] !== false && $data['upgrade'] !== null ) {
			if ( ! is_array( $data['upgrade'] ) ) {
				throw ServerException::unexpectedResponse(
					'Expected array or false "upgrade" in usage-limit response'
				);
			}
			/** @var array<string, mixed> $upgradeData */
			$upgradeData = $data['upgrade'];
			$upgrade     = UpgradeRecommendation::fromResponse( $upgradeData );
		}

		return new self(
			$limit,
			(int) $data['usage'],
			(string) $data['percentage'],
			(bool) $data['throttled'],
			$noticeLevel,
			$upgrade,
		);
	}
}
