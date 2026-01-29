<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Automattic\Akismet\Akismet;
use Automattic\Akismet\Config\Configuration;
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;
use Automattic\Akismet\Exception\AkismetException;

// Configuration
$apiKey = getenv('AKISMET_API_KEY');
$siteUrl = getenv('AKISMET_SITE_URL');

if (!$apiKey || !$siteUrl) {
    echo "Please set AKISMET_API_KEY and AKISMET_SITE_URL environment variables\n";
    exit(1);
}

try {
    // Initialize the SDK
    $config = new Configuration(
        apiKey: $apiKey,
        blog: $siteUrl,
        isTest: true // Enable test mode
    );

    $akismet = new Akismet($config);

    // Step 1: Verify your API key
    echo "Verifying API key...\n";
    $isValid = $akismet->verifyKey();
    echo $isValid ? "✓ API key is valid\n" : "✗ API key is invalid\n";

    if (!$isValid) {
        exit(1);
    }

    // Step 2: Check a comment for spam
    echo "\nChecking a comment...\n";

    $comment = new Comment(
        user_ip: '192.168.1.1',
        user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        comment_content: 'Great article! Thanks for sharing.',
        comment_author: 'John Doe',
        comment_author_email: 'john@example.com',
        comment_type: CommentType::COMMENT,
        referrer: 'https://google.com',
        permalink: 'https://example.com/article',
        is_test: true // Test mode flag
    );

    $result = $akismet->checkComment($comment);

    echo "Spam verdict: {$result->verdict->value}\n";
    if ($result->isSpam()) {
        echo "This comment appears to be spam";
        if ($result->isDiscard()) {
            echo " (blatant spam - safe to discard)";
        }
        echo "\n";
    } else {
        echo "This comment appears to be legitimate\n";
    }

    // Step 3: Check usage limits
    echo "\nChecking API usage...\n";
    $usage = $akismet->getUsageLimit();

    echo sprintf(
        "Usage: %d/%d (%d%%)\n",
        $usage->usage,
        $usage->limit,
        $usage->percentage
    );

    if ($usage->throttled) {
        echo "⚠ Warning: You are being throttled\n";
    }

    // Example: Submit spam (if we got it wrong)
    if (!$result->isSpam()) {
        echo "\nIf this was actually spam, you could report it:\n";
        echo "// \$akismet->submitSpam(\$comment);\n";
    }

    // Example: Submit ham (false positive)
    if ($result->isSpam()) {
        echo "\nIf this was a false positive, you could report it:\n";
        echo "// \$akismet->submitHam(\$comment);\n";
    }

    echo "\n✓ Example completed successfully\n";

} catch (AkismetException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
