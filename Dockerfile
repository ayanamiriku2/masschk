FROM php:8.2-cli

# Install curl extension for API calls
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# PHP settings
RUN echo 'post_max_size = 64M' > /usr/local/etc/php/conf.d/app.ini && \
    echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'max_input_vars = 10000' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'session.cookie_httponly = 1' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'session.cookie_samesite = Strict' >> /usr/local/etc/php/conf.d/app.ini && \
    echo 'session.use_strict_mode = 1' >> /usr/local/etc/php/conf.d/app.ini

WORKDIR /app
COPY . /app/

RUN mkdir -p /tmp/masschk_rate && chmod 777 /tmp/masschk_rate \
    && chmod -R 777 /app/data

ENV PORT=8080
EXPOSE 8080

CMD sh -c "php -S 0.0.0.0:${PORT} -t /app /app/router.php"
