<?php
/**
 * Tests for InvalidApiKeyException.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Exception;

use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( InvalidApiKeyException::class )]
final class InvalidApiKeyExceptionTest extends TestCase {

	public function testImplementsAkismetException(): void {
		$exception = InvalidApiKeyException::forKey( 'test-key' );
		$this->assertInstanceOf( AkismetException::class, $exception );
	}

	public function testForKeyMasksApiKey(): void {
		$exception = InvalidApiKeyException::forKey( 'abc123456789' );
		$this->assertStringContainsString( 'ab', $exception->getMessage() );
		$this->assertStringContainsString( '**********', $exception->getMessage() );
		$this->assertStringNotContainsString( 'c123456789', $exception->getMessage() );
	}

	public function testVerificationFailedWithoutDebugHelp(): void {
		$exception = InvalidApiKeyException::verificationFailed();
		$this->assertSame( 'Akismet API key verification failed', $exception->getMessage() );
	}

	public function testVerificationFailedWithDebugHelp(): void {
		$exception = InvalidApiKeyException::verificationFailed( 'Invalid blog URL' );
		$this->assertStringContainsString( 'Invalid blog URL', $exception->getMessage() );
	}

	public function testForKeyWithShortKeyShowsAtMostTwoChars(): void {
		$exception = InvalidApiKeyException::forKey( 'ab' );
		// 2-char key: 'ab' visible, no mask
		$this->assertStringContainsString( 'ab', $exception->getMessage() );
	}

	public function testForKeyWithOneCharKey(): void {
		$exception = InvalidApiKeyException::forKey( 'a' );
		// 1-char key: 'a' visible, no mask
		$this->assertStringContainsString( 'a', $exception->getMessage() );
	}

	public function testForKeyWithFourCharKeyShowsTwoAndMasksRest(): void {
		$exception = InvalidApiKeyException::forKey( 'abcd' );
		// 4-char key: 'ab' visible + '**' masked
		$this->assertStringContainsString( 'ab**', $exception->getMessage() );
		$this->assertStringNotContainsString( 'abcd', $exception->getMessage() );
	}
}
