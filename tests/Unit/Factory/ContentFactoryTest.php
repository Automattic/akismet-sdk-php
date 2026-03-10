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

	public function testFromArrayReturnsNullForInvalidDateStrings(): void {
		$data = [
			'userIp'  => '192.168.1.1',
			'dateGmt' => 'not-a-date',
		];

		$content = ContentFactory::fromArray( $data );

		$this->assertNull( $content->dateGmt );
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
