<?php
/**
 * Tests for ClientErrorException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\ClientErrorException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ClientErrorException::class )]
final class ClientErrorExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = ClientErrorException::fromStatusCode( 400 );
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	#[DataProvider( 'statusCodeWithoutBodyProvider' )]
	public function testFromStatusCodeWithoutBody( int $statusCode ): void {
		$exception = ClientErrorException::fromStatusCode( $statusCode );
		$this->assertSame( "Akismet API returned client error: {$statusCode}", $exception->getMessage() );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function statusCodeWithoutBodyProvider(): array {
		return [
			'400' => [ 400 ],
			'404' => [ 404 ],
		];
	}

	#[DataProvider( 'statusCodeWithBodyProvider' )]
	public function testFromStatusCodeWithBody( int $statusCode, string $body ): void {
		$exception = ClientErrorException::fromStatusCode( $statusCode, $body );
		$this->assertStringContainsString( (string) $statusCode, $exception->getMessage() );
		$this->assertStringContainsString( $body, $exception->getMessage() );
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function statusCodeWithBodyProvider(): array {
		return [
			'400' => [ 400, 'Invalid request parameters' ],
			'403' => [ 403, 'Forbidden' ],
			'422' => [ 422, 'Unprocessable entity' ],
		];
	}
}
