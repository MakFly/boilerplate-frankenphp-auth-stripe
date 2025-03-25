#!/bin/bash

echo "Liste des bases de données PostgreSQL :"
docker exec boilerplate-symfony-frankenphp-simple-postgres-1 psql -U symfony_test -c "\l"

# Vérification de l'existence de la base de données symfony_test
echo "\nVérification de la base de données de test..."
result=$(docker exec boilerplate-symfony-simple php bin/console doctrine:database:exists --env=test 2>&1)

if [[ $result == *"Could not find"* ]]; then
    echo "La base de données symfony_test n'existe pas. Création en cours..."
    docker exec boilerplate-symfony-simple php bin/console doctrine:database:create --env=test
    echo "Base de données symfony_test créée avec succès"
    
    echo "\nNouvelle liste des bases de données :"
    docker exec boilerplate-symfony-frankenphp-simple-postgres-1 psql -U test -c "\l"
else
    echo "La base de données symfony_test existe déjà"
fi