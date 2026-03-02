<?php
/**
 * Tests for Configuration.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Config;

use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Configuration::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class ConfigurationTest extends TestCase {

	public function testCreatesWithRequiredParameters(): void {
		$config = new Configuration( 'test-api-key', 'https://example.com' );

		$this->assertSame( 'test-api-key', $config->apiKey );
		$this->assertSame( 'https://example.com', $config->blog );
		$this->assertSame( Configuration::DEFAULT_BASE_URL, $config->baseUrl );
		$this->assertFalse( $config->isTest );
	}

	public function testCreatesWithAllParameters(): void {
		$config = new Configuration(
			'test-api-key',
			'https://example.com',
			'https://custom.api.com',
			true,
		);

		$this->assertSame( 'https://custom.api.com', $config->baseUrl );
		$this->assertTrue( $config->isTest );
	}

	public function testTrimsTrailingSlashFromBlog(): void {
		$config = new Configuration( 'key', 'https://example.com/' );
		$this->assertSame( 'https://example.com', $config->blog );
	}

	public function testTrimsTrailingSlashFromBaseUrl(): void {
		$config = new Configuration( 'key', 'https://example.com', 'https://api.com/' );
		$this->assertSame( 'https://api.com', $config->baseUrl );
	}

	public function testThrowsOnEmptyApiKey(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'apiKey' );

		new Configuration( '', 'https://example.com' );
	}

	public function testThrowsOnEmptyBlog(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'blog' );

		new Configuration( 'key', '' );
	}

	public function testThrowsOnInvalidBlogUrl(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'valid URL' );

		new Configuration( 'key', 'not-a-url' );
	}

	public function testWithTestModeReturnsNewInstance(): void {
		$config     = new Configuration( 'key', 'https://example.com' );
		$testConfig = $config->withTestMode();

		$this->assertNotSame( $config, $testConfig );
		$this->assertFalse( $config->isTest );
		$this->assertTrue( $testConfig->isTest );
	}
}
