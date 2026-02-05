<?php
/**
 * Tests for InputValidator.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Validator;

use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Test input validation.
 */
#[CoversClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class InputValidatorTest extends TestCase {

	/**
	 * @param non-empty-string $url
	 */
	#[DataProvider( 'validUrlProvider' )]
	public function test_validates_valid_urls( string $url ): void {
		$this->expectNotToPerformAssertions();
		InputValidator::validateUrl( $url );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function validUrlProvider(): array {
		return [
			'simple http'         => [ 'http://example.com' ],
			'simple https'        => [ 'https://example.com' ],
			'with path'           => [ 'https://example.com/path' ],
			'with query'          => [ 'https://example.com?foo=bar' ],
			'with fragment'       => [ 'https://example.com#section' ],
			'with port'           => [ 'https://example.com:8080' ],
			'with auth'           => [ 'https://user:pass@example.com' ],
			'subdomain'           => [ 'https://sub.example.com' ],
			'multiple subdomains' => [ 'https://deep.sub.example.com' ],
			'with trailing slash' => [ 'https://example.com/' ],
			'with complex path'   => [ 'https://example.com/path/to/page' ],
			'localhost'           => [ 'http://localhost' ],
			'localhost with port' => [ 'http://localhost:3000' ],
			'ip address'          => [ 'http://127.0.0.1' ],
			'ip with port'        => [ 'http://127.0.0.1:8080' ],
		];
	}

	/**
	 * @param non-empty-string $url
	 * @param non-empty-string $expectedMessage
	 */
	#[DataProvider( 'invalidUrlProvider' )]
	public function test_rejects_invalid_urls( string $url, string $expectedMessage ): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( $expectedMessage );
		InputValidator::validateUrl( $url );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidUrlProvider(): array {
		return [
			'empty string'      => [ '', 'must be a valid URL' ],
			'no scheme'         => [ 'example.com', 'must be a valid URL' ],
			'ftp scheme'        => [ 'ftp://example.com', 'must use http or https scheme' ],
			'javascript scheme' => [ 'javascript:alert(1)', 'must be a valid URL' ],
			'data scheme'       => [ 'data:text/plain,hello', 'must be a valid URL' ],
			'file scheme'       => [ 'file:///etc/passwd', 'must be a valid URL' ],
			'no host'           => [ 'https://', 'must be a valid URL' ],
			'only scheme'       => [ 'http://', 'must be a valid URL' ],
			'relative path'     => [ '/path/to/page', 'must be a valid URL' ],
			'protocol relative' => [ '//example.com', 'must be a valid URL' ],
		];
	}

	public function test_validates_url_with_custom_field_name(): void {
		try {
			InputValidator::validateUrl( '', 'customField' );
			$this->fail( 'Expected ValidationException was not thrown' );
		} catch ( ValidationException $e ) {
			$this->assertStringContainsString( 'customField', $e->getMessage() );
		}
	}

	/**
	 * @param non-empty-string $ip
	 */
	#[DataProvider( 'validIpProvider' )]
	public function test_validates_valid_ip_addresses( string $ip ): void {
		$this->expectNotToPerformAssertions();
		InputValidator::validateIp( $ip );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function validIpProvider(): array {
		return [
			'ipv4 simple'     => [ '192.168.1.1' ],
			'ipv4 localhost'  => [ '127.0.0.1' ],
			'ipv4 zero'       => [ '0.0.0.0' ],
			'ipv4 broadcast'  => [ '255.255.255.255' ],
			'ipv4 class a'    => [ '10.0.0.1' ],
			'ipv4 class b'    => [ '172.16.0.1' ],
			'ipv4 class c'    => [ '192.168.0.1' ],
			'ipv6 full'       => [ '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ],
			'ipv6 compressed' => [ '2001:db8:85a3::8a2e:370:7334' ],
			'ipv6 localhost'  => [ '::1' ],
			'ipv6 all zeros'  => [ '::' ],
			'ipv6 with ipv4'  => [ '::ffff:192.0.2.1' ],
		];
	}

	/**
	 * @param non-empty-string $ip
	 * @param non-empty-string $expectedMessage
	 */
	#[DataProvider( 'invalidIpProvider' )]
	public function test_rejects_invalid_ip_addresses( string $ip, string $expectedMessage ): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( $expectedMessage );
		InputValidator::validateIp( $ip );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidIpProvider(): array {
		return [
			'empty string'           => [ '', 'must be a valid IP address' ],
			'not an ip'              => [ 'not-an-ip', 'must be a valid IP address' ],
			'hostname'               => [ 'example.com', 'must be a valid IP address' ],
			'ipv4 too large'         => [ '256.1.1.1', 'must be a valid IP address' ],
			'ipv4 negative'          => [ '-1.0.0.1', 'must be a valid IP address' ],
			'ipv4 too many octets'   => [ '192.168.1.1.1', 'must be a valid IP address' ],
			'ipv4 too few octets'    => [ '192.168.1', 'must be a valid IP address' ],
			'ipv4 with letters'      => [ '192.168.a.1', 'must be a valid IP address' ],
			'ipv6 invalid'           => [ 'gggg::1', 'must be a valid IP address' ],
			'ipv6 too many segments' => [ '1:2:3:4:5:6:7:8:9', 'must be a valid IP address' ],
		];
	}

	public function test_validates_ip_with_custom_field_name(): void {
		try {
			InputValidator::validateIp( '', 'customIp' );
			$this->fail( 'Expected ValidationException was not thrown' );
		} catch ( ValidationException $e ) {
			$this->assertStringContainsString( 'customIp', $e->getMessage() );
		}
	}

	/**
	 * @param non-empty-string $email
	 */
	#[DataProvider( 'validEmailProvider' )]
	public function test_validates_valid_email_addresses( string $email ): void {
		$this->expectNotToPerformAssertions();
		InputValidator::validateEmail( $email );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function validEmailProvider(): array {
		return [
			'simple'          => [ 'user@example.com' ],
			'with plus'       => [ 'user+tag@example.com' ],
			'with dot'        => [ 'first.last@example.com' ],
			'with dash'       => [ 'user-name@example.com' ],
			'with underscore' => [ 'user_name@example.com' ],
			'subdomain'       => [ 'user@mail.example.com' ],
			'numbers'         => [ 'user123@example.com' ],
			'short domain'    => [ 'a@b.co' ],
			'long tld'        => [ 'user@example.photography' ],
		];
	}

	/**
	 * @param non-empty-string $email
	 * @param non-empty-string $expectedMessage
	 */
	#[DataProvider( 'invalidEmailProvider' )]
	public function test_rejects_invalid_email_addresses( string $email, string $expectedMessage ): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( $expectedMessage );
		InputValidator::validateEmail( $email );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function invalidEmailProvider(): array {
		return [
			'empty string'       => [ '', 'must be a valid email address' ],
			'no at sign'         => [ 'notanemail', 'must be a valid email address' ],
			'no domain'          => [ 'user@', 'must be a valid email address' ],
			'no local part'      => [ '@example.com', 'must be a valid email address' ],
			'multiple at signs'  => [ 'user@@example.com', 'must be a valid email address' ],
			'spaces'             => [ 'user name@example.com', 'must be a valid email address' ],
			'missing tld'        => [ 'user@example', 'must be a valid email address' ],
			'double dot'         => [ 'user..name@example.com', 'must be a valid email address' ],
			'leading dot'        => [ '.user@example.com', 'must be a valid email address' ],
			'trailing dot local' => [ 'user.@example.com', 'must be a valid email address' ],
		];
	}

	public function test_validates_email_with_custom_field_name(): void {
		try {
			InputValidator::validateEmail( '', 'customEmail' );
			$this->fail( 'Expected ValidationException was not thrown' );
		} catch ( ValidationException $e ) {
			$this->assertStringContainsString( 'customEmail', $e->getMessage() );
		}
	}
}
