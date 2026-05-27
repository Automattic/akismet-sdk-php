<?php
/**
 * Tests for SiteStats DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\SiteStats;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SiteStats::class )]
#[UsesClass( ServerException::class )]
final class SiteStatsTest extends TestCase {

	public function testCreatesWithAllFields(): void {
		$stats = new SiteStats(
			site: 'example.com',
			totalCalls: 1000,
			spam: 400,
			ham: 580,
			missedSpam: 10,
			falsePositives: 5,
			isRevoked: false,
			hash: 'site-hash',
			eligibleForRevoke: true,
		);

		$this->assertSame( 'example.com', $stats->site );
		$this->assertSame( 1000, $stats->totalCalls );
		$this->assertSame( 400, $stats->spam );
		$this->assertSame( 580, $stats->ham );
		$this->assertSame( 10, $stats->missedSpam );
		$this->assertSame( 5, $stats->falsePositives );
		$this->assertFalse( $stats->isRevoked );
		$this->assertSame( 'site-hash', $stats->hash );
		$this->assertTrue( $stats->eligibleForRevoke );
	}

	public function testGetAccuracyCalculatesCorrectly(): void {
		// 1000 calls, 15 errors (10 missed + 5 false positives) = 98.5% accuracy
		$stats = new SiteStats( 'example.com', 1000, 400, 580, 10, 5, false );
		$this->assertSame( 98.5, $stats->getAccuracy() );
	}

	public function testGetAccuracyReturnsNullForZeroCalls(): void {
		$stats = new SiteStats( 'example.com', 0, 0, 0, 0, 0, false );
		$this->assertNull( $stats->getAccuracy() );
	}

	public function testGetAccuracyReturnsPerfectScore(): void {
		$stats = new SiteStats( 'example.com', 1000, 400, 600, 0, 0, false );
		$this->assertSame( 100.0, $stats->getAccuracy() );
	}

	public function testToArrayReturnsApiFormat(): void {
		$stats = new SiteStats(
			site: 'example.com',
			totalCalls: 1000,
			spam: 400,
			ham: 580,
			missedSpam: 10,
			falsePositives: 5,
			isRevoked: false,
			hash: 'site-hash',
			eligibleForRevoke: true,
		);

		$this->assertSame(
			[
				'site'                => 'example.com',
				'api_calls'           => 1000,
				'spam'                => 400,
				'ham'                 => 580,
				'missed_spam'         => 10,
				'false_positives'     => 5,
				'is_revoked'          => false,
				'hash'                => 'site-hash',
				'eligible_for_revoke' => true,
			],
			$stats->toArray()
		);
	}

	public function testToArrayWithRevokedSite(): void {
		$stats = new SiteStats( 'revoked.com', 500, 200, 295, 3, 2, true );

		$this->assertTrue( $stats->toArray()['is_revoked'] );
	}

	public function testFromResponseWithApiCalls(): void {
		$data = [
			'site'                => 'test.example.com',
			'api_calls'           => 5000,
			'spam'                => 2000,
			'ham'                 => 2950,
			'missed_spam'         => 30,
			'false_positives'     => 20,
			'is_revoked'          => false,
			'hash'                => 'site-hash',
			'eligible_for_revoke' => true,
		];

		$stats = SiteStats::fromResponse( $data );

		$this->assertSame( 'test.example.com', $stats->site );
		$this->assertSame( 5000, $stats->totalCalls );
		$this->assertSame( 2000, $stats->spam );
		$this->assertSame( 2950, $stats->ham );
		$this->assertSame( 30, $stats->missedSpam );
		$this->assertSame( 20, $stats->falsePositives );
		$this->assertFalse( $stats->isRevoked );
		$this->assertSame( 'site-hash', $stats->hash );
		$this->assertTrue( $stats->eligibleForRevoke );
	}

	public function testFromResponseParsesEligibleForRevokeStrings(): void {
		$true       = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 'true' ] ) );
		$false      = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 'false' ] ) );
		$upperTrue  = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 'TRUE' ] ) );
		$mixedFalse = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 'False' ] ) );

		$this->assertTrue( $true->eligibleForRevoke );
		$this->assertFalse( $false->eligibleForRevoke );
		$this->assertTrue( $upperTrue->eligibleForRevoke );
		$this->assertFalse( $mixedFalse->eligibleForRevoke );
	}

	public function testFromResponseParsesEligibleForRevokeIntegers(): void {
		$true  = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 1 ] ) );
		$false = SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 0 ] ) );

		$this->assertTrue( $true->eligibleForRevoke );
		$this->assertFalse( $false->eligibleForRevoke );
	}

	public function testFromResponseThrowsOnUnknownEligibleForRevokeString(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Unrecognized string "eligible_for_revoke" value "maybe"/' );

		SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 'maybe' ] ) );
	}

	public function testFromResponseThrowsOnUnknownEligibleForRevokeInteger(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Unrecognized integer "eligible_for_revoke" value 2/' );

		SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => 2 ] ) );
	}

	public function testFromResponseThrowsOnGarbageEligibleForRevokeType(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Expected bool\/int\/string "eligible_for_revoke"/' );

		SiteStats::fromResponse( $this->buildPayload( [ 'eligible_for_revoke' => [ 'true' ] ] ) );
	}

	public function testFromResponseThrowsOnNonStringHash(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Expected string "hash"/' );

		SiteStats::fromResponse( $this->buildPayload( [ 'hash' => 12345 ] ) );
	}

	public function testFromResponseLeavesExtendedFieldsNullWhenAbsent(): void {
		$stats = SiteStats::fromResponse( $this->buildPayload() );

		$this->assertNull( $stats->hash );
		$this->assertNull( $stats->eligibleForRevoke );

		$array = $stats->toArray();
		$this->assertArrayNotHasKey( 'hash', $array );
		$this->assertArrayNotHasKey( 'eligible_for_revoke', $array );
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return array<string, mixed>
	 */
	private function buildPayload( array $overrides = [] ): array {
		return array_merge(
			[
				'site'            => 'test.example.com',
				'api_calls'       => 5000,
				'spam'            => 2000,
				'ham'             => 2950,
				'missed_spam'     => 30,
				'false_positives' => 20,
				'is_revoked'      => false,
			],
			$overrides
		);
	}

	public function testFromResponseWithTotal(): void {
		$data = [
			'site'            => 'test.example.com',
			'total'           => 3000,
			'spam'            => 1000,
			'ham'             => 1990,
			'missed_spam'     => 5,
			'false_positives' => 5,
			'is_revoked'      => true,
		];

		$stats = SiteStats::fromResponse( $data );

		$this->assertSame( 3000, $stats->totalCalls );
		$this->assertTrue( $stats->isRevoked );
	}

	public function testFromResponseThrowsOnMissingSiteKey(): void {
		$this->expectException( ServerException::class );

		SiteStats::fromResponse(
			[
				'api_calls'       => 1000,
				'spam'            => 400,
				'ham'             => 580,
				'missed_spam'     => 10,
				'false_positives' => 5,
				'is_revoked'      => false,
			]
		);
	}

	public function testFromResponseThrowsOnMissingTotalAndApiCalls(): void {
		$this->expectException( ServerException::class );

		SiteStats::fromResponse(
			[
				'site'            => 'example.com',
				'spam'            => 400,
				'ham'             => 580,
				'missed_spam'     => 10,
				'false_positives' => 5,
				'is_revoked'      => false,
			]
		);
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );

		SiteStats::fromResponse( [] );
	}
}
