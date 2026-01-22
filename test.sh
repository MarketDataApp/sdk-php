#!/bin/bash

# Test runner script for MarketDataApp PHP SDK
# Requires explicit test suite selection: unit, integration, or coverage
# Outputs to console and creates a log file

# Don't use set -e because we handle errors manually

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
TEST_MODE=""
PHP_VERSION="8.5"
LOG_FILE="test-output-$(date +%Y%m%d-%H%M%S).log"
COVERAGE_HTML_DIR=""
COVERAGE_TEXT_FILE=""
COVERAGE_CLOVER_FILE=""

# Function to print usage
print_usage() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}MarketDataApp PHP SDK Test Runner${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
    echo "Usage: $0 MODE [OPTIONS]"
    echo ""
    echo "MODE (required):"
    echo "  unit         Run unit tests only"
    echo "  integration  Run integration tests only"
    echo "  coverage     Run both unit and integration tests with coverage report"
    echo ""
    echo "OPTIONS:"
    echo "  --php-version=V      Use specific PHP version (default: 8.5)"
    echo "  --log-file=FILE      Specify log file path (default: test-output-TIMESTAMP.log)"
    echo "  --help, -h           Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 unit                      # Run unit tests only"
    echo "  $0 integration               # Run integration tests only"
    echo "  $0 coverage                  # Run all tests with coverage"
    echo "  $0 unit --php-version=8.4    # Run unit tests with PHP 8.4"
    echo ""
    echo -e "${YELLOW}Note: Integration tests and coverage require MARKETDATA_TOKEN environment variable${NC}"
    echo ""
}

# Parse command line arguments
if [ $# -eq 0 ]; then
    echo -e "${RED}Error: MODE parameter is required${NC}"
    echo ""
    print_usage
    exit 1
fi

# First argument is the mode
TEST_MODE="$1"
shift

# Validate mode
case "$TEST_MODE" in
    unit|integration|coverage)
        ;;
    --help|-h|help)
        print_usage
        exit 0
        ;;
    *)
        echo -e "${RED}Error: Invalid MODE: $TEST_MODE${NC}"
        echo -e "${RED}Valid modes are: unit, integration, coverage${NC}"
        echo ""
        print_usage
        exit 1
        ;;
esac

# Parse remaining options
while [[ $# -gt 0 ]]; do
    case $1 in
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
log_and_echo "Mode: $TEST_MODE"
log_and_echo "PHP Version: $PHP_VERSION"
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
    local generate_coverage=$3  # true or false
    local exit_code=0
    
    log_and_echo_color "${BLUE}========================================${NC}"
    log_and_echo_color "${BLUE}Running $test_name Tests${NC}"
    log_and_echo_color "${BLUE}========================================${NC}"
    log_and_echo ""
    
    # Build PHPUnit command arguments
    local phpunit_args=(
        -d output_buffering=0
        vendor/bin/phpunit
        --testsuite "$test_suite"
        --testdox
        --display-skipped
        --display-incomplete
        --display-all-issues
    )
    
    # Enable or disable coverage based on parameter
    if [ "$generate_coverage" = "true" ]; then
        # Coverage will be generated (default behavior when not using --no-coverage)
        :
    else
        phpunit_args+=(--no-coverage)
    fi
    
    # Run tests with verbose output, streaming to both console and log file in real-time
    # Use tee to show progress as it happens - output streams immediately
    # Capture exit code using PIPESTATUS (bash-specific, but we're using bash)
    $PHP_BIN "${phpunit_args[@]}" 2>&1 | tee -a "$LOG_FILE"
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

# Execute based on mode
case "$TEST_MODE" in
    unit)
        log_and_echo_color "${YELLOW}Running Unit Tests...${NC}"
        log_and_echo ""
        
        if ! run_tests "Unit" "Unit" "false"; then
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
            log_and_echo_color "${RED}Unit tests failed.${NC}"
            log_and_echo_color "${RED}========================================${NC}"
            log_and_echo ""
            log_and_echo "Test run completed with failures at $(date)"
            log_and_echo "Total execution time: $TIME_DISPLAY"
            log_and_echo "Full output saved to: $LOG_FILE"
            exit 1
        fi
        ;;
    
    integration)
        log_and_echo_color "${YELLOW}Running Integration Tests...${NC}"
        log_and_echo ""
        
        # Check if MARKETDATA_TOKEN is set
        if [ -z "${MARKETDATA_TOKEN:-}" ]; then
            log_and_echo_color "${YELLOW}Warning: MARKETDATA_TOKEN not set. Integration tests may be skipped.${NC}"
            log_and_echo ""
        fi
        
        if ! run_tests "Integration" "Integration" "false"; then
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
        ;;
    
    coverage)
        log_and_echo_color "${YELLOW}Running Full Test Suite with Coverage...${NC}"
        log_and_echo ""
        
        # Check if MARKETDATA_TOKEN is set
        if [ -z "${MARKETDATA_TOKEN:-}" ]; then
            log_and_echo_color "${YELLOW}Warning: MARKETDATA_TOKEN not set. Integration tests may be skipped.${NC}"
            log_and_echo ""
        fi
        
        # Extract timestamp from log file name (format: test-output-YYYYMMDD-HHMMSS.log)
        # If custom log file was provided, generate timestamp from current time
        timestamp=""
        if [[ "$LOG_FILE" =~ test-output-([0-9]{8}-[0-9]{6})\.log$ ]]; then
            timestamp="${BASH_REMATCH[1]}"
        else
            # Generate timestamp from current time if custom log file name
            timestamp=$(date +%Y%m%d-%H%M%S)
        fi
        
        # Create timestamped coverage output paths
        COVERAGE_HTML_DIR="build/coverage-${timestamp}"
        COVERAGE_TEXT_FILE="build/coverage-${timestamp}.txt"
        COVERAGE_CLOVER_FILE="build/logs/clover-${timestamp}.xml"
        
        # Ensure build/logs directory exists
        mkdir -p "build/logs"
        
        log_and_echo "Coverage reports will be saved with timestamp: ${timestamp}"
        log_and_echo "  HTML: ${COVERAGE_HTML_DIR}/"
        log_and_echo "  Text: ${COVERAGE_TEXT_FILE}"
        log_and_echo "  Clover: ${COVERAGE_CLOVER_FILE}"
        log_and_echo ""
        
        # Run both test suites with coverage enabled
        log_and_echo_color "${BLUE}========================================${NC}"
        log_and_echo_color "${BLUE}Running Unit and Integration Tests with Coverage${NC}"
        log_and_echo_color "${BLUE}========================================${NC}"
        log_and_echo ""
        
        # Build PHPUnit command arguments for coverage run
        # Note: --coverage-text uses = format, others use space-separated format
        # Omit --testsuite flags to run all tests (both Unit and Integration)
        phpunit_args=(
            -d output_buffering=0
            vendor/bin/phpunit
            --testdox
            --display-skipped
            --display-incomplete
            --display-all-issues
            --coverage-html "${COVERAGE_HTML_DIR}"
            --coverage-text="${COVERAGE_TEXT_FILE}"
            --coverage-clover "${COVERAGE_CLOVER_FILE}"
        )
        
        # Run tests with coverage
        $PHP_BIN "${phpunit_args[@]}" 2>&1 | tee -a "$LOG_FILE"
        exit_code=${PIPESTATUS[0]}
        
        log_and_echo ""
        
        if [ $exit_code -ne 0 ]; then
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
            log_and_echo_color "${RED}Tests failed.${NC}"
            log_and_echo_color "${RED}========================================${NC}"
            log_and_echo ""
            log_and_echo "Test run completed with failures at $(date)"
            log_and_echo "Total execution time: $TIME_DISPLAY"
            log_and_echo "Full output saved to: $LOG_FILE"
            exit 1
        fi
        ;;
esac

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
if [ "$TEST_MODE" = "coverage" ]; then
    log_and_echo_color "${GREEN}Coverage report generated.${NC}"
    log_and_echo ""
    log_and_echo "Coverage reports saved:"
    log_and_echo "  HTML: ${COVERAGE_HTML_DIR}/"
    log_and_echo "  Text: ${COVERAGE_TEXT_FILE}"
    log_and_echo "  Clover: ${COVERAGE_CLOVER_FILE}"
fi
log_and_echo_color "${GREEN}========================================${NC}"
log_and_echo ""
log_and_echo "Test run completed successfully at $(date)"
log_and_echo "Total execution time: $TIME_DISPLAY"
log_and_echo "Full output saved to: $LOG_FILE"
log_and_echo ""

exit 0
