#!/bin/bash

# ============================================================================
# Feature Initialization Script
# ============================================================================
# Usage: ./init-feature.sh [IDENTIFIER] [OPTIONS]
# 
# Examples:
#   ./init-feature.sh UE-268                    # Auto-detect Jira story
#   ./init-feature.sh UE-250 --type=epic        # Force epic type
#   ./init-feature.sh --interactive             # Interactive mode
#   ./init-feature.sh --local=todos/feature.md  # From local file
#   ./init-feature.sh --profile=feature-standard UE-268
# ============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
TODOS_DIR="/Users/fred/PhpstormProjects/up-engage-api/todos"
WORKFLOWS_DIR="/Users/fred/PhpstormProjects/up-engage-api/workflows"
TEMPLATES_DIR="$WORKFLOWS_DIR/templates"

# ============================================================================
# Helper Functions
# ============================================================================

print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}     Feature Initialization System${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

prompt_user() {
    local prompt=$1
    local var_name=$2
    echo -n -e "${YELLOW}?${NC} $prompt: "
    read -r $var_name
}

prompt_yes_no() {
    local prompt=$1
    echo -n -e "${YELLOW}?${NC} $prompt (y/n): "
    read -r response
    [[ "$response" =~ ^[Yy]$ ]]
}

# ============================================================================
# Detection Functions
# ============================================================================

detect_type() {
    local identifier=$1
    
    # Check if it's a Jira key format
    if [[ $identifier =~ ^[A-Z]+-[0-9]+$ ]]; then
        print_info "Detected Jira identifier: $identifier"
        # Here we would call MCP to get the actual type
        # For now, return story as default
        echo "jira-story"
        return
    fi
    
    # Check if it's a file path
    if [[ -f "$identifier" ]]; then
        print_info "Detected local file: $identifier"
        echo "local-file"
        return
    fi
    
    # Check if it's numeric (potential Sentry ID)
    if [[ $identifier =~ ^[0-9]+$ ]]; then
        print_info "Detected potential Sentry ID: $identifier"
        echo "sentry-error"
        return
    fi
    
    echo "unknown"
}

# ============================================================================
# Profile Selection
# ============================================================================

select_profile() {
    local type=$1
    
    case $type in
        "jira-epic")
            echo "epic-standard"
            ;;
        "jira-story")
            select_story_profile
            ;;
        "jira-bug")
            echo "bugfix"
            ;;
        "sentry-error")
            echo "hotfix"
            ;;
        *)
            echo "flexible"
            ;;
    esac
}

select_story_profile() {
    echo ""
    echo "Select story complexity:"
    echo "  1) Simple (CRUD, minor change)"
    echo "  2) Standard (business logic, API)"
    echo "  3) Complex (integration, architecture)"
    echo ""
    prompt_user "Enter choice (1-3)" complexity
    
    case $complexity in
        1) echo "story-simple" ;;
        2) echo "story-standard" ;;
        3) echo "story-complex" ;;
        *) echo "story-standard" ;;
    esac
}

# ============================================================================
# Phase Selection
# ============================================================================

select_phases() {
    local profile=$1
    
    case $profile in
        "hotfix")
            echo "implementation,validation"
            ;;
        "bugfix")
            echo "analysis,implementation,validation"
            ;;
        "story-simple")
            echo "implementation,validation,documentation"
            ;;
        "story-standard")
            echo "analysis,tdd,implementation,validation,documentation"
            ;;
        "story-complex"|"epic-standard")
            echo "discovery,analysis,design,tdd,implementation,validation,documentation"
            ;;
        *)
            interactive_phase_selection
            ;;
    esac
}

interactive_phase_selection() {
    echo ""
    echo "Select phases to activate:"
    echo ""
    
    local phases=""
    
    if prompt_yes_no "Discovery (understand requirements)?"; then
        phases="${phases}discovery,"
    fi
    
    if prompt_yes_no "Analysis (technical analysis)?"; then
        phases="${phases}analysis,"
    fi
    
    if prompt_yes_no "Design (architecture design)?"; then
        phases="${phases}design,"
    fi
    
    if prompt_yes_no "TDD (test-driven development)?"; then
        phases="${phases}tdd,"
    fi
    
    # Implementation and Validation are always required
    phases="${phases}implementation,validation,"
    
    if prompt_yes_no "Documentation (API docs, guides)?"; then
        phases="${phases}documentation,"
    fi
    
    # Remove trailing comma
    echo "${phases%,}"
}

# ============================================================================
# Workspace Creation
# ============================================================================

create_workspace() {
    local identifier=$1
    local type=$2
    local profile=$3
    
    local workspace_dir=""
    
    case $type in
        "jira-epic")
            workspace_dir="$TODOS_DIR/epics/$identifier"
            create_epic_workspace "$workspace_dir" "$identifier"
            ;;
        "jira-story"|"jira-bug")
            workspace_dir="$TODOS_DIR/stories/$identifier"
            create_story_workspace "$workspace_dir" "$identifier"
            ;;
        "sentry-error")
            workspace_dir="$TODOS_DIR/sentry/$identifier"
            create_sentry_workspace "$workspace_dir" "$identifier"
            ;;
        "local-file")
            workspace_dir="$TODOS_DIR/local/$(date +%Y%m%d-%H%M%S)"
            create_local_workspace "$workspace_dir" "$identifier"
            ;;
        *)
            workspace_dir="$TODOS_DIR/tasks/$(date +%Y%m%d-%H%M%S)"
            create_generic_workspace "$workspace_dir"
            ;;
    esac
    
    echo "$workspace_dir"
}

create_epic_workspace() {
    local dir=$1
    local epic_key=$2
    
    mkdir -p "$dir/stories"
    
    # Copy templates
    cp "$TEMPLATES_DIR/epic-analysis-template.md" "$dir/epic-analysis.md"
    
    # Create initial files
    cat > "$dir/README.md" <<EOF
# Epic: $epic_key

## Status
- **Phase**: Discovery
- **Started**: $(date +%Y-%m-%d)

## Quick Links
- [Epic Analysis](epic-analysis.md)
- [Architecture](architecture.md)
- [Stories](stories/)

## Progress
See dashboard.md for detailed progress tracking.
EOF
    
    touch "$dir/architecture.md"
    touch "$dir/dependency-matrix.md"
    touch "$dir/dashboard.md"
}

create_story_workspace() {
    local dir=$1
    local story_key=$2
    
    mkdir -p "$dir"
    
    # Copy templates
    cp "$TEMPLATES_DIR/story-analysis-template.md" "$dir/analysis.md"
    cp "$TEMPLATES_DIR/tdd-strategy-template.md" "$dir/tdd-plan.md"
    
    # Create initial files
    cat > "$dir/README.md" <<EOF
# Story: $story_key

## Status
- **Phase**: Discovery
- **Started**: $(date +%Y-%m-%d)

## Documents
- [Analysis](analysis.md)
- [TDD Plan](tdd-plan.md)
- [Implementation Notes](implementation.md)

## Progress
Track progress in this file or use TodoWrite tool.
EOF
    
    touch "$dir/implementation.md"
    touch "$dir/documentation.md"
}

create_sentry_workspace() {
    local dir=$1
    local sentry_id=$2
    
    mkdir -p "$dir"
    
    cat > "$dir/error-analysis.md" <<EOF
# Sentry Error: $sentry_id

## Error Details
- **ID**: $sentry_id
- **Detected**: $(date +%Y-%m-%d)
- **Status**: Analyzing

## Stack Trace
[Fetch from Sentry MCP]

## Root Cause Analysis
[To be completed]

## Fix Applied
[Document the fix here]

## Testing
- [ ] Fix verified locally
- [ ] Regression test added
- [ ] Deployed to staging
- [ ] Verified in production
EOF
}

create_local_workspace() {
    local dir=$1
    local file=$2
    
    mkdir -p "$dir"
    
    # Copy the original file
    cp "$file" "$dir/original.md"
    
    cat > "$dir/README.md" <<EOF
# Local Task

## Source
- **File**: $file
- **Imported**: $(date +%Y-%m-%d)

## Status
- **Phase**: Analysis
- **Type**: To be determined

## Documents
- [Original](original.md)
- [Analysis](analysis.md)
EOF
    
    touch "$dir/analysis.md"
}

create_generic_workspace() {
    local dir=$1
    
    mkdir -p "$dir"
    
    cat > "$dir/README.md" <<EOF
# Task

## Status
- **Phase**: Planning
- **Started**: $(date +%Y-%m-%d)

## Type
To be determined

## Documents
Create as needed
EOF
}

# ============================================================================
# Main Execution
# ============================================================================

main() {
    print_header
    
    # Parse arguments
    IDENTIFIER=""
    TYPE=""
    PROFILE=""
    INTERACTIVE=false
    LOCAL_FILE=""
    
    while [[ $# -gt 0 ]]; do
        case $1 in
            --type=*)
                TYPE="${1#*=}"
                shift
                ;;
            --profile=*)
                PROFILE="${1#*=}"
                shift
                ;;
            --interactive)
                INTERACTIVE=true
                shift
                ;;
            --local=*)
                LOCAL_FILE="${1#*=}"
                IDENTIFIER="$LOCAL_FILE"
                TYPE="local-file"
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                IDENTIFIER="$1"
                shift
                ;;
        esac
    done
    
    # Interactive mode
    if [[ "$INTERACTIVE" == true ]] || [[ -z "$IDENTIFIER" ]]; then
        echo "Interactive Feature Initialization"
        echo ""
        echo "Select source:"
        echo "  1) Jira"
        echo "  2) Todoist"
        echo "  3) Sentry"
        echo "  4) Local file"
        echo "  5) New initiative"
        echo ""
        prompt_user "Enter choice (1-5)" source_choice
        
        case $source_choice in
            1)
                prompt_user "Enter Jira key (e.g., UE-268)" IDENTIFIER
                ;;
            2)
                print_info "Todoist integration coming soon"
                exit 0
                ;;
            3)
                prompt_user "Enter Sentry issue ID" IDENTIFIER
                TYPE="sentry-error"
                ;;
            4)
                prompt_user "Enter file path" LOCAL_FILE
                IDENTIFIER="$LOCAL_FILE"
                TYPE="local-file"
                ;;
            5)
                prompt_user "Enter task name" task_name
                IDENTIFIER="manual-$(date +%Y%m%d-%H%M%S)"
                TYPE="manual"
                ;;
        esac
    fi
    
    # Auto-detect type if not specified
    if [[ -z "$TYPE" ]]; then
        TYPE=$(detect_type "$IDENTIFIER")
        print_info "Detected type: $TYPE"
    fi
    
    # Select profile if not specified
    if [[ -z "$PROFILE" ]]; then
        PROFILE=$(select_profile "$TYPE")
        print_info "Selected profile: $PROFILE"
    fi
    
    # Select phases
    PHASES=$(select_phases "$PROFILE")
    print_info "Active phases: $PHASES"
    
    # Create workspace
    print_info "Creating workspace..."
    WORKSPACE=$(create_workspace "$IDENTIFIER" "$TYPE" "$PROFILE")
    print_success "Workspace created: $WORKSPACE"
    
    # Create initialization report
    cat > "$WORKSPACE/init-report.md" <<EOF
# Initialization Report

## Configuration
- **Identifier**: $IDENTIFIER
- **Type**: $TYPE
- **Profile**: $PROFILE
- **Phases**: $PHASES
- **Workspace**: $WORKSPACE
- **Initialized**: $(date)

## Next Steps
1. Review the analysis template
2. Run MCP commands to fetch details
3. Start with the first active phase
4. Use TodoWrite to track progress

## Commands
\`\`\`bash
# Navigate to workspace
cd $WORKSPACE

# Fetch Jira details (if applicable)
claude-mcp jira get-issue $IDENTIFIER

# Start tracking
claude-mcp todoist create-task "Work on $IDENTIFIER"
\`\`\`
EOF
    
    # Final message
    echo ""
    print_success "Feature initialization complete!"
    echo ""
    echo "Workspace created at:"
    echo -e "${GREEN}$WORKSPACE${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. cd $WORKSPACE"
    echo "  2. Review the templates"
    echo "  3. Start with the first phase"
    echo ""
    
    # Optionally open in editor
    if prompt_yes_no "Open workspace in editor?"; then
        code "$WORKSPACE" 2>/dev/null || echo "Please open $WORKSPACE in your editor"
    fi
}

show_help() {
    cat <<EOF
Feature Initialization Script

Usage: ./init-feature.sh [IDENTIFIER] [OPTIONS]

Options:
  --type=TYPE          Force specific type (epic, story, bug, etc.)
  --profile=PROFILE    Use specific profile
  --interactive        Run in interactive mode
  --local=FILE         Initialize from local file
  --help               Show this help message

Examples:
  ./init-feature.sh UE-268                    # Auto-detect Jira story
  ./init-feature.sh UE-250 --type=epic        # Force epic type
  ./init-feature.sh --interactive             # Interactive mode
  ./init-feature.sh --local=todos/feature.md  # From local file

Profiles:
  - hotfix: Urgent production fix
  - bugfix: Standard bug fix
  - story-simple: Simple story (CRUD)
  - story-standard: Standard story
  - story-complex: Complex story
  - epic-standard: Standard epic

Phases:
  - discovery: Understand requirements
  - analysis: Technical analysis
  - design: Architecture design
  - tdd: Test-driven development
  - implementation: Code development (required)
  - validation: Quality checks (required)
  - documentation: API docs and guides
EOF
}

# Run main function
main "$@"