#!/bin/sh
# Hook to prevent direct commits on the dev branch

current_branch=$(git symbolic-ref HEAD 2>/dev/null | sed 's/refs\/heads\///')

if [ "$current_branch" = "dev" ]; then
    echo "❌ ERROR: Direct commits are not allowed on the 'dev' branch!"
    echo ""
    echo "Please follow the proper workflow:"
    echo "  1. Create a feature branch: git checkout -b feature/your-feature"
    echo "  2. Make your changes and commit them"
    echo "  3. Push your branch to remote"
    echo "  4. Create a Merge Request via GitLab"
    echo ""
    echo "If you really need to commit to dev (not recommended):"
    echo "  - Use: git commit --no-verify"
    echo ""
    exit 1
fi

# Allow commits on other branches
exit 0