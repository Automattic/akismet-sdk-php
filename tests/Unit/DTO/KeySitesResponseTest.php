<?php
/**
 * Tests for KeySitesResponse DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\SiteStats;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( KeySitesResponse::class )]
#[UsesClass( SiteStats::class )]
#[UsesClass( ServerException::class )]
final class KeySitesResponseTest extends TestCase {

	public function testCreatesWithSites(): void {
		$sites = [
			new SiteStats( 'site1.com', 1000, 400, 590, 5, 5, false ),
			new SiteStats( 'site2.com', 500, 200, 295, 3, 2, false ),
		];

		$response = new KeySitesResponse( $sites, 500, 0, 2 );

		$this->assertCount( 2, $response->sites );
		$this->assertSame( 500, $response->limit );
		$this->assertSame( 0, $response->offset );
		$this->assertSame( 2, $response->total );
	}

	public function testHasMoreReturnsFalseWhenAllResultsReturned(): void {
		$sites = [
			new SiteStats( 'site1.com', 1000, 400, 590, 5, 5, false ),
		];

		$response = new KeySitesResponse( $sites, 500, 0, 1 );
		$this->assertFalse( $response->hasMore() );
	}

	public function testHasMoreReturnsTrueWhenMoreResultsExist(): void {
		$sites = array_fill( 0, 500, new SiteStats( 'site.com', 100, 50, 50, 0, 0, false ) );

		$response = new KeySitesResponse( $sites, 500, 0, 1000 );
		$this->assertTrue( $response->hasMore() );
	}

	public function testGetNextOffset(): void {
		$response = new KeySitesResponse( [], 500, 0, 1500 );
		$this->assertSame( 500, $response->getNextOffset() );

		$response2 = new KeySitesResponse( [], 500, 500, 1500 );
		$this->assertSame( 1000, $response2->getNextOffset() );
	}

	public function testToArrayReturnsApiFormat(): void {
		$sites = [
			new SiteStats( 'site1.com', 1000, 400, 590, 5, 5, false ),
			new SiteStats( 'site2.com', 500, 200, 295, 3, 2, true ),
		];

		$response = new KeySitesResponse( $sites, 500, 0, 2, '2024-01' );
		$array    = $response->toArray();

		$this->assertSame( 500, $array['limit'] );
		$this->assertSame( 0, $array['offset'] );
		$this->assertSame( 2, $array['total'] );
		$this->assertSame( '2024-01', $array['month'] );
		$this->assertCount( 2, $array['sites'] );

		$this->assertSame( 'site1.com', $array['sites'][0]['site'] );
		$this->assertSame( 1000, $array['sites'][0]['api_calls'] );
		$this->assertFalse( $array['sites'][0]['is_revoked'] );

		$this->assertSame( 'site2.com', $array['sites'][1]['site'] );
		$this->assertSame( 500, $array['sites'][1]['api_calls'] );
		$this->assertTrue( $array['sites'][1]['is_revoked'] );
	}

	public function testToArrayFromResponseRoundTrips(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'2024-01' => [
					[
						'site'            => 'example.com',
						'api_calls'       => 100,
						'spam'            => 50,
						'ham'             => 50,
						'missed_spam'     => 0,
						'false_positives' => 0,
						'is_revoked'      => false,
					],
				],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 1,
			]
		);

		$roundTripped = KeySitesResponse::fromResponse( $response->toArray() );

		$this->assertSame( '2024-01', $roundTripped->month );
		$this->assertCount( 1, $roundTripped->sites );
		$this->assertSame( 'example.com', $roundTripped->sites[0]->site );
	}

	public function testToArrayFromEmptyMonthBucketRoundTrips(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'2024-01' => [],
				'limit'   => 500,
				'offset'  => 500,
				'total'   => 1,
			]
		);

		$roundTripped = KeySitesResponse::fromResponse( $response->toArray() );

		$this->assertSame( '2024-01', $roundTripped->month );
		$this->assertSame( [], $roundTripped->sites );
	}

	public function testToArrayWithEmptySites(): void {
		$response = new KeySitesResponse( [], 500, 0, 0 );
		$array    = $response->toArray();

		$this->assertSame( [], $array['sites'] );
		$this->assertSame( 500, $array['limit'] );
		$this->assertSame( 0, $array['offset'] );
		$this->assertSame( 0, $array['total'] );
		$this->assertArrayNotHasKey( 'month', $array );
	}

	public function testFromResponseParsesCorrectly(): void {
		$data = [
			'2024-01' => [
				[
					'site'            => 'example1.com',
					'api_calls'       => 1000,
					'spam'            => 400,
					'ham'             => 590,
					'missed_spam'     => 5,
					'false_positives' => 5,
					'is_revoked'      => false,
				],
				[
					'site'            => 'example2.com',
					'api_calls'       => 2000,
					'spam'            => 800,
					'ham'             => 1180,
					'missed_spam'     => 10,
					'false_positives' => 10,
					'is_revoked'      => false,
				],
			],
			'limit'   => 500,
			'offset'  => 0,
			'total'   => 2,
		];

		$response = KeySitesResponse::fromResponse( $data );

		$this->assertCount( 2, $response->sites );
		$this->assertSame( 500, $response->limit );
		$this->assertSame( 0, $response->offset );
		$this->assertSame( 2, $response->total );
		$this->assertSame( '2024-01', $response->month );

		$this->assertSame( 'example1.com', $response->sites[0]->site );
		$this->assertSame( 'example2.com', $response->sites[1]->site );
	}

	public function testFromResponseAllowsEmptyResponseWithoutMonthBucket(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 0,
			]
		);

		$this->assertNull( $response->month );
		$this->assertSame( [], $response->sites );
	}

	public function testFromResponseAllowsEmptyMonthBucketWithPositiveTotal(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'2024-01' => [],
				'limit'   => 500,
				'offset'  => 500,
				'total'   => 1,
			]
		);

		$this->assertSame( '2024-01', $response->month );
		$this->assertSame( [], $response->sites );
	}

	public function testFromResponseThrowsWhenPositiveTotalHasNoSiteBucket(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Missing site bucket/' );

		KeySitesResponse::fromResponse(
			[
				'limit'  => 500,
				'offset' => 0,
				'total'  => 1,
			]
		);
	}

	public function testFromResponseThrowsWhenMonthBucketIsFlatSiteObject(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Expected site object/' );

		KeySitesResponse::fromResponse(
			[
				'2024-01' => [
					'site'            => 'example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 1,
			]
		);
	}

	public function testFromResponseThrowsWhenMonthBucketHasInvalidMonth(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Invalid month bucket "2024-13"/' );

		KeySitesResponse::fromResponse(
			[
				'2024-13' => [
					'site'            => 'example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 1,
			]
		);
	}

	public function testFromResponseThrowsWhenMonthBucketHasLooseMonthFormat(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Invalid month bucket "2024-1"/' );

		KeySitesResponse::fromResponse(
			[
				'2024-1' => [
					'site'            => 'example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'  => 500,
				'offset' => 0,
				'total'  => 1,
			]
		);
	}

	public function testFromResponseThrowsWhenMultipleMonthBucketsPresent(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Multiple site buckets.*2024-01.*2024-02/' );

		KeySitesResponse::fromResponse(
			[
				'2024-01' => [],
				'2024-02' => [],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 0,
			]
		);
	}

	public function testFromResponseThrowsWhenMonthBucketValueIsNotArray(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Month bucket "2024-01" is not an array/' );

		KeySitesResponse::fromResponse(
			[
				'2024-01' => 'not-an-array',
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 1,
			]
		);
	}

	public function testFromResponseThrowsWhenTopLevelMonthDoesNotMatchBucket(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Top-level month "2024-02" does not match bucket "2024-01"/' );

		KeySitesResponse::fromResponse(
			[
				'month'   => '2024-02',
				'2024-01' => [],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 0,
			]
		);
	}

	public function testFromResponseThrowsWhenTopLevelMonthIsInvalid(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Invalid month "2024-13"/' );

		KeySitesResponse::fromResponse(
			[
				'month'  => '2024-13',
				'limit'  => 500,
				'offset' => 0,
				'total'  => 0,
			]
		);
	}

	public function testFromResponseThrowsWhenSitesKeyAndLegacyFlatEntriesBothPresent(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Mixed "sites" key and legacy flat site entries/' );

		KeySitesResponse::fromResponse(
			[
				'sites'    => [
					[
						'site'            => 'example.com',
						'api_calls'       => 100,
						'spam'            => 50,
						'ham'             => 50,
						'missed_spam'     => 0,
						'false_positives' => 0,
						'is_revoked'      => false,
					],
				],
				'site-key' => [
					'site'            => 'legacy.example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'    => 500,
				'offset'   => 0,
				'total'    => 2,
			]
		);
	}

	public function testFromResponseThrowsWhenLegacyAndMonthBucketBothPresent(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessageMatches( '/Mixed month bucket and legacy site entries/' );

		KeySitesResponse::fromResponse(
			[
				'2024-01'  => [
					[
						'site'            => 'example.com',
						'api_calls'       => 100,
						'spam'            => 50,
						'ham'             => 50,
						'missed_spam'     => 0,
						'false_positives' => 0,
						'is_revoked'      => false,
					],
				],
				'site-key' => [
					'site'            => 'legacy.example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'    => 500,
				'offset'   => 0,
				'total'    => 1,
			]
		);
	}

	public function testFromResponseAcceptsTopLevelMonthMatchingBucket(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'month'   => '2024-01',
				'2024-01' => [
					[
						'site'            => 'example.com',
						'api_calls'       => 100,
						'spam'            => 50,
						'ham'             => 50,
						'missed_spam'     => 0,
						'false_positives' => 0,
						'is_revoked'      => false,
					],
				],
				'limit'   => 500,
				'offset'  => 0,
				'total'   => 1,
			]
		);

		$this->assertSame( '2024-01', $response->month );
		$this->assertCount( 1, $response->sites );
	}

	public function testFromResponseAcceptsTopLevelMonthWithSitesKey(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'month'  => '2024-01',
				'sites'  => [
					[
						'site'            => 'example.com',
						'api_calls'       => 100,
						'spam'            => 50,
						'ham'             => 50,
						'missed_spam'     => 0,
						'false_positives' => 0,
						'is_revoked'      => false,
					],
				],
				'limit'  => 500,
				'offset' => 0,
				'total'  => 1,
			]
		);

		$this->assertSame( '2024-01', $response->month );
		$this->assertCount( 1, $response->sites );
	}

	public function testFromResponseParsesLegacyFlatSiteObjects(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'site-key' => [
					'site'            => 'legacy.example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
				'limit'    => 500,
				'offset'   => 0,
				'total'    => 1,
			]
		);

		$this->assertNull( $response->month );
		$this->assertCount( 1, $response->sites );
		$this->assertSame( 'legacy.example.com', $response->sites[0]->site );
	}

	public function testFromResponseThrowsWhenPaginationMissing(): void {
		$this->expectException( ServerException::class );

		KeySitesResponse::fromResponse(
			[
				'site-key' => [
					'site'            => 'example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
			]
		);
	}

	public function testFromResponseSkipsNonSiteEntries(): void {
		$response = KeySitesResponse::fromResponse(
			[
				'site-key' => 'not-an-array',
				'limit'    => 500,
				'offset'   => 0,
				'total'    => 0,
			]
		);

		$this->assertCount( 0, $response->sites );
	}

	public function testFromResponseThrowsOnMissingPaginationFields(): void {
		$this->expectException( ServerException::class );

		// No limit, offset, or total keys provided.
		KeySitesResponse::fromResponse(
			[
				'site-key' => [
					'site'            => 'example.com',
					'api_calls'       => 100,
					'spam'            => 50,
					'ham'             => 50,
					'missed_spam'     => 0,
					'false_positives' => 0,
					'is_revoked'      => false,
				],
			]
		);
	}
}
