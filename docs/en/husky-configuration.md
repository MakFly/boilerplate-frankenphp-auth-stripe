# Husky Configuration

## Introduction

Husky is a powerful tool that allows managing Git hooks in our Symfony project. It is used to maintain code quality by automatically running checks before each commit and ensuring commit messages follow established conventions.

## Prerequisites

- Node.js (v16 or higher)
- PNPM (package manager)
- Git
- Docker (project runs in containers)

## Installation

The project uses PNPM as package manager. Husky is automatically installed and configured through the `prepare` script in `package.json`.

```bash
# Install dependencies including Husky
pnpm install
```

## Configured Git Hooks

### 1. Pre-commit (`/.husky/pre-commit`)

This hook runs automatically before each commit and performs the following checks:

1. **PHP Code Validation**:
   - Static analysis with PHPStan (level 9)
   - PSR-12 standards verification
   - composer.json validation

2. **Automated Tests**:
   - Database migrations execution in test environment
   - Test suite launch with PestPHP

```bash
# Command executed by pre-commit hook
pnpm run check
```

### 2. Commit-msg (`/.husky/commit-msg`)

This hook verifies that commit messages follow Conventional Commits conventions.

Expected format:
```
type(scope): description

[body]
[footer]
```

Allowed commit types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Formatting changes
- `refactor`: Code refactoring
- `test`: Adding or modifying tests
- `chore`: Other changes

## Detailed Configuration

### Package.json

Main scripts:
```json
{
  "scripts": {
    "test": "docker exec boilerplate-symfony-simple php bin/console d:m:m --env=test --no-interaction && docker exec boilerplate-symfony-simple ./vendor/bin/pest",
    "phpstan": "vendor/bin/phpstan analyse",
    "check": "composer validate && npm run phpstan && npm run test",
    "prepare": "husky install"
  }
}
```

### Commitlint

The `commitlint.config.js` file defines rules for commit messages:
```javascript
module.exports = {
  extends: ["@commitlint/config-conventional"]
};
```

## Common Issues Resolution

### 1. TTY Error in Hooks

If you encounter TTY-related errors in Docker, make sure not to use `-t` or `-ti` flags in Docker commands within scripts.

### 2. Bypassing Hooks

To temporarily ignore Husky hooks:
```bash
HUSKY=0 git commit -m "your message"
```

⚠️ Use with caution and only in exceptional cases.

## Maintenance

### Updating Husky

```bash
# Update Husky
pnpm update husky

# Update commitlint dependencies
pnpm update @commitlint/cli @commitlint/config-conventional
```

### Best Practices

1. **Commit Messages**:
   ```bash
   # Good example
   git commit -m "feat(auth): add OAuth2 authentication"
   
   # Bad example
   git commit -m "changes"
   ```

2. **Local Verification**:
   ```bash
   # Test hooks manually
   pnpm run check
   ```

## Support

If you encounter issues with Husky configuration:
1. Check logs in git command output
2. Ensure all prerequisites are installed
3. Verify paths in scripts match your Docker environment

## Important Notes

- Hooks run from project root
- Configuration is optimized for Docker environment
- Tests run in dedicated Docker container
- Paths in scripts must match Docker service names