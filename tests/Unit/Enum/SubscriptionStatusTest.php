<?php
/**
 * Tests for SubscriptionStatus enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\SubscriptionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( SubscriptionStatus::class )]
final class SubscriptionStatusTest extends TestCase {

	public function testActiveValue(): void {
		$this->assertSame( 'active', SubscriptionStatus::from( 'active' )->value );
	}

	public function testMissingValue(): void {
		$this->assertSame( 'missing', SubscriptionStatus::from( 'missing' )->value );
	}

	public function testSuspendedValue(): void {
		$this->assertSame( 'suspended', SubscriptionStatus::from( 'suspended' )->value );
	}

	public function testCancelledValue(): void {
		$this->assertSame( 'cancelled', SubscriptionStatus::from( 'cancelled' )->value );
	}

	public function testNoSubValue(): void {
		$this->assertSame( 'no-sub', SubscriptionStatus::from( 'no-sub' )->value );
	}

	public function testTryFromInvalidStringReturnsNull(): void {
		$this->assertNull( SubscriptionStatus::tryFrom( 'unknown' ) );
	}

	#[DataProvider( 'isActiveProvider' )]
	public function testIsActive( SubscriptionStatus $status, bool $expectedIsActive ): void {
		$this->assertSame( $expectedIsActive, $status->isActive() );
	}

	/**
	 * @return array<string, array{SubscriptionStatus, bool}>
	 */
	public static function isActiveProvider(): array {
		return [
			'active is active'        => [ SubscriptionStatus::Active, true ],
			'missing is not active'   => [ SubscriptionStatus::Missing, false ],
			'suspended is not active' => [ SubscriptionStatus::Suspended, false ],
			'cancelled is not active' => [ SubscriptionStatus::Cancelled, false ],
			'no-sub is not active'    => [ SubscriptionStatus::NoSub, false ],
		];
	}
}
