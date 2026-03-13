<?php
/**
 * Tests for UsageLimit DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\UpgradeRecommendation;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UsageLimit::class )]
#[UsesClass( ServerException::class )]
#[UsesClass( UpgradeRecommendation::class )]
final class UsageLimitTest extends TestCase {

	public function testCreatesWithLimitedPlan(): void {
		$usage = new UsageLimit(
			limit: 10000,
			usage: 4500,
			percentage: '45.0',
			throttled: false,
		);

		$this->assertSame( 10000, $usage->limit );
		$this->assertSame( 4500, $usage->usage );
		$this->assertSame( '45.0', $usage->percentage );
		$this->assertFalse( $usage->throttled );
	}

	public function testIsUnlimitedReturnsFalseForLimitedPlan(): void {
		$usage = new UsageLimit( 10000, 4500, '45.0', false );
		$this->assertFalse( $usage->isUnlimited() );
	}

	public function testIsUnlimitedReturnsTrueForUnlimitedPlan(): void {
		$usage = new UsageLimit( null, 4500, '0', false );
		$this->assertTrue( $usage->isUnlimited() );
	}

	public function testGetRemainingForLimitedPlan(): void {
		$usage = new UsageLimit( 10000, 4500, '45.0', false );
		$this->assertSame( 5500, $usage->getRemaining() );
	}

	public function testGetRemainingReturnsZeroWhenOverLimit(): void {
		$usage = new UsageLimit( 10000, 12000, '120.0', true );
		$this->assertSame( 0, $usage->getRemaining() );
	}

	public function testGetRemainingReturnsNullForUnlimited(): void {
		$usage = new UsageLimit( null, 4500, '0', false );
		$this->assertNull( $usage->getRemaining() );
	}

	public function testFromResponseWithNumericLimit(): void {
		$data = [
			'limit'      => 50000,
			'usage'      => 12345,
			'percentage' => '24.69',
			'throttled'  => false,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( 50000, $usage->limit );
		$this->assertSame( 12345, $usage->usage );
		$this->assertSame( '24.69', $usage->percentage );
		$this->assertFalse( $usage->throttled );
	}

	public function testFromResponseWithNoneLimit(): void {
		$data = [
			'limit'      => 'none',
			'usage'      => 100000,
			'percentage' => '0',
			'throttled'  => false,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertNull( $usage->limit );
		$this->assertTrue( $usage->isUnlimited() );
	}

	public function testFromResponseWithThrottled(): void {
		$data = [
			'limit'      => 10000,
			'usage'      => 15000,
			'percentage' => '150.0',
			'throttled'  => true,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertTrue( $usage->throttled );
	}

	public function testFromResponseThrowsOnMissingKeys(): void {
		$this->expectException( ServerException::class );

		UsageLimit::fromResponse( [ 'limit' => 10000 ] );
	}

	public function testFromResponseThrowsOnEmptyArray(): void {
		$this->expectException( ServerException::class );

		UsageLimit::fromResponse( [] );
	}

	// Extended mode tests

	public function testExtendedFieldsDefaultToNull(): void {
		$usage = new UsageLimit( 10000, 4500, '45.0', false );

		$this->assertNull( $usage->noticeLevel );
		$this->assertNull( $usage->upgrade );
	}

	public function testConstructorAcceptsExtendedFields(): void {
		$upgrade = new UpgradeRecommendation( 'plus', 'Plus', 'https://akismet.com/upgrade/plus' );
		$usage   = new UsageLimit(
			limit: 10000,
			usage: 9500,
			percentage: '95.0',
			throttled: false,
			noticeLevel: 'NOTICE_FIRST_MONTH_OVER_LIMIT',
			upgrade: $upgrade,
		);

		$this->assertSame( 'NOTICE_FIRST_MONTH_OVER_LIMIT', $usage->noticeLevel );
		$this->assertSame( $upgrade, $usage->upgrade );
	}

	public function testFromResponseWithExtendedFields(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 9500,
			'percentage'   => '95.0',
			'throttled'    => false,
			'notice_level' => 'NOTICE_FIRST_MONTH_OVER_LIMIT',
			'upgrade'      => [
				'plan' => 'plus',
				'name' => 'Plus',
				'url'  => 'https://akismet.com/upgrade/plus',
			],
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( 'NOTICE_FIRST_MONTH_OVER_LIMIT', $usage->noticeLevel );
		$this->assertNotNull( $usage->upgrade );
		$this->assertSame( 'plus', $usage->upgrade->plan );
		$this->assertSame( 'Plus', $usage->upgrade->name );
		$this->assertSame( 'https://akismet.com/upgrade/plus', $usage->upgrade->url );
	}

	public function testFromResponseWithNoticeLevelOnly(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 4500,
			'percentage'   => '45.0',
			'throttled'    => false,
			'notice_level' => 'NOTICE_NONE',
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( 'NOTICE_NONE', $usage->noticeLevel );
		$this->assertNull( $usage->upgrade );
	}

	public function testFromResponseWithUpgradeFalse(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 4500,
			'percentage'   => '45.0',
			'throttled'    => false,
			'notice_level' => 'NOTICE_NONE',
			'upgrade'      => false,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertNull( $usage->upgrade );
	}

	public function testFromResponseWithoutExtendedFields(): void {
		$data = [
			'limit'      => 10000,
			'usage'      => 4500,
			'percentage' => '45.0',
			'throttled'  => false,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertNull( $usage->noticeLevel );
		$this->assertNull( $usage->upgrade );
	}

	public function testFromResponseWithNullNoticeLevel(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 4500,
			'percentage'   => '45.0',
			'throttled'    => false,
			'notice_level' => null,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertNull( $usage->noticeLevel );
	}

	public function testFromResponseWithIntNoticeLevel(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 4500,
			'percentage'   => '45.0',
			'throttled'    => false,
			'notice_level' => 0,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( '0', $usage->noticeLevel );
	}

	public function testFromResponseThrowsOnInvalidNoticeLevel(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'notice_level' );

		UsageLimit::fromResponse(
			[
				'limit'        => 10000,
				'usage'        => 4500,
				'percentage'   => '45.0',
				'throttled'    => false,
				'notice_level' => [ 'unexpected' ],
			]
		);
	}

	public function testFromResponseThrowsOnNonArrayUpgrade(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'upgrade' );

		UsageLimit::fromResponse(
			[
				'limit'      => 10000,
				'usage'      => 4500,
				'percentage' => '45.0',
				'throttled'  => false,
				'upgrade'    => 'invalid',
			]
		);
	}

	public function testFromResponseThrowsOnBooleanTrueUpgrade(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'upgrade' );

		UsageLimit::fromResponse(
			[
				'limit'      => 10000,
				'usage'      => 4500,
				'percentage' => '45.0',
				'throttled'  => false,
				'upgrade'    => true,
			]
		);
	}

	public function testFromResponseWithNullUpgrade(): void {
		$data = [
			'limit'      => 10000,
			'usage'      => 4500,
			'percentage' => '45.0',
			'throttled'  => false,
			'upgrade'    => null,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertNull( $usage->upgrade );
	}

	// toArray tests

	public function testToArrayWithBasicFields(): void {
		$usage = new UsageLimit( 10000, 4500, '45.0', false );

		$this->assertSame(
			[
				'limit'      => 10000,
				'usage'      => 4500,
				'percentage' => '45.0',
				'throttled'  => false,
			],
			$usage->toArray()
		);
	}

	public function testToArrayWithUnlimitedPlan(): void {
		$usage = new UsageLimit( null, 100000, '0', false );

		$this->assertSame( 'none', $usage->toArray()['limit'] );
	}

	public function testToArrayWithExtendedFields(): void {
		$upgrade = new UpgradeRecommendation( 'plus', 'Plus', 'https://akismet.com/upgrade/plus' );
		$usage   = new UsageLimit( 10000, 9500, '95.0', false, 'NOTICE_FIRST_MONTH_OVER_LIMIT', $upgrade );

		$array = $usage->toArray();

		$this->assertSame( 'NOTICE_FIRST_MONTH_OVER_LIMIT', $array['notice_level'] );
		$this->assertSame(
			[
				'plan' => 'plus',
				'name' => 'Plus',
				'url'  => 'https://akismet.com/upgrade/plus',
			],
			$array['upgrade']
		);
	}

	public function testToArrayOmitsNullExtendedFields(): void {
		$usage = new UsageLimit( 10000, 4500, '45.0', false );

		$array = $usage->toArray();

		$this->assertArrayNotHasKey( 'notice_level', $array );
		$this->assertArrayNotHasKey( 'upgrade', $array );
	}

	public function testToArrayFromResponseRoundTrip(): void {
		$data = [
			'limit'        => 10000,
			'usage'        => 9500,
			'percentage'   => '95.0',
			'throttled'    => false,
			'notice_level' => 'NOTICE_FIRST_MONTH_OVER_LIMIT',
			'upgrade'      => [
				'plan' => 'plus',
				'name' => 'Plus',
				'url'  => 'https://akismet.com/upgrade/plus',
			],
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( $data, $usage->toArray() );
	}

	public function testToArrayFromResponseRoundTripUnlimited(): void {
		$data = [
			'limit'      => 'none',
			'usage'      => 100000,
			'percentage' => '0',
			'throttled'  => false,
		];

		$usage = UsageLimit::fromResponse( $data );

		$this->assertSame( $data, $usage->toArray() );
	}
}
