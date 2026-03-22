FROM php:8.2-apache

# Fix MPM conflict: remove all MPM configs, then enable only prefork + needed modules
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-enabled/mpm_*.load && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load && \
    a2enmod rewrite headers

# Increase PHP limits for large card lists
RUN echo 'post_max_size = 64M' > /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'upload_max_filesize = 64M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_input_vars = 10000' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'session.cookie_httponly = 1' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'session.cookie_secure = 1' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'session.cookie_samesite = Strict' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'session.use_strict_mode = 1' >> /usr/local/etc/php/conf.d/uploads.ini

# Create session and rate limit directories
RUN mkdir -p /tmp/masschk_rate && chmod 700 /tmp/masschk_rate

COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/data /tmp/masschk_rate

EXPOSE 80
