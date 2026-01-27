<?php
/**
 * Tests for InvalidApiKeyException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Automattic\Akismet\Exception\InvalidApiKeyException
 */
final class InvalidApiKeyExceptionTest extends TestCase
{
    public function testImplementsAkismetException(): void
    {
        $exception = InvalidApiKeyException::forKey('test-key');
        $this->assertInstanceOf(AkismetException::class, $exception);
    }

    public function testForKeyMasksApiKey(): void
    {
        $exception = InvalidApiKeyException::forKey('abc123456789');
        $this->assertStringContainsString('abc1', $exception->getMessage());
        $this->assertStringContainsString('****', $exception->getMessage());
        $this->assertStringNotContainsString('123456789', $exception->getMessage());
    }

    public function testVerificationFailedWithoutDebugHelp(): void
    {
        $exception = InvalidApiKeyException::verificationFailed();
        $this->assertSame('Akismet API key verification failed', $exception->getMessage());
    }

    public function testVerificationFailedWithDebugHelp(): void
    {
        $exception = InvalidApiKeyException::verificationFailed('Invalid blog URL');
        $this->assertStringContainsString('Invalid blog URL', $exception->getMessage());
    }
}