## 1.5.0 - 2026-05-27
### Added
- `blogLang`, `blogCharset`, `contextValues`, and `classify` properties on `Content` for richer comment-check submissions, with matching support in `ContentFactory::fromRequest()` and `ContentFactory::fromArray()`. `comment_context[]` is encoded as repeated form parameters.
- `error` and `classification` properties on `CheckResult`, parsed from the `X-Akismet-Error` and `X-Akismet-Classification` response headers and preserved through `toArray()`, `jsonSerialize()`, and `fromJson()`.
- `getExtendedKeySites()` method on `AkismetInterface` and `Akismet` for requesting optional per-site extended metadata via `extended=true`.
- `hash` and `eligibleForRevoke` properties on `SiteStats`, populated from the extended key-sites response with conservative bool parsing.
- Optional `month` property on `KeySitesResponse` capturing the `YYYY-MM` bucket key from the documented response shape.

### Fixed
- `KeySitesResponse::fromResponse()` now parses the documented `/1.2/key-sites` response shape (`YYYY-MM` bucket with `limit`, `offset`, `total`) while keeping the legacy flat shape as a fallback. Adds coverage for empty buckets and malformed month keys.

## 1.4.0 - 2026-04-06
### Added
- Add deactivate() method to notify API when a site stops using its API key.

## 1.3.0 - 2026-03-16
### Added
- `recheckAfter` property and `shouldRecheck()` method on `CheckResult` for deferred verdict handling via the `X-akismet-recheck-after` response header.
- `callback` property on `Content` for webhook verdict update callbacks, with URL validation. Intentionally omitted from `withFeedback()` since callbacks are only relevant for comment-check requests.
- `callback` parameter on `ContentFactory::fromRequest()` and `ContentFactory::fromArray()`.

### Fixed
- `CheckResult::parseRecheckAfter()` now correctly handles float values (e.g., `120.0` from JSON) instead of silently returning null.

## 1.2.0 - 2026-03-14
### Added
- `getConfiguration()` on `AkismetInterface` for mock/decorator access to immutable configuration.
- `UpgradeRecommendation::fromJson()` factory for JSON round-trip serialization, with strict validation via `ValidationException`.

### Fixed
- Add missing unit test for `KeySitesOrder` enum — was the only source class without test coverage.

### Improved
- `UpgradeRecommendation` factory methods use single array-shape `@var` annotation instead of per-variable annotations.
- `UpgradeRecommendation::fromJson()` uses `ValidationException::missingRequired()` for missing keys and `invalidValue()` for type errors, matching the semantic intent of each factory method.
- GitHub Actions updated to Node.js 24 compatible versions.

## 1.1.0 - 2026-03-13
### Added
- `getSubscription()` method and `Subscription` DTO for retrieving account plan information via the `get-subscription` endpoint. Includes `SubscriptionStatus` enum with `isActive()` helper.
- `getStats()` method, `Stats` DTO, `StatsBreakdownEntry` DTO, and `StatsInterval` enum for retrieving historical spam/ham statistics via the `get-key-stats` endpoint.
- `getExtendedUsageLimit()` method and `UpgradeRecommendation` DTO for retrieving usage notice level and upgrade recommendations via the extended `usage-limit` endpoint.
- `toArray()` on all DTOs: `CheckResult`, `AlertMetadata`, `KeySitesResponse`, and `SiteStats` now have `toArray()` for consistent serialization across the entire DTO layer.

### Fixed
- `UsageLimit` percentage values now correctly reflect the API format (bare numeric strings like `"45.0"`, not `"45.0%"`).

### Improved
- `UsageLimit::fromResponse()` now validates field types before casting, matching the strict validation pattern used by other DTOs.
- `CheckResult` and `AlertMetadata` `jsonSerialize()` now delegates to `toArray()` for a single source of truth.

## 1.0.0 - 2026-03-11

Initial stable release of the official Akismet PHP SDK.

### Added
- Full support for Akismet API 1.1 (`verify-key`, `comment-check`, `submit-spam`, `submit-ham`) and API 1.2 (`usage-limit`, `key-sites`, `get-access-token`).
- PSR-18 HTTP client compliance with automatic client discovery via `php-http/discovery`.
- Immutable, readonly DTOs: `Content`, `CheckResult`, `UsageLimit`, `KeySitesResponse`, `SiteStats`, `AlertMetadata`.
- `ContentFactory` for building `Content` from PSR-7 requests or arrays, with `$trustedProxies` for safe IP extraction from forwarded headers.
- Native PHP 8.1 backed enums: `ContentType`, `SpamVerdict`, `KeySitesOrder`, `CheckResponse`.
- `AkismetInterface` for mocking and alternative implementations.
- Typed exception hierarchy: `InvalidApiKeyException`, `ClientErrorException`, `ServerException`, `NetworkException`, `RateLimitException`, `ValidationException`.
- API key redaction in exception messages to prevent credential leakage in logs.
- Input validation for URLs, IPs, emails, month format, and pagination parameters.
- `Content::RESERVED_KEYS` filtering to prevent `serverVariables` from overwriting canonical Akismet fields.
- `applicationUserAgent` option on `Configuration` for integration identification in the User-Agent header.
- JSON serialization on DTOs (`toArray()`, `toJson()`, `fromJson()`) for queue and cache storage.
- Convenience factory: `Akismet::create(apiKey, site)` for quick initialization.
