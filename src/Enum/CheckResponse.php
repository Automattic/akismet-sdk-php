<?php
/**
 * Check response enum for Akismet feedback submissions.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Represents the original comment-check API response for feedback submissions.
 *
 * Used with submit-spam and submit-ham to indicate what the original check returned.
 */
enum CheckResponse: string {

	case Spam = 'true';
	case Ham  = 'false';
}
