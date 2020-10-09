# Take latest PHP Image with Apache
FROM php:apache

# Unzip is requirement to install composer
RUN apt-get update && apt-get install -y unzip

# Install PHP composer and INfluxDB 1.7 module
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer require influxdb/influxdb-php

# Set correct Timezone
RUN ln -sf /usr/share/zoneinfo/Europe/Berlin /etc/localtime
RUN echo "date.timezone = Europe/Berlin" > $PHP_INI_DIR/conf.d/php-datetime.ini

# download ChartJS
RUN curl -o /var/www/html/charts.js -L https://github.com/chartjs/Chart.js/releases/download/v2.9.3/Chart.bundle.js

# default Environment Variables  for Script
ENV smadb_ip="192.168.1.3" smadb_port="8086" smadb_db="SMA" smadb_user="user" smadb_pw="pw" lang="en" table_borders="yes" chart="all" emdb_ip="192.168.1.3" emdb_port="8086" emdb_db="measurements" emdb_user="user" emdb_pw="pw"

# copy scripts into the container
ADD ./sma.php /var/www/html/sma.php
ADD ./em.php /var/www/html/em.php
