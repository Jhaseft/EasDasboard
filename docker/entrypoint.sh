#!/bin/sh
# Entrypoint del panel: prepara la app y arranca web + queue worker (supervisor).
set -e

cd /var/www/html

# Migra la base de datos (idempotente: solo aplica lo pendiente). Crea, entre
# otras, la tabla 'jobs' que necesita el queue worker (QUEUE_CONNECTION=database).
php artisan migrate --force || echo "AVISO: migrate falló (¿BD no lista?). Continúo."

# Enlace de storage para archivos públicos (ignora si ya existe).
php artisan storage:link 2>/dev/null || true

# Cachea la configuración con las variables de entorno reales del contenedor.
# (NO usamos route:cache: hay rutas con closures en web.php y fallaría.)
php artisan config:cache || true

# Arranca supervisor: levanta el servidor web y el queue worker a la vez.
exec supervisord -c /var/www/html/docker/supervisord.conf
