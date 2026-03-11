# Akismet PHP SDK

Official PHP SDK for the [Akismet](https://akismet.com) spam protection service by Automattic.

> **Note**: This SDK is for non-WordPress PHP applications. WordPress sites should use the [official Akismet plugin](https://wordpress.org/plugins/akismet/).

## Requirements

- PHP 8.1 or higher
- A [PSR-18 HTTP client](https://packagist.org/providers/psr/http-client-implementation) (e.g., Guzzle, Symfony HttpClient)

## Installation

```bash
composer require automattic/akismet-sdk
```

If you don't have a PSR-18 HTTP client installed, add one:

```bash
composer require guzzlehttp/guzzle
```

## Quick Start

```php
<?php

use Automattic\Akismet\Akismet;
use Automattic\Akismet\DTO\Content;
use Automattic\Akismet\Enum\ContentType;

// Initialize the client
$akismet = Akismet::create(
    apiKey: 'your-api-key',
    site: 'https://your-site.com'
);

// Check if content is spam
$content = new Content(
    userIp: $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT'],
    body: $formData['message'],
    authorName: $formData['name'],
    authorEmail: $formData['email'],
    type: ContentType::ContactForm
);

$result = $akismet->check($content);

if ($result->isSpam()) {
    // Handle spam
    if ($result->shouldDiscard()) {
        // Blatant spam - safe to discard silently
    }
} else {
    // Process legitimate submission
}
```

## Features

- Full support for Akismet API 1.1 and 1.2 endpoints
- PSR-18 HTTP client compatibility (works with Guzzle, Symfony, etc.)
- Immutable, type-safe DTOs
- Native PHP 8.1 enums for content types and verdicts
- Built-in test mode for development

## API Methods

| Method | Description |
|--------|-------------|
| `verifyKey()` | Verify your API key is valid |
| `check($content)` | Check if content is spam |
| `submitSpam($content)` | Report missed spam (false negative) |
| `submitHam($content)` | Report false positive |
| `getUsageLimit()` | Get API usage stats and limits |
| `getKeySites()` | Get sites using your API key (JSON format only; CSV is not supported) |
| `getAccessToken()` | Exchange API key for a scoped access token (stats pages only, not for API calls) |

## Integration Identification

If you're building a framework integration (Drupal, Laravel, Symfony, etc.), identify your integration via `applicationUserAgent`. This is prepended to the SDK's User-Agent header so Akismet can track compatibility and usage:

```php
$akismet = Akismet::create(
    apiKey: 'your-api-key',
    site: 'https://your-site.com',
    applicationUserAgent: 'MyDrupalModule/1.0.0'
);
```

## Framework Integration

Use `ContentFactory` to create `Content` objects from PSR-7 requests with automatic IP and user agent extraction:

```php
use Automattic\Akismet\Factory\ContentFactory;
use Automattic\Akismet\Enum\ContentType;

$content = ContentFactory::fromRequest(
    request: $psr7Request,
    body: $formData['message'],
    authorName: $formData['name'],
    authorEmail: $formData['email'],
    type: ContentType::ContactForm,
);
```

### Trusted Proxies

By default, `ContentFactory::fromRequest()` uses `REMOTE_ADDR` as the client IP. If your application runs behind a reverse proxy or load balancer, pass the proxy IPs to trust forwarded headers (`X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, `True-Client-IP`):

```php
// Trust specific proxy IPs
$content = ContentFactory::fromRequest(
    request: $psr7Request,
    body: $formData['message'],
    trustedProxies: ['10.0.0.1', '10.0.0.2'],
);

// Trust all proxies (use only in controlled environments)
$content = ContentFactory::fromRequest(
    request: $psr7Request,
    body: $formData['message'],
    trustedProxies: ['*'],
);
```

## Submitting Feedback

Help improve Akismet's accuracy by reporting mistakes:

```php
// Report a missed spam (was marked as ham but is actually spam)
$akismet->submitSpam($content);

// Report a false positive (was marked as spam but is actually ham)
$akismet->submitHam($content);
```

## Error Handling

All SDK exceptions implement `AkismetException`, so you can catch everything with a single type:

```php
use Automattic\Akismet\Exception\AkismetException;
use Automattic\Akismet\Exception\InvalidApiKeyException;
use Automattic\Akismet\Exception\RateLimitException;

try {
    $result = $akismet->check($content);
} catch (InvalidApiKeyException $e) {
    // API key is invalid or revoked
} catch (RateLimitException $e) {
    // Too many requests — retry after $e->getRetryAfter() seconds
} catch (AkismetException $e) {
    // Catch-all for network errors, server errors, validation errors, etc.
}
```

| Exception | When |
|-----------|------|
| `InvalidApiKeyException` | API key is invalid or revoked |
| `ValidationException` | Invalid input (e.g., bad IP address, malformed month format) |
| `ClientErrorException` | HTTP 4xx client errors (excluding 429) |
| `RateLimitException` | HTTP 429 — too many requests |
| `NetworkException` | Connection failures or DNS resolution errors |
| `ServerException` | HTTP 5xx or unexpected API response body |

API keys are automatically redacted from exception messages to prevent credential leakage in logs.

## Testing

Use test mode during development to avoid affecting your accuracy metrics:

```php
$akismet = Akismet::create(
    apiKey: 'your-api-key',
    site: 'https://your-site.com',
    isTest: true
);
```

In test mode:
- `akismet-guaranteed-spam@example.com` as author email returns spam
- `akismet-guaranteed-spam` as author name returns spam
- Normal content returns ham

## Documentation

- [Akismet Developer Documentation](https://akismet.com/developers/)
- [API Reference](https://akismet.com/developers/detailed-docs/)

## License

GPL-2.0-or-later