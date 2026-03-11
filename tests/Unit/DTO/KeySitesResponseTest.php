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

	public function testFromResponseParsesCorrectly(): void {
		$data = [
			'2024-01' => [
				'site'            => 'example1.com',
				'api_calls'       => 1000,
				'spam'            => 400,
				'ham'             => 590,
				'missed_spam'     => 5,
				'false_positives' => 5,
				'is_revoked'      => false,
			],
			'2024-02' => [
				'site'            => 'example2.com',
				'api_calls'       => 2000,
				'spam'            => 800,
				'ham'             => 1180,
				'missed_spam'     => 10,
				'false_positives' => 10,
				'is_revoked'      => false,
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

		$this->assertSame( 'example1.com', $response->sites[0]->site );
		$this->assertSame( 'example2.com', $response->sites[1]->site );
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
				'total'    => 1,
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
