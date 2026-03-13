<?php
/**
 * Tests for Subscription DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\Subscription;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Subscription::class )]
#[UsesClass( ServerException::class )]
final class SubscriptionTest extends TestCase {

	public function testCreatesWithPaidPlan(): void {
		$sub = new Subscription(
			accountId: 123,
			slug: 'pro',
			displayName: 'Professional',
			status: 'active',
			nextBillingDate: 1741824000,
			limitReached: false,
		);

		$this->assertSame( 123, $sub->accountId );
		$this->assertSame( 'pro', $sub->slug );
		$this->assertSame( 'Professional', $sub->displayName );
		$this->assertSame( 'active', $sub->status );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
		$this->assertFalse( $sub->limitReached );
	}

	public function testCreatesWithFreePlan(): void {
		$sub = new Subscription(
			accountId: 456,
			slug: 'free-api-key',
			displayName: 'Free',
			status: 'active',
			nextBillingDate: null,
			limitReached: false,
		);

		$this->assertSame( 'free-api-key', $sub->slug );
		$this->assertNull( $sub->nextBillingDate );
	}

	public function testIsActiveReturnsTrueForActiveStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', 'active', 1741824000, false );
		$this->assertTrue( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForCancelledStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', 'cancelled', null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForSuspendedStatus(): void {
		$sub = new Subscription( 1, 'pro', 'Professional', 'suspended', null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForMissingStatus(): void {
		$sub = new Subscription( 1, '', '', 'missing', null, false );
		$this->assertFalse( $sub->isActive() );
	}

	public function testIsActiveReturnsFalseForNoSubStatus(): void {
		$sub = new Subscription( 1, '', '', 'no-sub', null, false );
		$this->assertFalse( $sub->isActive() );
	}

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
		$this->assertSame( 'active', $sub->status );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
		$this->assertFalse( $sub->limitReached );
	}

	public function testFromResponseWithFreePlanFalseBillingDate(): void {
		$data = [
			'account_id'        => 456,
			'account_type'      => 'free-api-key',
			'account_name'      => 'Free',
			'status'            => 'active',
			'next_billing_date' => false,
			'limit_reached'     => false,
		];

		$sub = Subscription::fromResponse( $data );

		$this->assertNull( $sub->nextBillingDate );
	}

	public function testFromResponseWithNoSubscription(): void {
		$data = [
			'account_id'        => 789,
			'account_type'      => '',
			'account_name'      => '',
			'status'            => 'no-sub',
			'next_billing_date' => false,
			'limit_reached'     => false,
		];

		$sub = Subscription::fromResponse( $data );

		$this->assertSame( '', $sub->slug );
		$this->assertSame( 'no-sub', $sub->status );
		$this->assertFalse( $sub->isActive() );
	}

	public function testFromResponseThrowsOnMissingKeys(): void {
		$this->expectException( ServerException::class );

		Subscription::fromResponse( [ 'account_id' => 123 ] );
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );

		Subscription::fromResponse( [] );
	}

	public function testFromResponseCastsStringAccountId(): void {
		$data = [
			'account_id'        => '999',
			'account_type'      => 'plus',
			'account_name'      => 'Plus',
			'status'            => 'active',
			'next_billing_date' => '1741824000',
			'limit_reached'     => false,
		];

		$sub = Subscription::fromResponse( $data );

		$this->assertSame( 999, $sub->accountId );
		$this->assertSame( 1741824000, $sub->nextBillingDate );
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
