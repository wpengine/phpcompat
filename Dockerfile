# Start with the latest WordPress image.
FROM wordpress:5.8.0-php7.4-apache

# Set up nodejs PPA
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -

# Install server dependencies.
RUN apt-get update && apt-get install -qq -y nodejs build-essential pkg-config libcairo2-dev libjpeg-dev libgif-dev git subversion mariadb-client zip unzip vim libyaml-dev --fix-missing --no-install-recommends

# Setup phpunit dependencies (needed for coverage).
RUN pecl install xdebug && \
		docker-php-ext-enable xdebug

COPY tests/install-wp-tests.sh /
RUN /install-wp-tests.sh wordpress root password mysql 5.8.0 true

# Download wp-cli
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod 755 /usr/local/bin/wp

# Speed up phpcs https://stackoverflow.com/questions/37450185/php-code-sniffer-via-grunt-is-incredibly-slow
RUN echo "default_socket_timeout = 5\nlog_errors = On\nerror_log = /dev/stderr\nopcache.enable= 0" > /usr/local/etc/php/php.ini

# Disable PHP opcache (not great while developing)
RUN rm -rf /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install composer.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer global require "phpunit/phpunit:^6"

# Install global grunt task runner.
RUN npm install grunt-cli -g

ENV PATH="/root/.composer/vendor/bin:${PATH}"
ENV WP_VERSION="5.8.0"

# DONT run as ROOT
# RUN useradd -ms /bin/bash phpcompat
# USER phpcompat
