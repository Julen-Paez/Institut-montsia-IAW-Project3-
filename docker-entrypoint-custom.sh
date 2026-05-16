#!/bin/bash
# docker-entrypoint-custom.sh
# S'executa quan el contenidor web arrenca.
# Espera que la BD estigui llesta i actualitza les contrasenyes.

echo "⏳ Esperant que la BD estigui llesta..."
sleep 10

echo "🔑 Actualitzant contrasenyes..."
php /var/www/html/docker-init.php

echo "✅ Inicialització completada."

# Arrenca Apache normalment
apache2-foreground
