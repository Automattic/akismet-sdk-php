# Design: Expose x-akismet-recheck-after header and callback parameter

**Issue:** [#38](https://github.a8c.com/Automattic/akismet-sdk-php/issues/38)
**Date:** 2026-03-15
**Branch:** `add/recheck-after-header`

## Problem

The Akismet API returns an `x-akismet-recheck-after` response header on comment-check requests when async processing (URL resolution, image scanning) is still pending. The verdict is provisional ham — the API said "not spam" but may revise after background checks complete. The SDK ignores this header today.

Separately, the API accepts a `callback` parameter on comment-check requests — a webhook URL that receives verdict updates when async processing completes. The `Content` DTO has no property for this.

The Drupal module (NOSPAM-597) needs both to implement deferred verdict handling.

## Approach: Property + convenience method (no new enum case)

We chose **not** to add `SpamVerdict::Pending` because:
- The API returns `false` (ham) — `Pending` would editorialize the verdict
- `Discard` refines spam into a more specific spam action (same direction); `Pending` would change direction entirely
- Adding an enum case is a breaking change for exhaustive match/switch consumers
- Consumers that don't handle recheck-after still get the safe default (treat as ham), matching WP plugin behavior

Instead: a nullable `recheckAfter` property with a `shouldRecheck()` convenience method, following the existing `shouldDiscard()` pattern.

## API behavior (from backend source)

- **Header:** `X-akismet-recheck-after: 120` (seconds, integer, currently hardcoded to 2 minutes)
- **Only on ham responses** (`false`) — never on spam
- **Only when async checks are pending** (`deferred_actions()` is non-empty)
- **Never for trusted users** or non-edit rechecks
- **Callback parameter:** `callback` in POST body — URL the API POSTs verdict updates to

## CheckResult changes

### New constructor property

```php
public readonly ?int $recheckAfter = null,
```

Nullable int. Seconds until the consumer should recheck. `null` when header is absent. Positioned after `alertMetadata` in the constructor signature.

### New method

```php
public function shouldRecheck(): bool {
    return $this->recheckAfter !== null;
}
```

### fromResponse() parsing

Extract `x-akismet-recheck-after` alongside existing headers. Cast non-empty string value to `int`, use `null` for absent/empty.

### Serialization

- `toArray()` / `jsonSerialize()`: add `'recheckAfter' => $this->recheckAfter`
- `fromJson()`: read `$data['recheckAfter'] ?? null`, cast to `?int`

## Content changes

### New constructor property

```php
public readonly ?string $callback = null,
```

Nullable string. Webhook URL for the API to POST verdict updates to. Positioned before `serverVariables` (after `commentCheckResponse`).

### RESERVED_KEYS

Add `'callback' => true` to prevent `serverVariables` from overwriting it.

### toArray()

Serialize as `'callback' => $this->callback` when non-null.

### withFeedback()

Pass through the existing `callback` value to the new instance.

### Validation

Validate with `InputValidator::validateUrl()` when non-null (same pattern as `authorUrl`, `permalink`).

## Files touched

| File | Change |
|------|--------|
| `src/DTO/CheckResult.php` | Add `recheckAfter` property, `shouldRecheck()`, update `fromResponse()`, `toArray()`, `jsonSerialize()`, `fromJson()` |
| `src/DTO/Content.php` | Add `callback` property, reserved key, `toArray()`, `withFeedback()` pass-through, URL validation |
| `tests/Unit/DTO/CheckResultTest.php` | Tests: parse header present/absent/empty, `shouldRecheck()` true/false, serialization round-trip |
| `tests/Unit/DTO/ContentTest.php` | Tests: callback serialization, URL validation, reserved key filtering, `withFeedback()` pass-through |

## Files NOT touched

- `SpamVerdict.php` — no new enum case
- `Akismet.php` / `AkismetInterface.php` — header parsing lives in `CheckResult::fromResponse()`, already called with full headers
- `ContentFactory.php` — callback is consumer-provided, not extracted from PSR-7 requests

## Test plan

- `fromResponse()` with `x-akismet-recheck-after: 120` header → `recheckAfter === 120`, `shouldRecheck() === true`
- `fromResponse()` without the header → `recheckAfter === null`, `shouldRecheck() === false`
- `fromResponse()` with empty header value → `recheckAfter === null`
- JSON round-trip: encode with `recheckAfter`, decode via `fromJson()`, values match
- JSON round-trip: encode without `recheckAfter`, decode via `fromJson()`, null preserved
- `Content` with callback URL serializes to `['callback' => 'https://...']` in `toArray()`
- `Content` with null callback omits key from `toArray()`
- `Content` with invalid callback URL throws `ValidationException`
- `Content` callback in `serverVariables` is filtered out (reserved key)
- `Content::withFeedback()` preserves callback value
