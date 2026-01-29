---
name: Bug Report
about: Report a bug in the Akismet PHP SDK
title: '[Bug]: '
labels: bug
assignees: ''
---

## Description

A clear and concise description of what the bug is.

## Environment

- **SDK Version**: [e.g., 1.0.0]
- **PHP Version**: [e.g., 8.1.0]
- **Operating System**: [e.g., Ubuntu 22.04, macOS 13.0]
- **HTTP Client**: [e.g., Guzzle 7.5, Symfony HTTP Client]
- **Framework** (if applicable): [e.g., Laravel 10, Symfony 6]

## Steps to Reproduce

1. Initialize SDK with '...'
2. Call method '...'
3. Pass parameters '...'
4. See error

## Code Sample

```php
// Minimal code to reproduce the issue
use Automattic\Akismet\Akismet;
use Automattic\Akismet\Config\Configuration;

$config = new Configuration(
    apiKey: 'your_key',
    blog: 'https://example.com'
);

$akismet = new Akismet($config);

// Code that triggers the bug
```

## Expected Behavior

A clear description of what you expected to happen.

## Actual Behavior

A clear description of what actually happened.

## Error Messages

If applicable, include any error messages, stack traces, or logs:

```
Paste error messages here
```

## Additional Context

Add any other context about the problem here:

- Does it happen consistently or intermittently?
- Did it work in a previous version?
- Any workarounds you've found?
- Related issues or PRs

## Possible Solution

If you have suggestions on how to fix the bug, please share them here.

## Checklist

- [ ] I have searched existing issues to ensure this is not a duplicate
- [ ] I have tested with the latest version of the SDK
- [ ] I have included a minimal code sample to reproduce the issue
- [ ] I have removed any sensitive information (API keys, personal data)
