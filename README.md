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
use Automattic\Akismet\DTO\Comment;
use Automattic\Akismet\Enum\CommentType;

// Initialize the client
$akismet = new Akismet(
    apiKey: 'your-api-key',
    blog: 'https://your-site.com'
);

// Check if content is spam
$comment = new Comment(
    userIp: $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT'],
    content: $formData['message'],
    authorName: $formData['name'],
    authorEmail: $formData['email'],
    type: CommentType::ContactForm
);

$result = $akismet->check($comment);

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
- Native PHP 8.1 enums for comment types and verdicts
- Built-in test mode for development

## API Methods

| Method | Description |
|--------|-------------|
| `verifyKey()` | Verify your API key is valid |
| `check($comment)` | Check if content is spam |
| `submitSpam($comment)` | Report missed spam (false negative) |
| `submitHam($comment)` | Report false positive |
| `getUsageLimit()` | Get API usage stats and limits |
| `getKeySites()` | Get sites using your API key |

## Submitting Feedback

Help improve Akismet's accuracy by reporting mistakes:

```php
// Report a missed spam (was marked as ham but is actually spam)
$akismet->submitSpam($comment);

// Report a false positive (was marked as spam but is actually ham)
$akismet->submitHam($comment);
```

## Testing

Use test mode during development to avoid affecting your accuracy metrics:

```php
$akismet = new Akismet(
    apiKey: 'your-api-key',
    blog: 'https://your-site.com',
    isTest: true
);
```

In test mode:
- `akismet-guaranteed-spam@example.com` as author email returns spam
- `viagra-test-123` in content returns spam
- Normal content returns ham

## Documentation

- [Akismet Developer Documentation](https://akismet.com/developers/)
- [API Reference](https://akismet.com/developers/detailed-docs/)

## License

GPL-2.0-or-later