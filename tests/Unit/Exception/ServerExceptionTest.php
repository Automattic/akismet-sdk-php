<?php
/**
 * Tests for ServerException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\ServerException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ServerException::class )]
final class ServerExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = ServerException::fromStatusCode( 500 );
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	public function testFromStatusCodeWithoutBody(): void {
		$exception = ServerException::fromStatusCode( 500 );
		$this->assertSame( 'Akismet API returned server error: 500', $exception->getMessage() );
	}

	public function testFromStatusCodeWithBody(): void {
		$exception = ServerException::fromStatusCode( 503, 'Service temporarily unavailable' );
		$this->assertSame(
			'Akismet API returned server error: 503 - Service temporarily unavailable',
			$exception->getMessage()
		);
	}

	public function testFromStatusCodeWith502(): void {
		$exception = ServerException::fromStatusCode( 502 );
		$this->assertSame( 'Akismet API returned server error: 502', $exception->getMessage() );
	}

	public function testFromStatusCodeWith504(): void {
		$exception = ServerException::fromStatusCode( 504, 'Gateway timeout' );
		$this->assertStringContainsString( '504', $exception->getMessage() );
		$this->assertStringContainsString( 'Gateway timeout', $exception->getMessage() );
	}
}
