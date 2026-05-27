<?php
/**
 * Tests for ContentFactory.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Factory;

use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\CheckResponse;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Factory\ContentFactory;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass( ContentFactory::class )]
#[UsesClass( CheckResponse::class )]
#[UsesClass( Content::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class ContentFactoryTest extends TestCase {

	public function testFromRequestExtractsBasicInfo(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.100' ],
			headers: [
				'User-Agent' => 'Mozilla/5.0 Test Browser',
				'Referer'    => 'https://google.com/search',
			],
		);

		$content = ContentFactory::fromRequest( $request );

		$this->assertSame( '192.168.1.100', $content->userIp );
		$this->assertSame( 'Mozilla/5.0 Test Browser', $content->userAgent );
		$this->assertSame( 'https://google.com/search', $content->referrer );
	}

	public function testFromRequestExtractsForwardedIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50, 70.41.3.18, 150.172.238.178' ],
		);

		$content = ContentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		// Should use the first IP from X-Forwarded-For
		$this->assertSame( '203.0.113.50', $content->userIp );
	}

	public function testFromRequestExtractsCloudflareIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'CF-Connecting-IP' => '198.51.100.25' ],
		);

		$content = ContentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		$this->assertSame( '198.51.100.25', $content->userIp );
	}

	public function testFromRequestExtractsXRealIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Real-IP' => '198.51.100.50' ],
		);

		$content = ContentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		$this->assertSame( '198.51.100.50', $content->userIp );
	}

	public function testFromRequestExtractsTrueClientIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'True-Client-IP' => '203.0.113.75' ],
		);

		$content = ContentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		$this->assertSame( '203.0.113.75', $content->userIp );
	}

	public function testFromRequestIgnoresForwardedHeadersWithoutTrustedProxies(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50' ],
		);

		$content = ContentFactory::fromRequest( $request );

		$this->assertSame( '10.0.0.1', $content->userIp );
	}

	public function testFromRequestTrustsForwardedHeadersWithWildcard(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50' ],
		);

		$content = ContentFactory::fromRequest( $request, trustedProxies: [ '*' ] );

		$this->assertSame( '203.0.113.50', $content->userIp );
	}

	public function testFromRequestWithAllParameters(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.1' ],
			headers: [ 'User-Agent' => 'Test Browser' ],
		);

		$date = new DateTimeImmutable( '2024-01-15T10:30:00Z' );

		$content = ContentFactory::fromRequest(
			request: $request,
			body: 'Test message content',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: ContentType::ContactForm,
			permalink: 'https://example.com/contact',
			dateGmt: $date,
			userRole: 'guest',
			recheckReason: 'manual review',
			honeypotFieldName: 'website',
			honeypotFieldValue: '',
		);

		$this->assertSame( 'Test message content', $content->body );
		$this->assertSame( 'John Doe', $content->authorName );
		$this->assertSame( 'john@example.com', $content->authorEmail );
		$this->assertSame( 'https://john.example.com', $content->authorUrl );
		$this->assertSame( ContentType::ContactForm, $content->type );
		$this->assertSame( 'https://example.com/contact', $content->permalink );
		$this->assertSame( $date, $content->dateGmt );
		$this->assertSame( 'guest', $content->userRole );
		$this->assertSame( 'website', $content->honeypotFieldName );
	}

	public function testFromRequestPassesContext(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.1' ],
			headers: [],
		);

		$content = ContentFactory::fromRequest(
			request: $request,
			context: 'sidebar-widget',
		);

		$this->assertSame( 'sidebar-widget', $content->context );
	}

	public function testFromRequestPassesRequestFields(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.1' ],
			headers: [],
		);

		$content = ContentFactory::fromRequest(
			request: $request,
			blogLang: 'en',
			blogCharset: 'UTF-8',
			contextValues: [ 'contact-form' ],
			classify: true,
		);

		$this->assertSame( 'en', $content->blogLang );
		$this->assertSame( 'UTF-8', $content->blogCharset );
		$this->assertSame( [ 'contact-form' ], $content->contextValues );
		$this->assertTrue( $content->classify );
	}

	public function testFromRequestAcceptsCallbackParameter(): void {
		$request = $this->createMockRequest(
			[ 'REMOTE_ADDR' => '203.0.113.1' ],
			[ 'User-Agent' => 'TestBot/1.0' ],
		);

		$content = ContentFactory::fromRequest(
			$request,
			callback: 'https://example.com/webhook',
		);

		$this->assertSame( 'https://example.com/webhook', $content->callback );
		$this->assertSame( 'https://example.com/webhook', $content->toArray()['callback'] );
	}

	public function testFromRequestExtractsServerVariables(): void {
		$request = $this->createMockRequest(
			serverParams: [
				'REMOTE_ADDR'          => '192.168.1.1',
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
				'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
				'SERVER_NAME'          => 'example.com',
				'REQUEST_URI'          => '/contact',
				'SOME_OTHER_VAR'       => 'should be included now',
			],
			headers: [],
		);

		$content = ContentFactory::fromRequest( $request );

		$this->assertArrayHasKey( 'HTTP_ACCEPT_LANGUAGE', $content->serverVariables );
		$this->assertArrayHasKey( 'HTTP_ACCEPT_ENCODING', $content->serverVariables );
		$this->assertArrayHasKey( 'SERVER_NAME', $content->serverVariables );
		$this->assertArrayHasKey( 'REQUEST_URI', $content->serverVariables );
		$this->assertArrayHasKey( 'SOME_OTHER_VAR', $content->serverVariables );
	}

	public function testFromRequestExcludesSensitiveServerVariables(): void {
		$request = $this->createMockRequest(
			serverParams: [
				'REMOTE_ADDR'  => '192.168.1.1',
				'SERVER_NAME'  => 'example.com',
				'HTTP_COOKIE'  => 'session=abc123',
				'HTTP_COOKIE2' => 'old_cookie=xyz',
				'PHP_AUTH_PW'  => 'secret_password',
			],
			headers: [],
		);

		$content = ContentFactory::fromRequest( $request );

		$this->assertArrayHasKey( 'SERVER_NAME', $content->serverVariables );
		$this->assertArrayNotHasKey( 'HTTP_COOKIE', $content->serverVariables );
		$this->assertArrayNotHasKey( 'HTTP_COOKIE2', $content->serverVariables );
		$this->assertArrayNotHasKey( 'PHP_AUTH_PW', $content->serverVariables );
	}

	public function testFromRequestWithMissingIpThrowsValidationException(): void {
		$request = $this->createMockRequest(
			serverParams: [],
			headers: [],
		);

		$this->expectException( ValidationException::class );

		ContentFactory::fromRequest( $request );
	}

	public function testFromArrayWithCamelCaseKeys(): void {
		$data = [
			'userIp'      => '192.168.1.1',
			'userAgent'   => 'Test Browser',
			'body'        => 'Test content',
			'authorName'  => 'Jane Doe',
			'authorEmail' => 'jane@example.com',
			'type'        => 'contact-form',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( '192.168.1.1', $content->userIp );
		$this->assertSame( 'Test Browser', $content->userAgent );
		$this->assertSame( 'Test content', $content->body );
		$this->assertSame( 'Jane Doe', $content->authorName );
		$this->assertSame( 'jane@example.com', $content->authorEmail );
		$this->assertSame( ContentType::ContactForm, $content->type );
	}

	public function testFromArrayWithSnakeCaseKeys(): void {
		$data = [
			'user_ip'              => '192.168.1.1',
			'user_agent'           => 'Test Browser',
			'comment_content'      => 'Test content',
			'comment_author'       => 'Jane Doe',
			'comment_author_email' => 'jane@example.com',
			'comment_author_url'   => 'https://jane.example.com',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( '192.168.1.1', $content->userIp );
		$this->assertSame( 'Test Browser', $content->userAgent );
		$this->assertSame( 'Test content', $content->body );
		$this->assertSame( 'Jane Doe', $content->authorName );
		$this->assertSame( 'jane@example.com', $content->authorEmail );
		$this->assertSame( 'https://jane.example.com', $content->authorUrl );
	}

	public function testFromArrayWithLegacyContentKey(): void {
		$data = [
			'userIp'  => '192.168.1.1',
			'content' => 'Legacy content value',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'Legacy content value', $content->body );
	}

	public function testFromArrayBodyKeyTakesPrecedenceOverContentKey(): void {
		$data = [
			'userIp'  => '192.168.1.1',
			'body'    => 'New body value',
			'content' => 'Legacy content value',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'New body value', $content->body );
	}

	public function testFromArrayReadsContext(): void {
		$data = [
			'userIp'  => '192.168.1.1',
			'context' => 'footer-form',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'footer-form', $content->context );
	}

	public function testFromArrayReadsCommentContextKey(): void {
		$data = [
			'user_ip'         => '192.168.1.1',
			'comment_context' => 'footer-form',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'footer-form', $content->context );
	}

	public function testFromArrayReadsRequestFields(): void {
		$content = ContentFactory::fromArray(
			[
				'user_ip'         => '192.168.1.1',
				'blog_lang'       => 'en, fr_ca',
				'blog_charset'    => 'UTF-8',
				'comment_context' => [ 'contact-form', 'pricing-page' ],
				'classify'        => '1',
			]
		);

		$this->assertSame( 'en, fr_ca', $content->blogLang );
		$this->assertSame( 'UTF-8', $content->blogCharset );
		$this->assertSame( [ 'contact-form', 'pricing-page' ], $content->contextValues );
		$this->assertTrue( $content->classify );
	}

	public function testFromArrayReadsCamelCaseRequestFields(): void {
		$content = ContentFactory::fromArray(
			[
				'userIp'        => '192.168.1.1',
				'blogLang'      => 'en',
				'blogCharset'   => 'UTF-8',
				'contextValues' => [ 'contact-form' ],
				'classify'      => true,
			]
		);

		$this->assertSame( 'en', $content->blogLang );
		$this->assertSame( 'UTF-8', $content->blogCharset );
		$this->assertSame( [ 'contact-form' ], $content->contextValues );
		$this->assertTrue( $content->classify );
	}

	/**
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public static function classifyAcceptedValues(): array {
		return [
			'bool true'      => [ true, true ],
			'bool false'     => [ false, false ],
			'int 1'          => [ 1, true ],
			'int 0'          => [ 0, false ],
			'string "1"'     => [ '1', true ],
			'string "0"'     => [ '0', false ],
			'string "true"'  => [ 'true', true ],
			'string "false"' => [ 'false', false ],
			'string "TRUE"'  => [ 'TRUE', true ],
			'string "False"' => [ 'False', false ],
			'missing key'    => [ '__MISSING__', false ],
		];
	}

	#[DataProvider( 'classifyAcceptedValues' )]
	public function testFromArrayAcceptsClassifyValues( mixed $input, bool $expected ): void {
		$data = [ 'user_ip' => '192.168.1.1' ];
		if ( $input !== '__MISSING__' ) {
			$data['classify'] = $input;
		}

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( $expected, $content->classify );
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function classifyRejectedValues(): array {
		return [
			'unknown int'    => [ 2 ],
			'unknown string' => [ 'yes' ],
			'whitespace'     => [ 'true ' ],
			'float'          => [ 1.0 ],
			'array'          => [ [ 'enabled' ] ],
		];
	}

	#[DataProvider( 'classifyRejectedValues' )]
	public function testFromArrayRejectsBogusClassify( mixed $input ): void {
		$this->expectException( ValidationException::class );

		ContentFactory::fromArray(
			[
				'user_ip'  => '192.168.1.1',
				'classify' => $input,
			]
		);
	}

	public function testFromArrayReadsFeedbackFields(): void {
		$camelCase = ContentFactory::fromArray(
			[
				'userIp'               => '192.168.1.1',
				'reporter'             => 'admin-user',
				'commentCheckResponse' => 'true',
			]
		);

		$this->assertSame( 'admin-user', $camelCase->reporter );
		$this->assertSame( CheckResponse::Spam, $camelCase->commentCheckResponse );

		$snakeCase = ContentFactory::fromArray(
			[
				'user_ip'                => '192.168.1.1',
				'reporter'               => 'moderator',
				'comment_check_response' => 'false',
			]
		);

		$this->assertSame( 'moderator', $snakeCase->reporter );
		$this->assertSame( CheckResponse::Ham, $snakeCase->commentCheckResponse );
	}

	public function testFromArrayWithCustomContentType(): void {
		$data = [
			'userIp' => '192.168.1.1',
			'type'   => 'custom-type-not-in-enum',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'custom-type-not-in-enum', $content->type );
	}

	public function testFromArrayWithWireFormatCommentTypeKey(): void {
		$data = [
			'userIp'       => '192.168.1.1',
			'comment_type' => 'contact-form',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( ContentType::ContactForm, $content->type );
	}

	public function testFromArrayTypeKeyTakesPrecedenceOverCommentTypeKey(): void {
		$data = [
			'userIp'       => '192.168.1.1',
			'type'         => 'forum-post',
			'comment_type' => 'contact-form',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( ContentType::ForumPost, $content->type );
	}

	public function testFromArrayWithEnumContentType(): void {
		$data = [
			'userIp' => '192.168.1.1',
			'type'   => ContentType::Signup,
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( ContentType::Signup, $content->type );
	}

	public function testFromArrayAcceptsIso8601DateStrings(): void {
		$data = [
			'userIp'          => '192.168.1.1',
			'dateGmt'         => '2024-01-15T10:30:00+00:00',
			'postModifiedGmt' => '2024-02-20T14:00:00+00:00',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertNotNull( $content->dateGmt );
		$this->assertSame( '2024-01-15T10:30:00+00:00', $content->dateGmt->format( 'c' ) );
		$this->assertNotNull( $content->postModifiedGmt );
		$this->assertSame( '2024-02-20T14:00:00+00:00', $content->postModifiedGmt->format( 'c' ) );
	}

	public function testFromArrayThrowsOnInvalidDateStrings(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'dateGmt' );

		ContentFactory::fromArray(
			[
				'userIp'  => '192.168.1.1',
				'dateGmt' => 'not-a-date',
			]
		);
	}

	public function testFromArrayRoundTripsDateCorrectly(): void {
		$date         = new DateTimeImmutable( '2024-06-15T12:00:00+00:00' );
		$postModified = new DateTimeImmutable( '2024-06-10T08:30:00+00:00' );

		$original = new Content(
			userIp: '192.168.1.1',
			dateGmt: $date,
			postModifiedGmt: $postModified,
		);

		$array         = $original->toArray();
		$reconstructed = ContentFactory::fromArray( $array );

		$this->assertNotNull( $reconstructed->dateGmt );
		$this->assertSame( $date->format( 'c' ), $reconstructed->dateGmt->format( 'c' ) );
		$this->assertNotNull( $reconstructed->postModifiedGmt );
		$this->assertSame( $postModified->format( 'c' ), $reconstructed->postModifiedGmt->format( 'c' ) );
	}

	public function testFromArrayRoundTripsHoneypotCorrectly(): void {
		$original = new Content(
			userIp: '192.168.1.1',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: 'sneaky-bot-value',
		);

		$array         = $original->toArray();
		$reconstructed = ContentFactory::fromArray( $array );

		$this->assertSame( 'website_url', $reconstructed->honeypotFieldName );
		$this->assertSame( 'sneaky-bot-value', $reconstructed->honeypotFieldValue );
	}

	public function testFromArrayReadsHoneypotValueFromSnakeCaseKey(): void {
		$data = [
			'userIp'               => '192.168.1.1',
			'honeypotFieldName'    => 'website_url',
			'honeypot_field_value' => 'bot-input',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'website_url', $content->honeypotFieldName );
		$this->assertSame( 'bot-input', $content->honeypotFieldValue );
	}

	public function testFromArrayReturnsNullForEmptyStringDate(): void {
		$content = ContentFactory::fromArray(
			[
				'userIp'  => '192.168.1.1',
				'dateGmt' => '',
			]
		);

		$this->assertNull( $content->dateGmt );
	}

	public function testFromArrayReturnsNullForNonStringDate(): void {
		$content = ContentFactory::fromArray(
			[
				'userIp'  => '192.168.1.1',
				'dateGmt' => 42,
			]
		);

		$this->assertNull( $content->dateGmt );
	}

	public function testFromArrayWithOnlyCommentContentKey(): void {
		$data = [
			'userIp'          => '192.168.1.1',
			'comment_content' => 'Wire format only',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'Wire format only', $content->body );
	}

	public function testFromArrayContentKeyTakesPrecedenceOverCommentContentKey(): void {
		$data = [
			'userIp'          => '192.168.1.1',
			'content'         => 'Legacy content key',
			'comment_content' => 'Wire format key',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertSame( 'Legacy content key', $content->body );
	}

	public function testFromArrayNonStringBodyFallsThrough(): void {
		$data = [
			'userIp'          => '192.168.1.1',
			'body'            => 12345,
			'comment_content' => 'Fallback text',
		];

		$content = ContentFactory::fromArray( $data );

		// Non-string body is discarded by getString(), falls through to comment_content.
		$this->assertSame( 'Fallback text', $content->body );
	}

	public function testFromArrayRoundTripsAllFields(): void {
		$date         = new DateTimeImmutable( '2024-06-15T12:00:00+00:00' );
		$postModified = new DateTimeImmutable( '2024-06-10T08:30:00+00:00' );

		$original = new Content(
			userIp: '203.0.113.42',
			userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
			body: 'This is a test comment with all fields populated.',
			authorName: 'Jane Smith',
			authorEmail: 'jane@example.com',
			authorUrl: 'https://jane.example.com',
			type: ContentType::ContactForm,
			permalink: 'https://example.com/blog/post-123',
			referrer: 'https://google.com/search?q=example',
			dateGmt: $date,
			postModifiedGmt: $postModified,
			parentId: '456',
			userRole: 'subscriber',
			recheckReason: 'edit',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: 'bot-filled',
			context: 'sidebar-widget',
			reporter: 'admin',
			commentCheckResponse: CheckResponse::Spam,
			callback: 'https://example.com/webhook',
		);

		$array         = $original->toArray();
		$reconstructed = ContentFactory::fromArray( $array );

		$this->assertSame( $original->userIp, $reconstructed->userIp );
		$this->assertSame( $original->userAgent, $reconstructed->userAgent );
		$this->assertSame( $original->body, $reconstructed->body );
		$this->assertSame( $original->authorName, $reconstructed->authorName );
		$this->assertSame( $original->authorEmail, $reconstructed->authorEmail );
		$this->assertSame( $original->authorUrl, $reconstructed->authorUrl );
		$this->assertSame( ContentType::ContactForm, $reconstructed->type );
		$this->assertSame( $original->permalink, $reconstructed->permalink );
		$this->assertSame( $original->referrer, $reconstructed->referrer );
		$this->assertNotNull( $reconstructed->dateGmt );
		$this->assertSame( $date->format( 'c' ), $reconstructed->dateGmt->format( 'c' ) );
		$this->assertNotNull( $reconstructed->postModifiedGmt );
		$this->assertSame( $postModified->format( 'c' ), $reconstructed->postModifiedGmt->format( 'c' ) );
		$this->assertSame( $original->parentId, $reconstructed->parentId );
		$this->assertSame( $original->userRole, $reconstructed->userRole );
		$this->assertSame( $original->recheckReason, $reconstructed->recheckReason );
		$this->assertSame( $original->honeypotFieldName, $reconstructed->honeypotFieldName );
		$this->assertSame( $original->honeypotFieldValue, $reconstructed->honeypotFieldValue );
		$this->assertSame( $original->context, $reconstructed->context );
		$this->assertSame( $original->reporter, $reconstructed->reporter );
		$this->assertSame( CheckResponse::Spam, $reconstructed->commentCheckResponse );
		$this->assertSame( $original->callback, $reconstructed->callback );
	}

	public function testFromArrayWithEmptyArrayThrowsValidation(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'userIp' );

		ContentFactory::fromArray( [] );
	}

	/**
	 * Create a mock ServerRequestInterface.
	 *
	 * @param array<string, mixed>  $serverParams Server parameters.
	 * @param array<string, string> $headers      HTTP headers.
	 */
	private function createMockRequest( array $serverParams, array $headers ): ServerRequestInterface {
		$request = $this->createMock( ServerRequestInterface::class );

		$request->method( 'getServerParams' )
			->willReturn( $serverParams );

		$request->method( 'getHeaderLine' )
			->willReturnCallback(
				function ( string $name ) use ( $headers ): string {
					// Headers are case-insensitive
					foreach ( $headers as $key => $value ) {
						if ( strcasecmp( $key, $name ) === 0 ) {
							return $value;
						}
					}
					return '';
				}
			);

		return $request;
	}
}
