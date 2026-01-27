<?php
/**
 * Tests for CheckResult DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\Enum\SpamVerdict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckResult::class)]
final class CheckResultTest extends TestCase
{
    public function testIsSpamReturnsTrueForSpamVerdict(): void
    {
        $result = new CheckResult(SpamVerdict::Spam);
        $this->assertTrue($result->isSpam());
    }

    public function testIsSpamReturnsTrueForDiscardVerdict(): void
    {
        $result = new CheckResult(SpamVerdict::Discard);
        $this->assertTrue($result->isSpam());
    }

    public function testIsSpamReturnsFalseForHamVerdict(): void
    {
        $result = new CheckResult(SpamVerdict::Ham);
        $this->assertFalse($result->isSpam());
    }

    public function testShouldDiscardReturnsTrueOnlyForDiscardVerdict(): void
    {
        $this->assertFalse((new CheckResult(SpamVerdict::Ham))->shouldDiscard());
        $this->assertFalse((new CheckResult(SpamVerdict::Spam))->shouldDiscard());
        $this->assertTrue((new CheckResult(SpamVerdict::Discard))->shouldDiscard());
    }

    public function testFromResponseWithFalseBody(): void
    {
        $result = CheckResult::fromResponse('false');

        $this->assertSame(SpamVerdict::Ham, $result->verdict);
        $this->assertFalse($result->isSpam());
    }

    public function testFromResponseWithTrueBody(): void
    {
        $result = CheckResult::fromResponse('true');

        $this->assertSame(SpamVerdict::Spam, $result->verdict);
        $this->assertTrue($result->isSpam());
        $this->assertFalse($result->shouldDiscard());
    }

    public function testFromResponseWithDiscardProTip(): void
    {
        $result = CheckResult::fromResponse('true', [
            'X-akismet-pro-tip' => 'discard',
        ]);

        $this->assertSame(SpamVerdict::Discard, $result->verdict);
        $this->assertTrue($result->isSpam());
        $this->assertTrue($result->shouldDiscard());
        $this->assertSame('discard', $result->proTip);
    }

    public function testFromResponseExtractsHeaders(): void
    {
        $result = CheckResult::fromResponse('true', [
            'X-Akismet-Debug-Help' => 'Some debug info',
            'X-Akismet-Alert-Code' => '10001',
            'X-Akismet-Alert-Msg' => 'Usage limit warning',
        ]);

        $this->assertSame('Some debug info', $result->debugHelp);
        $this->assertSame('10001', $result->alertCode);
        $this->assertSame('Usage limit warning', $result->alertMessage);
    }

    public function testFromResponseHandlesCaseInsensitiveHeaders(): void
    {
        $result = CheckResult::fromResponse('false', [
            'x-akismet-debug-help' => 'lowercase headers',
        ]);

        $this->assertSame('lowercase headers', $result->debugHelp);
    }

    public function testJsonSerializeAndFromJson(): void
    {
        $original = new CheckResult(
            SpamVerdict::Spam,
            'discard',
            'debug info',
            '10001',
            'alert message',
        );

        $json = json_encode($original);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $restored = CheckResult::fromJson($decoded);

        $this->assertSame($original->verdict, $restored->verdict);
        $this->assertSame($original->proTip, $restored->proTip);
        $this->assertSame($original->debugHelp, $restored->debugHelp);
        $this->assertSame($original->alertCode, $restored->alertCode);
        $this->assertSame($original->alertMessage, $restored->alertMessage);
    }

    public function testJsonSerializeReturnsCorrectStructure(): void
    {
        $result = new CheckResult(SpamVerdict::Ham);
        $json = $result->jsonSerialize();

        $this->assertArrayHasKey('verdict', $json);
        $this->assertArrayHasKey('proTip', $json);
        $this->assertArrayHasKey('debugHelp', $json);
        $this->assertArrayHasKey('alertCode', $json);
        $this->assertArrayHasKey('alertMessage', $json);

        $this->assertSame('ham', $json['verdict']);
    }
}