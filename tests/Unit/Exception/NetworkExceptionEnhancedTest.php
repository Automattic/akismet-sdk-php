<?php

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\NetworkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( NetworkException::class )]
final class NetworkExceptionEnhancedTest extends TestCase {

	public function testFromEndpointIncludesContext(): void {
		$exception = NetworkException::fromEndpoint(
			'/1.1/comment-check',
			'Connection timeout'
		);

		$this->assertStringContainsString( '/1.1/comment-check', $exception->getMessage() );
		$this->assertStringContainsString( 'Connection timeout', $exception->getMessage() );
	}
}
