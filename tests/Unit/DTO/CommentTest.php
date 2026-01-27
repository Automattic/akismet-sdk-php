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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Automattic\Akismet\DTO\Comment
 */
final class CommentTest extends TestCase
{
    public function testCreatesWithRequiredFields(): void
    {
        $comment = new Comment(userIp: '192.168.1.1');

        $this->assertSame('192.168.1.1', $comment->userIp);
        $this->assertNull($comment->userAgent);
        $this->assertNull($comment->content);
    }

    public function testCreatesWithAllFields(): void
    {
        $date = new DateTimeImmutable('2024-01-15T10:30:00Z');
        $postDate = new DateTimeImmutable('2024-01-10T08:00:00Z');

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
            serverVariables: ['HTTP_ACCEPT_LANGUAGE' => 'en-US'],
        );

        $this->assertSame('192.168.1.1', $comment->userIp);
        $this->assertSame('Mozilla/5.0', $comment->userAgent);
        $this->assertSame('Test comment', $comment->content);
        $this->assertSame('John Doe', $comment->authorName);
        $this->assertSame('john@example.com', $comment->authorEmail);
        $this->assertSame(CommentType::Comment, $comment->type);
    }

    public function testToArrayWithMinimalData(): void
    {
        $comment = new Comment(userIp: '192.168.1.1');
        $array = $comment->toArray();

        $this->assertSame(['user_ip' => '192.168.1.1'], $array);
    }

    public function testToArrayWithAllFields(): void
    {
        $date = new DateTimeImmutable('2024-01-15T10:30:00+00:00');

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

        $this->assertSame('192.168.1.1', $array['user_ip']);
        $this->assertSame('Mozilla/5.0', $array['user_agent']);
        $this->assertSame('Test comment', $array['comment_content']);
        $this->assertSame('John Doe', $array['comment_author']);
        $this->assertSame('john@example.com', $array['comment_author_email']);
        $this->assertSame('https://john.example.com', $array['comment_author_url']);
        $this->assertSame('contact-form', $array['comment_type']);
        $this->assertSame('https://example.com/contact', $array['permalink']);
        $this->assertSame('https://google.com', $array['referrer']);
        $this->assertSame('guest', $array['user_role']);
        $this->assertArrayHasKey('comment_date_gmt', $array);
    }

    public function testToArrayWithStringCommentType(): void
    {
        $comment = new Comment(
            userIp: '192.168.1.1',
            type: 'custom-type',
        );

        $array = $comment->toArray();
        $this->assertSame('custom-type', $array['comment_type']);
    }

    public function testToArrayIncludesHoneypotField(): void
    {
        $comment = new Comment(
            userIp: '192.168.1.1',
            honeypotFieldName: 'website_url',
            honeypotFieldValue: 'spam-bot-filled-this',
        );

        $array = $comment->toArray();

        $this->assertSame('website_url', $array['honeypot_field_name']);
        $this->assertSame('spam-bot-filled-this', $array['website_url']);
    }

    public function testToArrayIncludesServerVariables(): void
    {
        $comment = new Comment(
            userIp: '192.168.1.1',
            serverVariables: [
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
                'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
            ],
        );

        $array = $comment->toArray();

        $this->assertSame('en-US,en;q=0.9', $array['HTTP_ACCEPT_LANGUAGE']);
        $this->assertSame('gzip, deflate', $array['HTTP_ACCEPT_ENCODING']);
    }
}