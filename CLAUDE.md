# CLAUDE.md - Akismet PHP SDK

Official Automattic PHP SDK for Akismet spam protection. Targets **non-WordPress PHP applications** (PHP 8.1+). WordPress sites should use the [Akismet WordPress plugin](https://wordpress.org/plugins/akismet/).

**Goals**: First-party SDK supporting all API endpoints (including API 1.2), PSR-18 compliant, foundation for Drupal/Laravel/Symfony integrations.

## Architecture

```
src/
├── Akismet.php              # Main facade
├── AkismetInterface.php     # Interface for mocking
├── Client/                  # PSR-18 HTTP client wrapper + auto-discovery
├── Config/Configuration.php # Immutable config
├── DTO/                     # Comment, CheckResult, SiteActivity, UsageLimit (readonly classes)
├── Enum/                    # CommentType, SpamVerdict (PHP 8.1 enums)
├── Factory/                 # CommentFactory, ContainerFactory
└── Exception/               # AkismetException, InvalidApiKeyException, RateLimitException, NetworkException, ValidationException
```

**Design**: Immutable DTOs with `readonly` classes, native enums, PSR-18 HTTP via php-http/discovery, factory methods for PSR-7/Symfony integration, JSON serializable for queue storage.

## API Reference

Base: `https://rest.akismet.com/`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/1.1/verify-key` | POST | Validate API key (params: `key`, `blog`) |
| `/1.1/comment-check` | POST | Check spam (required: `api_key`, `blog`, `user_ip`) |
| `/1.1/submit-spam` | POST | Report missed spam |
| `/1.1/submit-ham` | POST | Report false positive |
| `/1.2/usage-limit` | GET | API usage/limits (returns: `limit`, `usage`, `percentage`, `throttled`) |
| `/1.2/key-sites` | GET | Sites using this key (params: `month`, `filter`, `format`, `order`, `limit`, `offset`) |

**Recommended params** for comment-check: `user_agent`, `comment_content`, `comment_author`, `comment_author_email`, `comment_type`, `referrer`, `permalink`

**Response headers**: `X-akismet-pro-tip: discard` (blatant spam), `X-akismet-alert-code`/`X-akismet-alert-msg` (errors), `X-akismet-debug-help` (debugging)

**Test mode**: Set `is_test=1`. Use `akismet-guaranteed-spam@example.com` or `viagra-test-123` for spam, normal content for ham.

## Coding Standards

- PSR-12 + WordPress-Extra PHPCS (excluding filename rules)
- PHPStan level: max
- `declare(strict_types=1)` in all files
- Namespace: `Automattic\Akismet`

**Patterns**:
- DTOs: `final readonly class` with constructor promotion, `toArray()`, optional `fromRequest()` factory
- Enums: Backed string enums (`enum CommentType: string`)
- Exceptions: Interface `AkismetException extends Throwable`, static factory methods

## Commands

```bash
composer install           # Install dependencies (run first)

composer test              # All tests
composer lint              # PHPCS
composer analyze           # PHPStan
composer check             # All quality checks
composer lint:fix          # Auto-fix

# Integration tests
AKISMET_API_KEY=xxx ./vendor/bin/phpunit --testsuite=integration
```

## Dependencies

**Required**: `psr/http-client`, `psr/http-factory`, `psr/http-message`, `psr/container`, `php-http/discovery`

**Dev**: `guzzlehttp/guzzle`, `phpunit/phpunit`, `phpstan/phpstan`, `squizlabs/php_codesniffer`, `automattic/jetpack-changelogger`

## Common Tasks

**New DTO**: Create readonly class in `src/DTO/`, add `toArray()`, optional `toJson()`/`fromJson()`, write tests in `tests/Unit/DTO/`

**New endpoint**: Add to `AkismetInterface.php`, implement in `Akismet.php`, create response DTO, add integration test with `is_test=1`

**Release**: `vendor/bin/changelogger add` then `vendor/bin/changelogger write --release-version=X.Y.Z`. Follow semver.

## Resources

- [Akismet OpenAPI Spec](https://github.com/Automattic/akismet-api)
- [Akismet Developer Docs](https://akismet.com/developers/)
- [Jetpack Monorepo](https://github.com/Automattic/jetpack) (patterns)