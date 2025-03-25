#!/bin/bash

# Script pour nettoyer le système de paiement et réinitialiser les migrations
# Usage: ./scripts/clean_payment_system.sh

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher un message d'information
info() {
    echo -e "${BLUE}INFO:${NC} $1"
}

# Fonction pour afficher un message de succès
success() {
    echo -e "${GREEN}SUCCESS:${NC} $1"
}

# Fonction pour afficher un avertissement
warning() {
    echo -e "${YELLOW}WARNING:${NC} $1"
}

# Fonction pour afficher une erreur
error() {
    echo -e "${RED}ERROR:${NC} $1"
    exit 1
}

# Vérifier si on est à la racine du projet
if [ ! -f "composer.json" ] || [ ! -d "src" ]; then
    error "Ce script doit être exécuté depuis la racine du projet"
fi

# Confirmation avant de continuer
echo "Ce script va supprimer :"
echo " - Toutes les entités liées au système de paiement"
echo " - Toutes les migrations existantes"
echo " - Tous les services et contrôleurs liés aux paiements"
echo ""
read -p "Êtes-vous sûr de vouloir continuer ? (o/N) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    info "Opération annulée"
    exit 0
fi

# Supprimer les entités liées aux paiements
info "Suppression des entités liées aux paiements..."

FILES_TO_REMOVE=(
    "src/Entity/Payment.php"
    "src/Entity/Subscription.php"
    "src/Entity/Invoice.php"
    "src/Repository/PaymentRepository.php"
    "src/Repository/SubscriptionRepository.php"
    "src/Repository/InvoiceRepository.php"
    "src/Interface/PaymentServiceInterface.php"
    "src/Service/Payment/PaymentIntentService.php"
    "src/Service/Payment/SubscriptionService.php"
    "src/Service/Payment/PaymentLoggerDecorator.php"
    "src/Service/Payment/PaymentServiceFactory.php"
    "src/Service/Invoice/InvoiceService.php"
    "src/Controller/Api/PaymentController.php"
    "src/Controller/Api/WebhookController.php"
)

for file in "${FILES_TO_REMOVE[@]}"; do
    if [ -f "$file" ]; then
        rm "$file"
        info "Supprimé : $file"
    else
        warning "Fichier non trouvé : $file"
    fi
done

# Suppression des migrations
info "Suppression des migrations existantes..."
rm -rf migrations/*
success "Migrations supprimées"

# Créer une nouvelle migration vide
info "Création d'une nouvelle migration vide..."
php bin/console doctrine:migrations:generate
success "Nouvelle migration créée"

# Mettre à jour la base de données avec la nouvelle migration
info "Mise à jour du schéma de la base de données..."
php bin/console doctrine:schema:update --force
success "Schéma de base de données mis à jour"

# Créer une nouvelle migration avec le schéma actuel
info "Création d'une nouvelle migration avec le schéma actuel..."
php bin/console doctrine:migrations:diff
success "Nouvelle migration créée avec le schéma actuel"

# Nettoyage des fichiers de cache
info "Nettoyage du cache..."
php bin/console cache:clear
success "Cache nettoyé"

# Message final
success "Nettoyage du système de paiement terminé avec succès !"
echo ""
echo "Pour désactiver complètement le système de paiement, assurez-vous de définir :"
echo "PAYMENT_SYSTEM_ENABLED=false dans votre fichier .env ou .env.local"
echo ""
echo "Si vous utilisez l'attribut DisablePayment, assurez-vous qu'il est appliqué"
echo "aux contrôleurs ou méthodes appropriés."