<?php
/**
 * Tests for Comment DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Comment::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class CommentTest extends TestCase {

	public function testCreatesWithRequiredFields(): void {
		$comment = new Comment( userIp: '192.168.1.1' );

		$this->assertSame( '192.168.1.1', $comment->userIp );
		$this->assertNull( $comment->userAgent );
		$this->assertNull( $comment->content );
	}

	public function testCreatesWithAllFields(): void {
		$date     = new DateTimeImmutable( '2024-01-15T10:30:00Z' );
		$postDate = new DateTimeImmutable( '2024-01-10T08:00:00Z' );

		$comment = new Comment(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			content: 'Test comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: CommentType::Comment,
			permalink: 'https://example.com/post/123',
			referrer: 'https://google.com',
			dateGmt: $date,
			postModifiedGmt: $postDate,
			parentId: '456',
			userRole: 'subscriber',
			recheckReason: 'manual review',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: '',
			serverVariables: [ 'HTTP_ACCEPT_LANGUAGE' => 'en-US' ],
		);

		$this->assertSame( '192.168.1.1', $comment->userIp );
		$this->assertSame( 'Mozilla/5.0', $comment->userAgent );
		$this->assertSame( 'Test comment', $comment->content );
		$this->assertSame( 'John Doe', $comment->authorName );
		$this->assertSame( 'john@example.com', $comment->authorEmail );
		$this->assertSame( CommentType::Comment, $comment->type );
	}

	public function testToArrayWithMinimalData(): void {
		$comment = new Comment( userIp: '192.168.1.1' );
		$array   = $comment->toArray();

		$this->assertSame( [ 'user_ip' => '192.168.1.1' ], $array );
	}

	public function testToArrayWithAllFields(): void {
		$date = new DateTimeImmutable( '2024-01-15T10:30:00+00:00' );

		$comment = new Comment(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			content: 'Test comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: CommentType::ContactForm,
			permalink: 'https://example.com/contact',
			referrer: 'https://google.com',
			dateGmt: $date,
			userRole: 'guest',
		);

		$array = $comment->toArray();

		$this->assertSame( '192.168.1.1', $array['user_ip'] );
		$this->assertSame( 'Mozilla/5.0', $array['user_agent'] );
		$this->assertSame( 'Test comment', $array['comment_content'] );
		$this->assertSame( 'John Doe', $array['comment_author'] );
		$this->assertSame( 'john@example.com', $array['comment_author_email'] );
		$this->assertSame( 'https://john.example.com', $array['comment_author_url'] );
		$this->assertSame( 'contact-form', $array['comment_type'] );
		$this->assertSame( 'https://example.com/contact', $array['permalink'] );
		$this->assertSame( 'https://google.com', $array['referrer'] );
		$this->assertSame( 'guest', $array['user_role'] );
		$this->assertArrayHasKey( 'comment_date_gmt', $array );
	}

	public function testToArrayWithStringCommentType(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			type: 'custom-type',
		);

		$array = $comment->toArray();
		$this->assertSame( 'custom-type', $array['comment_type'] );
	}

	public function testToArrayIncludesHoneypotField(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: 'spam-bot-filled-this',
		);

		$array = $comment->toArray();

		$this->assertSame( 'website_url', $array['honeypot_field_name'] );
		$this->assertSame( 'spam-bot-filled-this', $array['website_url'] );
	}

	public function testToArrayIncludesServerVariables(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			serverVariables: [
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
				'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
			],
		);

		$array = $comment->toArray();

		$this->assertSame( 'en-US,en;q=0.9', $array['HTTP_ACCEPT_LANGUAGE'] );
		$this->assertSame( 'gzip, deflate', $array['HTTP_ACCEPT_ENCODING'] );
	}

	public function testNormalizesEmptyStringToNullForAuthorEmail(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			authorEmail: '',
		);

		$this->assertNull( $comment->authorEmail );
		$this->assertArrayNotHasKey( 'comment_author_email', $comment->toArray() );
	}

	public function testNormalizesEmptyStringToNullForAuthorUrl(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			authorUrl: '',
		);

		$this->assertNull( $comment->authorUrl );
		$this->assertArrayNotHasKey( 'comment_author_url', $comment->toArray() );
	}

	public function testNormalizesEmptyStringToNullForPermalink(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			permalink: '',
		);

		$this->assertNull( $comment->permalink );
		$this->assertArrayNotHasKey( 'permalink', $comment->toArray() );
	}

	public function testValidatesAuthorUrl(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'authorUrl' );

		new Comment(
			userIp: '192.168.1.1',
			authorUrl: 'not-a-valid-url',
		);
	}

	public function testValidatesPermalink(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'permalink' );

		new Comment(
			userIp: '192.168.1.1',
			permalink: 'not-a-valid-url',
		);
	}

	public function testAcceptsValidUrls(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			authorUrl: 'https://example.com',
			permalink: 'https://example.com/post/123',
		);

		$this->assertSame( 'https://example.com', $comment->authorUrl );
		$this->assertSame( 'https://example.com/post/123', $comment->permalink );
	}

	public function testContextIncludedInToArray(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			context: 'sidebar-widget',
		);

		$array = $comment->toArray();

		$this->assertSame( 'sidebar-widget', $array['comment_context'] );
	}

	public function testContextNullOmittedFromToArray(): void {
		$comment = new Comment( userIp: '192.168.1.1' );

		$this->assertNull( $comment->context );
		$this->assertArrayNotHasKey( 'comment_context', $comment->toArray() );
	}

	public function testToArrayIncludesFeedbackFields(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			reporter: 'admin-user',
			commentCheckResponse: 'true',
		);

		$array = $comment->toArray();

		$this->assertSame( 'admin-user', $array['reporter'] );
		$this->assertSame( 'true', $array['comment_check_response'] );
	}

	public function testFeedbackFieldsNullByDefault(): void {
		$comment = new Comment( userIp: '192.168.1.1' );

		$this->assertNull( $comment->reporter );
		$this->assertNull( $comment->commentCheckResponse );
		$this->assertArrayNotHasKey( 'reporter', $comment->toArray() );
		$this->assertArrayNotHasKey( 'comment_check_response', $comment->toArray() );
	}

	public function testServerVariablesCannotOverrideFeedbackReservedKeys(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			reporter: 'admin-user',
			commentCheckResponse: 'true',
			serverVariables: [
				'reporter'               => 'evil-user',
				'comment_check_response' => 'false',
			],
		);

		$array = $comment->toArray();

		$this->assertSame( 'admin-user', $array['reporter'] );
		$this->assertSame( 'true', $array['comment_check_response'] );
	}

	public function testServerVariablesCannotOverrideReservedKeys(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			content: 'Legit comment',
			serverVariables: [
				'user_ip'         => '10.0.0.1',
				'user_agent'      => 'EvilBot',
				'comment_content' => 'Buy cheap stuff',
				'api_key'         => 'stolen-key',
				'blog'            => 'https://evil.com',
				'is_test'         => '0',
				'HTTP_ACCEPT'     => 'text/html',
				'REMOTE_ADDR'     => '172.16.0.1',
			],
		);

		$array = $comment->toArray();

		// Reserved keys retain their original values.
		$this->assertSame( '192.168.1.1', $array['user_ip'] );
		$this->assertSame( 'Mozilla/5.0', $array['user_agent'] );
		$this->assertSame( 'Legit comment', $array['comment_content'] );

		// Injected reserved keys must not appear.
		$this->assertArrayNotHasKey( 'api_key', $array );
		$this->assertArrayNotHasKey( 'blog', $array );
		$this->assertArrayNotHasKey( 'is_test', $array );

		// Non-reserved server variables are included.
		$this->assertSame( 'text/html', $array['HTTP_ACCEPT'] );
		$this->assertSame( '172.16.0.1', $array['REMOTE_ADDR'] );
	}

	public function testValidatesUserIp(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'userIp' );

		new Comment( userIp: 'not-a-valid-ip' );
	}

	public function testValidatesAuthorEmail(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'authorEmail' );

		new Comment(
			userIp: '192.168.1.1',
			authorEmail: 'not-a-valid-email',
		);
	}

	public function testAcceptsValidAuthorEmail(): void {
		$comment = new Comment(
			userIp: '192.168.1.1',
			authorEmail: 'user@example.com',
		);

		$this->assertSame( 'user@example.com', $comment->authorEmail );
	}
}
