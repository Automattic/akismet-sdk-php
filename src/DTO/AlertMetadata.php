<?php
/**
 * Alert metadata DTO for extended Akismet response headers.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use JsonSerializable;

/**
 * Extended alert metadata from Akismet API response headers.
 *
 * Parsed from X-akismet-alert-* headers returned on comment-check and
 * verify-key responses. These headers are NOT part of the published Akismet
 * API specification but have been used by the official WordPress plugin for
 * years and are considered stable. They provide usage-limit context and
 * upgrade information when an account approaches or exceeds its plan limits.
 *
 * SDK consumers may rely on these fields but should be aware they are not
 * covered by the public API contract and could change without notice.
 *
 * @see https://akismet.com/developers/errors/
 */
final class AlertMetadata implements JsonSerializable {

	public function __construct(
		public readonly ?int $apiCalls = null,
		public readonly ?int $usageLimit = null,
		public readonly ?string $upgradePlan = null,
		public readonly ?string $upgradeUrl = null,
		public readonly ?string $upgradeType = null,
		public readonly bool $upgradeViaSupport = false,
		public readonly ?string $recommendedPlanName = null,
	) {
	}

	/**
	 * Create from response headers.
	 *
	 * Returns null if no extended alert headers are present.
	 * Header keys are normalized to lowercase internally.
	 *
	 * @param array<string, string> $headers Response headers.
	 */
	public static function fromHeaders( array $headers ): ?self {
		$headers     = array_change_key_case( $headers, CASE_LOWER );
		$nullIfEmpty = static fn( ?string $value ): ?string => ( $value !== null && $value !== '' ) ? $value : null;

		$apiCalls            = $nullIfEmpty( $headers['x-akismet-alert-api-calls'] ?? null );
		$usageLimit          = $nullIfEmpty( $headers['x-akismet-alert-usage-limit'] ?? null );
		$upgradePlan         = $nullIfEmpty( $headers['x-akismet-alert-upgrade-plan'] ?? null );
		$upgradeUrl          = $nullIfEmpty( $headers['x-akismet-alert-upgrade-url'] ?? null );
		$upgradeType         = $nullIfEmpty( $headers['x-akismet-alert-upgrade-type'] ?? null );
		$upgradeViaSupport   = $nullIfEmpty( $headers['x-akismet-alert-upgrade-via-support'] ?? null );
		$recommendedPlanName = $nullIfEmpty( $headers['x-akismet-alert-recommended-plan-name'] ?? null );

		// Return null if no extended alert headers are present.
		if (
			$apiCalls === null
			&& $usageLimit === null
			&& $upgradePlan === null
			&& $upgradeUrl === null
			&& $upgradeType === null
			&& $upgradeViaSupport === null
			&& $recommendedPlanName === null
		) {
			return null;
		}

		return new self(
			apiCalls: $apiCalls !== null && is_numeric( $apiCalls ) ? (int) $apiCalls : null,
			usageLimit: $usageLimit !== null && is_numeric( $usageLimit ) ? (int) $usageLimit : null,
			upgradePlan: $upgradePlan,
			upgradeUrl: $upgradeUrl,
			upgradeType: $upgradeType,
			upgradeViaSupport: $upgradeViaSupport === 'true',
			recommendedPlanName: $recommendedPlanName,
		);
	}

	/**
	 * Create from JSON data (for round-tripping via CheckResult::fromJson).
	 *
	 * @param array<mixed, mixed> $data
	 */
	public static function fromJson( array $data ): self {
		return new self(
			apiCalls: isset( $data['apiCalls'] ) && is_numeric( $data['apiCalls'] ) ? (int) $data['apiCalls'] : null,
			usageLimit: isset( $data['usageLimit'] ) && is_numeric( $data['usageLimit'] ) ? (int) $data['usageLimit'] : null,
			upgradePlan: isset( $data['upgradePlan'] ) && is_string( $data['upgradePlan'] ) ? $data['upgradePlan'] : null,
			upgradeUrl: isset( $data['upgradeUrl'] ) && is_string( $data['upgradeUrl'] ) ? $data['upgradeUrl'] : null,
			upgradeType: isset( $data['upgradeType'] ) && is_string( $data['upgradeType'] ) ? $data['upgradeType'] : null,
			upgradeViaSupport: isset( $data['upgradeViaSupport'] ) && ( $data['upgradeViaSupport'] === true || $data['upgradeViaSupport'] === 'true' ),
			recommendedPlanName: isset( $data['recommendedPlanName'] ) && is_string( $data['recommendedPlanName'] ) ? $data['recommendedPlanName'] : null,
		);
	}

	/**
	 * Convert to an array.
	 *
	 * @return array{apiCalls: int|null, usageLimit: int|null, upgradePlan: string|null, upgradeUrl: string|null, upgradeType: string|null, upgradeViaSupport: bool, recommendedPlanName: string|null}
	 */
	public function toArray(): array {
		return [
			'apiCalls'            => $this->apiCalls,
			'usageLimit'          => $this->usageLimit,
			'upgradePlan'         => $this->upgradePlan,
			'upgradeUrl'          => $this->upgradeUrl,
			'upgradeType'         => $this->upgradeType,
			'upgradeViaSupport'   => $this->upgradeViaSupport,
			'recommendedPlanName' => $this->recommendedPlanName,
		];
	}

	/**
	 * @return array{apiCalls: int|null, usageLimit: int|null, upgradePlan: string|null, upgradeUrl: string|null, upgradeType: string|null, upgradeViaSupport: bool, recommendedPlanName: string|null}
	 */
	public function jsonSerialize(): array {
		return $this->toArray();
	}
}
