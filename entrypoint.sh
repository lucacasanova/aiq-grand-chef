#!/bin/bash

# Ajustar permissões dos diretórios storage e bootstrap/cache
echo "Ajustando permissões dos diretórios storage e bootstrap/cache..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Esperar o banco de dados de produção estar disponível
echo "Aguardando o banco de dados de produção estar disponível..."
while ! nc -z db 5432; do
    sleep 1
done

# Esperar o banco de dados de teste estar disponível
echo "Aguardando o banco de dados de teste estar disponível..."
while ! nc -z test_db 5432; do
    sleep 1
done

# Verificar se o arquivo .env existe, se não, copiar do .env.example
if [ ! -f /var/www/html/.env ]; then
    echo "Arquivo .env não encontrado. Copiando do .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Instalar dependências do Composer
echo "Instalando dependências do Composer..."
composer install

# Instalar dependências do npm
echo "Instalando dependências do npm..."
npm install

# Compilar assets do Vite
echo "Compilando assets do Vite..."
npm run build

# Gerar chave de criptografia do Laravel
echo "Gerando chave de criptografia do Laravel..."
php artisan key:generate --force

# Executar as migrações do Laravel para o ambiente de produção
echo "Executando migrações para o ambiente de produção..."
php artisan migrate --force

# Executar as migrações do Laravel para o ambiente de teste
echo "Executando migrações para o ambiente de teste..."
php artisan migrate --env=testing --force

# Iniciar PHP-FPM
echo "Iniciando PHP-FPM..."
php-fpm8.3 &

# Esperar que todos os processos em segundo plano sejam concluídos
wait

# Iniciar o Reverb do Laravel em segundo plano
echo "Iniciando o Reverb do Laravel..."
php artisan reverb:start &

# Iniciar o Worker do Laravel em segundo plano
echo "Iniciando o Worker do Laravel..."
php artisan queue:work &

# Iniciar Nginx
echo "Iniciando Nginx..."
nginx -g 'daemon off;'