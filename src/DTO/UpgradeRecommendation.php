<?php
/**
 * UpgradeRecommendation DTO for Akismet extended usage-limit response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Exception\ValidationException;

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
	 * Create from a previously serialized array (e.g., from toArray() or JSON round-trip).
	 *
	 * @param array<string, mixed> $data
	 * @throws ValidationException If required keys are missing or have unexpected types.
	 */
	public static function fromJson( array $data ): self {
		foreach ( [ 'plan', 'name', 'url' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ValidationException::invalidValue( $key, 'is required in upgrade recommendation' );
			}
			if ( ! is_string( $data[ $key ] ) ) {
				throw ValidationException::invalidValue( $key, 'must be a string in upgrade recommendation' );
			}
		}

		/** @var string $plan */
		$plan = $data['plan'];
		/** @var string $name */
		$name = $data['name'];
		/** @var string $url */
		$url = $data['url'];

		return new self( $plan, $name, $url );
	}

	/**
	 * Create from API response data.
	 *
	 * @param array<string, mixed> $data
	 * @throws ServerException If required keys are missing or have unexpected types.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'plan', 'name', 'url' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in upgrade recommendation', $key )
				);
			}
			if ( ! is_string( $data[ $key ] ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Expected string "%s" in upgrade recommendation', $key )
				);
			}
		}

		/** @var string $plan */
		$plan = $data['plan'];
		/** @var string $name */
		$name = $data['name'];
		/** @var string $url */
		$url = $data['url'];

		return new self( $plan, $name, $url );
	}
}
