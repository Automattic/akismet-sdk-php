<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Automattic\Akismet\Akismet;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\NetworkException;
use Automattic\Akismet\Exception\RateLimitException;

// Configuration
$apiKey = getenv('AKISMET_API_KEY');
$siteUrl = getenv('AKISMET_SITE_URL');

if (!$apiKey || !$siteUrl) {
    echo "Please set AKISMET_API_KEY and AKISMET_SITE_URL environment variables\n";
    exit(1);
}

try {
    // Initialize the SDK
    $akismet = Akismet::create(
        apiKey: $apiKey,
        site: $siteUrl,
        isTest: true // Enable test mode
    );

    // Step 1: Verify your API key
    // verifyKey() throws InvalidApiKeyException on invalid keys
    echo "Verifying API key...\n";
    try {
        $akismet->verifyKey();
        echo "✓ API key is valid\n";
    } catch (InvalidApiKeyException $e) {
        echo "✗ API key is invalid: " . $e->getMessage() . "\n";
        exit(1);
    }

    // Step 2: Check a comment for spam
    echo "\nChecking a comment...\n";

    $content = new Content(
        userIp: '192.168.1.1',
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        body: 'Great article! Thanks for sharing.',
        authorName: 'John Doe',
        authorEmail: 'john@example.com',
        type: ContentType::Comment,
        referrer: 'https://google.com',
        permalink: 'https://example.com/article',
    );

    $result = $akismet->check($content);

    echo "Spam verdict: {$result->verdict->value}\n";
    if ($result->isSpam()) {
        echo "This comment appears to be spam";
        if ($result->shouldDiscard()) {
            echo " (blatant spam - safe to discard)";
        }
        echo "\n";
    } else {
        echo "This comment appears to be legitimate\n";
    }

    // Step 3: Check subscription info
    echo "\nChecking subscription...\n";
    $subscription = $akismet->getSubscription();

    echo sprintf(
        "Plan: %s (%s) - Status: %s\n",
        $subscription->displayName,
        $subscription->slug,
        $subscription->status->value
    );

    if ($subscription->limitReached) {
        echo "⚠ Warning: Usage limit reached\n";
    }

    // Step 4: Check historical stats (API 1.2)
    echo "\nChecking stats...\n";
    $stats = $akismet->getStats(\Automattic\Akismet\Enum\StatsInterval::SixMonths);

    echo sprintf(
        "Last 6 months: %d spam, %d ham (accuracy: %s%%)\n",
        $stats->spam,
        $stats->ham,
        $stats->accuracy
    );

    if ($stats->timeSaved > 0) {
        echo sprintf("Time saved: %d hours\n", intdiv($stats->timeSaved, 3600));
    }

    echo sprintf("Breakdown periods: %d\n", count($stats->breakdown));

    // Step 5: Check usage limits (API 1.2)
    echo "\nChecking API usage...\n";
    $usage = $akismet->getUsageLimit();

    echo sprintf(
        "Usage: %d/%s (%s)\n",
        $usage->usage,
        $usage->limit ?? 'unlimited',
        $usage->percentage
    );

    if ($usage->throttled) {
        echo "⚠ Warning: You are being throttled\n";
    }

    // Step 5b: Extended usage limit (notice level + upgrade recommendation)
    echo "\nChecking extended usage info...\n";
    $extendedUsage = $akismet->getExtendedUsageLimit();

    if ($extendedUsage->noticeLevel !== null) {
        echo sprintf("Notice level: %s\n", $extendedUsage->noticeLevel);
    }

    if ($extendedUsage->upgrade !== null) {
        echo sprintf(
            "Upgrade recommended: %s (%s) — %s\n",
            $extendedUsage->upgrade->name,
            $extendedUsage->upgrade->plan,
            $extendedUsage->upgrade->url
        );
    } else {
        echo "No upgrade recommended\n";
    }

    // Step 6: List sites using this API key
    echo "\nListing sites for this API key...\n";
    $sitesResponse = $akismet->getKeySites(limit: 10);

    echo sprintf("Showing %d of %d sites:\n", count($sitesResponse->sites), $sitesResponse->total);
    foreach ($sitesResponse->sites as $site) {
        echo sprintf(
            "  - %s: %d calls, %d spam, %d ham\n",
            $site->site,
            $site->totalCalls,
            $site->spam,
            $site->ham
        );
    }

    // Paginate if there are more results
    if ($sitesResponse->hasMore()) {
        $nextPage = $akismet->getKeySites(limit: 10, offset: $sitesResponse->getNextOffset());
        echo sprintf("  ... and %d more sites\n", $nextPage->total - count($sitesResponse->sites));
    }

    // Step 7: Exchange API key for an access token
    echo "\nGetting access token...\n";
    try {
        $token = $akismet->getAccessToken();
        echo "✓ Access token retrieved (length: " . strlen($token) . ")\n";
    } catch (InvalidApiKeyException $e) {
        echo "✗ Failed to get access token: " . $e->getMessage() . "\n";
    }

    // Example: Submit spam (if we got it wrong)
    if (!$result->isSpam()) {
        echo "\nIf this was actually spam, you could report it:\n";
        echo "// \$akismet->submitSpam(\$content);\n";
    }

    // Example: Submit ham (false positive)
    if ($result->isSpam()) {
        echo "\nIf this was a false positive, you could report it:\n";
        echo "// \$akismet->submitHam(\$content);\n";
    }

    echo "\n✓ Example completed successfully\n";

} catch (RateLimitException $e) {
    echo "Rate limited: " . $e->getMessage() . "\n";
    if ($e->getRetryAfter() !== null) {
        echo "Retry after: " . $e->getRetryAfter() . " seconds\n";
    }
    exit(1);
} catch (NetworkException $e) {
    echo "Network error: " . $e->getMessage() . "\n";
    exit(1);
} catch (InvalidApiKeyException $e) {
    echo "Invalid API key: " . $e->getMessage() . "\n";
    exit(1);
}
