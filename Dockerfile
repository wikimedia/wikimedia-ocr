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
	libmagickwand-dev \
	ghostscript \
      tesseract-ocr-all \
      && pecl install imagick \
      && docker-php-ext-enable imagick \
      && docker-php-ext-install intl \
      && docker-php-ext-install bcmath \
      && wget -nv -O- https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
      && wget -nv -O- https://get.symfony.com/cli/installer | bash \
      && mv /root/.symfony/bin/symfony /usr/local/bin/symfony \
      && curl -fsSL https://deb.nodesource.com/setup_12.x | bash - \
      && apt-get install -y nodejs


# Allow ImageMagick to process PDF files (requires Ghostscript).
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
      sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' /etc/ImageMagick-6/policy.xml; \
    fi

CMD npm run watch & symfony serve && fg
