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
	 * Best Practices:
	 * - Only submit after human review confirms it is spam
	 * - Include all available context (author info, content, user agent)
	 * - Submit within a reasonable timeframe of the original check
	 * - Use consistent data between check and submit calls
	 * - Do not submit based solely on automated rules without verification
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
	 * Best Practices:
	 * - Submit as soon as false positives are identified to improve accuracy
	 * - Submit the same data (including all context fields) that was originally checked
	 * - Submit consistently to help train the spam detection system
	 * - Consider batching submissions if processing historical data
	 * - Track and monitor false positive rates to identify patterns
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
	 * @param string|null $order  Sort column: 'total', 'spam', 'ham', 'missed_spam', or 'false_positives'.
	 * @return KeySitesResponse List of sites with statistics (JSON format only; CSV is not supported).
	 * @throws AkismetException On network or API errors.
	 */
	public function getKeySites(
		?string $month = null,
		?string $filter = null,
		int $limit = 500,
		int $offset = 0,
		?string $order = null,
	): KeySitesResponse;

	/**
	 * Exchange the API key for a scoped access token.
	 *
	 * The token can only be used to authenticate requests to
	 * tools.akismet.com stats pages. It cannot be used for
	 * comment-check, submit-spam, submit-ham, or other API calls.
	 *
	 * @return string Opaque access token string.
	 * @throws AkismetException On network or API errors.
	 */
	public function getAccessToken(): string;
}
