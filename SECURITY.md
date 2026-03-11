# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

We recommend always using the latest version of the SDK.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in the Akismet PHP SDK, please report it to:

**security@automattic.com**

### What to Include

Please include the following information in your report:

1. **Description**: A clear description of the vulnerability
2. **Impact**: What an attacker could achieve by exploiting it
3. **Reproduction**: Step-by-step instructions to reproduce the issue
4. **Environment**: PHP version, SDK version, and relevant configuration
5. **Proof of Concept**: Code sample or demonstration (if applicable)
6. **Suggested Fix**: Your recommendations (if any)

### What to Expect

1. **Acknowledgment**: We will acknowledge receipt of your report within 48 hours
2. **Assessment**: We will assess the vulnerability and its impact
3. **Updates**: We will keep you informed of our progress
4. **Resolution**: We will work to fix the issue and release a patch
5. **Credit**: We will credit you in the security advisory (unless you prefer anonymity)

### Security Advisory

Once a fix is released, we will:

- Publish a security advisory on GitHub
- Release a new version with the fix
- Document the issue in the changelog
- Credit the reporter (if they wish)

## Security Best Practices

### API Key Protection

**Never expose your Akismet API key in public code or repositories.**

- Store API keys in environment variables or secure configuration files
- Add `.env` and config files to `.gitignore`
- Use different keys for development, staging, and production
- Rotate keys if compromised

```php
// Good: Load from environment
$apiKey = getenv('AKISMET_API_KEY');

// Bad: Hardcoded key
$apiKey = 'abc123xyz'; // Never do this!
```

### User Input Validation

Always validate and sanitize user input before passing to the SDK:

```php
// Validate IP addresses
if (filter_var($userIp, FILTER_VALIDATE_IP) === false) {
    throw new \InvalidArgumentException('Invalid IP address');
}

// Sanitize URLs
$site = filter_var($site, FILTER_SANITIZE_URL);

// Validate email addresses
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new \InvalidArgumentException('Invalid email address');
}
```

### Error Handling

The SDK automatically redacts API keys from exception messages, but you should still avoid exposing internal details to end users:

```php
try {
    $result = $akismet->check($content);
} catch (AkismetException $e) {
    // Good: Log full details for debugging (API keys are redacted automatically)
    error_log($e->getMessage());

    // Good: Show generic message to users
    throw new \RuntimeException('Spam check failed. Please try again.');

    // Bad: Expose API details to users
    // throw $e; // Don't do this!
}
```

### HTTPS Only

The SDK uses HTTPS by default. Never configure it to use HTTP:

```php
// Default: Uses HTTPS (good)
$akismet = new Akismet($config);

// Never override to use HTTP in production
```

### Rate Limiting

Implement rate limiting to prevent abuse:

```php
try {
    $result = $akismet->check($content);
} catch (RateLimitException $e) {
    // Handle rate limit gracefully
    // Don't retry immediately
    // Log for monitoring
}
```

### Dependency Security

Keep dependencies up to date:

```bash
# Check for security vulnerabilities
composer audit

# Update dependencies
composer update
```

### Test Mode

Never use test mode in production. The `is_test` parameter is set at the API request
level, not on the Content DTO. Ensure your application only enables it in development
environments.

## Known Security Considerations

### IP Address Handling

The SDK requires the user's IP address for spam checking. Use `ContentFactory::fromRequest()` with the `trustedProxies` parameter to safely resolve the real client IP behind proxies:

```php
use Automattic\Akismet\Factory\ContentFactory;

// Trust specific proxy IPs — forwarded headers are only consulted
// when the request comes from a listed proxy
$content = ContentFactory::fromRequest(
    request: $psr7Request,
    body: $formData['message'],
    trustedProxies: ['10.0.0.1', '10.0.0.2'],
);
```

The factory checks `X-Forwarded-For`, `X-Real-IP`, `CF-Connecting-IP`, and `True-Client-IP` headers — but only when the direct connection IP matches a trusted proxy. Without `trustedProxies`, only `REMOTE_ADDR` is used.

> **Warning**: Never trust forwarded headers unconditionally. Clients can spoof `X-Forwarded-For` and similar headers. Only list IPs of reverse proxies you control.

### User Agent

Always pass the user's real User-Agent, not your application's:

```php
// Good: User's browser User-Agent
$content = new Content(
    userIp: $_SERVER['REMOTE_ADDR'],
    userAgent: $_SERVER['HTTP_USER_AGENT'],
    // ... other params
);

// Bad: Omitting userAgent or substituting your application's User-Agent
// This reduces spam detection accuracy
```

## Security Updates

To receive security updates:

1. Watch this repository on GitHub (Settings → Watch → Custom → Security alerts)
2. Subscribe to Automattic security announcements
3. Regularly check for updates: `composer outdated`
4. Enable GitHub Dependabot alerts

## Responsible Disclosure

We follow responsible disclosure practices:

1. Security issues are fixed privately
2. Patches are prepared and tested
3. Affected versions are documented
4. Fixes are released simultaneously with the advisory
5. Details are disclosed after users have had time to update

## Bug Bounty

Automattic operates a bug bounty program through HackerOne:

**https://hackerone.com/automattic**

Eligible security vulnerabilities in the Akismet PHP SDK may qualify for bounties.

## Questions?

If you have questions about security but don't have a vulnerability to report, you can
open an issue on GitHub.

For security vulnerabilities, always use security@automattic.com.

## Additional Resources

- [Akismet Privacy Policy](https://akismet.com/privacy/)
- [Automattic Security](https://automattic.com/security/)
- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
