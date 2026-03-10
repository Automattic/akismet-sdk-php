<?php

declare(strict_types=1);

/**
 * Testing Example
 *
 * This example shows how to test your Akismet integration using PHPUnit
 * and the AkismetInterface for mocking. Copy the test class into your
 * test suite and adapt to your application.
 *
 * The SDK provides AkismetInterface specifically for testability — you
 * never need to call the real API in your unit tests.
 */

// Example PHPUnit test class:

namespace App\Tests;

use Automattic\Akismet\AkismetInterface;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Enum\SpamVerdict;
use PHPUnit\Framework\TestCase;

class SpamCheckTest extends TestCase
{
    /**
     * Test that ham comments are approved.
     */
    public function testHamCommentIsApproved(): void
    {
        $akismet = $this->createMock(AkismetInterface::class);
        $akismet->method('check')
            ->willReturn(new CheckResult(SpamVerdict::Ham));

        $content = new Content(
            userIp: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            body: 'Great article!',
            authorName: 'Jane Doe',
            authorEmail: 'jane@example.com',
            type: ContentType::Comment
        );

        $result = $akismet->check($content);

        $this->assertFalse($result->isSpam());
        $this->assertFalse($result->shouldDiscard());
        $this->assertSame('ham', $result->verdict->value);
    }

    /**
     * Test that spam comments are rejected.
     */
    public function testSpamCommentIsRejected(): void
    {
        $akismet = $this->createMock(AkismetInterface::class);
        $akismet->method('check')
            ->willReturn(new CheckResult(SpamVerdict::Spam));

        $content = new Content(
            userIp: '10.0.0.1',
            userAgent: 'SpamBot/1.0',
            body: 'Buy cheap products now!',
            authorName: 'akismet-guaranteed-spam',
            authorEmail: 'akismet-guaranteed-spam@example.com',
            type: ContentType::Comment
        );

        $result = $akismet->check($content);

        $this->assertTrue($result->isSpam());
        $this->assertFalse($result->shouldDiscard());
    }

    /**
     * Test that blatant spam is flagged for discard.
     */
    public function testBlatantSpamIsDiscarded(): void
    {
        $akismet = $this->createMock(AkismetInterface::class);
        $akismet->method('check')
            ->willReturn(new CheckResult(SpamVerdict::Discard, proTip: 'discard'));

        $content = new Content(
            userIp: '10.0.0.1',
            body: 'Blatant spam content',
            type: ContentType::Comment
        );

        $result = $akismet->check($content);

        $this->assertTrue($result->isSpam());
        $this->assertTrue($result->shouldDiscard());
        $this->assertSame('discard', $result->proTip);
    }

    /**
     * Test CheckResult JSON round-trip for queue/fixture storage.
     */
    public function testCheckResultJsonRoundTrip(): void
    {
        $original = new CheckResult(
            SpamVerdict::Spam,
            proTip: null,
            debugHelp: 'test debug info',
        );

        // Serialize (CheckResult implements JsonSerializable)
        $json = json_encode($original);
        $this->assertIsString($json);

        // Deserialize
        $restored = CheckResult::fromJson(json_decode($json, true));

        $this->assertSame($original->verdict, $restored->verdict);
        $this->assertSame($original->proTip, $restored->proTip);
        $this->assertSame($original->debugHelp, $restored->debugHelp);
    }

    /**
     * Test that submitSpam is called with the correct comment.
     */
    public function testSubmitSpamIsCalledCorrectly(): void
    {
        $content = new Content(
            userIp: '10.0.0.1',
            body: 'Missed spam content',
            type: ContentType::Comment
        );

        $akismet = $this->createMock(AkismetInterface::class);
        $akismet->expects($this->once())
            ->method('submitSpam')
            ->with($this->identicalTo($content));

        $akismet->submitSpam($content);
    }
}
