<?php
/**
 * Tests for BadRequestException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\BadRequestException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( BadRequestException::class )]
final class BadRequestExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = BadRequestException::fromStatusCode( 400 );
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	public function testFromStatusCodeWithoutBody(): void {
		$exception = BadRequestException::fromStatusCode( 400 );
		$this->assertSame( 'Akismet API returned client error: 400', $exception->getMessage() );
	}

	public function testFromStatusCodeWithBody(): void {
		$exception = BadRequestException::fromStatusCode( 400, 'Invalid request parameters' );
		$this->assertSame(
			'Akismet API returned client error: 400 - Invalid request parameters',
			$exception->getMessage()
		);
	}

	public function testFromStatusCodeWith403(): void {
		$exception = BadRequestException::fromStatusCode( 403, 'Forbidden' );
		$this->assertStringContainsString( '403', $exception->getMessage() );
		$this->assertStringContainsString( 'Forbidden', $exception->getMessage() );
	}

	public function testFromStatusCodeWith404(): void {
		$exception = BadRequestException::fromStatusCode( 404 );
		$this->assertSame( 'Akismet API returned client error: 404', $exception->getMessage() );
	}

	public function testFromStatusCodeWith422(): void {
		$exception = BadRequestException::fromStatusCode( 422, 'Unprocessable entity' );
		$this->assertStringContainsString( '422', $exception->getMessage() );
		$this->assertStringContainsString( 'Unprocessable entity', $exception->getMessage() );
	}
}
