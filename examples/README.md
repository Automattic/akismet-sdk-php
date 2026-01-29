# Akismet SDK Examples

This directory contains practical examples demonstrating how to integrate the Akismet PHP SDK into your application.

## Examples

- **basic-usage.php** - Getting started with the SDK, including verifying your API key, checking comments, and monitoring usage
- **laravel-integration.php** - Integrating with Laravel using a service provider
- **symfony-integration.php** - Integrating with Symfony using service configuration
- **async-processing.php** - Processing spam checks asynchronously with queues

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

For testing purposes, use `is_test=1` in your Comment data:
- `akismet-guaranteed-spam@example.com` will always be marked as spam
- `viagra-test-123` in content will be marked as spam
- Normal content will be marked as ham

## Integration Patterns

These examples demonstrate common integration patterns, but you should adapt them to your specific framework and requirements.
