#!/usr/bin/env bash
set -euo pipefail

# generate-tasks.sh
# Generate planned_task.md (resumable task checklist) from a profile's todo_structure or a template fallback
# Usage:
#   ./workflows/scripts/generate-tasks.sh [--profile=feature-standard] [--profile-file=Workflows/templates/profiles/feature-standard.yaml] [--output=./planned_task.md] [--key=UE-XXX] [--title="Titre"]

PROFILE=""
PROFILE_FILE=""
OUTPUT="./planned_task.md"
KEY=""
TITLE=""
DATE_STR="$(date +%F)"

for arg in "$@"; do
  case $arg in
    --profile=*) PROFILE="${arg#*=}" ;;
    --profile-file=*) PROFILE_FILE="${arg#*=}" ;;
    --output=*) OUTPUT="${arg#*=}" ;;
    --key=*) KEY="${arg#*=}" ;;
    --title=*) TITLE="${arg#*=}" ;;
    *) echo "Unknown option: $arg"; exit 1 ;;
  esac
done

# Resolve profile file from name if not provided
if [[ -z "$PROFILE_FILE" && -n "$PROFILE" ]]; then
  # Try common locations
  CAND1="Workflows/templates/profiles/${PROFILE}.yaml"
  CAND2="workflows/templates/profiles/${PROFILE}.yaml"
  if [[ -f "$CAND1" ]]; then PROFILE_FILE="$CAND1"; fi
  if [[ -z "$PROFILE_FILE" && -f "$CAND2" ]]; then PROFILE_FILE="$CAND2"; fi
fi

mkdir -p "$(dirname "$OUTPUT")"

extract_todo_structure() {
  local file="$1"
  # Extract block after `todo_structure: |` until EOF
  awk 'f{print} /todo_structure:[[:space:]]*\|/{f=1}' "$file"
}

render_template_with_meta() {
  local template_file="$1"
  sed -e "s/{{DATE}}/$DATE_STR/g" \
      -e "s/{{KEY}}/${KEY:-}/g" \
      -e "s/{{TITLE}}/${TITLE:-}/g" \
      -e "s/{{PROFILE}}/${PROFILE:-}/g" \
      -e "s/{{LINKS}}//g" \
      -e "s/{{DECISIONS}}//g" "$template_file"
}

CONTENT=""
if [[ -n "$PROFILE_FILE" && -f "$PROFILE_FILE" ]]; then
  # Attempt to extract todo structure
  TODO_BLOCK="$(extract_todo_structure "$PROFILE_FILE" || true)"
  if [[ -n "$TODO_BLOCK" ]]; then
    CONTENT="# Tasks Plan for ${KEY:-N/A} (${PROFILE:-custom})\n\nGenerated: ${DATE_STR}\n\n${TODO_BLOCK}\n"
  fi
fi

if [[ -z "$CONTENT" ]]; then
  # Fallback to tasks template
  TEMPLATE1="Workflows/templates/tasks-template.md"
  TEMPLATE2="workflows/templates/tasks-template.md"
  if [[ -f "$TEMPLATE1" ]]; then
    CONTENT="$(render_template_with_meta "$TEMPLATE1")"
  elif [[ -f "$TEMPLATE2" ]]; then
    CONTENT="$(render_template_with_meta "$TEMPLATE2")"
  else
    echo "Tasks template not found. Looked for $TEMPLATE1 and $TEMPLATE2" >&2
    exit 2
  fi
fi

printf "%s\n" "$CONTENT" > "$OUTPUT"

echo "✅ planned_task.md generated at: $OUTPUT"
