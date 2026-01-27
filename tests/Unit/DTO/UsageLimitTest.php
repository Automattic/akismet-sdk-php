<?php
/**
 * Tests for UsageLimit DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\UsageLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsageLimit::class)]
final class UsageLimitTest extends TestCase
{
    public function testCreatesWithLimitedPlan(): void
    {
        $usage = new UsageLimit(
            limit: 10000,
            usage: 4500,
            percentage: '45.0%',
            throttled: false,
        );

        $this->assertSame(10000, $usage->limit);
        $this->assertSame(4500, $usage->usage);
        $this->assertSame('45.0%', $usage->percentage);
        $this->assertFalse($usage->throttled);
    }

    public function testIsUnlimitedReturnsFalseForLimitedPlan(): void
    {
        $usage = new UsageLimit(10000, 4500, '45.0%', false);
        $this->assertFalse($usage->isUnlimited());
    }

    public function testIsUnlimitedReturnsTrueForUnlimitedPlan(): void
    {
        $usage = new UsageLimit(null, 4500, '0%', false);
        $this->assertTrue($usage->isUnlimited());
    }

    public function testGetRemainingForLimitedPlan(): void
    {
        $usage = new UsageLimit(10000, 4500, '45.0%', false);
        $this->assertSame(5500, $usage->getRemaining());
    }

    public function testGetRemainingReturnsZeroWhenOverLimit(): void
    {
        $usage = new UsageLimit(10000, 12000, '120.0%', true);
        $this->assertSame(0, $usage->getRemaining());
    }

    public function testGetRemainingReturnsNullForUnlimited(): void
    {
        $usage = new UsageLimit(null, 4500, '0%', false);
        $this->assertNull($usage->getRemaining());
    }

    public function testFromResponseWithNumericLimit(): void
    {
        $data = [
            'limit' => 50000,
            'usage' => 12345,
            'percentage' => '24.69%',
            'throttled' => false,
        ];

        $usage = UsageLimit::fromResponse($data);

        $this->assertSame(50000, $usage->limit);
        $this->assertSame(12345, $usage->usage);
        $this->assertSame('24.69%', $usage->percentage);
        $this->assertFalse($usage->throttled);
    }

    public function testFromResponseWithNoneLimit(): void
    {
        $data = [
            'limit' => 'none',
            'usage' => 100000,
            'percentage' => '0%',
            'throttled' => false,
        ];

        $usage = UsageLimit::fromResponse($data);

        $this->assertNull($usage->limit);
        $this->assertTrue($usage->isUnlimited());
    }

    public function testFromResponseWithThrottled(): void
    {
        $data = [
            'limit' => 10000,
            'usage' => 15000,
            'percentage' => '150.0%',
            'throttled' => true,
        ];

        $usage = UsageLimit::fromResponse($data);

        $this->assertTrue($usage->throttled);
    }
}