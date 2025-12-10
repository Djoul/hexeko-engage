#!/bin/sh
# Run Rector and always continue (never fail the commit)
vendor/bin/rector process --ansi || true
# Add any modified files to the current commit
git add -u
# Always exit with success
exit 0
