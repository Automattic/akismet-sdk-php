<?php
/**
 * UpgradeRecommendation DTO for Akismet extended usage-limit response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents an upgrade recommendation returned when the API key is
 * approaching or exceeding its plan limits.
 *
 * Only present in extended usage-limit responses (extended=true).
 */
final class UpgradeRecommendation {

	/**
	 * @param string $plan Plan slug (e.g., "plus").
	 * @param string $name Display name (e.g., "Plus").
	 * @param string $url  Upgrade URL.
	 */
	public function __construct(
		public readonly string $plan,
		public readonly string $name,
		public readonly string $url,
	) {
	}

	/**
	 * @return array{plan: string, name: string, url: string}
	 */
	public function toArray(): array {
		return [
			'plan' => $this->plan,
			'name' => $this->name,
			'url'  => $this->url,
		];
	}

	/**
	 * Create from API response data.
	 *
	 * @param array{plan: string, name: string, url: string} $data
	 * @throws ServerException If required keys are missing.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'plan', 'name', 'url' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in upgrade recommendation', $key )
				);
			}
		}

		return new self(
			(string) $data['plan'],
			(string) $data['name'],
			(string) $data['url'],
		);
	}
}
