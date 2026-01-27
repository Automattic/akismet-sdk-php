<?php
/**
 * Tests for SiteStats DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\SiteStats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteStats::class)]
final class SiteStatsTest extends TestCase
{
    public function testCreatesWithAllFields(): void
    {
        $stats = new SiteStats(
            site: 'example.com',
            totalCalls: 1000,
            spam: 400,
            ham: 580,
            missedSpam: 10,
            falsePositives: 5,
            isRevoked: false,
        );

        $this->assertSame('example.com', $stats->site);
        $this->assertSame(1000, $stats->totalCalls);
        $this->assertSame(400, $stats->spam);
        $this->assertSame(580, $stats->ham);
        $this->assertSame(10, $stats->missedSpam);
        $this->assertSame(5, $stats->falsePositives);
        $this->assertFalse($stats->isRevoked);
    }

    public function testGetAccuracyCalculatesCorrectly(): void
    {
        // 1000 calls, 15 errors (10 missed + 5 false positives) = 98.5% accuracy
        $stats = new SiteStats('example.com', 1000, 400, 580, 10, 5, false);
        $this->assertSame(98.5, $stats->getAccuracy());
    }

    public function testGetAccuracyReturnsNullForZeroCalls(): void
    {
        $stats = new SiteStats('example.com', 0, 0, 0, 0, 0, false);
        $this->assertNull($stats->getAccuracy());
    }

    public function testGetAccuracyReturnsPerfectScore(): void
    {
        $stats = new SiteStats('example.com', 1000, 400, 600, 0, 0, false);
        $this->assertSame(100.0, $stats->getAccuracy());
    }

    public function testFromResponseWithApiCalls(): void
    {
        $data = [
            'site' => 'test.example.com',
            'api_calls' => 5000,
            'spam' => 2000,
            'ham' => 2950,
            'missed_spam' => 30,
            'false_positives' => 20,
            'is_revoked' => false,
        ];

        $stats = SiteStats::fromResponse($data);

        $this->assertSame('test.example.com', $stats->site);
        $this->assertSame(5000, $stats->totalCalls);
        $this->assertSame(2000, $stats->spam);
        $this->assertSame(2950, $stats->ham);
        $this->assertSame(30, $stats->missedSpam);
        $this->assertSame(20, $stats->falsePositives);
        $this->assertFalse($stats->isRevoked);
    }

    public function testFromResponseWithTotal(): void
    {
        $data = [
            'site' => 'test.example.com',
            'total' => 3000,
            'spam' => 1000,
            'ham' => 1990,
            'missed_spam' => 5,
            'false_positives' => 5,
            'is_revoked' => true,
        ];

        $stats = SiteStats::fromResponse($data);

        $this->assertSame(3000, $stats->totalCalls);
        $this->assertTrue($stats->isRevoked);
    }
}