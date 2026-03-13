<?php
/**
 * Tests for StatsBreakdownEntry DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\StatsBreakdownEntry;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( StatsBreakdownEntry::class )]
#[UsesClass( ServerException::class )]
final class StatsBreakdownEntryTest extends TestCase {

	// =========================================================================
	// Construction Tests
	// =========================================================================

	public function testCreatesWithValidData(): void {
		$entry = new StatsBreakdownEntry(
			period: '2026-03',
			spam: 100,
			ham: 200,
			missedSpam: 1,
			falsePositives: 0,
			blogs: 2,
			date: '2026-03-01',
		);

		$this->assertSame( '2026-03', $entry->period );
		$this->assertSame( 100, $entry->spam );
		$this->assertSame( 200, $entry->ham );
		$this->assertSame( 1, $entry->missedSpam );
		$this->assertSame( 0, $entry->falsePositives );
		$this->assertSame( 2, $entry->blogs );
		$this->assertSame( '2026-03-01', $entry->date );
	}

	// =========================================================================
	// toArray Tests
	// =========================================================================

	public function testToArrayReturnsSnakeCaseKeys(): void {
		$entry = new StatsBreakdownEntry( '2026-03', 100, 200, 1, 0, 2, '2026-03-01' );

		$this->assertSame(
			[
				'period'          => '2026-03',
				'spam'            => 100,
				'ham'             => 200,
				'missed_spam'     => 1,
				'false_positives' => 0,
				'blogs'           => 2,
				'da'              => '2026-03-01',
			],
			$entry->toArray()
		);
	}

	// =========================================================================
	// fromResponse Tests
	// =========================================================================

	public function testFromResponseWithNormalValues(): void {
		$entry = StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => '5',
				'ham'             => '10',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);

		$this->assertSame( '2026-01', $entry->period );
		$this->assertSame( 5, $entry->spam );
		$this->assertSame( 10, $entry->ham );
		$this->assertSame( 0, $entry->missedSpam );
		$this->assertSame( 0, $entry->falsePositives );
		$this->assertSame( 1, $entry->blogs );
		$this->assertSame( '2026-01-01', $entry->date );
	}

	public function testFromResponseConvertsNullToZero(): void {
		$entry = StatsBreakdownEntry::fromResponse(
			'2025-09',
			[
				'spam'            => null,
				'ham'             => null,
				'missed_spam'     => null,
				'false_positives' => null,
				'blogs'           => '0',
				'da'              => '2025-09-01',
			]
		);

		$this->assertSame( 0, $entry->spam );
		$this->assertSame( 0, $entry->ham );
		$this->assertSame( 0, $entry->missedSpam );
		$this->assertSame( 0, $entry->falsePositives );
	}

	public function testFromResponseCastsIntegerValues(): void {
		$entry = StatsBreakdownEntry::fromResponse(
			'2026',
			[
				'spam'            => 149,
				'ham'             => 242,
				'missed_spam'     => 10,
				'false_positives' => 10,
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);

		$this->assertSame( 149, $entry->spam );
		$this->assertSame( 242, $entry->ham );
	}

	public function testFromResponseIgnoresExtraFields(): void {
		$entry = StatsBreakdownEntry::fromResponse(
			'2026',
			[
				'spam'                    => 10,
				'ham'                     => 20,
				'missed_spam'             => 0,
				'false_positives'         => 0,
				'missed_spam_ignored'     => null,
				'false_positives_ignored' => null,
				'blogs'                   => '1',
				'da'                      => '2026-01-01',
			]
		);

		$this->assertSame( 10, $entry->spam );
	}

	// =========================================================================
	// fromResponse Validation Tests
	// =========================================================================

	public function testFromResponseThrowsOnMissingKey(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'ham' );

		StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => '5',
				// 'ham' missing
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );

		StatsBreakdownEntry::fromResponse( '2026-01', [] );
	}

	public function testFromResponseThrowsOnNonStringDate(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'da' );

		StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => '5',
				'ham'             => '10',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => 12345,
			]
		);
	}

	public function testFromResponseThrowsOnNonNumericStringSpam(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'spam' );

		StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => 'abc',
				'ham'             => '10',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);
	}

	public function testFromResponseThrowsOnEmptyStringHam(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'ham' );

		StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => '5',
				'ham'             => '',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);
	}

	public function testFromResponseThrowsOnBooleanSpam(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'spam' );

		StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => true,
				'ham'             => '10',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1',
				'da'              => '2026-01-01',
			]
		);
	}

	public function testFromResponseAcceptsNumericFloatStringBlogs(): void {
		// "1.5" is numeric, so castToInt truncates it to 1 — accepted, not rejected.
		$entry = StatsBreakdownEntry::fromResponse(
			'2026-01',
			[
				'spam'            => '5',
				'ham'             => '10',
				'missed_spam'     => '0',
				'false_positives' => '0',
				'blogs'           => '1.5',
				'da'              => '2026-01-01',
			]
		);

		$this->assertSame( 1, $entry->blogs );
	}
}
