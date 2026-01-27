<?php
/**
 * Tests for RateLimitException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\RateLimitException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitException::class)]
final class RateLimitExceptionTest extends TestCase
{
    public function testImplementsAkismetException(): void
    {
        $exception = RateLimitException::fromResponse();
        $this->assertInstanceOf(AkismetException::class, $exception);
    }

    public function testFromResponseStoresRetryAfterSeconds(): void
    {
        // retryAfter is just stored metadata, not an actual delay
        $exception = RateLimitException::fromResponse(60);
        $this->assertSame(60, $exception->getRetryAfter());
        $this->assertStringContainsString('rate limit', $exception->getMessage());
    }

    public function testFromResponseWithoutRetryAfter(): void
    {
        $exception = RateLimitException::fromResponse();
        $this->assertNull($exception->getRetryAfter());
    }

    public function testThrottled(): void
    {
        $exception = RateLimitException::throttled();
        $this->assertStringContainsString('throttled', $exception->getMessage());
        $this->assertNull($exception->getRetryAfter());
    }
}