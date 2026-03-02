<?php
/**
 * Tests for CommentFactory.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Factory;

use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Factory\CommentFactory;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass( CommentFactory::class )]
#[UsesClass( Comment::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class CommentFactoryTest extends TestCase {

	public function testFromRequestExtractsBasicInfo(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.100' ],
			headers: [
				'User-Agent' => 'Mozilla/5.0 Test Browser',
				'Referer'    => 'https://google.com/search',
			],
		);

		$comment = CommentFactory::fromRequest( $request );

		$this->assertSame( '192.168.1.100', $comment->userIp );
		$this->assertSame( 'Mozilla/5.0 Test Browser', $comment->userAgent );
		$this->assertSame( 'https://google.com/search', $comment->referrer );
	}

	public function testFromRequestExtractsForwardedIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50, 70.41.3.18, 150.172.238.178' ],
		);

		$comment = CommentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		// Should use the first IP from X-Forwarded-For
		$this->assertSame( '203.0.113.50', $comment->userIp );
	}

	public function testFromRequestExtractsCloudflareIpWithTrustedProxy(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'CF-Connecting-IP' => '198.51.100.25' ],
		);

		$comment = CommentFactory::fromRequest( $request, trustedProxies: [ '10.0.0.1' ] );

		$this->assertSame( '198.51.100.25', $comment->userIp );
	}

	public function testFromRequestIgnoresForwardedHeadersWithoutTrustedProxies(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50' ],
		);

		$comment = CommentFactory::fromRequest( $request );

		$this->assertSame( '10.0.0.1', $comment->userIp );
	}

	public function testFromRequestTrustsForwardedHeadersWithWildcard(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '10.0.0.1' ],
			headers: [ 'X-Forwarded-For' => '203.0.113.50' ],
		);

		$comment = CommentFactory::fromRequest( $request, trustedProxies: [ '*' ] );

		$this->assertSame( '203.0.113.50', $comment->userIp );
	}

	public function testFromRequestWithAllParameters(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.1' ],
			headers: [ 'User-Agent' => 'Test Browser' ],
		);

		$date = new DateTimeImmutable( '2024-01-15T10:30:00Z' );

		$comment = CommentFactory::fromRequest(
			request: $request,
			content: 'Test message content',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: CommentType::ContactForm,
			permalink: 'https://example.com/contact',
			dateGmt: $date,
			userRole: 'guest',
			recheckReason: 'manual review',
			honeypotFieldName: 'website',
			honeypotFieldValue: '',
		);

		$this->assertSame( 'Test message content', $comment->content );
		$this->assertSame( 'John Doe', $comment->authorName );
		$this->assertSame( 'john@example.com', $comment->authorEmail );
		$this->assertSame( 'https://john.example.com', $comment->authorUrl );
		$this->assertSame( CommentType::ContactForm, $comment->type );
		$this->assertSame( 'https://example.com/contact', $comment->permalink );
		$this->assertSame( $date, $comment->dateGmt );
		$this->assertSame( 'guest', $comment->userRole );
		$this->assertSame( 'website', $comment->honeypotFieldName );
	}

	public function testFromRequestPassesContext(): void {
		$request = $this->createMockRequest(
			serverParams: [ 'REMOTE_ADDR' => '192.168.1.1' ],
			headers: [],
		);

		$comment = CommentFactory::fromRequest(
			request: $request,
			context: 'sidebar-widget',
		);

		$this->assertSame( 'sidebar-widget', $comment->context );
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

		$comment = CommentFactory::fromRequest( $request );

		$this->assertArrayHasKey( 'HTTP_ACCEPT_LANGUAGE', $comment->serverVariables );
		$this->assertArrayHasKey( 'HTTP_ACCEPT_ENCODING', $comment->serverVariables );
		$this->assertArrayHasKey( 'SERVER_NAME', $comment->serverVariables );
		$this->assertArrayHasKey( 'REQUEST_URI', $comment->serverVariables );
		$this->assertArrayHasKey( 'SOME_OTHER_VAR', $comment->serverVariables );
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

		$comment = CommentFactory::fromRequest( $request );

		$this->assertArrayHasKey( 'SERVER_NAME', $comment->serverVariables );
		$this->assertArrayNotHasKey( 'HTTP_COOKIE', $comment->serverVariables );
		$this->assertArrayNotHasKey( 'HTTP_COOKIE2', $comment->serverVariables );
		$this->assertArrayNotHasKey( 'PHP_AUTH_PW', $comment->serverVariables );
	}

	public function testFromRequestWithMissingIpThrowsValidationException(): void {
		$request = $this->createMockRequest(
			serverParams: [],
			headers: [],
		);

		$this->expectException( ValidationException::class );

		CommentFactory::fromRequest( $request );
	}

	public function testFromArrayWithCamelCaseKeys(): void {
		$data = [
			'userIp'      => '192.168.1.1',
			'userAgent'   => 'Test Browser',
			'content'     => 'Test content',
			'authorName'  => 'Jane Doe',
			'authorEmail' => 'jane@example.com',
			'type'        => 'contact-form',
		];

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( '192.168.1.1', $comment->userIp );
		$this->assertSame( 'Test Browser', $comment->userAgent );
		$this->assertSame( 'Test content', $comment->content );
		$this->assertSame( 'Jane Doe', $comment->authorName );
		$this->assertSame( 'jane@example.com', $comment->authorEmail );
		$this->assertSame( CommentType::ContactForm, $comment->type );
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

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( '192.168.1.1', $comment->userIp );
		$this->assertSame( 'Test Browser', $comment->userAgent );
		$this->assertSame( 'Test content', $comment->content );
		$this->assertSame( 'Jane Doe', $comment->authorName );
		$this->assertSame( 'jane@example.com', $comment->authorEmail );
		$this->assertSame( 'https://jane.example.com', $comment->authorUrl );
	}

	public function testFromArrayReadsContext(): void {
		$data = [
			'userIp'  => '192.168.1.1',
			'context' => 'footer-form',
		];

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( 'footer-form', $comment->context );
	}

	public function testFromArrayReadsCommentContextKey(): void {
		$data = [
			'user_ip'         => '192.168.1.1',
			'comment_context' => 'footer-form',
		];

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( 'footer-form', $comment->context );
	}

	public function testFromArrayWithCustomCommentType(): void {
		$data = [
			'userIp' => '192.168.1.1',
			'type'   => 'custom-type-not-in-enum',
		];

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( 'custom-type-not-in-enum', $comment->type );
	}

	public function testFromArrayWithEnumCommentType(): void {
		$data = [
			'userIp' => '192.168.1.1',
			'type'   => CommentType::Signup,
		];

		$comment = CommentFactory::fromArray( $data );

		$this->assertSame( CommentType::Signup, $comment->type );
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
