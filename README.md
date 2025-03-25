# Boilerplate Symfony FrankenPHP

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

Pour plus de détails sur chaque système :

- [Documentation du Système de Notifications](docs/notification-system.md)
- [Documentation du Système de Paiement](docs/payment-system.md)
- [Documentation de l'Authentification SSO Google](docs/sso-google-authentication.md)

## 🤝 Contribution

1. Fork le projet
2. Créez votre branche (`git checkout -b feature/amazing-feature`)
3. Committez vos changements (`git commit -m 'feat: add amazing feature'`)
4. Push sur la branche (`git push origin feature/amazing-feature`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT - voir le fichier [LICENSE](LICENSE) pour plus de détails.