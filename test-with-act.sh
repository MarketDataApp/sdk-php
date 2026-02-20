#!/bin/bash

# Test using act - runs the exact same GitHub Actions workflow locally
# This tests all PHP versions (8.2, 8.3, 8.4, 8.5) with both prefer-lowest and prefer-stable
# FAILS if any tests are skipped (skipped tests = failure)
#
# Usage:
#   ./test-with-act.sh              # Test all PHP versions (default)
#   ./test-with-act.sh 8.5          # Quick test: PHP 8.5 only (prefer-stable)
#   ./test-with-act.sh 8.4          # Quick test: PHP 8.4 only (prefer-stable)
#   ./test-with-act.sh 8.3          # Quick test: PHP 8.3 only (prefer-stable)
#   ./test-with-act.sh 8.2          # Quick test: PHP 8.2 only (prefer-stable)

set -e
set -o pipefail

# Parse optional PHP version argument
PHP_VERSION="${1:-}"
VALID_VERSIONS=("8.2" "8.3" "8.4" "8.5")

if [ -n "$PHP_VERSION" ]; then
    # Validate PHP version
    if [[ ! " ${VALID_VERSIONS[@]} " =~ " ${PHP_VERSION} " ]]; then
        echo "Error: Invalid PHP version '$PHP_VERSION'"
        echo "Valid versions: ${VALID_VERSIONS[*]}"
        exit 1
    fi
fi

# Colors for output (will fall back to plain text if not supported)
if [ -t 1 ]; then
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    CYAN='\033[0;36m'
    BOLD='\033[1m'
    NC='\033[0m' # No Color
    SEPARATOR_COLOR="$CYAN"
else
    RED=''
    GREEN=''
    YELLOW=''
    BLUE=''
    CYAN=''
    BOLD=''
    NC=''
    SEPARATOR_COLOR=''
fi

# Function to print a section separator
print_separator() {
    echo ""
    echo -e "${SEPARATOR_COLOR}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${SEPARATOR_COLOR}$1${NC}"
    echo -e "${SEPARATOR_COLOR}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Function to print a job separator
print_job_separator() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║${BOLD}  $1${NC}${BLUE}║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_separator "Testing with act (GitHub Actions locally)"
echo -e "${BOLD}This runs the EXACT same workflow as CI/CD${NC}"
echo ""
if [ -n "$PHP_VERSION" ]; then
    echo -e "Testing PHP version: ${CYAN}$PHP_VERSION${NC} (quick test mode)"
    EXPECTED_JOBS=1  # 1 PHP version × 1 stability option (prefer-stable)
else
    echo -e "Testing all PHP versions: ${CYAN}8.2, 8.3, 8.4, 8.5${NC}"
    EXPECTED_JOBS=8  # 4 PHP versions × 2 stability options
fi
if [ -z "$PHP_VERSION" ]; then
    echo -e "With both: ${CYAN}prefer-lowest${NC} and ${CYAN}prefer-stable${NC}"
else
    echo -e "With: ${CYAN}prefer-stable${NC} (quick test)"
fi
echo ""
echo -e "${YELLOW}Note:${NC} Windows jobs will be skipped (act limitation)"
if [ -n "$PHP_VERSION" ]; then
    echo -e "      Only Ubuntu jobs will run (${CYAN}$EXPECTED_JOBS job${NC})"
    echo ""
    echo -e "${YELLOW}Quick test mode - should complete faster...${NC}"
else
    echo -e "      Only Ubuntu jobs will run (${CYAN}$EXPECTED_JOBS total jobs${NC})"
    echo ""
    echo -e "${YELLOW}This may take several minutes...${NC}"
fi

# Create temporary output file
OUTPUT_FILE=$(mktemp)
trap "rm -f $OUTPUT_FILE" EXIT

# Check if MARKETDATA_TOKEN is set
if [ -z "$MARKETDATA_TOKEN" ]; then
    echo ""
    echo -e "${YELLOW}⚠️  WARNING: MARKETDATA_TOKEN environment variable is not set!${NC}"
    echo "   Integration tests will be skipped."
    echo "   Set MARKETDATA_TOKEN before running this script to test all tests."
fi

print_separator "Starting workflow execution"

# Run act with the test job, filtering to Ubuntu only
# Pass MARKETDATA_TOKEN as environment variable to the workflow if it's set
# Use --verbose for better output formatting
# Use --pull=false to use locally cached Docker images (faster subsequent runs)
ACT_CMD="act push -j test --matrix os:ubuntu-latest --container-architecture linux/amd64 --verbose --pull=false"

# Add PHP version and stability filters if specified (quick test mode)
if [ -n "$PHP_VERSION" ]; then
    ACT_CMD="$ACT_CMD --matrix php:$PHP_VERSION --matrix stability:prefer-stable"
fi

if [ -n "$MARKETDATA_TOKEN" ]; then
    ACT_CMD="$ACT_CMD --env MARKETDATA_TOKEN=$MARKETDATA_TOKEN"
fi

# Capture output to check for skipped tests
# Note: act runs jobs sequentially, so output will be ordered by job
if eval "$ACT_CMD" 2>&1 | tee "$OUTPUT_FILE"; then
    ACT_EXIT_CODE=0
else
    ACT_EXIT_CODE=$?
fi

print_separator "Workflow execution complete - Analyzing results"

# Extract job results summary
echo -e "${BOLD}Job Execution Summary:${NC}"
echo ""
echo -e "  ${CYAN}Note:${NC} Jobs run sequentially (one after another)"
if [ -n "$PHP_VERSION" ]; then
    echo -e "  ${CYAN}Expected:${NC} $EXPECTED_JOBS job total (PHP $PHP_VERSION with prefer-stable)"
else
    echo -e "  ${CYAN}Expected:${NC} $EXPECTED_JOBS jobs total (4 PHP versions × 2 stability options)"
fi
echo ""
echo -e "  Review the output above to see each job's execution details."
echo -e "  Each job's output appears in order as it completes."
echo ""

# Check for skipped tests in the output
SKIPPED_COUNT=$(grep -i "skipped" "$OUTPUT_FILE" | grep -E "Skipped:\s*[1-9]" | wc -l | tr -d ' ' || echo "0")
SKIPPED_LINES=$(grep -i "skipped" "$OUTPUT_FILE" | grep -E "Skipped:\s*[1-9]" || true)

if [ -n "$SKIPPED_LINES" ]; then
    print_separator "❌ FAILED: Found skipped tests!"
    echo -e "${RED}Skipped test details:${NC}"
    echo "$SKIPPED_LINES"
    echo ""
    echo -e "${RED}Skipped tests are considered failures.${NC}"
    exit 1
fi

# Also check for "OK, but some tests were skipped!" message
if grep -qi "but some tests were skipped" "$OUTPUT_FILE"; then
    print_separator "❌ FAILED: Found 'OK, but some tests were skipped' message!"
    grep -i "but some tests were skipped" "$OUTPUT_FILE"
    echo ""
    echo -e "${RED}Skipped tests are considered failures.${NC}"
    exit 1
fi

# Check if act itself failed
if [ $ACT_EXIT_CODE -ne 0 ]; then
    print_separator "❌ FAILED: Act workflow execution failed"
    echo -e "${RED}Exit code: $ACT_EXIT_CODE${NC}"
    exit $ACT_EXIT_CODE
fi

# Defensive check: fail if act output indicates any job/setup failures
# even if the process exit code is unexpectedly 0.
if grep -Eq "🏁  Job failed|❌  Failure - " "$OUTPUT_FILE"; then
    print_separator "❌ FAILED: Act reported job/setup failures"
    grep -E "🏁  Job failed|❌  Failure - " "$OUTPUT_FILE"
    exit 1
fi

# Check for test failures
if grep -qi "FAILURES\|ERRORS" "$OUTPUT_FILE"; then
    print_separator "❌ FAILED: Test failures detected!"
    echo -e "${RED}Failure details:${NC}"
    grep -i "FAILURES\|ERRORS" "$OUTPUT_FILE"
    exit 1
fi

print_separator "✅ SUCCESS: All tests passed!"
echo -e "${GREEN}✓ All tests passed with 0 skipped!${NC}"
echo -e "${GREEN}✓ All $EXPECTED_JOBS jobs completed successfully${NC}"
echo ""
echo -e "${BOLD}Test complete - All checks passed!${NC}"
