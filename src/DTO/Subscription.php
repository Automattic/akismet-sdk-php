<?php
/**
 * Subscription DTO for Akismet get-subscription response.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\DTO;

use Automattic\Akismet\Enum\SubscriptionStatus;
use Automattic\Akismet\Exception\ServerException;

/**
 * Represents an Akismet subscription / account plan.
 */
final class Subscription {

	/**
	 * @param int                $accountId       The account ID.
	 * @param string             $slug            Plan slug (e.g., "pro", "free-api-key").
	 * @param string             $displayName     Human-readable plan name (e.g., "Professional").
	 * @param SubscriptionStatus $status          Account status.
	 * @param int|null           $nextBillingDate Unix timestamp of next billing date, or null for free plans.
	 * @param bool               $limitReached    Whether the usage limit has been reached.
	 */
	public function __construct(
		public readonly int $accountId,
		public readonly string $slug,
		public readonly string $displayName,
		public readonly SubscriptionStatus $status,
		public readonly ?int $nextBillingDate,
		public readonly bool $limitReached,
	) {
	}

	/**
	 * Check if the subscription is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return $this->status->isActive();
	}

	/**
	 * Check if this is a paid plan.
	 *
	 * @return bool
	 */
	public function isPaid(): bool {
		return $this->nextBillingDate !== null;
	}

	/**
	 * Convert to an array matching the API response format.
	 *
	 * @return array{account_id: int, account_type: string, account_name: string, status: string, next_billing_date: int|false, limit_reached: bool}
	 */
	public function toArray(): array {
		return [
			'account_id'        => $this->accountId,
			'account_type'      => $this->slug,
			'account_name'      => $this->displayName,
			'status'            => $this->status->value,
			'next_billing_date' => $this->nextBillingDate ?? false,
			'limit_reached'     => $this->limitReached,
		];
	}

	/**
	 * Create from API JSON response.
	 *
	 * @param array{account_id: mixed, account_type: mixed, account_name: mixed, status: mixed, next_billing_date: mixed, limit_reached: mixed} $data
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

		if ( ! is_numeric( $data['account_id'] ) || (int) $data['account_id'] <= 0 ) {
			throw ServerException::unexpectedResponse(
				'Expected positive integer "account_id" in get-subscription response'
			);
		}

		if ( ! is_string( $data['status'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected string "status" in get-subscription response'
			);
		}

		$status = SubscriptionStatus::tryFrom( $data['status'] );
		if ( $status === null ) {
			throw ServerException::unexpectedResponse(
				sprintf( 'Unknown status "%s" in get-subscription response', $data['status'] )
			);
		}

		$slug = $data['account_type'];
		if ( ! is_string( $slug ) ) {
			throw ServerException::unexpectedResponse(
				'Expected string "account_type" in get-subscription response'
			);
		}

		$displayName = $data['account_name'];
		if ( ! is_string( $displayName ) ) {
			throw ServerException::unexpectedResponse(
				'Expected string "account_name" in get-subscription response'
			);
		}

		// next_billing_date is a Unix timestamp for paid plans, false or null for free plans.
		$rawBillingDate = $data['next_billing_date'];
		if ( $rawBillingDate !== false && $rawBillingDate !== null && ! is_numeric( $rawBillingDate ) ) {
			throw ServerException::unexpectedResponse(
				'Expected numeric, false, or null "next_billing_date" in get-subscription response'
			);
		}
		$nextBillingDate = ( $rawBillingDate === false || $rawBillingDate === null )
			? null
			: (int) $rawBillingDate;

		if ( ! is_bool( $data['limit_reached'] ) ) {
			throw ServerException::unexpectedResponse(
				'Expected boolean "limit_reached" in get-subscription response'
			);
		}

		return new self(
			(int) $data['account_id'],
			$slug,
			$displayName,
			$status,
			$nextBillingDate,
			$data['limit_reached'],
		);
	}
}
