<?php
/**
 * CheckResult DTO for Akismet API responses.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\SpamVerdict;
use Automattic\Akismet\Exception\ValidationException;
use JsonSerializable;

/**
 * Immutable result from a spam check request.
 *
 * Implements JsonSerializable to allow storing results in moderation queues
 * for later feedback submission.
 *
 * Alert fields: `alertCode` and `alertMessage` expose the standard documented
 * Akismet alert headers. `alertMetadata` is an optional superset that captures
 * additional undocumented alert headers (used by the WordPress plugin). When
 * present, `alertMetadata` includes the same code/message plus extended fields.
 */
final class CheckResult implements JsonSerializable {

	public function __construct(
		public readonly SpamVerdict $verdict,
		public readonly ?string $proTip = null,
		public readonly ?string $debugHelp = null,
		public readonly ?string $alertCode = null,
		public readonly ?string $alertMessage = null,
		public readonly ?string $guid = null,
		public readonly ?AlertMetadata $alertMetadata = null,
		public readonly ?int $recheckAfter = null,
	) {
	}

	/**
	 * Check if the content was classified as spam.
	 */
	public function isSpam(): bool {
		return $this->verdict->isSpam();
	}

	/**
	 * Check if the content should be silently discarded.
	 *
	 * When true, the content is blatant spam and doesn't need human review.
	 */
	public function shouldDiscard(): bool {
		return $this->verdict->shouldDiscard();
	}

	/**
	 * Check if the verdict is provisional and should be rechecked.
	 *
	 * When true, the API is still processing additional checks and the
	 * verdict may change. Use recheckAfter for the delay in seconds before
	 * rechecking.
	 */
	public function shouldRecheck(): bool {
		return $this->recheckAfter !== null;
	}

	/**
	 * Create result from API response.
	 *
	 * @param string               $body         Response body ('true', 'false', or 'invalid').
	 * @param array<string, string> $headers     Response headers.
	 */
	public static function fromResponse( string $body, array $headers = [] ): self {
		$headers     = array_change_key_case( $headers, CASE_LOWER );
		$nullIfEmpty = static fn( ?string $value ): ?string => ( $value !== null && $value !== '' ) ? $value : null;

		$proTip       = $nullIfEmpty( $headers['x-akismet-pro-tip'] ?? null );
		$debugHelp    = $nullIfEmpty( $headers['x-akismet-debug-help'] ?? null );
		$alertCode    = $nullIfEmpty( $headers['x-akismet-alert-code'] ?? null );
		$alertMessage = $nullIfEmpty( $headers['x-akismet-alert-msg'] ?? null );
		$guid         = $nullIfEmpty( $headers['x-akismet-guid'] ?? null );

		$recheckAfterRaw = $nullIfEmpty( $headers['x-akismet-recheck-after'] ?? null );
		$recheckAfter    = self::parseRecheckAfter( $recheckAfterRaw );

		// Determine verdict
		if ( $body === 'true' ) {
			$verdict = ( $proTip === 'discard' ) ? SpamVerdict::Discard : SpamVerdict::Spam;
		} else {
			$verdict = SpamVerdict::Ham;
		}

		$alertMetadata = AlertMetadata::fromHeaders( $headers );

		return new self( $verdict, $proTip, $debugHelp, $alertCode, $alertMessage, $guid, $alertMetadata, $recheckAfter );
	}

	/**
	 * Create result from JSON data.
	 *
	 * @param array{verdict?: string, proTip?: string|null, debugHelp?: string|null, alertCode?: string|null, alertMessage?: string|null, guid?: string|null, alertMetadata?: mixed, recheckAfter?: mixed} $data
	 */
	public static function fromJson( array $data ): self {
		$verdict = SpamVerdict::tryFrom( $data['verdict'] ?? '' );
		if ( $verdict === null ) {
			throw ValidationException::invalidValue(
				'verdict',
				sprintf( 'expected one of: ham, spam, discard; got "%s"', $data['verdict'] ?? '' )
			);
		}

		$alertMetadata = isset( $data['alertMetadata'] ) && is_array( $data['alertMetadata'] )
			? AlertMetadata::fromJson( $data['alertMetadata'] )
			: null;

		$recheckAfter = self::parseRecheckAfter( $data['recheckAfter'] ?? null );

		return new self(
			$verdict,
			$data['proTip'] ?? null,
			$data['debugHelp'] ?? null,
			$data['alertCode'] ?? null,
			$data['alertMessage'] ?? null,
			$data['guid'] ?? null,
			$alertMetadata,
			$recheckAfter,
		);
	}

	/**
	 * Convert to an array.
	 *
	 * @return array{verdict: string, proTip: string|null, debugHelp: string|null, alertCode: string|null, alertMessage: string|null, guid: string|null, alertMetadata: array{apiCalls: int|null, usageLimit: int|null, upgradePlan: string|null, upgradeUrl: string|null, upgradeType: string|null, upgradeViaSupport: bool, recommendedPlanName: string|null}|null, recheckAfter: int|null}
	 */
	public function toArray(): array {
		return [
			'verdict'       => $this->verdict->value,
			'proTip'        => $this->proTip,
			'debugHelp'     => $this->debugHelp,
			'alertCode'     => $this->alertCode,
			'alertMessage'  => $this->alertMessage,
			'guid'          => $this->guid,
			'alertMetadata' => $this->alertMetadata?->toArray(),
			'recheckAfter'  => $this->recheckAfter,
		];
	}

	/**
	 * @return array{verdict: string, proTip: string|null, debugHelp: string|null, alertCode: string|null, alertMessage: string|null, guid: string|null, alertMetadata: array{apiCalls: int|null, usageLimit: int|null, upgradePlan: string|null, upgradeUrl: string|null, upgradeType: string|null, upgradeViaSupport: bool, recommendedPlanName: string|null}|null, recheckAfter: int|null}
	 */
	public function jsonSerialize(): array {
		return $this->toArray();
	}

	/**
	 * Parse a recheck-after value into a positive integer or null.
	 *
	 * Accepts string or int inputs. Returns null for absent, non-numeric,
	 * zero, or negative values.
	 */
	private static function parseRecheckAfter( mixed $value ): ?int {
		if ( $value === null ) {
			return null;
		}

		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( is_string( $value ) && ctype_digit( $value ) && (int) $value > 0 ) {
			return (int) $value;
		}

		return null;
	}
}
