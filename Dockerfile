# Usar imagem oficial do PHP com Apache
FROM php:8.2-apache

# Instalar extensões necessárias
RUN docker-php-ext-install curl && \
    docker-php-ext-install openssl && \
    docker-php-ext-install json

# Habilitar módulo rewrite do Apache
RUN a2enmod rewrite

# Configurar o Apache para usar a pasta correta
RUN sed -i 's!/var/www/html!/var/www/html!g' /etc/apache2/apache2.conf

# Copiar todos os arquivos para o container
COPY . /var/www/html/

# Definir permissões
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expor porta 80
EXPOSE 80
