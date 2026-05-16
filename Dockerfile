# Dockerfile
# Imatge base: Apache + PHP 8.2
FROM php:8.2-apache

# Instal·lar extensions necessàries
RUN docker-php-ext-install mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli

# Activar mod_rewrite d'Apache
RUN a2enmod rewrite

# Copiar l'script d'inicialització
COPY docker-entrypoint-custom.sh /usr/local/bin/docker-entrypoint-custom.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-custom.sh

# Configuració permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Usar l'script personalitzat com a entrypoint
CMD ["/usr/local/bin/docker-entrypoint-custom.sh"]
