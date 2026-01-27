<?php
/**
 * Tests for ValidationException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Automattic\Akismet\Exception\ValidationException
 */
final class ValidationExceptionTest extends TestCase
{
    public function testImplementsAkismetException(): void
    {
        $exception = ValidationException::missingRequired(['user_ip']);
        $this->assertInstanceOf(AkismetException::class, $exception);
    }

    public function testMissingRequired(): void
    {
        $exception = ValidationException::missingRequired(['user_ip', 'blog']);
        $this->assertStringContainsString('user_ip', $exception->getMessage());
        $this->assertStringContainsString('blog', $exception->getMessage());
        $this->assertSame(['user_ip', 'blog'], $exception->getMissingFields());
    }

    public function testInvalidValue(): void
    {
        $exception = ValidationException::invalidValue('user_ip', 'must be a valid IP address');
        $this->assertStringContainsString('user_ip', $exception->getMessage());
        $this->assertStringContainsString('must be a valid IP address', $exception->getMessage());
    }

    public function testGetMissingFieldsReturnsEmptyArrayByDefault(): void
    {
        $exception = ValidationException::invalidValue('field', 'reason');
        $this->assertSame([], $exception->getMissingFields());
    }
}