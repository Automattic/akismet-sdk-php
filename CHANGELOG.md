## Unreleased
### Added
- `getAccessToken()` method on `AkismetInterface` to exchange the API key for a scoped access token for stats iframes.
- `guid` property on `CheckResult` DTO, extracted from the `X-akismet-guid` response header.
- `order` parameter on `getKeySites()` for sorting results by column (`'total'`, `'spam'`, `'ham'`, `'missed_spam'`, `'false_positives'`).
- `ServerException::unexpectedResponse()` factory for malformed API responses.
- `context` property on `Comment` DTO, sent as `comment_context` to the API.
- `trustedProxies` parameter on `CommentFactory::fromRequest()` for safe proxy header handling.

### Changed
- **Breaking:** Removed `Configuration::$timeout`, `Configuration::DEFAULT_TIMEOUT`, and `Configuration::withTimeout()`. PSR-18 does not define a timeout concept; configure timeouts on your HTTP client directly.
- **Breaking:** `CommentFactory::fromRequest()` now uses `REMOTE_ADDR` by default. Forwarded headers (`X-Forwarded-For`, `X-Real-IP`, etc.) are only consulted when `trustedProxies` is provided.

### Fixed
- `check()` now throws `InvalidApiKeyException` when the API returns `"invalid"` instead of silently classifying it as ham.
- `submitSpam()` and `submitHam()` now throw `InvalidApiKeyException` on `"invalid"` response instead of silently succeeding.
- `getUsageLimit()` and `getKeySites()` now throw `InvalidApiKeyException` on `"invalid"` response and `ServerException` on malformed JSON instead of crashing on null.
- `verifyKey()` now throws `ServerException` on unexpected response body instead of returning `false`.
- Normalize empty response header values to `null` in `CheckResult::fromResponse()`.
- `CheckResult::fromJson()` now throws `ValidationException` instead of a raw `ValueError` for invalid verdict values.

## 0.1.0 - 2026-01-29
### Added
- Akismet PHP SDK with support for API 1.1 and 1.2 endpoints, PSR-18 compliance, immutable DTOs, and comprehensive test coverage.
