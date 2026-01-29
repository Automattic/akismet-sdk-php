<?php
/**
 * CheckResult DTO for Akismet API responses.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\SpamVerdict;
use JsonSerializable;

/**
 * Immutable result from a spam check request.
 *
 * Implements JsonSerializable to allow storing results in moderation queues
 * for later feedback submission.
 */
final readonly class CheckResult implements JsonSerializable {

	public function __construct(
		public SpamVerdict $verdict,
		public ?string $proTip = null,
		public ?string $debugHelp = null,
		public ?string $alertCode = null,
		public ?string $alertMessage = null,
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
	 * Create result from API response.
	 *
	 * @param string               $body         Response body ('true', 'false', or 'invalid').
	 * @param array<string, string> $headers     Response headers.
	 */
	public static function fromResponse( string $body, array $headers = [] ): self {
		$normalizedHeaders = array_change_key_case( $headers, CASE_LOWER );

		$proTip       = $normalizedHeaders['x-akismet-pro-tip'] ?? null;
		$debugHelp    = $normalizedHeaders['x-akismet-debug-help'] ?? null;
		$alertCode    = $normalizedHeaders['x-akismet-alert-code'] ?? null;
		$alertMessage = $normalizedHeaders['x-akismet-alert-msg'] ?? null;

		// Determine verdict
		if ( $body === 'true' ) {
			$verdict = ( $proTip === 'discard' ) ? SpamVerdict::Discard : SpamVerdict::Spam;
		} else {
			$verdict = SpamVerdict::Ham;
		}

		return new self( $verdict, $proTip, $debugHelp, $alertCode, $alertMessage );
	}

	/**
	 * Create result from JSON data.
	 *
	 * @param array{verdict: string, proTip?: string|null, debugHelp?: string|null, alertCode?: string|null, alertMessage?: string|null} $data
	 */
	public static function fromJson( array $data ): self {
		return new self(
			SpamVerdict::from( $data['verdict'] ),
			$data['proTip'] ?? null,
			$data['debugHelp'] ?? null,
			$data['alertCode'] ?? null,
			$data['alertMessage'] ?? null,
		);
	}

	/**
	 * @return array{verdict: string, proTip: string|null, debugHelp: string|null, alertCode: string|null, alertMessage: string|null}
	 */
	public function jsonSerialize(): array {
		return [
			'verdict'      => $this->verdict->value,
			'proTip'       => $this->proTip,
			'debugHelp'    => $this->debugHelp,
			'alertCode'    => $this->alertCode,
			'alertMessage' => $this->alertMessage,
		];
	}
}
