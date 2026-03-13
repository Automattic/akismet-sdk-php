<?php
/**
 * Subscription status enum for Akismet get-subscription responses.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Represents the status of an Akismet subscription.
 */
enum SubscriptionStatus: string {

	case Active    = 'active';
	case Missing   = 'missing';
	case Suspended = 'suspended';
	case Cancelled = 'cancelled';
	case NoSub     = 'no-sub';

	/**
	 * Check if this status represents an active subscription.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		return $this === self::Active;
	}
}
