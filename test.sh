#!/bin/bash

# Test runner script for MarketDataApp PHP SDK
# Runs unit tests first, then integration tests (if enabled)
# Outputs to console and creates a log file

# Don't use set -e because we handle errors manually

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
RUN_INTEGRATION=false
PHP_VERSION="8.5"
LOG_FILE="test-output-$(date +%Y%m%d-%H%M%S).log"

# Function to print usage
print_usage() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}MarketDataApp PHP SDK Test Runner${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  --integration, -i    Run integration tests after unit tests (default: false)"
    echo "  --php-version=V      Use specific PHP version (default: 8.5)"
    echo "  --log-file=FILE      Specify log file path (default: test-output-TIMESTAMP.log)"
    echo "  --help, -h           Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                          # Run unit tests only"
    echo "  $0 --integration            # Run unit tests, then integration tests"
    echo "  $0 -i --php-version=8.4     # Run all tests with PHP 8.4"
    echo ""
    echo -e "${YELLOW}Note: Integration tests require MARKETDATA_TOKEN environment variable${NC}"
    echo ""
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --integration|-i)
            RUN_INTEGRATION=true
            shift
            ;;
        --php-version=*)
            PHP_VERSION="${1#*=}"
            shift
            ;;
        --log-file=*)
            LOG_FILE="${1#*=}"
            shift
            ;;
        --help|-h)
            print_usage
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            print_usage
            exit 1
            ;;
    esac
done

# Print usage at start
print_usage

# Function to log and echo (plain text to both console and log)
log_and_echo() {
    echo "$1" | tee -a "$LOG_FILE"
}

# Function to log and echo with color (color to console, plain to log)
log_and_echo_color() {
    local message="$1"
    # Output colored version to console
    echo -e "$message"
    # Output plain version (strip ANSI codes) to log file
    echo -e "$message" | sed -r "s/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[mGK]//g" >> "$LOG_FILE"
}

# Initialize log file (create empty file first to ensure it's writable)
touch "$LOG_FILE" || {
    echo "Error: Cannot create log file: $LOG_FILE" >&2
    exit 1
}

# Record start time for total execution time calculation
START_TIME=$(date +%s)

# Initialize log file
log_and_echo_color "${BLUE}========================================${NC}"
log_and_echo "Test Run Started: $(date)"
log_and_echo "PHP Version: $PHP_VERSION"
log_and_echo "Run Integration Tests: $RUN_INTEGRATION"
log_and_echo "Log File: $LOG_FILE"
log_and_echo_color "${BLUE}========================================${NC}"
log_and_echo ""

# Check if PHP version is available
# Try php$PHP_VERSION first (e.g., php8.5), then fall back to php
if command -v "php$PHP_VERSION" &> /dev/null; then
    PHP_BIN="php$PHP_VERSION"
elif command -v "php" &> /dev/null; then
    PHP_BIN="php"
    # Verify the PHP version matches what we want
    PHP_ACTUAL_VERSION=$(php -r "echo PHP_VERSION;" 2>/dev/null)
    PHP_MAJOR_MINOR=$(echo "$PHP_ACTUAL_VERSION" | cut -d. -f1,2)
    if [ "$PHP_MAJOR_MINOR" != "$PHP_VERSION" ]; then
        log_and_echo_color "${YELLOW}Warning: Requested PHP $PHP_VERSION but found PHP $PHP_ACTUAL_VERSION${NC}"
        log_and_echo_color "${YELLOW}Continuing with available PHP version...${NC}"
    fi
else
    log_and_echo_color "${RED}Error: PHP not found in PATH${NC}"
    log_and_echo "Tried: php$PHP_VERSION and php"
    log_and_echo "Available PHP versions:"
    ls -1 /usr/bin/php* 2>/dev/null | grep -E 'php[0-9]' || echo "  (none found in /usr/bin)"
    ls -1 /opt/homebrew/bin/php* 2>/dev/null | grep -E 'php' || echo "  (none found in /opt/homebrew/bin)"
    exit 1
fi

# Verify PHP version
PHP_ACTUAL_VERSION=$($PHP_BIN -r "echo PHP_VERSION;" 2>/dev/null)
if [ -z "$PHP_ACTUAL_VERSION" ]; then
    log_and_echo_color "${RED}Error: Could not determine PHP version from $PHP_BIN${NC}"
    exit 1
fi
log_and_echo_color "${GREEN}Using PHP: $PHP_BIN (version $PHP_ACTUAL_VERSION)${NC}"
log_and_echo ""

# Check if vendor/bin/phpunit exists
if [ ! -f "vendor/bin/phpunit" ]; then
    log_and_echo_color "${RED}Error: vendor/bin/phpunit not found. Run 'composer install' first.${NC}"
    exit 1
fi

# Function to run tests
run_tests() {
    local test_suite=$1
    local test_name=$2
    local exit_code=0
    
    log_and_echo_color "${BLUE}========================================${NC}"
    log_and_echo_color "${BLUE}Running $test_name Tests${NC}"
    log_and_echo_color "${BLUE}========================================${NC}"
    log_and_echo ""
    
    # Run tests with verbose output, streaming to both console and log file in real-time
    # Use tee to show progress as it happens - output streams immediately
    # Capture exit code using PIPESTATUS (bash-specific, but we're using bash)
    # Force unbuffered output by using PHP's -d output_buffering=0
    $PHP_BIN -d output_buffering=0 vendor/bin/phpunit \
        --testsuite "$test_suite" \
        --testdox \
        --display-skipped \
        --display-incomplete \
        --display-all-issues \
        2>&1 | tee -a "$LOG_FILE"
    exit_code=${PIPESTATUS[0]}
    
    log_and_echo ""
    
    if [ $exit_code -eq 0 ]; then
        log_and_echo_color "${GREEN}✓ $test_name tests passed${NC}"
        log_and_echo ""
        return 0
    else
        log_and_echo_color "${RED}✗ $test_name tests failed (exit code: $exit_code)${NC}"
        log_and_echo ""
        return $exit_code
    fi
}

# Run unit tests first
log_and_echo_color "${YELLOW}Starting Unit Tests...${NC}"
log_and_echo ""

if ! run_tests "Unit" "Unit"; then
    # Calculate execution time even on failure
    END_TIME=$(date +%s)
    TOTAL_SECONDS=$((END_TIME - START_TIME))
    TOTAL_MINUTES=$((TOTAL_SECONDS / 60))
    REMAINING_SECONDS=$((TOTAL_SECONDS % 60))
    if [ $TOTAL_MINUTES -gt 0 ]; then
        TIME_DISPLAY="${TOTAL_MINUTES}m ${REMAINING_SECONDS}s"
    else
        TIME_DISPLAY="${TOTAL_SECONDS}s"
    fi
    
    log_and_echo_color "${RED}========================================${NC}"
    log_and_echo_color "${RED}Unit tests failed. Stopping.${NC}"
    log_and_echo_color "${RED}Integration tests will not be run.${NC}"
    log_and_echo_color "${RED}========================================${NC}"
    log_and_echo ""
    log_and_echo "Test run completed with failures at $(date)"
    log_and_echo "Total execution time: $TIME_DISPLAY"
    log_and_echo "Full output saved to: $LOG_FILE"
    exit 1
fi

# Run integration tests if enabled
if [ "$RUN_INTEGRATION" = true ]; then
    log_and_echo_color "${YELLOW}Starting Integration Tests...${NC}"
    log_and_echo ""
    
    # Check if MARKETDATA_TOKEN is set
    if [ -z "${MARKETDATA_TOKEN:-}" ]; then
        log_and_echo_color "${YELLOW}Warning: MARKETDATA_TOKEN not set. Integration tests may be skipped.${NC}"
        log_and_echo ""
    fi
    
    if ! run_tests "Integration" "Integration"; then
        # Calculate execution time even on failure
        END_TIME=$(date +%s)
        TOTAL_SECONDS=$((END_TIME - START_TIME))
        TOTAL_MINUTES=$((TOTAL_SECONDS / 60))
        REMAINING_SECONDS=$((TOTAL_SECONDS % 60))
        if [ $TOTAL_MINUTES -gt 0 ]; then
            TIME_DISPLAY="${TOTAL_MINUTES}m ${REMAINING_SECONDS}s"
        else
            TIME_DISPLAY="${TOTAL_SECONDS}s"
        fi
        
        log_and_echo_color "${RED}========================================${NC}"
        log_and_echo_color "${RED}Integration tests failed.${NC}"
        log_and_echo_color "${RED}========================================${NC}"
        log_and_echo ""
        log_and_echo "Test run completed with failures at $(date)"
        log_and_echo "Total execution time: $TIME_DISPLAY"
        log_and_echo "Full output saved to: $LOG_FILE"
        exit 1
    fi
else
    log_and_echo_color "${YELLOW}Skipping integration tests (use --integration to run them)${NC}"
    log_and_echo ""
fi

# Calculate total execution time
END_TIME=$(date +%s)
TOTAL_SECONDS=$((END_TIME - START_TIME))
TOTAL_MINUTES=$((TOTAL_SECONDS / 60))
REMAINING_SECONDS=$((TOTAL_SECONDS % 60))

# Format time display
if [ $TOTAL_MINUTES -gt 0 ]; then
    TIME_DISPLAY="${TOTAL_MINUTES}m ${REMAINING_SECONDS}s"
else
    TIME_DISPLAY="${TOTAL_SECONDS}s"
fi

# Summary
log_and_echo_color "${GREEN}========================================${NC}"
log_and_echo_color "${GREEN}All tests passed!${NC}"
log_and_echo_color "${GREEN}========================================${NC}"
log_and_echo ""
log_and_echo "Test run completed successfully at $(date)"
log_and_echo "Total execution time: $TIME_DISPLAY"
log_and_echo "Full output saved to: $LOG_FILE"
log_and_echo ""

exit 0
