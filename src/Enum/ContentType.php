<?php
/**
 * Content type enum for Akismet API.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Represents the type of content being checked for spam.
 *
 * These are the standard content types recognized by Akismet.
 * Custom types can be passed as strings directly to the API.
 */
enum ContentType: string {

	case Comment     = 'comment';
	case ForumPost   = 'forum-post';
	case Reply       = 'reply';
	case BlogPost    = 'blog-post';
	case ContactForm = 'contact-form';
	case Signup      = 'signup';
	case Message     = 'message';
	case Pingback    = 'pingback';
	case Trackback   = 'trackback';
	case Tweet       = 'tweet';
}
