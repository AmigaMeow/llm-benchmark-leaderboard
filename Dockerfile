FROM php:8.1-apache

RUN a2enmod rewrite headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html

# Runtime config: gitignored config/*.php is generated inside the image from
# the committed *.example.php templates; secrets come from env at run time.
RUN cp config/site.example.php config/site.php \
 && cp config/analytics.example.php config/analytics.php \
 && cp config/llm_sources.example.php config/llm_sources.php \
 && mkdir -p cache/llm logs \
 && cp data/llm-leaderboard.sample.json cache/llm/llm-leaderboard.json \
 && chown -R www-data:www-data cache logs

EXPOSE 80
