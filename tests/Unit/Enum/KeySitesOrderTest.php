<?php
/**
 * Tests for KeySitesOrder enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\KeySitesOrder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( KeySitesOrder::class )]
final class KeySitesOrderTest extends TestCase {

	public function testTotalValue(): void {
		$this->assertSame( 'total', KeySitesOrder::Total->value );
	}

	public function testSpamValue(): void {
		$this->assertSame( 'spam', KeySitesOrder::Spam->value );
	}

	public function testHamValue(): void {
		$this->assertSame( 'ham', KeySitesOrder::Ham->value );
	}

	public function testMissedSpamValue(): void {
		$this->assertSame( 'missed_spam', KeySitesOrder::MissedSpam->value );
	}

	public function testFalsePositivesValue(): void {
		$this->assertSame( 'false_positives', KeySitesOrder::FalsePositives->value );
	}

	public function testFromValidString(): void {
		$this->assertSame( KeySitesOrder::Total, KeySitesOrder::from( 'total' ) );
		$this->assertSame( KeySitesOrder::MissedSpam, KeySitesOrder::from( 'missed_spam' ) );
	}

	public function testTryFromInvalidStringReturnsNull(): void {
		$this->assertNull( KeySitesOrder::tryFrom( 'invalid' ) );
	}
}
