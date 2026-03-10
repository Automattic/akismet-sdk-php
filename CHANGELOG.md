## Unreleased
### Added
- `guid` property on `CheckResult` DTO, extracted from the `X-akismet-guid` response header.
- `ServerException::unexpectedResponse()` factory for malformed API responses.
- `context` property on `Content` DTO, sent as `comment_context` to the API.
- `Content::RESERVED_KEYS` filtering prevents `serverVariables` from overwriting canonical Akismet fields in `toArray()`.
- `applicationUserAgent` option on `Configuration` and `Akismet` constructor for integration identification in the User-Agent header.

### Changed
- **Breaking:** Added `getAccessToken()` and `$order` param on `getKeySites()` to `AkismetInterface`. Implementors must update their signatures.
- **Breaking:** Added `$trustedProxies` param to `ContentFactory::fromRequest()`. Forwarded headers (`X-Forwarded-For`, `X-Real-IP`, etc.) are now only consulted when `trustedProxies` is provided; default is `REMOTE_ADDR` only.
- **Breaking:** Removed `Configuration::$timeout`, `Configuration::DEFAULT_TIMEOUT`, and `Configuration::withTimeout()`. PSR-18 does not define a timeout concept; configure timeouts on your HTTP client directly.
- `getKeySites()` now only returns JSON format; CSV is not supported by this SDK.

### Fixed
- `check()` now throws `InvalidApiKeyException` when the API returns `"invalid"` instead of silently classifying it as ham.
- `submitSpam()` and `submitHam()` now throw `InvalidApiKeyException` on `"invalid"` response instead of silently succeeding.
- `submitSpam()` and `submitHam()` now throw `ServerException` on unexpected response body instead of silently succeeding.
- `getUsageLimit()` and `getKeySites()` now throw `InvalidApiKeyException` on `"invalid"` response and `ServerException` on malformed JSON instead of crashing on null.
- `getKeySites()` now validates `$month` (YYYY-MM format), `$order` (allowed values), `$limit` (positive), and `$offset` (non-negative) before making the API call.
- `verifyKey()` now throws `ServerException` on unexpected response body instead of returning `false`.
- Normalize empty response header values to `null` in `CheckResult::fromResponse()`.
- `CheckResult::fromJson()` now throws `ValidationException` instead of a raw `ValueError` for invalid verdict values.
- API keys are now redacted from exception messages to prevent credential leakage in logs.
- `Retry-After` header parsing now handles both integer seconds and HTTP-date formats.

## 0.1.0 - 2026-01-29
### Added
- Akismet PHP SDK with support for API 1.1 and 1.2 endpoints, PSR-18 compliance, immutable DTOs, and comprehensive test coverage.
