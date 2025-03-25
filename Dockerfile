# Stage 1: Base image
FROM dunglas/frankenphp AS base

LABEL maintainer="🐘🐳🐧🚀 Inopan"

# Définition des arguments globaux
ARG ENVIRONMENT
ARG SERVER_NAME

# Définir les variables d'environnement
ENV SERVER_NAME=${SERVER_NAME}
ENV APP_ENV=${ENVIRONMENT}

RUN echo "SERVER_NAME=$SERVER_NAME"

# Définir le répertoire de travail
WORKDIR /app

# Installer les extensions PHP nécessaires, en fonction de l'environnement
RUN install-php-extensions \
    @composer \
    pdo_pgsql \
    gd \
    intl \
    zip \
    bcmath \
    xsl \
    redis \
    amqp \
    $(if [ "$ENVIRONMENT" = "prod" ]; then echo "opcache"; else echo "xdebug"; fi)

# Installer des paquets supplémentaires en fonction de l'environnement
RUN apt-get update && \
    apt-get install -y git libnss3-tools redis-server && \
    if [ "$ENVIRONMENT" = "dev" ]; then \
        curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | bash && \
        apt-get update && \
        apt-get install -y symfony-cli; \
    fi && \
    apt-get clean

# Copier les fichiers de configuration
COPY ./Caddyfile /etc/caddy/Caddyfile

# Copier les fichiers du projet
COPY . /app

# Stage 2: Builder
FROM base AS builder

# Copy composer files
COPY composer.json composer.lock ./

# Installer les dépendances PHP avec ou sans les dépendances de développement
RUN if [ "$ENVIRONMENT" = "prod" ]; then \
        composer install --no-dev --optimize-autoloader --no-scripts; \
    else \
        composer install --no-scripts; \
    fi

# Stage 3: Image finale
FROM base AS final

COPY --from=builder /app/vendor /app/vendor
