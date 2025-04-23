FROM php:8.1-apache

# Copia os arquivos do repositório para o diretório padrão do Apache
COPY . /var/www/html/

# Ativa o mod_rewrite (opcional, útil para URLs amigáveis)
RUN a2enmod rewrite