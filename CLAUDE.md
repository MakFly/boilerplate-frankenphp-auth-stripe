# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture Overview

This is a modern Symfony 7.1 boilerplate using FrankenPHP, designed for building secure REST APIs with authentication, payments, and notifications. The architecture follows hexagonal principles with clear separation of concerns.

### Key Systems

**Authentication & Authorization**
- JWT-based authentication with Lexik JWT bundle
- Google SSO integration for simplified login
- Custom authenticators and event listeners
- Multi-factor authentication (OTP) support
- Account locking mechanisms via event subscribers

**Payment System (Stripe)**
- Payment Intents for one-time payments
- Subscription management with recurring billing
- Webhook handling for payment events
- Invoice generation and management
- Payment logging and monitoring

**Notification System**
- Multi-channel notifications (Email, SMS, Discord)
- Extensible factory pattern for adding new notification types
- Asynchronous processing via Symfony Messenger
- Template-based email notifications with Twig

**Logging & Monitoring**
- Custom Discord webhook handlers for different log levels
- Structured logging with Monolog
- Comprehensive error tracking and reporting

## Development Commands

### Docker Environment
```bash
# Initial setup
make install

# Start development environment
make dev

# Access container shell
make workspace

# View logs
make logs

# Restart services
make restart

# Stop all services
make stop

# Clean shutdown with volume removal
make down
```

### Database Management
```bash
# Create and run migrations
make create-migration
make migrate-run

# Load fixtures
make charge-database

# Run database diff
make diff-migration
```

### Testing
```bash
# Prepare test database
make test-prepare

# Run all tests
make test

# Run tests with coverage
make test-coverage

# Run specific test suites
make test-unit
make test-feature
```

### Code Quality
```bash
# Run PHPStan static analysis
make stan

# Check code style (via Docker)
docker exec -it boilerplate-symfony-simple php bin/console cache:clear
```

### Stripe Integration
```bash
# Listen to Stripe webhooks locally
make listen-webhook
```

## Key Configuration Files

**Environment Variables** (see `.env.example`):
- Database configuration (PostgreSQL)
- JWT keys and configuration
- Stripe API keys and webhook secrets
- Google OAuth credentials
- Mailer configuration
- Discord webhook URLs

**Service Configuration**:
- `config/services.yaml` - Service definitions and parameters
- `config/packages/` - Bundle-specific configurations
- `phpstan.neon` - Static analysis configuration

## Code Organization

### Controllers (`src/Controller/`)
- **Api/Public/** - Public API endpoints (auth, payments, webhooks)
- **Api/Admin/** - Admin-only endpoints
- **Api/** - General API controllers

### Services (`src/Service/`)
- **Auth/** - Authentication services (JWT, Google SSO, password reset)
- **Payment/** - Payment processing services
- **Notifier/** - Notification services
- **Stripe/** - Stripe integration services
- **Webhook/** - Webhook processing

### Entities (`src/Entity/`)
- User management with JWT integration
- Payment and subscription entities
- Stripe webhook logging
- Invoice management

### Event System (`src/EventListener/`, `src/EventSubscriber/`)
- JWT token creation and validation
- Payment route protection
- Account security features (locking, OTP)
- Exception handling

### Testing (`tests/`)
- **Unit/** - Service and component tests
- **Feature/** - API endpoint tests
- Uses PestPHP testing framework
- Separate test database configuration

## Development Best Practices

### Code Standards
- PHP 8.2+ with strict typing (`declare(strict_types=1)`)
- PSR-12 coding standards
- PHPStan level 6 analysis
- Symfony 7.1 conventions
- **IMPORTANT: All function/method comments MUST be in English**
  - Use English docblocks for all functions and methods
  - Inline comments should also be in English
  - This ensures code maintainability and international collaboration

### Security
- JWT authentication for API access
- CSRF protection enabled
- Input validation with Symfony constraints
- Rate limiting on sensitive endpoints
- Secure webhook signature verification

### Database
- PostgreSQL as primary database
- Doctrine ORM with migrations
- Proper indexing for performance
- Separate test database

### Async Processing
- Symfony Messenger for notifications
- Stripe webhook processing
- Queue-based background tasks

## Common Development Tasks

### Adding New API Endpoints
1. Create controller in appropriate directory
2. Add route attributes or YAML configuration
3. Implement proper validation with DTOs
4. Add authentication/authorization as needed
5. Write tests in `tests/Feature/`

### Extending Payment System
1. Services implement `PaymentServiceInterface`
2. Use factory pattern for service creation
3. Add logging via `PaymentLoggerDecorator`
4. Update webhook handlers if needed

### Adding Notification Channels
1. Implement `NotifierInterface`
2. Register in `NotifierFactory`
3. Add to dependency injection configuration
4. Create message templates if needed

### Database Changes
1. Create migration: `make create-migration`
2. Review generated migration
3. Run migration: `make migrate-run`
4. Update fixtures if needed

## Testing Strategy

**Unit Tests**: Focus on service layer logic, isolated components
**Feature Tests**: Test API endpoints, integration between components
**Test Database**: Separate PostgreSQL database for testing
**Coverage**: Aim for comprehensive coverage of business logic

## Deployment Notes

- Uses FrankenPHP for production deployment
- Docker-based containerization
- Environment-specific configurations
- Proper secret management required
- Database migrations run automatically on deployment