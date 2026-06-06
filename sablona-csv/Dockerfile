FROM php:8.2-apache

# aplikácia: jediný index.php (spracovanie CSV + UI)
COPY src/ /var/www/html/

# web root patrí www-data (UID/GID 33) — rovnaké ID používa initContainer pri chown na PVC
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
