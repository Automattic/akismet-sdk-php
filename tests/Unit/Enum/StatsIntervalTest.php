<?php
/**
 * Tests for StatsInterval enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\StatsInterval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( StatsInterval::class )]
final class StatsIntervalTest extends TestCase {

	public function testSixtyDaysValue(): void {
		$this->assertSame( '60-days', StatsInterval::SixtyDays->value );
	}

	public function testSixMonthsValue(): void {
		$this->assertSame( '6-months', StatsInterval::SixMonths->value );
	}

	public function testYearValue(): void {
		$this->assertSame( 'year', StatsInterval::Year->value );
	}

	public function testAllValue(): void {
		$this->assertSame( 'all', StatsInterval::All->value );
	}

	public function testFromValidString(): void {
		$this->assertSame( StatsInterval::SixMonths, StatsInterval::from( '6-months' ) );
	}

	public function testTryFromInvalidStringReturnsNull(): void {
		$this->assertNull( StatsInterval::tryFrom( 'invalid' ) );
	}
}
