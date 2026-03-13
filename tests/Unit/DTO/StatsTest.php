<?php
/**
 * Tests for Stats DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\Stats;
use Automattic\Akismet\DTO\StatsBreakdownEntry;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Stats::class )]
#[UsesClass( StatsBreakdownEntry::class )]
#[UsesClass( ServerException::class )]
final class StatsTest extends TestCase {

	// =========================================================================
	// Construction Tests
	// =========================================================================

	public function testCreatesWithBreakdownEntries(): void {
		$entry = new StatsBreakdownEntry( '2026-03', 100, 200, 1, 0, 2, '2026-03-01' );
		$stats = new Stats(
			spam: 100,
			ham: 200,
			missedSpam: 1,
			falsePositives: 0,
			accuracy: '99.5',
			timeSaved: 5000,
			breakdown: [ '2026-03' => $entry ],
		);

		$this->assertSame( 100, $stats->spam );
		$this->assertSame( 200, $stats->ham );
		$this->assertSame( 1, $stats->missedSpam );
		$this->assertSame( 0, $stats->falsePositives );
		$this->assertSame( '99.5', $stats->accuracy );
		$this->assertSame( 5000, $stats->timeSaved );
		$this->assertCount( 1, $stats->breakdown );
		$this->assertSame( $entry, $stats->breakdown['2026-03'] );
	}

	// =========================================================================
	// toArray Tests
	// =========================================================================

	public function testToArrayReturnsApiFormat(): void {
		$entry = new StatsBreakdownEntry( '2026-03', 100, 200, 1, 0, 2, '2026-03-01' );
		$stats = new Stats( 100, 200, 1, 0, '99.5', 5000, [ '2026-03' => $entry ] );

		$array = $stats->toArray();

		$this->assertSame( 100, $array['spam'] );
		$this->assertSame( 200, $array['ham'] );
		$this->assertSame( 1, $array['missed_spam'] );
		$this->assertSame( 0, $array['false_positives'] );
		$this->assertSame( '99.5', $array['accuracy'] );
		$this->assertSame( 5000, $array['time_saved'] );
		$this->assertArrayHasKey( '2026-03', $array['breakdown'] );
		$this->assertSame( 100, $array['breakdown']['2026-03']['spam'] );
	}

	public function testToArrayWithEmptyBreakdown(): void {
		$stats = new Stats( 0, 0, 0, 0, '0', 0, [] );

		$this->assertSame( [], $stats->toArray()['breakdown'] );
	}

	// =========================================================================
	// fromResponse Tests
	// =========================================================================

	public function testFromResponseHappyPath(): void {
		$data = [
			'spam'            => 149,
			'ham'             => 242,
			'missed_spam'     => 10,
			'false_positives' => 10,
			'accuracy'        => '94.88',
			'time_saved'      => 7455,
			'breakdown'       => [
				'2026-01' => [
					'spam'            => '5',
					'ham'             => '5',
					'missed_spam'     => '0',
					'false_positives' => '0',
					'blogs'           => '1',
					'da'              => '2026-01-01',
				],
				'2026-02' => [
					'spam'            => '29',
					'ham'             => '49',
					'missed_spam'     => '0',
					'false_positives' => '0',
					'blogs'           => '1',
					'da'              => '2026-02-01',
				],
			],
		];

		$stats = Stats::fromResponse( $data );

		$this->assertSame( 149, $stats->spam );
		$this->assertSame( 242, $stats->ham );
		$this->assertSame( 10, $stats->missedSpam );
		$this->assertSame( 10, $stats->falsePositives );
		$this->assertSame( '94.88', $stats->accuracy );
		$this->assertSame( 7455, $stats->timeSaved );
		$this->assertCount( 2, $stats->breakdown );
		$this->assertSame( 5, $stats->breakdown['2026-01']->spam );
		$this->assertSame( '2026-01', $stats->breakdown['2026-01']->period );
	}

	public function testFromResponseWithEmptyBreakdown(): void {
		$data = [
			'spam'            => 0,
			'ham'             => 0,
			'missed_spam'     => 0,
			'false_positives' => 0,
			'accuracy'        => 0,
			'time_saved'      => 0,
			'breakdown'       => [],
		];

		$stats = Stats::fromResponse( $data );

		$this->assertSame( [], $stats->breakdown );
	}

	public function testFromResponseWithMissingBreakdownKey(): void {
		$data = [
			'spam'            => 10,
			'ham'             => 20,
			'missed_spam'     => 0,
			'false_positives' => 0,
			'accuracy'        => '99.0',
			'time_saved'      => 100,
		];

		$stats = Stats::fromResponse( $data );

		$this->assertSame( [], $stats->breakdown );
	}

	public function testFromResponseCastsAccuracyIntToString(): void {
		$data = [
			'spam'            => 0,
			'ham'             => 0,
			'missed_spam'     => 0,
			'false_positives' => 0,
			'accuracy'        => 0,
			'time_saved'      => 0,
			'breakdown'       => [],
		];

		$stats = Stats::fromResponse( $data );

		$this->assertSame( '0', $stats->accuracy );
	}

	public function testFromResponseCastsAccuracyStringPreserved(): void {
		$data = [
			'spam'            => 100,
			'ham'             => 200,
			'missed_spam'     => 1,
			'false_positives' => 0,
			'accuracy'        => '94.88',
			'time_saved'      => 5000,
			'breakdown'       => [],
		];

		$stats = Stats::fromResponse( $data );

		$this->assertSame( '94.88', $stats->accuracy );
	}

	public function testFromResponseThrowsOnNonArrayBreakdown(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'breakdown' );

		Stats::fromResponse(
			[
				'spam'            => 10,
				'ham'             => 20,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '99.0',
				'time_saved'      => 100,
				'breakdown'       => 'not-an-array',
			]
		);
	}

	public function testFromResponseThrowsOnNonArrayBreakdownEntry(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( '2026-01' );

		Stats::fromResponse(
			[
				'spam'            => 10,
				'ham'             => 20,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '99.0',
				'time_saved'      => 100,
				'breakdown'       => [ '2026-01' => 'invalid' ],
			]
		);
	}

	// =========================================================================
	// fromResponse Validation Tests
	// =========================================================================

	public function testFromResponseThrowsOnNonNumericSpam(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'spam' );

		Stats::fromResponse(
			[
				'spam'            => 'abc',
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '0',
				'time_saved'      => 0,
			]
		);
	}

	public function testFromResponseThrowsOnMissingSpam(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'spam' );

		Stats::fromResponse(
			[
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '0',
				'time_saved'      => 0,
			]
		);
	}

	public function testFromResponseThrowsOnMissingAccuracy(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'accuracy' );

		Stats::fromResponse(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'time_saved'      => 0,
			]
		);
	}

	public function testFromResponseThrowsOnMissingTimeSaved(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'time_saved' );

		Stats::fromResponse(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '0',
			]
		);
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );

		Stats::fromResponse( [] );
	}

	public function testFromResponseThrowsOnNonNumericAccuracy(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'accuracy' );

		Stats::fromResponse(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => 'unknown',
				'time_saved'      => 0,
			]
		);
	}

	public function testFromResponseThrowsOnEmptyStringAccuracy(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'accuracy' );

		Stats::fromResponse(
			[
				'spam'            => 0,
				'ham'             => 0,
				'missed_spam'     => 0,
				'false_positives' => 0,
				'accuracy'        => '',
				'time_saved'      => 0,
			]
		);
	}
}
