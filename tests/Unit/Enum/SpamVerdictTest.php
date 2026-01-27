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

#[CoversClass(SpamVerdict::class)]
final class SpamVerdictTest extends TestCase
{
    public function testHamIsNotSpam(): void
    {
        $this->assertFalse(SpamVerdict::Ham->isSpam());
    }

    public function testSpamIsSpam(): void
    {
        $this->assertTrue(SpamVerdict::Spam->isSpam());
    }

    public function testDiscardIsSpam(): void
    {
        $this->assertTrue(SpamVerdict::Discard->isSpam());
    }

    public function testHamShouldNotDiscard(): void
    {
        $this->assertFalse(SpamVerdict::Ham->shouldDiscard());
    }

    public function testSpamShouldNotDiscard(): void
    {
        $this->assertFalse(SpamVerdict::Spam->shouldDiscard());
    }

    public function testDiscardShouldDiscard(): void
    {
        $this->assertTrue(SpamVerdict::Discard->shouldDiscard());
    }

    #[DataProvider('verdictValueProvider')]
    public function testVerdictValues(SpamVerdict $verdict, string $expected): void
    {
        $this->assertSame($expected, $verdict->value);
    }

    /**
     * @return array<string, array{SpamVerdict, string}>
     */
    public static function verdictValueProvider(): array
    {
        return [
            'ham' => [SpamVerdict::Ham, 'ham'],
            'spam' => [SpamVerdict::Spam, 'spam'],
            'discard' => [SpamVerdict::Discard, 'discard'],
        ];
    }
}