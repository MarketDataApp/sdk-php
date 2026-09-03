# Contributing to the Market Data PHP SDK

Thank you for your interest in contributing!

## Reporting Bugs

Use the [bug report template](https://github.com/MarketDataApp/sdk-php/issues/new?template=bug.yml) and provide:

1. **Endpoint and method** - Which SDK method has the bug
2. **Reproduction code** - Complete, runnable PHP that demonstrates the issue
3. **Expected vs actual behavior** - What should happen vs what does happen
4. **Environment** - SDK version, PHP version

### What Makes a Good Bug Report

- **Self-contained code**: Your reproduction should run without modification
- **Minimal example**: Remove unnecessary code that isn't related to the bug
- **Specific output**: Include exact error messages or incorrect values

### What Happens Next

1. **Validation**: We review the reproduction code and confirm the bug exists
2. **Test first**: A failing unit test is written that captures the bug
3. **Fix**: The minimal fix is implemented
4. **Verification**: The test passes and no regressions are introduced

If we need more information, we'll comment on the issue. Issues without a response within 7 days may be closed.

## Code Contributions

### Getting Started

1. Fork the repository
2. Clone your fork
3. Install dependencies: `composer install`
4. Create a branch: `git checkout -b fix/your-bug-description`

### Development Guidelines

- **PHP Version**: Code must work on PHP 8.2, 8.3, 8.4, and 8.5
- **Code Style**: Run `composer format` before committing
- **Testing**: All changes require unit tests with 100% coverage for new code

### Testing

```bash
# Unit tests (no API token needed)
./test.sh unit

# Integration tests (requires MARKETDATA_TOKEN)
./test.sh integration

# Quota-bounded live API contracts used by CI
vendor/bin/phpunit --testsuite Integration --group ci

# Test across all PHP versions
./test-with-act.sh
```

### Pull Requests

1. Ensure all tests pass
2. Add tests for any new functionality
3. Update documentation if needed
4. Keep commits focused and atomic
5. Reference any related issues

## Finding Bugs

Want to help find bugs before other users encounter them? See [`.github/BUG_FINDING.md`](.github/BUG_FINDING.md) for a systematic exploration workflow:

- Prioritized areas where bugs commonly occur
- Test scenarios with runnable code snippets
- Endpoint-specific checklists
- Instructions for documenting and submitting found bugs

Found bugs are submitted via the standard [bug report template](https://github.com/MarketDataApp/sdk-php/issues/new?template=bug.yml).

## For Maintainers

If you have write access to the repository, see [`.github/ISSUE_WORKFLOW.md`](.github/ISSUE_WORKFLOW.md) for the complete issue triage and resolution process:

- Validation checklist for bug reports
- Response templates for common scenarios
- Step-by-step bug fixing process
- Label definitions and gh CLI commands

## Questions?

- [GitHub Discussions](https://github.com/MarketDataApp/sdk-php/discussions) for questions
- [Discord](https://discord.com/invite/GmdeAVRtnT) for community chat
