<?php
/**
 * Tests for CheckResult DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\AlertMetadata;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\Enum\SpamVerdict;
use Automattic\Akismet\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( CheckResult::class )]
#[UsesClass( AlertMetadata::class )]
#[UsesClass( SpamVerdict::class )]
#[UsesClass( ValidationException::class )]
final class CheckResultTest extends TestCase {

	public function testIsSpamReturnsTrueForSpamVerdict(): void {
		$result = new CheckResult( SpamVerdict::Spam );
		$this->assertTrue( $result->isSpam() );
	}

	public function testIsSpamReturnsTrueForDiscardVerdict(): void {
		$result = new CheckResult( SpamVerdict::Discard );
		$this->assertTrue( $result->isSpam() );
	}

	public function testIsSpamReturnsFalseForHamVerdict(): void {
		$result = new CheckResult( SpamVerdict::Ham );
		$this->assertFalse( $result->isSpam() );
	}

	public function testShouldDiscardReturnsTrueOnlyForDiscardVerdict(): void {
		$this->assertFalse( ( new CheckResult( SpamVerdict::Ham ) )->shouldDiscard() );
		$this->assertFalse( ( new CheckResult( SpamVerdict::Spam ) )->shouldDiscard() );
		$this->assertTrue( ( new CheckResult( SpamVerdict::Discard ) )->shouldDiscard() );
	}

	public function testFromResponseWithFalseBody(): void {
		$result = CheckResult::fromResponse( 'false' );

		$this->assertSame( SpamVerdict::Ham, $result->verdict );
		$this->assertFalse( $result->isSpam() );
	}

	public function testFromResponseWithTrueBody(): void {
		$result = CheckResult::fromResponse( 'true' );

		$this->assertSame( SpamVerdict::Spam, $result->verdict );
		$this->assertTrue( $result->isSpam() );
		$this->assertFalse( $result->shouldDiscard() );
	}

	public function testFromResponseWithDiscardProTip(): void {
		$result = CheckResult::fromResponse(
			'true',
			[
				'X-akismet-pro-tip' => 'discard',
			]
		);

		$this->assertSame( SpamVerdict::Discard, $result->verdict );
		$this->assertTrue( $result->isSpam() );
		$this->assertTrue( $result->shouldDiscard() );
		$this->assertSame( 'discard', $result->proTip );
	}

	public function testFromResponseExtractsHeaders(): void {
		$result = CheckResult::fromResponse(
			'true',
			[
				'X-Akismet-Debug-Help'     => 'Some debug info',
				'X-Akismet-Alert-Code'     => '10001',
				'X-Akismet-Alert-Msg'      => 'Usage limit warning',
				'X-Akismet-Guid'           => 'abc123def456',
				'X-Akismet-Error'          => 'missing-required-field',
				'X-Akismet-Classification' => 'spam',
			]
		);

		$this->assertSame( 'Some debug info', $result->debugHelp );
		$this->assertSame( '10001', $result->alertCode );
		$this->assertSame( 'Usage limit warning', $result->alertMessage );
		$this->assertSame( 'abc123def456', $result->guid );
		$this->assertSame( 'missing-required-field', $result->error );
		$this->assertSame( 'spam', $result->classification );
	}

	public function testFromResponseWithoutGuid(): void {
		$result = CheckResult::fromResponse( 'false' );

		$this->assertNull( $result->guid );
	}

	public function testFromResponseExtractsGuidCaseInsensitively(): void {
		$result = CheckResult::fromResponse(
			'true',
			[ 'x-akismet-guid' => 'lowercase-guid-value' ]
		);

		$this->assertSame( 'lowercase-guid-value', $result->guid );
	}

	public function testFromResponseExtractsErrorMetadataCaseInsensitively(): void {
		$result = CheckResult::fromResponse(
			'true',
			[
				'x-akismet-error'          => 'missing-required-field',
				'x-akismet-classification' => 'spam',
			]
		);

		$this->assertSame( 'missing-required-field', $result->error );
		$this->assertSame( 'spam', $result->classification );
	}

	public function testFromResponseTreatsEmptyErrorMetadataAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[
				'X-Akismet-Error'          => '',
				'X-Akismet-Classification' => '',
			]
		);

		$this->assertNull( $result->error );
		$this->assertNull( $result->classification );
	}

	public function testFromResponseTreatsEmptyGuidAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[ 'X-Akismet-Guid' => '' ]
		);

		$this->assertNull( $result->guid );
	}

	public function testFromResponseHandlesCaseInsensitiveHeaders(): void {
		$result = CheckResult::fromResponse(
			'false',
			[
				'x-akismet-debug-help' => 'lowercase headers',
			]
		);

		$this->assertSame( 'lowercase headers', $result->debugHelp );
	}

	public function testJsonSerializeAndFromJson(): void {
		$original = new CheckResult(
			SpamVerdict::Spam,
			'discard',
			'debug info',
			'10001',
			'alert message',
			'abc123def456',
		);

		$json = json_encode( $original );
		$this->assertIsString( $json );

		$decoded  = json_decode( $json, true );
		$restored = CheckResult::fromJson( $decoded );

		$this->assertSame( $original->verdict, $restored->verdict );
		$this->assertSame( $original->proTip, $restored->proTip );
		$this->assertSame( $original->debugHelp, $restored->debugHelp );
		$this->assertSame( $original->alertCode, $restored->alertCode );
		$this->assertSame( $original->alertMessage, $restored->alertMessage );
		$this->assertSame( $original->guid, $restored->guid );
	}

	public function testJsonRoundTripsErrorAndClassification(): void {
		$original = new CheckResult(
			SpamVerdict::Spam,
			error: 'missing-required-field',
			classification: 'spam',
		);

		$json = json_encode( $original );
		$this->assertIsString( $json );

		$decoded = json_decode( $json, true );
		$this->assertSame( 'missing-required-field', $decoded['error'] );
		$this->assertSame( 'spam', $decoded['classification'] );

		$restored = CheckResult::fromJson( $decoded );

		$this->assertSame( $original->error, $restored->error );
		$this->assertSame( $original->classification, $restored->classification );
	}

	public function testFromJsonWithoutGuid(): void {
		$data = [
			'verdict' => 'ham',
			'proTip'  => null,
		];

		$result = CheckResult::fromJson( $data );

		$this->assertNull( $result->guid );
	}

	public function testFromJsonWithInvalidVerdict(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'expected one of: ham, spam, discard; got "unknown"' );

		CheckResult::fromJson( [ 'verdict' => 'unknown' ] );
	}

	public function testToArrayReturnsCorrectStructure(): void {
		$result = new CheckResult( SpamVerdict::Ham );
		$array  = $result->toArray();

		$this->assertArrayHasKey( 'verdict', $array );
		$this->assertArrayHasKey( 'proTip', $array );
		$this->assertArrayHasKey( 'debugHelp', $array );
		$this->assertArrayHasKey( 'alertCode', $array );
		$this->assertArrayHasKey( 'alertMessage', $array );
		$this->assertArrayHasKey( 'guid', $array );
		$this->assertArrayHasKey( 'alertMetadata', $array );
		$this->assertArrayHasKey( 'error', $array );
		$this->assertArrayHasKey( 'classification', $array );

		$this->assertSame( 'ham', $array['verdict'] );
		$this->assertNull( $array['guid'] );
		$this->assertNull( $array['alertMetadata'] );
		$this->assertNull( $array['error'] );
		$this->assertNull( $array['classification'] );
	}

	public function testToArrayIncludesAlertMetadata(): void {
		$metadata = new AlertMetadata(
			apiCalls: 15000,
			usageLimit: 10000,
			upgradePlan: 'Enterprise',
		);
		$result   = new CheckResult(
			SpamVerdict::Spam,
			'discard',
			'debug info',
			'10001',
			'alert message',
			'abc123def456',
			$metadata,
		);

		$array = $result->toArray();

		$this->assertSame( 'spam', $array['verdict'] );
		$this->assertSame( 'discard', $array['proTip'] );
		$this->assertSame( 'debug info', $array['debugHelp'] );
		$this->assertSame( '10001', $array['alertCode'] );
		$this->assertSame( 'alert message', $array['alertMessage'] );
		$this->assertSame( 'abc123def456', $array['guid'] );
		$this->assertIsArray( $array['alertMetadata'] );
		$this->assertSame( 15000, $array['alertMetadata']['apiCalls'] );
	}

	public function testJsonSerializeDelegatesToToArray(): void {
		$result = new CheckResult( SpamVerdict::Spam, 'discard' );
		$this->assertSame( $result->toArray(), $result->jsonSerialize() );
	}

	public function testFromResponseParsesAlertMetadata(): void {
		$result = CheckResult::fromResponse(
			'true',
			[
				'X-Akismet-Alert-Code'                  => '10502',
				'X-Akismet-Alert-Msg'                   => 'Usage limit warning',
				'X-Akismet-Alert-Api-Calls'             => '15000',
				'X-Akismet-Alert-Usage-Limit'           => '10000',
				'X-Akismet-Alert-Upgrade-Plan'          => 'Enterprise',
				'X-Akismet-Alert-Upgrade-Url'           => 'https://akismet.com/account/',
				'X-Akismet-Alert-Upgrade-Type'          => 'qty',
				'X-Akismet-Alert-Upgrade-Via-Support'   => 'false',
				'X-Akismet-Alert-Recommended-Plan-Name' => 'Akismet Pro (500)',
			]
		);

		$this->assertSame( '10502', $result->alertCode );
		$this->assertSame( 'Usage limit warning', $result->alertMessage );

		$this->assertNotNull( $result->alertMetadata );
		$this->assertSame( 15000, $result->alertMetadata->apiCalls );
		$this->assertSame( 10000, $result->alertMetadata->usageLimit );
		$this->assertSame( 'Enterprise', $result->alertMetadata->upgradePlan );
		$this->assertSame( 'https://akismet.com/account/', $result->alertMetadata->upgradeUrl );
		$this->assertSame( 'qty', $result->alertMetadata->upgradeType );
		$this->assertFalse( $result->alertMetadata->upgradeViaSupport );
		$this->assertSame( 'Akismet Pro (500)', $result->alertMetadata->recommendedPlanName );
	}

	public function testFromResponseAlertMetadataNullWithoutExtendedHeaders(): void {
		$result = CheckResult::fromResponse(
			'false',
			[
				'X-Akismet-Alert-Code' => '10001',
				'X-Akismet-Alert-Msg'  => 'Some alert',
			]
		);

		$this->assertSame( '10001', $result->alertCode );
		$this->assertNull( $result->alertMetadata );
	}

	public function testFromResponseParsesRecheckAfterHeader(): void {
		$result = CheckResult::fromResponse(
			'false',
			[
				'X-Akismet-Recheck-After' => '120',
			]
		);

		$this->assertSame( 120, $result->recheckAfter );
		$this->assertTrue( $result->shouldRecheck() );
		$this->assertSame( SpamVerdict::Ham, $result->verdict );
	}

	public function testFromResponseWithoutRecheckAfterHeader(): void {
		$result = CheckResult::fromResponse( 'false' );

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testFromResponseTreatsEmptyRecheckAfterAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[ 'X-Akismet-Recheck-After' => '' ]
		);

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testShouldRecheckReturnsTrueOnlyWhenRecheckAfterIsSet(): void {
		$withRecheck    = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );
		$withoutRecheck = new CheckResult( SpamVerdict::Ham );

		$this->assertTrue( $withRecheck->shouldRecheck() );
		$this->assertFalse( $withoutRecheck->shouldRecheck() );
	}

	public function testToArrayIncludesRecheckAfter(): void {
		$result = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );
		$array  = $result->toArray();

		$this->assertArrayHasKey( 'recheckAfter', $array );
		$this->assertSame( 120, $array['recheckAfter'] );
	}

	public function testToArrayIncludesNullRecheckAfterWhenAbsent(): void {
		$result = new CheckResult( SpamVerdict::Ham );
		$array  = $result->toArray();

		$this->assertArrayHasKey( 'recheckAfter', $array );
		$this->assertNull( $array['recheckAfter'] );
	}

	public function testJsonRoundTripWithRecheckAfter(): void {
		$original = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );

		$json     = json_encode( $original );
		$decoded  = json_decode( $json, true );
		$restored = CheckResult::fromJson( $decoded );

		$this->assertSame( 120, $restored->recheckAfter );
		$this->assertTrue( $restored->shouldRecheck() );
	}

	public function testJsonRoundTripWithoutRecheckAfter(): void {
		$original = new CheckResult( SpamVerdict::Ham );

		$json     = json_encode( $original );
		$decoded  = json_decode( $json, true );
		$restored = CheckResult::fromJson( $decoded );

		$this->assertNull( $restored->recheckAfter );
		$this->assertFalse( $restored->shouldRecheck() );
	}

	public function testJsonRoundTripWithAlertMetadata(): void {
		$original = CheckResult::fromResponse(
			'true',
			[
				'X-Akismet-Alert-Code'        => '10502',
				'X-Akismet-Alert-Msg'         => 'Usage limit',
				'X-Akismet-Alert-Upgrade-Url' => 'https://akismet.com/account/',
				'X-Akismet-Alert-Api-Calls'   => '15000',
				'X-Akismet-Alert-Usage-Limit' => '10000',
			]
		);

		$json     = json_encode( $original );
		$decoded  = json_decode( $json, true );
		$restored = CheckResult::fromJson( $decoded );

		$this->assertNotNull( $restored->alertMetadata );
		$this->assertSame( $original->alertMetadata->upgradeUrl, $restored->alertMetadata->upgradeUrl );
		$this->assertSame( $original->alertMetadata->apiCalls, $restored->alertMetadata->apiCalls );
		$this->assertSame( $original->alertMetadata->usageLimit, $restored->alertMetadata->usageLimit );
	}

	public function testFromResponseTreatsNonNumericRecheckAfterAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[ 'X-Akismet-Recheck-After' => 'soon' ]
		);

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testFromResponseTreatsZeroRecheckAfterAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[ 'X-Akismet-Recheck-After' => '0' ]
		);

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testFromResponseTreatsNegativeRecheckAfterAsNull(): void {
		$result = CheckResult::fromResponse(
			'false',
			[ 'X-Akismet-Recheck-After' => '-5' ]
		);

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testFromJsonTreatsNonNumericRecheckAfterAsNull(): void {
		$data = [
			'verdict'      => 'ham',
			'recheckAfter' => 'abc',
		];

		$result = CheckResult::fromJson( $data );

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}

	public function testFromJsonTreatsFloatRecheckAfterAsInt(): void {
		$data = [
			'verdict'      => 'ham',
			'recheckAfter' => 120.0,
		];

		$result = CheckResult::fromJson( $data );

		$this->assertSame( 120, $result->recheckAfter );
		$this->assertTrue( $result->shouldRecheck() );
	}

	public function testFromJsonTreatsNegativeFloatRecheckAfterAsNull(): void {
		$data = [
			'verdict'      => 'ham',
			'recheckAfter' => -5.5,
		];

		$result = CheckResult::fromJson( $data );

		$this->assertNull( $result->recheckAfter );
		$this->assertFalse( $result->shouldRecheck() );
	}
}
