# Contributing to Akismet PHP SDK

Thank you for your interest in contributing to the Akismet PHP SDK! This document provides guidelines and instructions for contributing to the project.

## Code of Conduct

This project adheres to a [Code of Conduct](CODE-OF-CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior by contacting the project team via the [Automattic contact form](https://developer.wordpress.com/contact/?g21-message=Code%20of%20Conduct%20-%20Akismet%20SDK%20PHP) with "Code of Conduct" in the message.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Development Setup

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/akismet-sdk-php.git
   cd akismet-sdk-php
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Create a branch for your changes:
   ```bash
   git checkout -b feature/your-feature-name
   ```

### Running Tests

```bash
# Run all tests
composer test

# Run only unit tests
./vendor/bin/phpunit --testsuite=unit

# Run integration tests (requires API key)
AKISMET_API_KEY=your_key_here ./vendor/bin/phpunit --testsuite=integration

# Run with coverage (outputs to .phpunit.cache/coverage-html)
composer test
```

### Code Quality

Before submitting your changes, ensure all quality checks pass:

```bash
# Run all checks
composer check

# Or run individually
composer lint          # PHP_CodeSniffer
composer analyze       # PHPStan
composer test          # PHPUnit

# Auto-fix coding standards issues
composer lint:fix
```

## Coding Standards

### PSR-12 + WordPress Coding Standards

This project follows PSR-12 with WordPress-Extra coding standards (excluding filename rules). All code must pass PHPCS checks.

### Type Safety

- Use strict types: `declare(strict_types=1)` in all files
- Add type hints for all parameters and return values
- Use readonly properties where appropriate
- Use native PHP 8.1 enums for fixed value sets

### PHPStan Level: Max

All code must pass PHPStan at maximum level with no errors.

### Patterns

**DTOs (Data Transfer Objects)**:
- Use `final readonly class` with constructor promotion
- Implement `toArray()` method
- Optional: `fromRequest()`, `toJson()`, `fromJson()` methods
- Place in `src/DTO/` directory

**Enums**:
- Use backed string enums: `enum CommentType: string`
- Place in `src/Enum/` directory

**Exceptions**:
- Implement `AkismetException` interface
- Use static factory methods for common cases
- Place in `src/Exception/` directory

### Documentation

- Add PHPDoc blocks for all classes, methods, and properties
- Include `@param` and `@return` tags
- Document exceptions with `@throws`
- Explain "why" in comments, not "what"

## Pull Request Process

### Before Submitting

1. Ensure all tests pass
2. Run all quality checks (`composer check`)
3. Update documentation if needed
4. Add changelog entry: `vendor/bin/changelogger add`
5. Ensure your branch is up to date with `trunk`

### PR Guidelines

1. **Title**: Use clear, descriptive titles in imperative mood
   - Good: "Add support for API 1.2 key-sites endpoint"
   - Bad: "Added some stuff"

2. **Description**: Use the PR template and provide:
   - Summary of changes
   - Related issue numbers
   - Testing instructions

3. **Commits**:
   - Keep commits focused and atomic
   - Write clear commit messages (under 100 characters)
   - Use imperative mood: "Add feature" not "Added feature"

4. **Size**: Keep PRs focused and reasonably sized
   - Large changes should be discussed in an issue first
   - Consider breaking large PRs into smaller ones

### Review Process

- All PRs require at least one approval from a maintainer
- Address review feedback promptly
- Be respectful and constructive in discussions
- Maintainers may request changes or additional tests

## Adding New Features

### New API Endpoints

1. Add method signature to `AkismetInterface.php`
2. Implement in `Akismet.php`
3. Create response DTO if needed
4. Add unit tests
5. Add integration test with `is_test=1`
6. Update documentation

### New DTOs

1. Create readonly class in `src/DTO/`
2. Add constructor with promoted properties
3. Implement `toArray()` method
4. Add serialization methods if needed
5. Write tests in `tests/Unit/DTO/`

### New Exceptions

1. Create class implementing `AkismetException`
2. Add static factory methods
3. Place in `src/Exception/`
4. Document in code and README

## Testing Guidelines

### Unit Tests

- Test all public methods
- Mock external dependencies
- Use data providers for multiple test cases
- Follow Arrange-Act-Assert pattern

### Integration Tests

- Test real API interactions
- Use `is_test=1` parameter
- Test both success and error cases
- Handle rate limits gracefully

### Test Organization

- Mirror `src/` structure in `tests/Unit/`
- One test class per class under test
- Group related tests with `@group` annotations

## Changelog

We use Jetpack Changelogger for changelog management.

### Adding Changelog Entries

```bash
# Add entry (interactive)
vendor/bin/changelogger add

# Add specific type
vendor/bin/changelogger add --type=added "Support for API 1.2 endpoints"
```

### Types

- `added`: New features
- `changed`: Changes to existing functionality
- `deprecated`: Soon-to-be removed features
- `removed`: Removed features
- `fixed`: Bug fixes
- `security`: Security improvements

## Release Process

Releases are handled by maintainers:

1. Ensure all PRs for the release are merged
2. Run: `vendor/bin/changelogger write --release-version=X.Y.Z`
3. Update version in relevant files
4. Create GitHub release
5. Publish to Packagist

## Getting Help

- **Issues**: Open an issue for bugs or feature requests
- **Security**: security@automattic.com (for security issues only)

## Resources

- [Akismet API Documentation](https://akismet.com/developers/)
- [Akismet OpenAPI Spec](https://github.com/Automattic/akismet-api)
- [PSR-18 HTTP Client](https://www.php-fig.org/psr/psr-18/)
- [PHP-FIG Standards](https://www.php-fig.org/)

## License

By contributing to Akismet PHP SDK, you agree that your contributions will be licensed under the GPLv2 or later license.

Thank you for contributing!
