FROM php:7.0-apache

# packages configuration
RUN echo 'mysql-server mysql-server/root_password password P4ssw0rd' | debconf-set-selections
RUN echo 'mysql-server mysql-server/root_password_again password P4ssw0rd' | debconf-set-selections

RUN apt-get update && apt-get install -y perl pwgen --no-install-recommends && rm -rf /var/lib/apt/lists/*

# gpg: key 5072E1F5: public key "MySQL Release Engineering <mysql-build@oss.oracle.com>" imported
RUN apt-key adv --keyserver ha.pool.sks-keyservers.net --recv-keys A4A9406876FCBD3C456770C88C718D3B5072E1F5

ENV MYSQL_MAJOR 5.6

RUN echo "deb http://repo.mysql.com/apt/debian/ jessie mysql-${MYSQL_MAJOR}" > /etc/apt/sources.list.d/mysql.list

# the "/var/lib/mysql" stuff here is because the mysql-server postinst doesn't have an explicit way to disable the mysql_install_db codepath besides having a database already "configured" (ie, stuff in /var/lib/mysql/mysql)
# also, we set debconf keys to make APT a little quieter
RUN { \
		echo mysql-community-server mysql-community-server/data-dir select ''; \
		echo mysql-community-server mysql-community-server/root-pass password ''; \
		echo mysql-community-server mysql-community-server/re-root-pass password ''; \
		echo mysql-community-server mysql-community-server/remove-test-db select false; \
	} | debconf-set-selections \
	&& apt-get update && apt-get install -y mysql-server && rm -rf /var/lib/apt/lists/* \
	&& rm -rf /var/lib/mysql && mkdir -p /var/lib/mysql

# comment out a few problematic configuration values
# don't reverse lookup hostnames, they are usually another container
RUN sed -Ei 's/^(bind-address|log)/#&/' /etc/mysql/my.cnf && echo 'skip-host-cache\nskip-name-resolve' | awk '{ print } $1 == "[mysqld]" && c == 0 { c = 1; system("cat") }' /etc/mysql/my.cnf > /tmp/my.cnf && mv /tmp/my.cnf /etc/mysql/my.cnf

# packages/dependencies installation
RUN apt-get update && apt-get install -y \
  php5-mysql \
  libmcrypt-dev \
  libxml2-dev \
  libfreetype6-dev \
  libjpeg62-turbo-dev \
  libicu-dev \
  vim emacs-nox \
  git-core \
  wget \
  libpng12-dev \
  libxslt-dev \
  zsh \
  bzip2

RUN docker-php-ext-install -j$(nproc) soap mysqli mcrypt gd zip intl xsl mbstring pdo pdo_mysql
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && docker-php-ext-install -j$(nproc) gd

RUN chsh -s /bin/zsh
RUN sh -x -c "$(curl -fsSL https://raw.github.com/robbyrussell/oh-my-zsh/master/tools/install.sh | grep -v 'set -e')"
COPY bin/.zshrc /root/.zshrc

RUN cd /tmp && curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

RUN cd /var/www/ && mkdir htdocs && cd htdocs && wget https://demos.algolia.com/downloads/magento2.0.2-with-sample-data.tar.bz2
RUN cd /var/www/htdocs && tar -xjvf magento2.0.2-with-sample-data.tar.bz2
RUN chown -R www-data:www-data /var/www/htdocs
RUN find /var/www/htdocs -type d -exec chmod 700 {} \; && find . -type f -exec chmod 600 {} \;
RUN cd /var/www/htdocs && chmod +x bin/magento
RUN chsh -s /bin/bash www-data
#RUN su www-data && cd /var/www/htdocs && composer install

WORKDIR /var/www/htdocs

COPY bin/php.ini /usr/local/etc/php/php.ini
RUN cd /tmp && curl -O https://phpmyadmin-downloads-532693.c.cdn77.org/phpMyAdmin/4.4.9/phpMyAdmin-4.4.9-english.tar.gz && tar xvf phpMyAdmin-4.4.9-english.tar.gz && mv phpMyAdmin-4.4.9-english /var/www/htdocs/phpmyadmin
COPY bin/config.inc.php /var/www/htdocs/phpmyadmin/


RUN service mysql start && mysql -uroot -e "create database magento;" && service mysql stop
RUN service mysql start && cd /var/www/htdocs && bin/magento setup:install --backend-frontname="admin" --db-host="localhost" --db-name="magento" --db-user="root" --base-url="http://192.168.99.100" --language="en_US" --currency="EUR" --timezone="Europe/Paris" --admin-use-security-key="0" --admin-user="admin" --admin-password="magentorocks1" --admin-firstname="admin" --admin-lastname="admin" --admin-email="admin@admin.com" && service mysql stop
RUN service mysql start && cd /var/www/htdocs && bin/magento cache:flush && bin/magento cache:disable && service mysql stop

RUN sed -i "s|www/html|www/htdocs|g" /etc/apache2/apache2.conf

RUN mv pub/errors/local.xml.sample pub/errors/local.xml

# enable mod_rewrite
RUN cp /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load

# magerun
RUN cd /var/www/htdocs && wget https://files.magerun.net/n98-magerun2.phar && mv n98-magerun2.phar /usr/bin/n98-magerun2 && chmod -R 777 /usr/bin/n98-magerun2

# start script
COPY ./bin/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN usermod -a -G root www-data
RUN usermod -a -G www-data root

COPY ./bin/auth.json /root/.composer/auth.json
RUN service mysql start && composer require algolia/algoliasearch-magento-2:dev-master && bin/magento setup:upgrade && bin/magento setup:static-content:deploy && service mysql stop

#RUN echo "<Directory /var/www/>\n\tAllowOverride All\n\tRequire all granted\n</Directory>" > /etc/apache2/apache2.conf
RUN sed -i 's/name="login\[username\]"/name="login[username]" value="admin"/g' /var/www/htdocs/vendor/magento/module-backend/view/adminhtml/templates/admin/login.phtml && \
	sed -i 's/name="login\[password\]"/name="login[password]" class="required-entry input-text" value="magentorocks1"/g' /var/www/htdocs/vendor/magento/module-backend/view/adminhtml/templates/admin/login.phtml && \
	sed -i 's/<\/form>/<\/form><script>document.addEventListener\("DOMContentLoaded", function\(\) {algoliaAdminBundle.\$\(function \(\$\) \{ \$\("#login-form"\).submit\(\); \}\);\}\);<\/script>/g' /var/www/htdocs/vendor/magento/module-backend/view/adminhtml/templates/admin/login.phtml

RUN sed -i 's/MaterializationStrategy\\Copy/MaterializationStrategy\\Symlink/g' /var/www/htdocs/app/etc/di.xml

RUN bin/magento deploy:mode:set developer

EXPOSE 80
CMD start.sh