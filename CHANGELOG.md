## Unreleased
### Added
- `guid` property on `CheckResult` DTO, extracted from the `X-akismet-guid` response header.

### Fixed
- Normalize empty response header values to `null` in `CheckResult::fromResponse()`.
- `CheckResult::fromJson()` now throws `ValidationException` instead of a raw `ValueError` for invalid verdict values.

## 0.1.0 - 2026-01-29
### Added
- Akismet PHP SDK with support for API 1.1 and 1.2 endpoints, PSR-18 compliance, immutable DTOs, and comprehensive test coverage.
