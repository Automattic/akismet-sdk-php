<?php
/**
 * Subscription DTO for Akismet get-subscription response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Exception\ServerException;

/**
 * Represents an Akismet subscription / account plan.
 */
final class Subscription {

	/**
	 * @param int         $accountId       The account ID.
	 * @param string      $slug            Plan slug (e.g., "pro", "free-api-key").
	 * @param string      $displayName     Human-readable plan name (e.g., "Professional").
	 * @param string      $status          Account status: "active", "missing", "suspended", "cancelled", or "no-sub".
	 * @param int|null    $nextBillingDate Unix timestamp of next billing date, or null for free plans.
	 * @param bool        $limitReached    Whether the usage limit has been reached.
	 */
	public function __construct(
		public readonly int $accountId,
		public readonly string $slug,
		public readonly string $displayName,
		public readonly string $status,
		public readonly ?int $nextBillingDate,
		public readonly bool $limitReached,
	) {
	}

	/**
	 * Check if the subscription is active.
	 */
	public function isActive(): bool {
		return $this->status === 'active';
	}

	/**
	 * Create from API JSON response.
	 *
	 * @param array{account_id: mixed, account_type: string, account_name: string, status: string, next_billing_date: mixed, limit_reached: mixed} $data
	 * @throws ServerException If required keys are missing or have invalid types.
	 */
	public static function fromResponse( array $data ): self {
		foreach ( [ 'account_id', 'account_type', 'account_name', 'status', 'next_billing_date', 'limit_reached' ] as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				throw ServerException::unexpectedResponse(
					sprintf( 'Missing required key "%s" in get-subscription response', $key )
				);
			}
		}

		if ( ! is_numeric( $data['account_id'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected numeric "account_id" in get-subscription response'
			);
		}

		if ( $data['next_billing_date'] !== false && ! is_numeric( $data['next_billing_date'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected numeric or false "next_billing_date" in get-subscription response'
			);
		}

		if ( ! is_bool( $data['limit_reached'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected boolean "limit_reached" in get-subscription response'
			);
		}

		$nextBillingDate = $data['next_billing_date'] === false
			? null
			: (int) $data['next_billing_date'];

		return new self(
			(int) $data['account_id'],
			(string) $data['account_type'],
			(string) $data['account_name'],
			(string) $data['status'],
			$nextBillingDate,
			(bool) $data['limit_reached'],
		);
	}
}
