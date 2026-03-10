<?php
/**
 * Tests for ContentType enum.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Tests\Unit\Enum;

use Automattic\Akismet\Enum\ContentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ContentType::class )]
final class ContentTypeTest extends TestCase {

	#[DataProvider( 'contentTypeProvider' )]
	public function testContentTypeValues( ContentType $type, string $expected ): void {
		$this->assertSame( $expected, $type->value );
	}

	/**
	 * @return array<string, array{ContentType, string}>
	 */
	public static function contentTypeProvider(): array {
		return [
			'comment'      => [ ContentType::Comment, 'comment' ],
			'forum-post'   => [ ContentType::ForumPost, 'forum-post' ],
			'reply'        => [ ContentType::Reply, 'reply' ],
			'blog-post'    => [ ContentType::BlogPost, 'blog-post' ],
			'contact-form' => [ ContentType::ContactForm, 'contact-form' ],
			'signup'       => [ ContentType::Signup, 'signup' ],
			'message'      => [ ContentType::Message, 'message' ],
			'pingback'     => [ ContentType::Pingback, 'pingback' ],
			'trackback'    => [ ContentType::Trackback, 'trackback' ],
			'tweet'        => [ ContentType::Tweet, 'tweet' ],
		];
	}

	public function testCanCreateFromString(): void {
		$type = ContentType::from( 'contact-form' );
		$this->assertSame( ContentType::ContactForm, $type );
	}

	public function testTryFromReturnsNullForInvalidValue(): void {
		$type = ContentType::tryFrom( 'invalid-type' );
		$this->assertNull( $type );
	}
}
