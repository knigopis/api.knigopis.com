FROM php:7.4-fpm

ARG WEB_USER_ID=33
ARG WEB_USER_NAME=www-data
RUN useradd -m -u ${WEB_USER_ID} ${WEB_USER_NAME} || echo "User exists, it's ok." \
    && sed -i -- "s/user = www-data/user = ${WEB_USER_NAME}/g" /usr/local/etc/php-fpm.d/www.conf

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/bin --filename=composer \
  && php -r "unlink('composer-setup.php');" \
  && mkdir /var/www/.composer && chown "${WEB_USER_ID}" /var/www/.composer

RUN apt-get update && apt-get install git unzip locales locales-all -y

USER ${WEB_USER_ID}
