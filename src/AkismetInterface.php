<?php
/**
 * Akismet client interface.
 *
 * @package Automattic\Akismet
 */

declare(strict_types=1);

namespace Automattic\Akismet;

use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\DTO\KeySitesResponse;
use Automattic\Akismet\DTO\Stats;
use Automattic\Akismet\DTO\Subscription;
use Automattic\Akismet\DTO\UsageLimit;
use Automattic\Akismet\Enum\KeySitesOrder;
use Automattic\Akismet\Enum\StatsInterval;
use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\ServerException;
use Automattic\Akismet\Exception\ValidationException;

/**
 * Interface for the Akismet client.
 *
 * Allows for mocking in tests and implementing alternative clients.
 */
interface AkismetInterface {

	/**
	 * Verify that the API key is valid.
	 *
	 * Returns normally if the key is valid; throws InvalidApiKeyException otherwise.
	 *
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns an unexpected response body.
	 * @throws AkismetException On network or API errors.
	 */
	public function verifyKey(): void;

	/**
	 * Notify the Akismet API that this site is deactivating.
	 *
	 * Signals that the API key is no longer in use on the configured site,
	 * allowing the backend to clean up stale key-site associations.
	 * This is a best-effort call — the response body is not inspected
	 * and an invalid key response is silently ignored. Transport and
	 * network failures still throw.
	 *
	 * @throws AkismetException On network or API errors.
	 */
	public function deactivate(): void;

	/**
	 * Check if content is spam.
	 *
	 * @param Content $content The content to check.
	 * @return CheckResult The spam check result.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns an unexpected response body.
	 * @throws AkismetException On network or API errors.
	 */
	public function check( Content $content ): CheckResult;

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
	 * @param Content $content The spam content.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns an unexpected response body.
	 * @throws AkismetException On network or API errors.
	 */
	public function submitSpam( Content $content ): void;

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
	 * @param Content $content The legitimate content.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns an unexpected response body.
	 * @throws AkismetException On network or API errors.
	 */
	public function submitHam( Content $content ): void;

	/**
	 * Get API usage statistics and limits.
	 *
	 * @return UsageLimit Current usage information.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns malformed JSON.
	 * @throws AkismetException On network or API errors.
	 */
	public function getUsageLimit(): UsageLimit;

	/**
	 * Get API usage statistics and limits with extended fields.
	 *
	 * Requests the usage-limit endpoint with extended=true, which includes
	 * a notice_level threshold indicator and an optional upgrade recommendation
	 * for accounts approaching or exceeding their plan limits.
	 *
	 * @return UsageLimit Current usage information including extended fields (upgrade may be null if no upgrade is recommended).
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns malformed JSON.
	 * @throws AkismetException On network or API errors.
	 */
	public function getExtendedUsageLimit(): UsageLimit;

	/**
	 * Get subscription / account plan information.
	 *
	 * @return Subscription Current subscription details.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns malformed JSON.
	 * @throws AkismetException On network or API errors.
	 */
	public function getSubscription(): Subscription;

	/**
	 * Get historical spam/ham statistics for this API key.
	 *
	 * @param StatsInterval $interval Time range for statistics.
	 * @return Stats Aggregate counts and per-period breakdown.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns malformed JSON.
	 * @throws AkismetException On network or API errors.
	 */
	public function getStats( StatsInterval $interval = StatsInterval::SixMonths ): Stats;

	/**
	 * Get sites using this API key with their statistics.
	 *
	 * @param string|null $month  Month to get stats for (YYYY-MM format, month 01-12). Defaults to current month.
	 * @param string|null $filter Filter results by site URL or partial URL.
	 * @param int         $limit  Maximum number of results (must be > 0, default 500).
	 * @param int         $offset Pagination offset (must be >= 0, default 0).
	 * @param KeySitesOrder|null $order  Sort column for results.
	 * @return KeySitesResponse List of sites with statistics (JSON format only; CSV is not supported).
	 * @throws ValidationException If month format, limit, or offset is invalid.
	 * @throws InvalidApiKeyException If the API key is invalid.
	 * @throws ServerException If the API returns malformed JSON.
	 * @throws AkismetException On network or API errors.
	 */
	public function getKeySites(
		?string $month = null,
		?string $filter = null,
		int $limit = 500,
		int $offset = 0,
		?KeySitesOrder $order = null,
	): KeySitesResponse;

	/**
	 * Exchange the API key for a scoped access token.
	 *
	 * The token can only be used to authenticate requests to
	 * tools.akismet.com stats pages. It cannot be used for
	 * comment-check, submit-spam, submit-ham, or other API calls.
	 *
	 * @return string Opaque access token string.
	 * @throws InvalidApiKeyException If the API key is invalid or token exchange fails.
	 * @throws AkismetException On network or API errors.
	 */
	public function getAccessToken(): string;

	/**
	 * Get the current configuration.
	 *
	 * @return Configuration
	 */
	public function getConfiguration(): Configuration;
}
