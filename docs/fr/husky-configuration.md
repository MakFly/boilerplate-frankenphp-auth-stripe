# Configuration de Husky

## Introduction

Husky est un outil puissant qui permet de gérer les hooks Git dans notre projet Symfony. Il est utilisé pour maintenir la qualité du code en exécutant automatiquement des vérifications avant chaque commit et en s'assurant que les messages de commit suivent les conventions établies.

## Prérequis

- Node.js (v16 ou supérieur)
- PNPM (gestionnaire de paquets)
- Git
- Docker (le projet s'exécute dans des conteneurs)

## Installation

Le projet utilise PNPM comme gestionnaire de paquets. Husky est automatiquement installé et configuré grâce au script `prepare` dans le `package.json`.

```bash
# Installation des dépendances incluant Husky
pnpm install
```

## Hooks Git configurés

### 1. Pre-commit (`/.husky/pre-commit`)

Ce hook s'exécute automatiquement avant chaque commit et effectue les vérifications suivantes :

1. **Validation du code PHP** :
   - Analyse statique avec PHPStan (niveau 9)
   - Vérification des normes PSR-12
   - Validation du composer.json

2. **Tests automatisés** :
   - Exécution des migrations de base de données en environnement de test
   - Lancement de la suite de tests avec PestPHP

```bash
# Commande exécutée par le hook pre-commit
pnpm run check
```

### 2. Commit-msg (`/.husky/commit-msg`)

Ce hook vérifie que les messages de commit suivent les conventions Conventional Commits.

Format attendu :
```
type(scope): description

[corps]
[pied de page]
```

Types de commit autorisés :
- `feat`: Nouvelle fonctionnalité
- `fix`: Correction de bug
- `docs`: Modification de la documentation
- `style`: Changements de formatage
- `refactor`: Refactorisation du code
- `test`: Ajout ou modification de tests
- `chore`: Autres modifications

## Configuration détaillée

### Package.json

Les scripts principaux :
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

Le fichier `commitlint.config.js` définit les règles pour les messages de commit :
```javascript
module.exports = {
  extends: ["@commitlint/config-conventional"]
};
```

## Résolution des problèmes courants

### 1. Erreur TTY dans les hooks

Si vous rencontrez des erreurs liées au TTY dans Docker, assurez-vous de ne pas utiliser les flags `-t` ou `-ti` dans les commandes Docker des scripts.

### 2. Contournement des hooks

Pour ignorer temporairement les hooks Husky :
```bash
HUSKY=0 git commit -m "votre message"
```

⚠️ À utiliser avec précaution et uniquement dans des cas exceptionnels.

## Maintenance

### Mise à jour de Husky

```bash
# Mettre à jour Husky
pnpm update husky

# Mettre à jour les dépendances de commitlint
pnpm update @commitlint/cli @commitlint/config-conventional
```

### Bonnes pratiques

1. **Messages de commit** :
   ```bash
   # Bon exemple
   git commit -m "feat(auth): ajouter l'authentification OAuth2"
   
   # Mauvais exemple
   git commit -m "modifications"
   ```

2. **Vérification locale** :
   ```bash
   # Tester les hooks manuellement
   pnpm run check
   ```

## Support

En cas de problèmes avec la configuration Husky :
1. Vérifier les logs dans la sortie de la commande git
2. S'assurer que tous les prérequis sont installés
3. Vérifier que les chemins dans les scripts correspondent à votre environnement Docker

## Notes importantes

- Les hooks s'exécutent depuis la racine du projet
- La configuration est optimisée pour l'environnement Docker
- Les tests sont exécutés dans un conteneur Docker dédié
- Les chemins dans les scripts doivent correspondre aux noms des services Docker