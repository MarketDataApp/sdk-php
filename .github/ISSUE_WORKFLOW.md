# Issue Workflow

This document defines the process for triaging and resolving bug reports. It is designed to be followed by maintainers (human or automated).

## Overview

```
Verify Permissions → New Issue → Validate → [Valid] → Accept → Fix → Close
                                          → [Needs Info] → Request Info → Wait 7 days → Close
                                          → [Not a Bug] → Explain → Close
```

---

## Step 0: Verify Permissions

Before processing issues, verify you have maintainer/contributor access to the repository.

### Check Access Level

```bash
gh api repos/MarketDataApp/sdk-php/collaborators/$( gh api user --jq '.login' )/permission --jq '.permission'
```

**Expected output for issue management:**
- `admin` - Full access ✓
- `maintain` - Can manage issues ✓
- `write` - Can manage issues ✓
- `triage` - Can manage issues ✓
- `read` - Cannot manage issues ✗

### If Permission Check Fails

| Result | Meaning | Action |
|--------|---------|--------|
| `admin`, `maintain`, `write`, or `triage` | You have sufficient permissions | Proceed to Step 1 |
| `read` | Read-only access | Stop - request elevated permissions from a maintainer |
| Error: "404 Not Found" | Not a collaborator | Stop - you cannot manage issues |
| Error: "401 Unauthorized" | Not authenticated | Run `gh auth login` first |

### Quick Verification

```bash
# One-liner: exits 0 if you can manage issues, exits 1 if not
gh api repos/MarketDataApp/sdk-php/collaborators/$(gh api user --jq '.login')/permission --jq '.permission' | grep -qE '^(admin|maintain|write|triage)$'
```

---

## Step 1: Validate the Bug Report

Run through this checklist for every new bug report. All items in the "Required" section must pass.

### Required Criteria

| # | Criterion | How to Check | Pass | Fail |
|---|-----------|--------------|------|------|
| 1 | **Has reproduction code** | Look for code block in "Reproduction Code" field | Contains `<?php` or PHP code | Empty, pseudocode, or prose description only |
| 2 | **Code is complete** | Check for autoloader and client instantiation | Has `require 'vendor/autoload.php'` (or equivalent) AND creates `Client` | Missing autoloader or client setup |
| 3 | **Specifies SDK version** | Check "SDK Version" field | Version number present (e.g., `1.0.0`) | Empty or "latest" |
| 4 | **Specifies PHP version** | Check "PHP Version" field | Version number present (e.g., `8.2.0`) | Empty or vague (e.g., "8.x") |
| 5 | **Describes expected behavior** | Check "Expected Behavior" field | Clear statement of what should happen | Empty or unclear |
| 6 | **Describes actual behavior** | Check "Actual Behavior" field | Clear statement of what happens, ideally with error message | Empty or unclear |

### Validation Decision

- **All 6 criteria pass** → Proceed to Step 2 (Reproduce)
- **Any criterion fails** → Go to Step 4 (Request More Information)

---

## Step 2: Reproduce the Bug

Attempt to reproduce the reported behavior.

### Reproduction Steps

1. Create a new PHP file with the provided reproduction code
2. Ensure you're using the reported SDK version: `composer require marketdataapp/sdk-php:X.X.X`
3. Ensure you're using the reported PHP version (or close to it)
4. Run the code
5. Compare output to the reported "Actual Behavior"

### Reproduction Decision

| Outcome | Next Step |
|---------|-----------|
| **Bug reproduces** - Output matches reported actual behavior | → Step 3A (Accept as Bug) |
| **Bug does not reproduce** - Code works correctly | → Step 3B (Cannot Reproduce) |
| **Different error occurs** - Code fails but differently than reported | → Step 4 (Request More Information) |
| **API error, not SDK error** - The API itself returns an error | → Step 3C (Not an SDK Bug) |
| **User error in code** - The reproduction code has mistakes | → Step 3C (Not an SDK Bug) |

---

## Step 3A: Accept as Bug

The bug has been validated and reproduced.

### Actions

1. **Add label**: `accepted`
2. **Remove label**: `bug` (if you want to distinguish new from accepted, otherwise keep both)
3. **Comment** (use template below)
4. **Proceed to fixing** (see Step 5)

### Comment Template: Accepted

```markdown
Thanks for the detailed report. I've reproduced this issue.

**Reproduction confirmed:**
- SDK version: [version]
- PHP version: [version]
- Behavior: [brief description of what you observed]

Working on a fix.
```

---

## Step 3B: Cannot Reproduce

The code runs without exhibiting the reported bug.

### Actions

1. **Add label**: `needs-info`
2. **Comment** (use template below)

### Comment Template: Cannot Reproduce

```markdown
I wasn't able to reproduce this issue with the information provided.

**My environment:**
- SDK version: [version]
- PHP version: [version]
- OS: [os]

**What I observed:**
[Describe what happened when you ran the code - it worked correctly, different output, etc.]

Could you provide:
- [ ] Any additional configuration (custom settings, environment variables)
- [ ] The complete error output including stack trace
- [ ] Confirmation of your exact SDK and PHP versions (`composer show marketdataapp/sdk-php` and `php -v`)

I'll keep this open for 7 days for additional information.
```

---

## Step 3C: Not an SDK Bug

The issue is not a bug in the SDK.

### Actions

1. **Add label**: `wontfix`
2. **Comment** (use appropriate template below)
3. **Close issue**

### Comment Template: API Issue (Not SDK)

```markdown
Thanks for the report. After investigation, this appears to be related to the Market Data API itself rather than the PHP SDK.

**What's happening:**
[Explain the API behavior]

**Suggested next steps:**
- Check the [API documentation](https://www.marketdata.app/docs/api) for this endpoint
- Contact Market Data support if you believe the API behavior is incorrect
- Join the [Discord](https://discord.com/invite/GmdeAVRtnT) for community help

Closing this as it's outside the SDK's scope, but feel free to open a new issue if you find an SDK-specific problem.
```

### Comment Template: User Error

~~~markdown
Thanks for the report. After reviewing the reproduction code, I found an issue with the implementation rather than a bug in the SDK.

**The issue:**
[Explain what's wrong with their code]

**Suggested fix:**
```php
// Show corrected code
```

**Documentation reference:**
[Link to relevant docs if applicable]

Feel free to ask questions in [GitHub Discussions](https://github.com/MarketDataApp/sdk-php/discussions) if you need more help. Closing this issue, but you're welcome to reopen if you believe there's still an SDK bug.
~~~

### Comment Template: Works as Designed

```markdown
Thanks for the report. After investigation, the SDK is behaving as designed here.

**Expected behavior:**
[Explain why the current behavior is correct]

**Documentation reference:**
[Link to docs explaining this behavior]

If you'd like to suggest a change to this behavior, please open a feature request in [Discussions](https://github.com/MarketDataApp/sdk-php/discussions/new?category=ideas).
```

---

## Step 4: Request More Information

The report is incomplete or unclear.

### Actions

1. **Add label**: `needs-info`
2. **Comment** specifying exactly what's needed (use template below)
3. **Set reminder**: Check back in 7 days

### Comment Template: Needs Information

```markdown
Thanks for the report. To investigate this issue, I need some additional information:

[Select applicable items:]

- [ ] **Complete reproduction code**: Please provide a full, runnable PHP script including the `require 'vendor/autoload.php'` line and client initialization
- [ ] **SDK version**: Run `composer show marketdataapp/sdk-php` and provide the version number
- [ ] **PHP version**: Run `php -v` and provide the version number
- [ ] **Expected behavior**: What did you expect to happen?
- [ ] **Actual behavior**: What actually happened? Please include the complete error message and stack trace if applicable
- [ ] **Additional context**: [Specify what else is needed]

I'll keep this open for 7 days. If there's no response, I'll close it—but you're always welcome to reopen with the additional details.
```

### 7-Day Follow-up

If no response after 7 days:

1. **Comment** (use template below)
2. **Close issue**

### Comment Template: Closing for Inactivity

```markdown
Closing this issue due to inactivity. If you're able to provide the requested information, feel free to reopen or create a new issue with the additional details.
```

---

## Step 5: Fix the Bug

Follow the standard bug-fixing process.

### Fixing Checklist

1. [ ] **Create failing test**: Write a unit test that reproduces the bug and verify it fails
2. [ ] **Implement fix**: Make the minimal code change to fix the issue
3. [ ] **Verify test passes**: Run the new test and confirm it passes
4. [ ] **Run full test suite**: `./test.sh unit` - ensure no regressions
5. [ ] **Commit**: Use message format `fix: Description (closes #NNN)`
6. [ ] **Push**: Push the fix to the appropriate branch

### Commit Message Format

```
fix: Brief description of what was fixed (closes #123)
```

Examples:
- `fix: Handle null response in candles endpoint (closes #45)`
- `fix: Correct date parsing for earnings with timezone (closes #67)`

---

## Step 6: Close the Issue

After the fix is merged:

1. **Verify auto-close**: GitHub should auto-close from the commit message `closes #NNN`
2. **If not auto-closed**: Manually close with a comment

### Comment Template: Fixed

~~~markdown
Fixed in [commit hash or PR link].

This will be available in the next release. If you need the fix immediately, you can:
```bash
composer require marketdataapp/sdk-php:dev-main
```
~~~

---

## Labels Reference

| Label | Meaning | When to Apply |
|-------|---------|---------------|
| `bug` | Default label from template | Applied automatically on new issues |
| `accepted` | Bug validated and reproduced | After successful reproduction |
| `needs-info` | Waiting for reporter input | When report is incomplete or cannot reproduce |
| `wontfix` | Not a bug / won't be fixed | When closing as not-a-bug |

---

## CLI Commands Reference

Common `gh` commands for issue management:

```bash
# Add a label
gh issue edit NUMBER --add-label "accepted"
gh issue edit NUMBER --add-label "needs-info"

# Remove a label
gh issue edit NUMBER --remove-label "bug"

# Close an issue
gh issue close NUMBER

# Reopen an issue
gh issue reopen NUMBER

# Add a comment
gh issue comment NUMBER --body "Comment text here"

# View issue details
gh issue view NUMBER

# List open bugs
gh issue list --label "bug"
gh issue list --label "needs-info"
```

---

## Examples

### Example A: Valid Bug Report

**Issue #42:**
- Endpoint: `stocks`
- Method: `candles`
- Reproduction code: Complete PHP script with autoloader
- Expected: Returns candle data
- Actual: `TypeError: Cannot access property on null`
- SDK Version: 1.0.0
- PHP Version: 8.2.4

**Action**: Passes all criteria → Reproduce → If confirmed, accept and fix

---

### Example B: Incomplete Report

**Issue #43:**
- Endpoint: `options`
- Method: `chain`
- Reproduction code: "I called the options chain method and it broke"
- Expected: "It should work"
- Actual: "It doesn't work"
- SDK Version: (empty)
- PHP Version: 8.x

**Action**: Fails criteria 1, 2, 3, 4, 5, 6 → Request more information with specific asks

---

### Example C: Not a Bug

**Issue #44:**
- Endpoint: `stocks`
- Method: `quote`
- Reproduction code: Complete PHP script
- Expected: "Should return after-hours price"
- Actual: "Returns regular session price"
- SDK Version: 1.0.0
- PHP Version: 8.3.0

**After investigation**: The API returns regular session prices by default; after-hours requires a different endpoint or parameter.

**Action**: Close as "Not an SDK Bug" (API behavior) with explanation and pointer to docs
