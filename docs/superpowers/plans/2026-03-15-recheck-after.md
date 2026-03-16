# Recheck-After Header & Callback Parameter Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose the `x-akismet-recheck-after` response header on `CheckResult` and add a `callback` request parameter to `Content`, enabling deferred verdict handling for SDK consumers.

**Architecture:** Two independent DTO changes. `CheckResult` gains a nullable `recheckAfter` property parsed from response headers, plus a `shouldRecheck()` convenience method. `Content` gains a nullable `callback` property serialized into the request payload. No changes to enums, client, or interface.

**Tech Stack:** PHP 8.1, PHPUnit 11, PSR-12 + WordPress-Extra PHPCS, PHPStan max

**Spec:** `docs/superpowers/specs/2026-03-15-recheck-after-design.md`

---

## Chunk 1: CheckResult — recheckAfter property

### Task 1: Add recheckAfter tests to CheckResultTest

**Files:**
- Modify: `tests/Unit/DTO/CheckResultTest.php`

- [ ] **Step 1: Write test for fromResponse with recheck-after header**

Add to `CheckResultTest`:

```php
public function testFromResponseParsesRecheckAfterHeader(): void {
	$result = CheckResult::fromResponse(
		'false',
		[
			'X-Akismet-Recheck-After' => '120',
		]
	);

	$this->assertSame( 120, $result->recheckAfter );
	$this->assertTrue( $result->shouldRecheck() );
	$this->assertSame( SpamVerdict::Ham, $result->verdict );
}
```

- [ ] **Step 2: Write test for fromResponse without recheck-after header**

```php
public function testFromResponseWithoutRecheckAfterHeader(): void {
	$result = CheckResult::fromResponse( 'false' );

	$this->assertNull( $result->recheckAfter );
	$this->assertFalse( $result->shouldRecheck() );
}
```

- [ ] **Step 3: Write test for fromResponse with empty recheck-after header**

```php
public function testFromResponseTreatsEmptyRecheckAfterAsNull(): void {
	$result = CheckResult::fromResponse(
		'false',
		[ 'X-Akismet-Recheck-After' => '' ]
	);

	$this->assertNull( $result->recheckAfter );
	$this->assertFalse( $result->shouldRecheck() );
}
```

- [ ] **Step 4: Write test for shouldRecheck convenience method**

```php
public function testShouldRecheckReturnsTrueOnlyWhenRecheckAfterIsSet(): void {
	$withRecheck    = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );
	$withoutRecheck = new CheckResult( SpamVerdict::Ham );

	$this->assertTrue( $withRecheck->shouldRecheck() );
	$this->assertFalse( $withoutRecheck->shouldRecheck() );
}
```

- [ ] **Step 5: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/DTO/CheckResultTest.php --no-coverage`
Expected: FAIL — `recheckAfter` property and `shouldRecheck()` method don't exist yet.

### Task 2: Implement recheckAfter on CheckResult

**Files:**
- Modify: `src/DTO/CheckResult.php`

- [ ] **Step 1: Add recheckAfter constructor property**

Add after the `alertMetadata` parameter in the constructor:

```php
public readonly ?int $recheckAfter = null,
```

- [ ] **Step 2: Add shouldRecheck() method**

Add after the `shouldDiscard()` method:

```php
/**
 * Check if the verdict is provisional and should be rechecked.
 *
 * When true, the API is still processing async checks (URL resolution,
 * image scanning) and the verdict may change. Use recheckAfter for the
 * delay in seconds before rechecking.
 */
public function shouldRecheck(): bool {
	return $this->recheckAfter !== null;
}
```

- [ ] **Step 3: Parse header in fromResponse()**

Add after the `$guid` extraction line (`$guid = $nullIfEmpty( ... )`):

```php
$recheckAfterRaw = $nullIfEmpty( $headers['x-akismet-recheck-after'] ?? null );
$recheckAfter    = $recheckAfterRaw !== null ? (int) $recheckAfterRaw : null;
```

Update the `return new self(...)` call to pass `recheckAfter: $recheckAfter` after `$alertMetadata`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/DTO/CheckResultTest.php --no-coverage`
Expected: All tests PASS.

- [ ] **Step 5: Run static analysis**

Run: `composer analyze`
Expected: No new errors.

- [ ] **Step 6: Commit**

```bash
git add src/DTO/CheckResult.php tests/Unit/DTO/CheckResultTest.php
git commit -m "Add recheckAfter property and shouldRecheck() to CheckResult"
```

### Task 3: Add recheckAfter serialization

**Files:**
- Modify: `src/DTO/CheckResult.php`
- Modify: `tests/Unit/DTO/CheckResultTest.php`

- [ ] **Step 1: Write test for toArray including recheckAfter**

```php
public function testToArrayIncludesRecheckAfter(): void {
	$result = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );
	$array  = $result->toArray();

	$this->assertArrayHasKey( 'recheckAfter', $array );
	$this->assertSame( 120, $array['recheckAfter'] );
}
```

- [ ] **Step 2: Write test for toArray with null recheckAfter**

```php
public function testToArrayIncludesNullRecheckAfterWhenAbsent(): void {
	$result = new CheckResult( SpamVerdict::Ham );
	$array  = $result->toArray();

	$this->assertArrayHasKey( 'recheckAfter', $array );
	$this->assertNull( $array['recheckAfter'] );
}
```

- [ ] **Step 3: Write test for JSON round-trip with recheckAfter**

```php
public function testJsonRoundTripWithRecheckAfter(): void {
	$original = new CheckResult( SpamVerdict::Ham, recheckAfter: 120 );

	$json     = json_encode( $original );
	$decoded  = json_decode( $json, true );
	$restored = CheckResult::fromJson( $decoded );

	$this->assertSame( 120, $restored->recheckAfter );
	$this->assertTrue( $restored->shouldRecheck() );
}
```

- [ ] **Step 4: Write test for JSON round-trip without recheckAfter**

```php
public function testJsonRoundTripWithoutRecheckAfter(): void {
	$original = new CheckResult( SpamVerdict::Ham );

	$json     = json_encode( $original );
	$decoded  = json_decode( $json, true );
	$restored = CheckResult::fromJson( $decoded );

	$this->assertNull( $restored->recheckAfter );
	$this->assertFalse( $restored->shouldRecheck() );
}
```

- [ ] **Step 5: Run tests to verify new ones fail**

Run: `./vendor/bin/phpunit tests/Unit/DTO/CheckResultTest.php --no-coverage`
Expected: New serialization tests FAIL — `recheckAfter` not in `toArray()` or `fromJson()` yet.

- [ ] **Step 6: Update toArray()**

Add `'recheckAfter' => $this->recheckAfter,` to the returned array, after the `'alertMetadata'` entry.

Update the `@return` PHPDoc on both `toArray()` and `jsonSerialize()` to include `recheckAfter: int|null` in the array shape.

- [ ] **Step 7: Update fromJson()**

In the `return new self(...)` call, add after `$alertMetadata`:

```php
isset( $data['recheckAfter'] ) ? (int) $data['recheckAfter'] : null,
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/DTO/CheckResultTest.php --no-coverage`
Expected: All tests PASS.

- [ ] **Step 9: Run full quality checks**

Run: `composer check`
Expected: lint, analyze, and all tests pass.

- [ ] **Step 10: Commit**

```bash
git add src/DTO/CheckResult.php tests/Unit/DTO/CheckResultTest.php
git commit -m "Add recheckAfter to CheckResult serialization and deserialization"
```

---

## Chunk 2: Content — callback property

### Task 4: Add callback tests to ContentTest

**Files:**
- Modify: `tests/Unit/DTO/ContentTest.php`

- [ ] **Step 1: Write test for callback in toArray**

```php
public function testToArrayIncludesCallback(): void {
	$content = new Content(
		userIp: '192.168.1.1',
		callback: 'https://example.com/webhook',
	);

	$array = $content->toArray();

	$this->assertSame( 'https://example.com/webhook', $array['callback'] );
}
```

- [ ] **Step 2: Write test for null callback omitted from toArray**

```php
public function testCallbackNullOmittedFromToArray(): void {
	$content = new Content( userIp: '192.168.1.1' );

	$this->assertNull( $content->callback );
	$this->assertArrayNotHasKey( 'callback', $content->toArray() );
}
```

- [ ] **Step 3: Write test for callback URL validation**

```php
public function testValidatesCallbackUrl(): void {
	$this->expectException( ValidationException::class );
	$this->expectExceptionMessage( 'callback' );

	new Content(
		userIp: '192.168.1.1',
		callback: 'not-a-valid-url',
	);
}
```

- [ ] **Step 4: Write test for callback as reserved key in serverVariables**

```php
public function testServerVariablesCannotOverrideCallback(): void {
	$content = new Content(
		userIp: '192.168.1.1',
		callback: 'https://example.com/webhook',
		serverVariables: [
			'callback' => 'https://evil.com/hook',
		],
	);

	$array = $content->toArray();

	$this->assertSame( 'https://example.com/webhook', $array['callback'] );
}
```

- [ ] **Step 5: Write test for withFeedback preserving callback**

```php
public function testWithFeedbackPreservesCallback(): void {
	$original = new Content(
		userIp: '192.168.1.1',
		callback: 'https://example.com/webhook',
	);

	$feedback = $original->withFeedback( 'admin', 'true' );

	$this->assertSame( 'https://example.com/webhook', $feedback->callback );
}
```

- [ ] **Step 6: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/DTO/ContentTest.php --no-coverage`
Expected: FAIL — `callback` property doesn't exist yet.

### Task 5: Implement callback on Content

**Files:**
- Modify: `src/DTO/Content.php`

- [ ] **Step 1: Add callback to RESERVED_KEYS**

Add to the `RESERVED_KEYS` constant array:

```php
'callback'                  => true,
```

- [ ] **Step 2: Add callback constructor parameter**

Add after the `commentCheckResponse` parameter, before `serverVariables`:

```php
public readonly ?string $callback = null,
```

Update the `@param` PHPDoc block to include:

```php
 * @param string|null               $callback                Webhook URL for verdict update callbacks.
```

- [ ] **Step 3: Add callback URL validation**

Add after the permalink validation block (the `if ( $this->permalink !== null )` block):

```php
if ( $this->callback !== null ) {
	InputValidator::validateUrl( $this->callback, 'callback' );
}
```

- [ ] **Step 4: Add callback to toArray()**

Add after the `commentCheckResponse` block in `toArray()`:

```php
if ( $this->callback !== null ) {
	$data['callback'] = $this->callback;
}
```

- [ ] **Step 5: Add callback to withFeedback()**

Add `callback: $this->callback,` to the `new self(...)` call in `withFeedback()`, after `commentCheckResponse:`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/DTO/ContentTest.php --no-coverage`
Expected: All tests PASS.

- [ ] **Step 7: Run full quality checks**

Run: `composer check`
Expected: lint, analyze, and all tests pass.

- [ ] **Step 8: Commit**

```bash
git add src/DTO/Content.php tests/Unit/DTO/ContentTest.php
git commit -m "Add callback property to Content for webhook verdict updates"
```

---

## Chunk 3: Final verification & changelog

### Task 6: Full suite verification and changelog entry

**Files:**
- Create: changelog entry via `vendor/bin/changelogger add`

- [ ] **Step 1: Run full quality checks**

Run: `composer check`
Expected: lint, analyze, and all tests pass with no errors.

- [ ] **Step 2: Add changelog entry**

Run: `vendor/bin/changelogger add --significance=minor --type=added --entry="Add recheckAfter property and shouldRecheck() method to CheckResult for deferred verdict handling. Add callback property to Content for webhook verdict updates. (issue #38)"`

- [ ] **Step 3: Commit changelog**

```bash
git add changelog/
git commit -m "Add changelog entry for recheck-after and callback support"
```
