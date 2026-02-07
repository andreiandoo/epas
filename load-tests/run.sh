#!/bin/bash
#
# Load Test Runner for bilete.online (Tixello Marketplace)
#
# Usage:
#   ./run.sh [scenario] [profile] [options]
#
# Examples:
#   ./run.sh                          # Run full mix with normal profile
#   ./run.sh pages smoke              # Smoke test pages only
#   ./run.sh api-proxy peak           # Peak traffic on API
#   ./run.sh seating spike            # Spike test on seating
#   ./run.sh onsale spike             # On-sale simulation
#   ./run.sh all normal               # All scenarios sequentially
#
# Environment variables:
#   BASE_URL         - Target URL (default: https://bilete.online)
#   CORE_API         - Core API URL (default: https://core.tixello.com)
#   API_KEY          - API key for core endpoints
#   SEATING_EVENT_ID - Event ID for seating tests
#   K6_OUT           - Output format (json, csv, cloud)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SCENARIO="${1:-full-mix}"
PROFILE="${2:-normal}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_DIR="${SCRIPT_DIR}/reports"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Create reports directory
mkdir -p "${REPORT_DIR}"

echo -e "${CYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║   bilete.online / Tixello – Load Test Suite             ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Scenario:${NC}  ${SCENARIO}"
echo -e "${BLUE}Profile:${NC}   ${PROFILE}"
echo -e "${BLUE}Target:${NC}    ${BASE_URL:-https://bilete.online}"
echo -e "${BLUE}Timestamp:${NC} ${TIMESTAMP}"
echo ""

# Map scenario names to files
get_scenario_file() {
    case "$1" in
        full-mix|mix|all-mix)   echo "scenarios/00-full-mix.js" ;;
        pages|page)             echo "scenarios/01-pages.js" ;;
        api|api-proxy|proxy)    echo "scenarios/02-api-proxy.js" ;;
        seating|seats)          echo "scenarios/03-seating.js" ;;
        journey|user-journey)   echo "scenarios/04-user-journey.js" ;;
        search)                 echo "scenarios/05-search-stress.js" ;;
        onsale|on-sale|flash)   echo "scenarios/06-onsale-simulation.js" ;;
        *)
            echo -e "${RED}Unknown scenario: $1${NC}" >&2
            echo -e "Available: full-mix, pages, api-proxy, seating, journey, search, onsale" >&2
            exit 1
            ;;
    esac
}

# Run a single scenario
run_scenario() {
    local name="$1"
    local file
    file=$(get_scenario_file "${name}")
    local json_out="${REPORT_DIR}/${name}_${PROFILE}_${TIMESTAMP}.json"
    local summary_out="${REPORT_DIR}/${name}_${PROFILE}_${TIMESTAMP}_summary.txt"

    echo -e "${YELLOW}▶ Running: ${name} (${PROFILE})${NC}"
    echo -e "  File: ${file}"
    echo -e "  Output: ${json_out}"
    echo ""

    # Build k6 command
    local k6_cmd="k6 run"
    k6_cmd+=" --env PROFILE=${PROFILE}"
    k6_cmd+=" --env BASE_URL=${BASE_URL:-https://bilete.online}"
    k6_cmd+=" --env CORE_API=${CORE_API:-https://core.tixello.com}"

    if [ -n "${API_KEY:-}" ]; then
        k6_cmd+=" --env API_KEY=${API_KEY}"
    fi

    if [ -n "${SEATING_EVENT_ID:-}" ]; then
        k6_cmd+=" --env SEATING_EVENT_ID=${SEATING_EVENT_ID}"
    fi

    # JSON output for analysis
    k6_cmd+=" --out json=${json_out}"

    # Additional output (cloud, etc.)
    if [ -n "${K6_OUT:-}" ]; then
        k6_cmd+=" --out ${K6_OUT}"
    fi

    k6_cmd+=" ${SCRIPT_DIR}/${file}"

    # Run and capture summary
    if eval "${k6_cmd}" 2>&1 | tee "${summary_out}"; then
        echo -e "${GREEN}✓ ${name} completed successfully${NC}"
    else
        echo -e "${RED}✗ ${name} failed (see ${summary_out})${NC}"
    fi

    echo ""
    echo "─────────────────────────────────────────────"
    echo ""
}

# Main execution
if [ "${SCENARIO}" = "all" ]; then
    echo -e "${CYAN}Running ALL scenarios sequentially...${NC}"
    echo ""
    for s in pages api-proxy search seating journey onsale full-mix; do
        run_scenario "${s}"
    done

    echo -e "${GREEN}╔══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║   All scenarios completed!                              ║${NC}"
    echo -e "${GREEN}║   Reports: ${REPORT_DIR}/                    ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════╝${NC}"
else
    run_scenario "${SCENARIO}"
fi

echo ""
echo -e "${BLUE}Report files:${NC}"
ls -la "${REPORT_DIR}"/*"${TIMESTAMP}"* 2>/dev/null || echo "  (no files generated)"
echo ""
echo -e "${CYAN}To analyze results:${NC}"
echo "  node ${SCRIPT_DIR}/analyze.js ${REPORT_DIR}/<file>.json"
