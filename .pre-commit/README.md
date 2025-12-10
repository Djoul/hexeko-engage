# Pre-commit Configuration

## Overview
This project uses [pre-commit](https://pre-commit.com/) to run automated checks and formatting before each commit.

## Installation Status
✅ Pre-commit is now installed and configured!

## What Runs on Every Commit

### 1. **Branch Protection** (`protect-dev-branch`)
- Prevents direct commits to the `dev` branch
- Ensures proper workflow with feature branches
- **Bypass**: Use `git commit --no-verify` (not recommended)

### 2. **Security Checks**
- `detect-aws-credentials`: Prevents AWS credentials from being committed
- `detect-private-key`: Detects private keys in code
- `check-yaml`: Validates YAML syntax

### 3. **Code Quality - Debug Tools** (`no-debug-tools`)
Prevents committing debugging statements:
- `ray()`
- `dump()` / `dd()`
- `var_dump()` / `print_r()` / `var_export()`
- `debug_backtrace()`
- `xdebug_*`
- `error_log()`

**Exception**: `_ide_helper.php` is excluded

### 4. **Auto-formatting - Laravel Pint** (`laravel-pint`)
- Automatically formats PHP code according to PSR-12
- Changes are automatically added to the commit
- **Never fails** - always exits with success

### 5. **Code Modernization - Rector** (`laravel-rector`)
- Applies PHP modernizations and best practices
- Changes are automatically added to the commit
- **Never fails** - always exits with success

## Commands

### Run all checks manually
```bash
pre-commit run --all-files
```

### Run specific hook
```bash
pre-commit run protect-dev-branch
pre-commit run no-debug-tools
pre-commit run laravel-pint
pre-commit run laravel-rector
```

### Run with verbose output
```bash
pre-commit run --all-files --verbose
```

### Reinstall hooks
```bash
pre-commit install
```

### Bypass pre-commit (use with caution!)
```bash
git commit --no-verify
```

## Performance Notes

- **Pint and Rector** can be slow on first run
- Subsequent runs are faster due to caching
- Only modified PHP files are checked by default
- Use `--all-files` sparingly in development

## Troubleshooting

### Hook not running
```bash
pre-commit install
```

### Clear cache
```bash
pre-commit clean
```

### Update hooks
```bash
pre-commit autoupdate
```

## Files Structure
```
.pre-commit-config.yaml       # Main configuration
.pre-commit/
├── protect-branch.sh         # Branch protection script
├── rector.sh                 # Rector wrapper script
└── README.md                 # This file
```

## Development Workflow

1. Create feature branch: `git checkout -b feature/my-feature`
2. Make changes
3. Stage files: `git add .`
4. Commit: `git commit -m "feat: my feature"`
   - Pre-commit hooks run automatically
   - Pint formats code
   - Rector applies improvements
   - Security checks validate
   - Debug tools are detected
5. Review auto-formatted changes if any
6. Push and create MR

## Notes

- Pint and Rector **auto-fix** code and add changes to commit
- If hooks fail, fix the issues and commit again
- Never commit to `dev` directly - always use feature branches
- Use `git commit --no-verify` only in exceptional cases