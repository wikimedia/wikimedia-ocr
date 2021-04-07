FROM php:7.4-buster

WORKDIR /wikimedia-ocr

EXPOSE 8000

RUN apt-get update -q && apt-get install -y \
        git \
        wget \
	curl \
	libicu-dev \
	libzip-dev \
	unzip \
      tesseract-ocr \
      libtesseract-dev \
      && docker-php-ext-install intl \
      && wget -nv -O- https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
      && wget -nv -O- https://get.symfony.com/cli/installer | bash \
      && mv /root/.symfony/bin/symfony /usr/local/bin/symfony \
      && curl -fsSL https://deb.nodesource.com/setup_12.x | bash - \
      && apt-get install -y nodejs


CMD npm run watch & symfony serve && fg
