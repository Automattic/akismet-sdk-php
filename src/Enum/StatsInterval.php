<?php
/**
 * Stats interval enum for Akismet get-key-stats requests.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet\Enum;

/**
 * Time range intervals for historical statistics.
 */
enum StatsInterval: string {

	case SixtyDays = '60-days';
	case SixMonths = '6-months';
	case Year      = 'year';
	case All       = 'all';
}
