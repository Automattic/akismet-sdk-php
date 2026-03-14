<?php
/**
 * Tests for UpgradeRecommendation DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\UpgradeRecommendation;
use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( UpgradeRecommendation::class )]
#[UsesClass( ServerException::class )]
#[UsesClass( ValidationException::class )]
final class UpgradeRecommendationTest extends TestCase {

	public function testConstructorSetsProperties(): void {
		$upgrade = new UpgradeRecommendation(
			plan: 'plus',
			name: 'Plus',
			url: 'https://akismet.com/upgrade/plus',
		);

		$this->assertSame( 'plus', $upgrade->plan );
		$this->assertSame( 'Plus', $upgrade->name );
		$this->assertSame( 'https://akismet.com/upgrade/plus', $upgrade->url );
	}

	public function testToArray(): void {
		$upgrade = new UpgradeRecommendation( 'plus', 'Plus', 'https://akismet.com/upgrade/plus' );

		$this->assertSame(
			[
				'plan' => 'plus',
				'name' => 'Plus',
				'url'  => 'https://akismet.com/upgrade/plus',
			],
			$upgrade->toArray()
		);
	}

	public function testFromResponse(): void {
		$data = [
			'plan' => 'enterprise',
			'name' => 'Enterprise',
			'url'  => 'https://akismet.com/upgrade/enterprise',
		];

		$upgrade = UpgradeRecommendation::fromResponse( $data );

		$this->assertSame( 'enterprise', $upgrade->plan );
		$this->assertSame( 'Enterprise', $upgrade->name );
		$this->assertSame( 'https://akismet.com/upgrade/enterprise', $upgrade->url );
	}

	public function testFromResponseThrowsOnMissingPlan(): void {
		$this->expectException( ServerException::class );

		UpgradeRecommendation::fromResponse(
			[
				'name' => 'Plus',
				'url'  => 'https://example.com',
			]
		);
	}

	public function testFromResponseThrowsOnMissingName(): void {
		$this->expectException( ServerException::class );

		UpgradeRecommendation::fromResponse(
			[
				'plan' => 'plus',
				'url'  => 'https://example.com',
			]
		);
	}

	public function testFromResponseThrowsOnMissingUrl(): void {
		$this->expectException( ServerException::class );

		UpgradeRecommendation::fromResponse(
			[
				'plan' => 'plus',
				'name' => 'Plus',
			]
		);
	}

	public function testFromJsonRoundTrip(): void {
		$original = new UpgradeRecommendation(
			plan: 'plus',
			name: 'Plus',
			url: 'https://akismet.com/upgrade/plus',
		);

		$restored = UpgradeRecommendation::fromJson( $original->toArray() );

		$this->assertSame( $original->plan, $restored->plan );
		$this->assertSame( $original->name, $restored->name );
		$this->assertSame( $original->url, $restored->url );
	}

	public function testFromJsonThrowsOnMissingPlan(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'plan' );

		UpgradeRecommendation::fromJson(
			[
				'name' => 'Plus',
				'url'  => 'https://example.com',
			]
		);
	}

	public function testFromJsonThrowsOnMissingName(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'name' );

		UpgradeRecommendation::fromJson(
			[
				'plan' => 'plus',
				'url'  => 'https://example.com',
			]
		);
	}

	public function testFromJsonThrowsOnMissingUrl(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'url' );

		UpgradeRecommendation::fromJson(
			[
				'plan' => 'plus',
				'name' => 'Plus',
			]
		);
	}

	public function testFromJsonThrowsOnNonStringValue(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'name' );

		UpgradeRecommendation::fromJson(
			[
				'plan' => 'plus',
				'name' => 42,
				'url'  => 'https://example.com',
			]
		);
	}

	public function testFromJsonThrowsOnNullValue(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'url' );

		UpgradeRecommendation::fromJson(
			[
				'plan' => 'plus',
				'name' => 'Plus',
				'url'  => null,
			]
		);
	}

	public function testFromResponseThrowsOnNonStringValue(): void {
		$this->expectException( ServerException::class );
		$this->expectExceptionMessage( 'plan' );

		UpgradeRecommendation::fromResponse(
			[
				'plan' => 42,
				'name' => 'Plus',
				'url'  => 'https://akismet.com/upgrade/plus',
			]
		);
	}
}
