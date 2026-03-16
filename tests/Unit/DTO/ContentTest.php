<?php
/**
 * Tests for Content DTO.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\DTO;

use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\CheckResponse;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\ValidationException;
use Automattic\Akismet\Validator\InputValidator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Content::class )]
#[UsesClass( CheckResponse::class )]
#[UsesClass( InputValidator::class )]
#[UsesClass( ValidationException::class )]
final class ContentTest extends TestCase {

	public function testCreatesWithRequiredFields(): void {
		$content = new Content( userIp: '192.168.1.1' );

		$this->assertSame( '192.168.1.1', $content->userIp );
		$this->assertNull( $content->userAgent );
		$this->assertNull( $content->body );
	}

	public function testCreatesWithAllFields(): void {
		$date     = new DateTimeImmutable( '2024-01-15T10:30:00Z' );
		$postDate = new DateTimeImmutable( '2024-01-10T08:00:00Z' );

		$content = new Content(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			body: 'Test comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: ContentType::Comment,
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

		$this->assertSame( '192.168.1.1', $content->userIp );
		$this->assertSame( 'Mozilla/5.0', $content->userAgent );
		$this->assertSame( 'Test comment', $content->body );
		$this->assertSame( 'John Doe', $content->authorName );
		$this->assertSame( 'john@example.com', $content->authorEmail );
		$this->assertSame( ContentType::Comment, $content->type );
	}

	public function testToArrayWithMinimalData(): void {
		$content = new Content( userIp: '192.168.1.1' );
		$array   = $content->toArray();

		$this->assertSame( [ 'user_ip' => '192.168.1.1' ], $array );
	}

	public function testToArrayWithAllFields(): void {
		$date = new DateTimeImmutable( '2024-01-15T10:30:00+00:00' );

		$content = new Content(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			body: 'Test comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
			authorUrl: 'https://john.example.com',
			type: ContentType::ContactForm,
			permalink: 'https://example.com/contact',
			referrer: 'https://google.com',
			dateGmt: $date,
			userRole: 'guest',
		);

		$array = $content->toArray();

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

	public function testToArrayWithStringContentType(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			type: 'custom-type',
		);

		$array = $content->toArray();
		$this->assertSame( 'custom-type', $array['comment_type'] );
	}

	public function testToArrayIncludesHoneypotField(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: 'spam-bot-filled-this',
		);

		$array = $content->toArray();

		$this->assertSame( 'website_url', $array['honeypot_field_name'] );
		$this->assertSame( 'spam-bot-filled-this', $array['website_url'] );
	}

	public function testToArrayServerVariablesDoNotOverwriteHoneypot(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			honeypotFieldName: 'website_url',
			honeypotFieldValue: 'spam-bot-filled-this',
			serverVariables: [
				'website_url' => 'should-be-ignored',
			],
		);

		$array = $content->toArray();

		$this->assertSame( 'spam-bot-filled-this', $array['website_url'] );
	}

	public function testToArrayIncludesServerVariables(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			serverVariables: [
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
				'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
			],
		);

		$array = $content->toArray();

		$this->assertSame( 'en-US,en;q=0.9', $array['HTTP_ACCEPT_LANGUAGE'] );
		$this->assertSame( 'gzip, deflate', $array['HTTP_ACCEPT_ENCODING'] );
	}

	public function testNormalizesEmptyStringToNullForAuthorEmail(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			authorEmail: '',
		);

		$this->assertNull( $content->authorEmail );
		$this->assertArrayNotHasKey( 'comment_author_email', $content->toArray() );
	}

	public function testNormalizesEmptyStringToNullForAuthorUrl(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			authorUrl: '',
		);

		$this->assertNull( $content->authorUrl );
		$this->assertArrayNotHasKey( 'comment_author_url', $content->toArray() );
	}

	public function testNormalizesEmptyStringToNullForPermalink(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			permalink: '',
		);

		$this->assertNull( $content->permalink );
		$this->assertArrayNotHasKey( 'permalink', $content->toArray() );
	}

	public function testValidatesAuthorUrl(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'authorUrl' );

		new Content(
			userIp: '192.168.1.1',
			authorUrl: 'not-a-valid-url',
		);
	}

	public function testValidatesPermalink(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'permalink' );

		new Content(
			userIp: '192.168.1.1',
			permalink: 'not-a-valid-url',
		);
	}

	public function testAcceptsValidUrls(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			authorUrl: 'https://example.com',
			permalink: 'https://example.com/post/123',
		);

		$this->assertSame( 'https://example.com', $content->authorUrl );
		$this->assertSame( 'https://example.com/post/123', $content->permalink );
	}

	public function testContextIncludedInToArray(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			context: 'sidebar-widget',
		);

		$array = $content->toArray();

		$this->assertSame( 'sidebar-widget', $array['comment_context'] );
	}

	public function testContextNullOmittedFromToArray(): void {
		$content = new Content( userIp: '192.168.1.1' );

		$this->assertNull( $content->context );
		$this->assertArrayNotHasKey( 'comment_context', $content->toArray() );
	}

	public function testToArrayIncludesFeedbackFields(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			reporter: 'admin-user',
			commentCheckResponse: 'true',
		);

		$array = $content->toArray();

		$this->assertSame( 'admin-user', $array['reporter'] );
		$this->assertSame( 'true', $array['comment_check_response'] );
	}

	public function testFeedbackFieldsNullByDefault(): void {
		$content = new Content( userIp: '192.168.1.1' );

		$this->assertNull( $content->reporter );
		$this->assertNull( $content->commentCheckResponse );
		$this->assertArrayNotHasKey( 'reporter', $content->toArray() );
		$this->assertArrayNotHasKey( 'comment_check_response', $content->toArray() );
	}

	public function testServerVariablesCannotOverrideFeedbackReservedKeys(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			reporter: 'admin-user',
			commentCheckResponse: 'true',
			serverVariables: [
				'reporter'               => 'evil-user',
				'comment_check_response' => 'false',
			],
		);

		$array = $content->toArray();

		$this->assertSame( 'admin-user', $array['reporter'] );
		$this->assertSame( 'true', $array['comment_check_response'] );
	}

	public function testServerVariablesCannotOverrideReservedKeys(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			body: 'Legit comment',
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

		$array = $content->toArray();

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

		new Content( userIp: 'not-a-valid-ip' );
	}

	public function testValidatesAuthorEmail(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'authorEmail' );

		new Content(
			userIp: '192.168.1.1',
			authorEmail: 'not-a-valid-email',
		);
	}

	public function testAcceptsValidAuthorEmail(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			authorEmail: 'user@example.com',
		);

		$this->assertSame( 'user@example.com', $content->authorEmail );
	}

	public function testValidatesCommentCheckResponse(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'commentCheckResponse' );

		new Content(
			userIp: '192.168.1.1',
			commentCheckResponse: 'maybe',
		);
	}

	public function testAcceptsValidCommentCheckResponseStrings(): void {
		$true = new Content( userIp: '192.168.1.1', commentCheckResponse: 'true' );
		$this->assertSame( CheckResponse::Spam, $true->commentCheckResponse );

		$false = new Content( userIp: '192.168.1.1', commentCheckResponse: 'false' );
		$this->assertSame( CheckResponse::Ham, $false->commentCheckResponse );
	}

	public function testAcceptsCheckResponseEnum(): void {
		$content = new Content( userIp: '192.168.1.1', commentCheckResponse: CheckResponse::Spam );
		$this->assertSame( CheckResponse::Spam, $content->commentCheckResponse );
	}

	public function testWithFeedbackAcceptsCheckResponseEnum(): void {
		$content  = new Content( userIp: '192.168.1.1' );
		$feedback = $content->withFeedback( 'admin', CheckResponse::Ham );

		$this->assertSame( CheckResponse::Ham, $feedback->commentCheckResponse );
		$this->assertSame( 'false', $feedback->toArray()['comment_check_response'] );
	}

	public function testWithFeedbackReturnsNewInstanceWithFeedbackFields(): void {
		$original = new Content(
			userIp: '192.168.1.1',
			userAgent: 'Mozilla/5.0',
			body: 'Test comment',
			authorName: 'John Doe',
			authorEmail: 'john@example.com',
		);

		$feedback = $original->withFeedback( 'admin', 'true' );

		// Feedback fields are set.
		$this->assertSame( 'admin', $feedback->reporter );
		$this->assertSame( CheckResponse::Spam, $feedback->commentCheckResponse );

		// Original fields are preserved.
		$this->assertSame( '192.168.1.1', $feedback->userIp );
		$this->assertSame( 'Mozilla/5.0', $feedback->userAgent );
		$this->assertSame( 'Test comment', $feedback->body );
		$this->assertSame( 'John Doe', $feedback->authorName );
		$this->assertSame( 'john@example.com', $feedback->authorEmail );

		// Original is unchanged.
		$this->assertNull( $original->reporter );
		$this->assertNull( $original->commentCheckResponse );
	}

	public function testHoneypotFieldValueRequiresFieldName(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'honeypotFieldValue' );

		new Content(
			userIp: '192.168.1.1',
			honeypotFieldValue: 'bot-filled',
		);
	}

	public function testServerVariablesFilteredAtConstruction(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			serverVariables: [
				'HTTP_ACCEPT' => 'text/html',
				'user_ip'     => 'injected',
				'api_key'     => 'stolen',
				'blog'        => 'https://evil.com',
			],
		);

		// Reserved keys are filtered at construction, not just in toArray().
		$this->assertArrayNotHasKey( 'user_ip', $content->serverVariables );
		$this->assertArrayNotHasKey( 'api_key', $content->serverVariables );
		$this->assertArrayNotHasKey( 'blog', $content->serverVariables );
		$this->assertArrayHasKey( 'HTTP_ACCEPT', $content->serverVariables );
	}

	public function testWithFeedbackValidatesCommentCheckResponse(): void {
		$content = new Content( userIp: '192.168.1.1' );

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'commentCheckResponse' );

		$content->withFeedback( 'admin', 'invalid' );
	}

	public function testToArrayIncludesCallback(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			callback: 'https://example.com/webhook',
		);

		$array = $content->toArray();

		$this->assertSame( 'https://example.com/webhook', $array['callback'] );
	}

	public function testCallbackNullOmittedFromToArray(): void {
		$content = new Content( userIp: '192.168.1.1' );

		$this->assertNull( $content->callback );
		$this->assertArrayNotHasKey( 'callback', $content->toArray() );
	}

	public function testValidatesCallbackUrl(): void {
		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'callback' );

		new Content(
			userIp: '192.168.1.1',
			callback: 'not-a-valid-url',
		);
	}

	public function testServerVariablesCannotOverrideCallback(): void {
		$content = new Content(
			userIp: '192.168.1.1',
			callback: 'https://example.com/webhook',
			serverVariables: [
				'callback' => 'https://evil.com/hook',
			],
		);

		$array = $content->toArray();

		$this->assertSame( 'https://example.com/webhook', $array['callback'] );
	}

	public function testWithFeedbackPreservesCallback(): void {
		$original = new Content(
			userIp: '192.168.1.1',
			callback: 'https://example.com/webhook',
		);

		$feedback = $original->withFeedback( 'admin', 'true' );

		$this->assertSame( 'https://example.com/webhook', $feedback->callback );
	}
}
