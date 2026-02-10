# Akismet SDK Examples

This directory contains practical examples demonstrating how to integrate the Akismet PHP SDK into your application.

## Examples

- **basic-usage.php** - Getting started with the SDK, including verifying your API key, checking comments, listing sites, and monitoring usage
- **async-processing.php** - Processing spam checks asynchronously with queues
- **testing-example.php** - Unit testing your Akismet integration with PHPUnit mocks

## Running Examples

1. Install dependencies:
```bash
composer install
```

2. Set your API key:
```bash
export AKISMET_API_KEY="your-api-key-here"
export AKISMET_SITE_URL="https://your-site.com"
```

3. Run an example:
```bash
php examples/basic-usage.php
```

## Getting an API Key

1. Sign up at [akismet.com](https://akismet.com/)
2. Add your site
3. Copy your API key from the account dashboard

## Testing

Enable test mode by passing `isTest: true` to the Akismet constructor:

```php
$akismet = new Akismet(
    apiKey: $apiKey,
    blog: $siteUrl,
    isTest: true
);
```

In test mode:
- Use `akismet-guaranteed-spam` as the author name or `akismet-guaranteed-spam@example.com` as the email to trigger a spam result
- Normal content will be marked as ham

See `testing-example.php` for how to mock `AkismetInterface` in your unit tests.

## Framework Integration

For framework-specific examples (Laravel, Symfony, etc.), see the [Akismet developer documentation](https://akismet.com/developers/).
