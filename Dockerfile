FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libssl-dev libonig-dev pkg-config libzstd-dev \
    && docker-php-ext-install mbstring pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p /var/www/html/data /var/www/html/assets/images \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/assets/images

EXPOSE 10000

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
