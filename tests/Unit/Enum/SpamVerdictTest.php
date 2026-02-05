<?php
/**
 * Tests for SpamVerdict enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\SpamVerdict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( SpamVerdict::class )]
final class SpamVerdictTest extends TestCase {

	#[DataProvider( 'isSpamProvider' )]
	public function testIsSpam( SpamVerdict $verdict, bool $expectedIsSpam ): void {
		$this->assertSame( $expectedIsSpam, $verdict->isSpam() );
	}

	/**
	 * @return array<string, array{SpamVerdict, bool}>
	 */
	public static function isSpamProvider(): array {
		return [
			'ham is not spam' => [ SpamVerdict::Ham, false ],
			'spam is spam'    => [ SpamVerdict::Spam, true ],
			'discard is spam' => [ SpamVerdict::Discard, true ],
		];
	}

	#[DataProvider( 'shouldDiscardProvider' )]
	public function testShouldDiscard( SpamVerdict $verdict, bool $expectedShouldDiscard ): void {
		$this->assertSame( $expectedShouldDiscard, $verdict->shouldDiscard() );
	}

	/**
	 * @return array<string, array{SpamVerdict, bool}>
	 */
	public static function shouldDiscardProvider(): array {
		return [
			'ham should not discard'  => [ SpamVerdict::Ham, false ],
			'spam should not discard' => [ SpamVerdict::Spam, false ],
			'discard should discard'  => [ SpamVerdict::Discard, true ],
		];
	}

	#[DataProvider( 'verdictValueProvider' )]
	public function testVerdictValues( SpamVerdict $verdict, string $expected ): void {
		$this->assertSame( $expected, $verdict->value );
	}

	/**
	 * @return array<string, array{SpamVerdict, string}>
	 */
	public static function verdictValueProvider(): array {
		return [
			'ham'     => [ SpamVerdict::Ham, 'ham' ],
			'spam'    => [ SpamVerdict::Spam, 'spam' ],
			'discard' => [ SpamVerdict::Discard, 'discard' ],
		];
	}
}
