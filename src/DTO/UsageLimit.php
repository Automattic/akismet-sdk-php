<?php
/**
 * UsageLimit DTO for Akismet API 1.2 usage-limit response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

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
	 * @param int|null $limit      Monthly API call limit, or null if unlimited.
	 * @param int      $usage      Number of API calls this month.
	 * @param string   $percentage Percentage of limit used (e.g., "45.2%").
	 * @param bool     $throttled  Whether requests are being throttled.
	 */
	public function __construct(
		public readonly ?int $limit,
		public readonly int $usage,
		public readonly string $percentage,
		public readonly bool $throttled,
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
	 * @param array{limit: int|string, usage: int|string, percentage: int|string, throttled: bool} $data
	 * @throws \Automattic\Akismet\Exception\ServerException If required keys are missing.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'limit', 'usage', 'percentage', 'throttled' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw \Automattic\Akismet\Exception\ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in usage-limit response', $key )
				);
			}
		}

		// limit can be an integer or "none" for unlimited
		$limit = $data['limit'] === 'none' ? null : (int) $data['limit'];

		return new self(
			$limit,
			(int) $data['usage'],
			(string) $data['percentage'],
			(bool) $data['throttled'],
		);
	}
}
