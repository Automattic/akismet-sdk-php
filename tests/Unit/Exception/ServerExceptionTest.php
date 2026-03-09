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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ServerException::class )]
final class ServerExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = ServerException::fromStatusCode( 500 );
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	#[DataProvider( 'statusCodeWithoutBodyProvider' )]
	public function testFromStatusCodeWithoutBody( int $statusCode ): void {
		$exception = ServerException::fromStatusCode( $statusCode );
		$this->assertSame( "Akismet API returned server error: {$statusCode}", $exception->getMessage() );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function statusCodeWithoutBodyProvider(): array {
		return [
			'500' => [ 500 ],
			'502' => [ 502 ],
		];
	}

	#[DataProvider( 'statusCodeWithBodyProvider' )]
	public function testFromStatusCodeWithBody( int $statusCode, string $body ): void {
		$exception = ServerException::fromStatusCode( $statusCode, $body );
		$this->assertStringContainsString( (string) $statusCode, $exception->getMessage() );
		$this->assertStringContainsString( $body, $exception->getMessage() );
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function statusCodeWithBodyProvider(): array {
		return [
			'503' => [ 503, 'Service temporarily unavailable' ],
			'504' => [ 504, 'Gateway timeout' ],
		];
	}

	public function testUnexpectedResponseIncludesBody(): void {
		$exception = ServerException::unexpectedResponse( 'some weird body' );
		$this->assertSame(
			'Unexpected Akismet API response: some weird body',
			$exception->getMessage()
		);
	}

	public function testUnexpectedResponseTruncatesLongBody(): void {
		$longBody  = str_repeat( 'x', 300 );
		$exception = ServerException::unexpectedResponse( $longBody );

		$this->assertStringContainsString( '...', $exception->getMessage() );
		// Message should be: "Unexpected Akismet API response: " (33 chars) + 200 chars + "..." (3 chars)
		$this->assertLessThanOrEqual( 236, strlen( $exception->getMessage() ) );
	}

	public function testUnexpectedResponseDoesNotTruncateShortBody(): void {
		$shortBody = str_repeat( 'x', 200 );
		$exception = ServerException::unexpectedResponse( $shortBody );

		$this->assertStringNotContainsString( '...', $exception->getMessage() );
	}

	public function testUnexpectedResponseWithEmptyBody(): void {
		$exception = ServerException::unexpectedResponse( '' );
		$this->assertSame( 'Unexpected Akismet API response: ', $exception->getMessage() );
	}
}
