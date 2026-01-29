<?php
/**
 * Tests for NetworkException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\NetworkException;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( NetworkException::class )]
final class NetworkExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = NetworkException::connectionFailed();
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	public function testConnectionFailed(): void {
		$exception = NetworkException::connectionFailed();
		$this->assertStringContainsString( 'connect', $exception->getMessage() );
	}

	public function testConnectionFailedWithPrevious(): void {
		$previous  = new Exception( 'DNS lookup failed' );
		$exception = NetworkException::connectionFailed( $previous );
		$this->assertSame( $previous, $exception->getPrevious() );
	}

	public function testTimeout(): void {
		$exception = NetworkException::timeout();
		$this->assertStringContainsString( 'timed out', $exception->getMessage() );
	}
}
