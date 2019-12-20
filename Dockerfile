FROM centos:7

#version defined
ENV SWOOLE_VERSION 4.4.12
ENV EASYSWOOLE_VERSION 3.x-dev
ENV PHPREDIS_VERSION 5.1.1

#install libs
RUN yum install -y curl zip unzip  wget openssl-devel gcc-c++ make autoconf

#install php
RUN yum install -y epel-release
RUN rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm
RUN yum clean all
RUN yum update -y
RUN yum install -y php71w-devel php71w-openssl php71w-mbstring

# composer
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/bin/composer

# use aliyun composer
RUN composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# swoole ext
RUN wget https://github.com/swoole/swoole-src/archive/v${SWOOLE_VERSION}.tar.gz -O swoole.tar.gz \
    && mkdir -p swoole \
    && tar -xf swoole.tar.gz -C swoole --strip-components=1 \
    && rm swoole.tar.gz \
    && ( \
    cd swoole \
    && phpize \
    && ./configure --enable-openssl \
    && make \
    && make install \
    ) \
    && sed -i "2i extension=swoole.so" /etc/php.ini \
    && rm -r swoole

# redis php ext
RUN wget https://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz -O phpredis.tgz \
	&& mkdir -p phpredis \
	&& tar -xf phpredis.tgz -C phpredis --strip-components=1 \
	&& ( \
	cd phpredis \
	&& phpize \
	&& ./configure \
	&& make \
	&& make install \
	) \
	&& touch /etc/php.d/redis.ini \
	&& echo -e "; Enable redis extension module\nextension=redis.so" > /etc/php.d/redis.ini \
	&& rm -rf phpredis

EXPOSE 9501

# 修改redis后台运行
RUN yum install -y redis
RUN sed -i 's/daemonize no/daemonize yes/' /etc/redis.conf

# 安装 supervisor
RUN yum install -y supervisor
COPY supervisord.conf /etc/supervisord.conf

# 启动
CMD [ "/usr/bin/supervisord"]
