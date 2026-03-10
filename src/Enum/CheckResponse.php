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
 * Maps to the `comment_check_response` wire key in the Akismet API.
 *
 * Not to be confused with {@see SpamVerdict}, which represents the SDK's
 * interpretation of a check result (Ham, Spam, Discard).
 */
enum CheckResponse: string {

	case Spam = 'true';
	case Ham  = 'false';
}
