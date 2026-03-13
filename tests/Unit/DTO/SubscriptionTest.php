<?php
/**
 * Tests for Subscription DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\Subscription;
use Automattic\Akismet\Enum\SubscriptionStatus;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Subscription::class )]
#[UsesClass( ServerException::class )]
#[UsesClass( SubscriptionStatus::class )]
final class SubscriptionTest extends TestCase {

	public function testCreatesWithPaidPlan(): void {
		$sub = new Subscription(
			accountId: 123,
			slug: 'pro',
			displayName: 'Professional',
			status: SubscriptionStatus::Active,
			nextBillingDate: 1741824000,
			limitReached: false,
		);

		$this->assertSame( 123, $sub->accountId );
		$this->assertSame( 'pro', $sub->slug );
		$this->assertSame( 'Professional', $sub->displayName );
		$this->assertSame( SubscriptionStatus::Active, $sub->status );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
		$this->assertFalse( $sub->limitReached );
	}

	public function testCreatesWithFreePlan(): void {
		$sub = new Subscription(
			accountId: 456,
			slug: 'free-api-key',
			displayName: 'Free',
			status: SubscriptionStatus::Active,
			nextBillingDate: null,
			limitReached: false,
		);

		$this->assertSame( 'free-api-key', $sub->slug );
		$this->assertNull( $sub->nextBillingDate );
	}

	// =========================================================================
	// isActive Tests
	// =========================================================================

	public function testIsActiveReturnsTrueForActiveStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', SubscriptionStatus::Active, 1741824000, false );
		$this->assertTrue( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForCancelledStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', SubscriptionStatus::Cancelled, null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForSuspendedStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', SubscriptionStatus::Suspended, null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForMissingStatus(): void {
		$sub = new Subscription( 1, '', '', SubscriptionStatus::Missing, null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForNoSubStatus(): void {
		$sub = new Subscription( 1, '', '', SubscriptionStatus::NoSub, null, false );
		$this->assertFalse( $sub->isActive() );
	}

	// =========================================================================
	// isPaid Tests
	// =========================================================================

	public function testIsPaidReturnsTrueWithBillingDate(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', SubscriptionStatus::Active, 1741824000, false );
		$this->assertTrue( $sub->isPaid() );
	}

	public function testIsPaidReturnsFalseWithNullBillingDate(): void {
		$sub = new Subscription( 1, 'free-api-key', 'Free', SubscriptionStatus::Active, null, false );
		$this->assertFalse( $sub->isPaid() );
	}

	// =========================================================================
	// toArray Tests
	// =========================================================================

	public function testToArrayReturnsApiFormat(): void {
		$sub = new Subscription( 123, 'pro', 'Professional', SubscriptionStatus::Active, 1741824000, false );

		$this->assertSame(
			[
				'account_id'        => 123,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			],
			$sub->toArray()
		);
	}

	public function testToArrayReturnsFalseForNullBillingDate(): void {
		$sub = new Subscription( 1, 'free-api-key', 'Free', SubscriptionStatus::Active, null, false );

		$this->assertFalse( $sub->toArray()['next_billing_date'] );
	}

	// =========================================================================
	// fromResponse Tests
	// =========================================================================

	public function testFromResponseWithPaidPlan(): void {
		$data = [
			'account_id'        => 123,
			'account_type'      => 'pro',
			'account_name'      => 'Professional',
			'status'            => 'active',
			'next_billing_date' => 1741824000,
			'limit_reached'     => false,
		];

		$sub = Subscription::fromResponse( $data );

		$this->assertSame( 123, $sub->accountId );
		$this->assertSame( 'pro', $sub->slug );
		$this->assertSame( 'Professional', $sub->displayName );
		$this->assertSame( SubscriptionStatus::Active, $sub->status );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
		$this->assertFalse( $sub->limitReached );
	}

	public function testFromResponseWithFreePlanFalseBillingDate(): void {
		$sub = Subscription::fromResponse(
			[
				'account_id'        => 456,
				'account_type'      => 'free-api-key',
				'account_name'      => 'Free',
				'status'            => 'active',
				'next_billing_date' => false,
				'limit_reached'     => false,
			]
		);

		$this->assertNull( $sub->nextBillingDate );
	}

	public function testFromResponseWithNullBillingDate(): void {
		$sub = Subscription::fromResponse(
			[
				'account_id'        => 456,
				'account_type'      => 'free-api-key',
				'account_name'      => 'Free',
				'status'            => 'active',
				'next_billing_date' => null,
				'limit_reached'     => false,
			]
		);

		$this->assertNull( $sub->nextBillingDate );
	}

	public function testFromResponseWithNoSubscription(): void {
		$sub = Subscription::fromResponse(
			[
				'account_id'        => 789,
				'account_type'      => '',
				'account_name'      => '',
				'status'            => 'no-sub',
				'next_billing_date' => false,
				'limit_reached'     => false,
			]
		);

		$this->assertSame( '', $sub->slug );
		$this->assertSame( SubscriptionStatus::NoSub, $sub->status );
		$this->assertFalse( $sub->isActive() );
	}

	public function testFromResponseCastsStringAccountId(): void {
		$sub = Subscription::fromResponse(
			[
				'account_id'        => '999',
				'account_type'      => 'plus',
				'account_name'      => 'Plus',
				'status'            => 'active',
				'next_billing_date' => '1741824000',
				'limit_reached'     => false,
			]
		);

		$this->assertSame( 999, $sub->accountId );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
	}

	// =========================================================================
	// fromResponse Validation Tests
	// =========================================================================

	public function testFromResponseThrowsOnMissingKeys(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'account_type' );

		Subscription::fromResponse( [ 'account_id' => 123 ] );
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'account_id' );

		Subscription::fromResponse( [] );
	}

	public function testFromResponseThrowsOnNonNumericAccountId(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'account_id' );

		Subscription::fromResponse(
			[
				'account_id'        => 'abc',
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			]
		);
	}

	public function testFromResponseThrowsOnZeroAccountId(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'account_id' );

		Subscription::fromResponse(
			[
				'account_id'        => 0,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			]
		);
	}

	public function testFromResponseThrowsOnNegativeAccountId(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'account_id' );

		Subscription::fromResponse(
			[
				'account_id'        => -1,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			]
		);
	}

	public function testFromResponseThrowsOnUnknownStatus(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'Unknown status' );

		Subscription::fromResponse(
			[
				'account_id'        => 123,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'banana',
				'next_billing_date' => 1741824000,
				'limit_reached'     => false,
			]
		);
	}

	public function testFromResponseThrowsOnNonNumericNextBillingDate(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'next_billing_date' );

		Subscription::fromResponse(
			[
				'account_id'        => 123,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 'tomorrow',
				'limit_reached'     => false,
			]
		);
	}

	public function testFromResponseThrowsOnNonBoolLimitReached(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'limit_reached' );

		Subscription::fromResponse(
			[
				'account_id'        => 123,
				'account_type'      => 'pro',
				'account_name'      => 'Professional',
				'status'            => 'active',
				'next_billing_date' => 1741824000,
				'limit_reached'     => 'yes',
			]
		);
	}
}
