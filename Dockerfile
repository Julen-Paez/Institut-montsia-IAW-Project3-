# Dockerfile
# Imatge base: Apache + PHP 8.2
FROM php:8.2-apache

# Instal·lar extensions necessàries
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli

# Activar mod_rewrite d'Apache
RUN a2enmod rewrite

# Configuració permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
