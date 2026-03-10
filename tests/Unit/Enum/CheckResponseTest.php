<?php
/**
 * Tests for CheckResponse enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\CheckResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( CheckResponse::class )]
final class CheckResponseTest extends TestCase {

	public function testSpamValueIsTrue(): void {
		$this->assertSame( 'true', CheckResponse::Spam->value );
	}

	public function testHamValueIsFalse(): void {
		$this->assertSame( 'false', CheckResponse::Ham->value );
	}

	public function testFromString(): void {
		$this->assertSame( CheckResponse::Spam, CheckResponse::from( 'true' ) );
		$this->assertSame( CheckResponse::Ham, CheckResponse::from( 'false' ) );
	}

	public function testTryFromReturnsNullForInvalidValue(): void {
		$this->assertNull( CheckResponse::tryFrom( 'maybe' ) );
	}
}
