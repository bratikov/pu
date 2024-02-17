FROM alpine:3.19

RUN apk add php83-fpm \
    php83-cli \ 
	php83-pear \
    php83-iconv \
	php83-xdebug \
    php83-mbstring \
	php83-zip \
    php83-dev \
    php83-openssl \
    gcc \ 
    musl-dev \
    make \
	bash

RUN pecl83 install dbase
RUN ln -s /usr/sbin/php-fpm83 /usr/sbin/php-fpm
RUN ln -s /usr/bin/php83 /usr/bin/php
RUN /bin/sh -c set -eux; adduser -u 82 -D -S -G www-data www-data
RUN echo "extension=dbase.so" > /etc/php83/conf.d/00_dbase.ini

RUN set -ex \
    && cd /etc/php83 \
    && { \
		echo '[global]'; \
		echo 'error_log = /proc/self/fd/2'; \
		echo; echo '; https://github.com/docker-library/php/pull/725#issuecomment-443540114'; echo 'log_limit = 8192'; \
		echo; \
		echo '[www]'; \
		echo '; if we send this to /proc/self/fd/1, it never appears'; \
		echo 'access.log = /proc/self/fd/2'; \
		echo; \
		echo 'clear_env = no'; \
		echo; \
		echo '; Ensure worker stdout and stderr are sent to the main error log.'; \
		echo 'catch_workers_output = yes'; \
		echo 'decorate_workers_output = no'; \
	} | tee php-fpm.d/hcc.conf \
	&& { \
		echo '[global]'; \
		echo 'daemonize = no'; \
		echo; \
		echo '[www]'; \
		echo 'listen = 9000'; \
        echo 'user = www-data'; \
        echo 'group = www-data'; \
		echo 'pm.max_children = 64'; \
		echo 'pm.start_servers = 6'; \
		echo 'pm.min_spare_servers = 4'; \
		echo 'pm.max_spare_servers = 8'; \
	} | tee php-fpm.d/zz-hcc.conf

COPY app.ini /etc/php83/conf.d/
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]