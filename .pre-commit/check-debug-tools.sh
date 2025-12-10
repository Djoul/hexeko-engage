#!/bin/bash
# Check for debugging tools in PHP files
# Exits with 1 if debug tools are found, 0 otherwise

# Color codes for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Debug tools patterns to check
PATTERNS=(
    '\bray\('
    '\bdump\('
    '\bdd\('
    '\bvar_dump\('
    '\bprint_r\('
    '\bvar_export\('
    '\bdebug_backtrace\('
    '\bxdebug_'
    '\berror_log\('
    '\bvar_log\('
)

# Files to exclude
EXCLUDE_PATTERN='_ide_helper\.php|vendor/|node_modules/'

found_issues=0

# Check all files passed as arguments
for file in "$@"; do
    # Skip excluded files
    if echo "$file" | grep -qE "$EXCLUDE_PATTERN"; then
        continue
    fi

    # Only check PHP files
    if [[ ! "$file" =~ \.php$ ]]; then
        continue
    fi

    # Check if file exists (it might have been deleted)
    if [[ ! -f "$file" ]]; then
        continue
    fi

    # Check each pattern
    for pattern in "${PATTERNS[@]}"; do
        # Allow debug_backtrace in AuthorizationContext.php (legitimate use for execution context)
        if [[ "$pattern" == '\bdebug_backtrace\(' ]] && [[ "$file" =~ app/Security/AuthorizationContext\.php$ ]]; then
            continue
        fi

        if grep -nE "$pattern" "$file" 2>/dev/null; then
            if [ $found_issues -eq 0 ]; then
                echo -e "${RED}❌ Debug tools found in staged files!${NC}"
                echo ""
            fi
            echo -e "${YELLOW}Found in $file:${NC}"
            grep -nE "$pattern" "$file" | head -5
            echo ""
            found_issues=1
        fi
    done
done

if [ $found_issues -eq 1 ]; then
    echo -e "${RED}Please remove all debug tools before committing.${NC}"
    echo "Common debug tools to remove:"
    echo "  - ray()"
    echo "  - dump(), dd()"
    echo "  - var_dump(), print_r()"
    echo "  - error_log()"
    echo ""
    exit 1
fi

exit 0
