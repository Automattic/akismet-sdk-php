<?php

declare(strict_types=1);

/**
 * Async Processing Example
 *
 * This example demonstrates patterns for processing spam checks asynchronously
 * to avoid blocking your main application flow.
 *
 * Why async processing?
 * - Improves user experience by not blocking on API calls
 * - Allows for retry logic on failures
 * - Can batch multiple checks together
 * - Reduces impact of API rate limits
 *
 * Common approaches:
 * 1. Queue-based (Laravel Queues, Symfony Messenger, Beanstalkd, RabbitMQ, etc.)
 * 2. Background workers (Supervisor, systemd, etc.)
 * 3. Cron-based batch processing
 *
 * This example shows a simple sequential approach that you can adapt
 * to your specific queuing system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Automattic\Akismet\Akismet;
use Automattic\Akismet\DTO\CheckResult;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\NetworkException;
use Automattic\Akismet\Exception\RateLimitException;

/**
 * Example: Queue-based async processing
 *
 * Flow:
 * 1. User submits comment
 * 2. Save comment to database with status='pending'
 * 3. Add job to queue with comment ID
 * 4. Return response to user immediately
 * 5. Background worker processes queue
 * 6. Worker checks comment with Akismet
 * 7. Worker updates comment status in database
 */

class CommentSpamChecker
{
    public function __construct(
        private readonly Akismet $akismet
    ) {}

    /**
     * Process a single comment from the queue.
     *
     * @param array<string, mixed> $commentData Comment data from queue.
     * @return array{success: bool, is_spam?: bool, is_discard?: bool, verdict?: string, error?: string, retry?: bool, retry_after?: int|null}
     */
    public function processComment(array $commentData): array
    {
        try {
            $content = new Content(
                userIp: $commentData['user_ip'],
                userAgent: $commentData['user_agent'] ?? null,
                body: $commentData['content'] ?? null,
                authorName: $commentData['author'] ?? null,
                authorEmail: $commentData['email'] ?? null,
                type: $commentData['type'] ?? ContentType::Comment,
                referrer: $commentData['referrer'] ?? null,
                permalink: $commentData['permalink'] ?? null
            );

            $result = $this->akismet->check($content);

            return [
                'success' => true,
                'is_spam' => $result->isSpam(),
                'is_discard' => $result->shouldDiscard(),
                'verdict' => $result->verdict->value,
            ];

        } catch (RateLimitException $e) {
            error_log("Akismet rate limited: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => true,
                'retry_after' => $e->getRetryAfter(),
            ];

        } catch (NetworkException $e) {
            error_log("Akismet network error: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => true,
            ];

        } catch (AkismetException $e) {
            error_log("Akismet check failed: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => false,
            ];
        }
    }

    /**
     * Process multiple comments in a batch.
     *
     * Useful for cron-based processing or batch workers.
     *
     * @param array<int, array<string, mixed>> $comments Comments to process.
     * @return array<int, array{id: mixed, result: array<string, mixed>}>
     */
    public function processBatch(array $comments): array
    {
        $results = [];

        foreach ($comments as $commentData) {
            $results[] = [
                'id' => $commentData['id'],
                'result' => $this->processComment($commentData),
            ];

            // Add small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        return $results;
    }
}

// Example: Storing and restoring CheckResult via JSON for queue workflows
function demonstrateCheckResultSerialization(): void
{
    // CheckResult implements JsonSerializable for queue storage
    // After checking a comment, you can serialize the result:
    //
    // $result = $akismet->check($comment);
    // $json = json_encode($result);
    // $queue->push(['comment_id' => $id, 'check_result' => $json]);
    //
    // Later, restore it without calling the API again:
    // $data = json_decode($json, true);
    // $result = CheckResult::fromJson($data);
    // if ($result->isSpam()) { ... }

    echo "CheckResult serialization example:\n";

    // Simulate a result from the API
    $result = CheckResult::fromResponse('true', ['x-akismet-pro-tip' => 'discard']);

    // Serialize to JSON for queue storage
    $json = json_encode($result);
    echo "  Serialized: {$json}\n";

    // Restore from JSON
    $restored = CheckResult::fromJson(json_decode($json, true));
    echo "  Restored verdict: {$restored->verdict->value}\n";
    echo "  Should discard: " . ($restored->shouldDiscard() ? 'yes' : 'no') . "\n";
}

// Example: Simple queue worker
function runWorker(): void
{
    $akismet = Akismet::create(
        apiKey: getenv('AKISMET_API_KEY') ?: '',
        site: getenv('AKISMET_SITE_URL') ?: '',
        isTest: true
    );

    $checker = new CommentSpamChecker($akismet);

    echo "Starting spam check worker...\n";

    while (true) {
        // In a real implementation, this would fetch from your queue
        // Example: Redis, RabbitMQ, database table, etc.
        $pendingComments = fetchPendingComments();

        if (empty($pendingComments)) {
            echo "No pending comments, sleeping...\n";
            sleep(5);
            continue;
        }

        echo "Processing " . count($pendingComments) . " comments...\n";

        foreach ($pendingComments as $comment) {
            $result = $checker->processComment($comment);

            if ($result['success']) {
                updateCommentStatus(
                    $comment['id'],
                    $result['is_spam'] ? 'spam' : 'approved'
                );
                echo "✓ Comment {$comment['id']}: {$result['verdict']}\n";
            } else {
                echo "✗ Comment {$comment['id']}: Failed - {$result['error']}\n";
                if ($result['retry']) {
                    requeueComment($comment['id']);
                }
            }
        }

        // Check API usage periodically
        static $checkCount = 0;
        if (++$checkCount % 100 === 0) {
            $usage = $akismet->getUsageLimit();
            $limit = $usage->limit ?? 'unlimited';
            echo "API Usage: {$usage->usage}/{$limit} ({$usage->percentage})\n";

            if ($usage->throttled) {
                echo "⚠ Warning: Being throttled, slowing down...\n";
                sleep(60);
            }
        }
    }
}

// Stub functions - implement these based on your storage backend
function fetchPendingComments(): array
{
    // Example: SELECT * FROM comments WHERE status='pending' LIMIT 10
    return [];
}

function updateCommentStatus(int $commentId, string $status): void
{
    // Example: UPDATE comments SET status=:status WHERE id=:id
}

function requeueComment(int $commentId): void
{
    // Example: Add back to queue with retry counter
}

// Run worker if executed directly
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    if (!getenv('AKISMET_API_KEY') || !getenv('AKISMET_SITE_URL')) {
        echo "Please set AKISMET_API_KEY and AKISMET_SITE_URL environment variables\n";
        exit(1);
    }

    // Demonstrate CheckResult serialization
    demonstrateCheckResultSerialization();

    // Uncomment to run the worker
    // runWorker();
    echo "\nWorker example - edit this file to implement your queue integration\n";
}

/**
 * Integration examples for popular queue systems:
 *
 * 1. Laravel Queues:
 *    dispatch(new CheckCommentForSpam($commentId, $commentData));
 *
 * 2. Symfony Messenger:
 *    $messageBus->dispatch(new CheckCommentMessage($commentId, $commentData));
 *
 * 3. Raw Redis:
 *    $redis->rpush('spam-check-queue', json_encode(['id' => $commentId, 'data' => $commentData]));
 *
 * 4. Beanstalkd:
 *    $pheanstalk->useTube('spam-check')->put(json_encode(['id' => $commentId, 'data' => $commentData]));
 */
