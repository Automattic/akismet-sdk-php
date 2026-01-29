# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

We recommend always using the latest stable version of the SDK.

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
$blog = filter_var($blog, FILTER_SANITIZE_URL);

// Validate email addresses
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new \InvalidArgumentException('Invalid email address');
}
```

### Error Handling

Don't expose sensitive information in error messages:

```php
try {
    $result = $akismet->commentCheck($comment);
} catch (AkismetException $e) {
    // Good: Log full details for debugging
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
    $result = $akismet->commentCheck($comment);
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

Never use test mode in production:

```php
// Good: Test mode only in development
$comment = Comment::fromRequest($_POST, [
    'is_test' => $_ENV['APP_ENV'] === 'development' ? 1 : 0,
]);

// Bad: Test mode in production
$comment = Comment::fromRequest($_POST, ['is_test' => 1]);
```

## Known Security Considerations

### IP Address Handling

The SDK requires the user's IP address for spam checking. Ensure you're capturing the correct IP:

- Behind a proxy: Use `X-Forwarded-For` header (validate first)
- Behind Cloudflare: Use `CF-Connecting-IP` header
- Direct connection: Use `$_SERVER['REMOTE_ADDR']`

```php
// Validate X-Forwarded-For
$forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
$ips = array_map('trim', explode(',', $forwardedFor));
$userIp = filter_var($ips[0] ?? $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
```

### User Agent

Always pass the user's real User-Agent, not your application's:

```php
// Good: User's browser User-Agent
$comment = Comment::fromRequest($_POST, [
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
]);

// Bad: Your application's User-Agent
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

If you have questions about security but don't have a vulnerability to report, you can:

- Open a GitHub Discussion
- Email opensource@automattic.com

For security vulnerabilities, always use security@automattic.com.

## Additional Resources

- [Akismet Privacy Policy](https://akismet.com/privacy/)
- [Automattic Security](https://automattic.com/security/)
- [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
