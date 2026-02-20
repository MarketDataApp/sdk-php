# PHP SDK Release Process

This document defines the release process for `MarketDataApp/sdk-php`, including the pre-release workflow we use before cutting a tag.

## 1. Scope

Use this process for:
- patch releases (`vX.Y.Z`)
- minor releases (`vX.Y.0`)
- major releases (`vX.0.0`)

## 2. Release Inputs

Before starting, confirm:
- target release version `X.Y.Z`
- release tag format: `vX.Y.Z`
- release title format: `Version X.Y.Z`
- release owner
- included PRs/issues
- intended release date/time

## 3. Pre-Release Workflow (Current)

Our pre-release gate artifacts live in `release-readiness/` and are reviewed as a package before tag cut.

Required gate docs:
- `release-readiness/01-api-contract.md`
- `release-readiness/02-quality-and-tests.md`
- `release-readiness/03-compatibility.md`
- `release-readiness/04-security.md`
- `release-readiness/05-docs-dx.md`
- `release-readiness/06-release-rollback.md`
- `release-readiness/final-go-no-go.md`

Gate execution checklist:
1. API contract gate:
   - Confirm intended API/signature changes and migration impact.
   - Record pass/fail in `01-api-contract.md`.
2. Quality/test gate:
   - `composer validate`
   - `./test.sh unit --php-version=<target>`
   - `./test.sh integration --php-version=<target>` (network-enabled context required)
   - Record evidence paths and pass/fail in `02-quality-and-tests.md`.
3. Compatibility gate:
   - Run local workflow parity check with `act`: `./test-with-act.sh <target>`
   - Confirm GitHub Actions `Tests` workflow is green (`.github/workflows/run-tests.yml`).
   - Record results in `03-compatibility.md`.
4. Security gate:
   - `composer audit --format=plain`
   - Confirm token handling stays header-based (`Authorization: Bearer`).
   - Record results in `04-security.md`.
5. Docs/DX gate:
   - Verify `README.md`, `CHANGELOG.md`, and `composer.json` version/support messaging align.
   - Run executable examples (`examples/quick_start.php`, `examples/error_handling.php`).
   - Record results in `05-docs-dx.md`.
6. Release/rollback gate:
   - Confirm no open blockers.
   - Update rollback path for a patch follow-up release.
   - Record in `06-release-rollback.md`.
7. Final decision:
   - Set `GO` or `NO-GO` in `final-go-no-go.md`.
   - No tag is cut unless status is `GO` and P0 blockers are empty.

## 4. Release Preparation

1. Ensure `main` is current and CI is green.
2. Finalize release notes (highlights, breaking changes, migration notes).
3. Confirm target tag does not already exist.

## 5. Publish Release

1. Create and push annotated tag:

```bash
git checkout main
git pull
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z
```

2. Create GitHub Release for that tag and publish release notes.
3. Set release title to exactly `Version X.Y.Z`.
4. Mark as `Latest` when appropriate.

## 6. Post-Release Checks

1. Confirm `Update Changelog` workflow ran (`.github/workflows/update-changelog.yml`).
2. Confirm changelog update commit landed on `main`.
3. Confirm package update is visible on Packagist.
4. Smoke-test install in clean project:

```bash
composer require MarketDataApp/sdk-php
```

## 7. Rollback and Hotfix

If release issues are discovered:
1. Stop promotion messaging.
2. Publish corrective note in release/changelog.
3. Ship a patch release (`vX.Y.(Z+1)`) from `main` with targeted fix.
4. Document root cause and remediation in next changelog entry.
