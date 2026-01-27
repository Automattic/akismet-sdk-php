<?php
/**
 * Tests for CommentType enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\CommentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommentType::class)]
final class CommentTypeTest extends TestCase
{
    #[DataProvider('commentTypeProvider')]
    public function testCommentTypeValues(CommentType $type, string $expected): void
    {
        $this->assertSame($expected, $type->value);
    }

    /**
     * @return array<string, array{CommentType, string}>
     */
    public static function commentTypeProvider(): array
    {
        return [
            'comment' => [CommentType::Comment, 'comment'],
            'forum-post' => [CommentType::ForumPost, 'forum-post'],
            'reply' => [CommentType::Reply, 'reply'],
            'blog-post' => [CommentType::BlogPost, 'blog-post'],
            'contact-form' => [CommentType::ContactForm, 'contact-form'],
            'signup' => [CommentType::Signup, 'signup'],
            'message' => [CommentType::Message, 'message'],
            'pingback' => [CommentType::Pingback, 'pingback'],
            'trackback' => [CommentType::Trackback, 'trackback'],
            'tweet' => [CommentType::Tweet, 'tweet'],
        ];
    }

    public function testCanCreateFromString(): void
    {
        $type = CommentType::from('contact-form');
        $this->assertSame(CommentType::ContactForm, $type);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $type = CommentType::tryFrom('invalid-type');
        $this->assertNull($type);
    }
}