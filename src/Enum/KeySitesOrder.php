<?php
/**
 * Sort order enum for key-sites endpoint.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Represents the sort column for key-sites results.
 */
enum KeySitesOrder: string {

	case Total          = 'total';
	case Spam           = 'spam';
	case Ham            = 'ham';
	case MissedSpam     = 'missed_spam';
	case FalsePositives = 'false_positives';
}
