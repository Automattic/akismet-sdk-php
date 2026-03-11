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
