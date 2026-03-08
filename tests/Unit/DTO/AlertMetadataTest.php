<?php
/**
 * Tests for AlertMetadata DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\AlertMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( AlertMetadata::class )]
final class AlertMetadataTest extends TestCase {

	public function testFromHeadersReturnsNullWhenNoAlertHeaders(): void {
		$this->assertNull( AlertMetadata::fromHeaders( [] ) );
		$this->assertNull(
			AlertMetadata::fromHeaders(
				[
					'x-akismet-pro-tip' => 'discard',
					'x-akismet-guid'    => 'abc123',
				]
			)
		);
	}

	public function testFromHeadersReturnsNullWhenAlertHeadersEmpty(): void {
		$this->assertNull(
			AlertMetadata::fromHeaders(
				[
					'x-akismet-alert-upgrade-url' => '',
					'x-akismet-alert-api-calls'   => '',
				]
			)
		);
	}

	public function testFromHeadersParsesAllFields(): void {
		$meta = AlertMetadata::fromHeaders(
			[
				'x-akismet-alert-api-calls'             => '15000',
				'x-akismet-alert-usage-limit'           => '10000',
				'x-akismet-alert-upgrade-plan'          => 'Enterprise',
				'x-akismet-alert-upgrade-url'           => 'https://akismet.com/account/',
				'x-akismet-alert-upgrade-type'          => 'qty',
				'x-akismet-alert-upgrade-via-support'   => 'true',
				'x-akismet-alert-recommended-plan-name' => 'Akismet Pro (500)',
			]
		);

		$this->assertNotNull( $meta );
		$this->assertSame( 15000, $meta->apiCalls );
		$this->assertSame( 10000, $meta->usageLimit );
		$this->assertSame( 'Enterprise', $meta->upgradePlan );
		$this->assertSame( 'https://akismet.com/account/', $meta->upgradeUrl );
		$this->assertSame( 'qty', $meta->upgradeType );
		$this->assertTrue( $meta->upgradeViaSupport );
		$this->assertSame( 'Akismet Pro (500)', $meta->recommendedPlanName );
	}

	public function testFromHeadersWithPartialFields(): void {
		$meta = AlertMetadata::fromHeaders(
			[
				'x-akismet-alert-upgrade-url' => 'https://akismet.com/pricing/',
			]
		);

		$this->assertNotNull( $meta );
		$this->assertNull( $meta->apiCalls );
		$this->assertNull( $meta->usageLimit );
		$this->assertNull( $meta->upgradePlan );
		$this->assertSame( 'https://akismet.com/pricing/', $meta->upgradeUrl );
		$this->assertNull( $meta->upgradeType );
		$this->assertFalse( $meta->upgradeViaSupport );
		$this->assertNull( $meta->recommendedPlanName );
	}

	public function testUpgradeViaSupportFalseWhenNotTrue(): void {
		$meta = AlertMetadata::fromHeaders(
			[
				'x-akismet-alert-upgrade-via-support' => 'false',
			]
		);

		$this->assertNotNull( $meta );
		$this->assertFalse( $meta->upgradeViaSupport );
	}

	public function testFromHeadersNonNumericApiCallsReturnsNull(): void {
		$meta = AlertMetadata::fromHeaders(
			[
				'x-akismet-alert-api-calls'   => 'unknown',
				'x-akismet-alert-usage-limit' => 'unlimited',
			]
		);

		$this->assertNotNull( $meta );
		$this->assertNull( $meta->apiCalls );
		$this->assertNull( $meta->usageLimit );
	}

	public function testFromJsonStringFalseIsNotTruthy(): void {
		$meta = AlertMetadata::fromJson(
			[
				'upgradeViaSupport' => 'false',
			]
		);

		$this->assertFalse( $meta->upgradeViaSupport );
	}

	public function testFromJsonBoolTruePreserved(): void {
		$meta = AlertMetadata::fromJson(
			[
				'upgradeViaSupport' => true,
			]
		);

		$this->assertTrue( $meta->upgradeViaSupport );
	}

	public function testFromJsonNonNumericApiCallsReturnsNull(): void {
		$meta = AlertMetadata::fromJson(
			[
				'apiCalls'   => 'not-a-number',
				'usageLimit' => 'unlimited',
			]
		);

		$this->assertNull( $meta->apiCalls );
		$this->assertNull( $meta->usageLimit );
	}

	public function testFromJsonWrongTypeStringFieldsReturnNull(): void {
		$meta = AlertMetadata::fromJson(
			[
				'upgradePlan' => 123,
				'upgradeUrl'  => true,
				'upgradeType' => [ 'array' ],
			]
		);

		$this->assertNull( $meta->upgradePlan );
		$this->assertNull( $meta->upgradeUrl );
		$this->assertNull( $meta->upgradeType );
	}

	public function testJsonRoundTrip(): void {
		$original = new AlertMetadata(
			apiCalls: 15000,
			usageLimit: 10000,
			upgradePlan: 'Enterprise',
			upgradeUrl: 'https://akismet.com/account/',
			upgradeType: 'qty',
			upgradeViaSupport: true,
			recommendedPlanName: 'Akismet Pro (500)',
		);

		$json = json_encode( $original );
		$this->assertIsString( $json );

		$decoded  = json_decode( $json, true );
		$restored = AlertMetadata::fromJson( $decoded );

		$this->assertSame( $original->apiCalls, $restored->apiCalls );
		$this->assertSame( $original->usageLimit, $restored->usageLimit );
		$this->assertSame( $original->upgradePlan, $restored->upgradePlan );
		$this->assertSame( $original->upgradeUrl, $restored->upgradeUrl );
		$this->assertSame( $original->upgradeType, $restored->upgradeType );
		$this->assertSame( $original->upgradeViaSupport, $restored->upgradeViaSupport );
		$this->assertSame( $original->recommendedPlanName, $restored->recommendedPlanName );
	}
}
