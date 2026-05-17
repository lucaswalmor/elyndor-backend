FROM php:8.4-fpm-alpine

# Instalar dependências do sistema + Supervisor
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    mysql-client \
    nodejs \
    npm

# Instalar extensões PHP para Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório de trabalho
WORKDIR /var/www

# Copiar arquivos do projeto
COPY . .

# Instalar dependências PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Instalar dependências Node
RUN npm install && npm run build

# Configurar permissões
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Configurar Nginx com proxy para o Reverb (WebSocket)
RUN printf 'server {\n\
    listen 80;\n\
    root /var/www/public;\n\
    index index.php index.html;\n\
\n\
    # Proxy WebSocket do Reverb\n\
    location /app {\n\
        proxy_pass http://127.0.0.1:8080;\n\
        proxy_http_version 1.1;\n\
        proxy_set_header Upgrade $http_upgrade;\n\
        proxy_set_header Connection "Upgrade";\n\
        proxy_set_header Host $host;\n\
        proxy_set_header X-Real-IP $remote_addr;\n\
        proxy_read_timeout 60s;\n\
    }\n\
\n\
    # Proxy autenticação de broadcast\n\
    location /apps {\n\
        proxy_pass http://127.0.0.1:8080;\n\
        proxy_http_version 1.1;\n\
        proxy_set_header Host $host;\n\
        proxy_set_header X-Real-IP $remote_addr;\n\
    }\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
\n\
    location ~ \.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
    }\n\
\n\
    location ~ /\.(?!well-known).* {\n\
        deny all;\n\
    }\n\
}' > /etc/nginx/http.d/default.conf

# Configurar Supervisor para gerenciar todos os processos
RUN printf '[supervisord]\n\
nodaemon=true\n\
logfile=/var/log/supervisord.log\n\
\n\
[program:php-fpm]\n\
command=php-fpm\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:nginx]\n\
command=nginx -g "daemon off;"\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/dev/stdout\n\
stdout_logfile_maxbytes=0\n\
stderr_logfile=/dev/stderr\n\
stderr_logfile_maxbytes=0\n\
\n\
[program:reverb]\n\
command=php /var/www/artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction\n\
directory=/var/www\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/storage/logs/reverb.log\n\
stderr_logfile=/var/www/storage/logs/reverb.log\n\
\n\
[program:queue]\n\
command=php /var/www/artisan queue:work --tries=3 --timeout=60\n\
directory=/var/www\n\
autostart=true\n\
autorestart=true\n\
stdout_logfile=/var/www/storage/logs/queue.log\n\
stderr_logfile=/var/www/storage/logs/queue.log\n' > /etc/supervisor/conf.d/supervisord.conf

# Script de inicialização: cache do Laravel + Supervisor
RUN printf '#!/bin/sh\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf\n' > /start.sh && chmod +x /start.sh

EXPOSE 80

CMD ["/bin/sh", "/start.sh"]
