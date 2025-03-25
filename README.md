# Boilerplate Symfony FrankenPHP

[English](#english) | [Français](#français)

# Français

Un boilerplate Symfony moderne et robuste utilisant FrankenPHP, conçu pour le développement d'APIs REST sécurisées.

## 🚀 Caractéristiques

- **PHP 8.2+** avec les dernières fonctionnalités
- **Symfony 7.0+** 
- **Docker** avec FrankenPHP pour un environnement de développement optimisé
- **Architecture modulaire** suivant les principes SOLID
- **Tests** avec PestPHP
- Système d'**authentification JWT** avec support SSO Google
- Système de **paiement** intégré avec Stripe
- Système de **notifications** multi-canaux

## 📦 Prérequis

- Docker & Docker Compose
- Make (pour les commandes utilitaires)
- Git

## 🛠️ Installation

```bash
git clone [repo-url]
cd boilerplate-symfony-frankenphp-simple
make install
```

## 💡 Architecture du Projet

### Systèmes Principaux

1. **Système d'Authentification SSO Google**
   - Authentification simplifiée via Google
   - Gestion JWT pour les sessions
   - Support frontend NextJS avec better-auth
   - Liaison automatique des comptes

2. **Système de Paiement Stripe**
   - Paiements ponctuels (Payment Intents)
   - Abonnements récurrents
   - Webhooks sécurisés
   - Génération automatique de factures
   - Interface frontend NextJS

3. **Système de Notifications**
   - Architecture extensible multi-canaux
   - Support Email (Symfony Mailer)
   - Support SMS
   - Notifications push
   - Pattern Strategy & Factory

## 🔒 Sécurité

- JWT pour l'authentification API
- Protection CSRF
- Rate limiting
- Validation des données entrantes
- Gestion sécurisée des tokens

## 📝 Convention de Code

- PSR-12
- Typage strict (declare(strict_types=1))
- Architecture hexagonale
- Tests unitaires et fonctionnels
- Documentation PHPDoc

## 🗄️ Structure des Dossiers

```
src/
  ├── Attribute/         # Attributs PHP
  ├── Controller/        # Controllers API
  ├── Entity/           # Entités Doctrine
  ├── Service/          # Services métier
  ├── Interface/        # Interfaces
  ├── Repository/       # Repositories Doctrine
  ├── EventListener/    # Listeners d'événements
  └── EventSubscriber/  # Subscribers d'événements
```

## 🔧 Configuration

Les variables d'environnement principales sont à définir dans `.env`:

```env
# Base
APP_ENV=dev
APP_SECRET=your_secret

# Database
DATABASE_URL=postgresql://user:pass@postgres:5432/db_name

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase

# Google SSO
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Stripe
STRIPE_PUBLIC_KEY=your_stripe_public_key
STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret
```

## 🧪 Tests

```bash
# Préparation de la base de test
make test-prepare

# Exécution des tests
make test

# Tests avec couverture
make test-coverage
```

## 🛠️ Commandes Make Disponibles

- `make install` : Installation initiale du projet
- `make dev` : Lance l'environnement de développement
- `make test` : Lance les tests
- `make stan` : Analyse statique du code
- `make workspace` : Ouvre un shell dans le container
- `make logs` : Affiche les logs
- `make restart` : Redémarre les containers

## 📚 Documentation Détaillée

La documentation est disponible en français et en anglais :

**Français**
- [Système de Notifications](docs/fr/notification-system.md)
- [Système de Paiement](docs/fr/payment-system.md)
- [Authentification SSO Google](docs/fr/sso-google-authentication.md)
- [Configuration Husky](docs/fr/husky-configuration.md)

**English**
- [Notification System](docs/en/notification-system.md)
- [Payment System](docs/en/payment-system.md)
- [Google SSO Authentication](docs/en/sso-google-authentication.md)
- [Husky Configuration](docs/en/husky-configuration.md)

---

# English

A modern and robust Symfony boilerplate using FrankenPHP, designed for secure REST API development.

## 🚀 Features

- **PHP 8.2+** with latest features
- **Symfony 7.0+** 
- **Docker** with FrankenPHP for optimized development environment
- **Modular architecture** following SOLID principles
- **Tests** with PestPHP
- **JWT authentication** system with Google SSO support
- Integrated **payment system** with Stripe
- Multi-channel **notification** system

## 📦 Prerequisites

- Docker & Docker Compose
- Make (for utility commands)
- Git

## 🛠️ Installation

```bash
git clone [repo-url]
cd boilerplate-symfony-frankenphp-simple
make install
```

## 💡 Project Architecture

### Main Systems

1. **Google SSO Authentication System**
   - Simplified authentication via Google
   - JWT session management
   - NextJS frontend support with better-auth
   - Automatic account linking

2. **Stripe Payment System**
   - One-time payments (Payment Intents)
   - Recurring subscriptions
   - Secure webhooks
   - Automatic invoice generation
   - NextJS frontend interface

3. **Notification System**
   - Extensible multi-channel architecture
   - Email support (Symfony Mailer)
   - SMS support
   - Push notifications
   - Strategy & Factory patterns

## 🔒 Security

- JWT for API authentication
- CSRF protection
- Rate limiting
- Input data validation
- Secure token management

## 📝 Code Convention

- PSR-12
- Strict typing (declare(strict_types=1))
- Hexagonal architecture
- Unit and functional tests
- PHPDoc documentation

## 🗄️ Folder Structure

```
src/
  ├── Attribute/         # PHP Attributes
  ├── Controller/        # API Controllers
  ├── Entity/           # Doctrine Entities
  ├── Service/          # Business Services
  ├── Interface/        # Interfaces
  ├── Repository/       # Doctrine Repositories
  ├── EventListener/    # Event Listeners
  └── EventSubscriber/  # Event Subscribers
```

## 🔧 Configuration

Main environment variables to be defined in `.env`:

```env
# Base
APP_ENV=dev
APP_SECRET=your_secret

# Database
DATABASE_URL=postgresql://user:pass@postgres:5432/db_name

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase

# Google SSO
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret

# Stripe
STRIPE_PUBLIC_KEY=your_stripe_public_key
STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret
```

## 🧪 Tests

```bash
# Prepare test database
make test-prepare

# Run tests
make test

# Tests with coverage
make test-coverage
```

## 🛠️ Available Make Commands

- `make install` : Initial project installation
- `make dev` : Start development environment
- `make test` : Run tests
- `make stan` : Static code analysis
- `make workspace` : Open shell in container
- `make logs` : Display logs
- `make restart` : Restart containers

## 📚 Detailed Documentation

For more details on each system, see:

- [Notification System Documentation](docs/en/notification-system.md)
- [Payment System Documentation](docs/en/payment-system.md)
- [Google SSO Authentication Documentation](docs/en/sso-google-authentication.md)

## 🤝 Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.