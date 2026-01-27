<?php
/**
 * Akismet client interface.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet;

use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Exception\AkismetException;

/**
 * Interface for the Akismet client.
 *
 * Allows for mocking in tests and implementing alternative clients.
 */
interface AkismetInterface {

	/**
	 * Verify that the API key is valid.
	 *
	 * @return bool True if the key is valid.
	 * @throws AkismetException On network or API errors.
	 */
	public function verifyKey(): bool;

	/**
	 * Check if content is spam.
	 *
	 * @param Comment $comment The content to check.
	 * @return CheckResult The spam check result.
	 * @throws AkismetException On network or API errors.
	 */
	public function check( Comment $comment ): CheckResult;

	/**
	 * Submit content as spam (missed spam / false negative).
	 *
	 * Use this when content was not caught as spam but should have been.
	 *
	 * @param Comment $comment The spam content.
	 * @throws AkismetException On network or API errors.
	 */
	public function submitSpam( Comment $comment ): void;

	/**
	 * Submit content as ham (false positive).
	 *
	 * Use this when content was incorrectly marked as spam.
	 *
	 * @param Comment $comment The legitimate content.
	 * @throws AkismetException On network or API errors.
	 */
	public function submitHam( Comment $comment ): void;

	/**
	 * Get API usage statistics and limits.
	 *
	 * @return UsageLimit Current usage information.
	 * @throws AkismetException On network or API errors.
	 */
	public function getUsageLimit(): UsageLimit;

	/**
	 * Get sites using this API key with their statistics.
	 *
	 * @param string|null $month  Month to get stats for (YYYY-MM format). Defaults to current month.
	 * @param string|null $filter Filter results by site URL or partial URL.
	 * @param int         $limit  Maximum number of results (default 500).
	 * @param int         $offset Pagination offset (default 0).
	 * @return KeySitesResponse List of sites with statistics.
	 * @throws AkismetException On network or API errors.
	 */
	public function getKeySites(
		?string $month = null,
		?string $filter = null,
		int $limit = 500,
		int $offset = 0,
	): KeySitesResponse;
}
