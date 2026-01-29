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
use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\AkismetException;

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
     */
    public function processComment(array $commentData): array
    {
        try {
            $comment = new Comment(
                user_ip: $commentData['user_ip'],
                user_agent: $commentData['user_agent'],
                comment_content: $commentData['content'],
                comment_author: $commentData['author'] ?? null,
                comment_author_email: $commentData['email'] ?? null,
                comment_type: CommentType::from($commentData['type'] ?? 'comment'),
                referrer: $commentData['referrer'] ?? null,
                permalink: $commentData['permalink'] ?? null
            );

            $result = $this->akismet->checkComment($comment);

            return [
                'success' => true,
                'is_spam' => $result->isSpam(),
                'is_discard' => $result->isDiscard(),
                'verdict' => $result->verdict->value,
            ];

        } catch (AkismetException $e) {
            // Log error and return failure status for retry
            error_log("Akismet check failed: " . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry' => true,
            ];
        }
    }

    /**
     * Process multiple comments in a batch.
     * Useful for cron-based processing or batch workers.
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

// Example: Simple queue worker
function runWorker(): void
{
    $config = new Configuration(
        apiKey: getenv('AKISMET_API_KEY') ?: '',
        blog: getenv('AKISMET_SITE_URL') ?: '',
        isTest: true
    );

    $akismet = new Akismet($config);
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
            echo "API Usage: {$usage->usage}/{$usage->limit} ({$usage->percentage}%)\n";

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

    // Uncomment to run the worker
    // runWorker();
    echo "Worker example - edit this file to implement your queue integration\n";
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
