# Teste Aiq Grand Chef Laravel

Bem-vindo ao Teste Aiq Grand Chef Laravel! 🎉 Este projeto é uma API para gerenciar categorias, produtos e pedidos de um restaurante, desenvolvida com o framework Laravel. Utilizamos diversas tecnologias para criar um ambiente de desenvolvimento robusto e eficiente. Abaixo estão as instruções para configurar e executar o projeto.

## Tecnologias Utilizadas

-   **Docker**: Execução e gerenciamento de contêineres.
-   **Nginx**: Servidor web utilizado para servir a aplicação.
-   **PHP-FPM 8.3**: Gerenciador de processos FastCGI para PHP.
-   **Laravel 11**: Framework PHP utilizado para desenvolver a aplicação.
-   **Swagger/OpenAPI**: Ferramenta para documentação da API.
-   **Redis**: Armazenamento de dados em memória utilizado para cache.
-   **WebSocket**: Protocolo utilizado para comunicação em tempo real entre o backend e o frontend.
-   **PostgreSQL**: Banco de dados relacional utilizado para armazenar dados da aplicação.

## Pré-requisitos

Certifique-se de ter o Docker e o Docker Compose instalados em sua máquina.

## Configuração do Ambiente

1. Clone o repositório do projeto:

    ```sh
    git clone <URL_DO_REPOSITORIO>
    cd <NOME_DO_REPOSITORIO>
    ```

2. Copie o arquivo de exemplo `.env.example` para `.env`:

    ```sh
    cp .env.example .env
    ```

3. Construa e inicie os containers Docker:
    ```sh
    docker-compose up --build
    ```

## Estrutura de Arquivos

-   `docker-compose.yml`: Arquivo de configuração do Docker Compose.
-   `nginx.conf`: Configuração do Nginx.
-   `php.ini`: Configuração do PHP.
-   `www.conf`: Configuração do PHP-FPM.
-   `entrypoint.sh`: Script de entrada para inicialização dos serviços.

## Executando a Aplicação

Após seguir os passos de configuração, a aplicação estará disponível em `http://localhost:8000`.

## Aah, esse backend utiliza websocket para manter o frontend sempre atualizado.

## Acesse [http://localhost:8000/](http://localhost:8000/) em uma segunda aba para ver as atualizações conforme os testes 💜

## Documentação da API

A documentação da API é gerada utilizando Swagger/OpenAPI. Para acessar a documentação, siga os passos abaixo:

1. Certifique-se de que a aplicação está em execução.
2. Acesse [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation) no seu navegador.

## Explicação do `entrypoint.sh`

O script `entrypoint.sh` é responsável por configurar e iniciar os serviços necessários para a aplicação. Aqui está um resumo das principais etapas realizadas pelo script:

1. **Ajuste de Permissões**: Configura as permissões dos diretórios `storage` e `bootstrap/cache` para garantir que o servidor web tenha acesso adequado.

2. **Verificação do Banco de Dados**: Aguarda até que os bancos de dados de produção e teste estejam disponíveis antes de prosseguir.

3. **Configuração do Ambiente**: Verifica se o arquivo `.env` existe. Se não existir, copia o arquivo de exemplo `.env.example` para `.env`.

4. **Geração de Chave do Laravel**: Gera a chave de criptografia necessária para o Laravel.

5. **Migrações do Banco de Dados**: Executa as migrações do banco de dados tanto para o ambiente de produção quanto para o ambiente de teste.

6. **Inicialização dos Serviços**: Inicia o PHP-FPM e o Nginx para servir a aplicação.

Essas etapas garantem que o ambiente esteja corretamente configurado e que todos os serviços necessários estejam em execução.

## Testes Automatizados

Para executar os testes automatizados, execute o comando abaixo com a aplicação em execução

```sh
docker-compose exec app php artisan test
```

## Funcionamento do Cache

O cache é utilizado para melhorar a performance da aplicação, armazenando os resultados de consultas frequentes por um período de tempo determinado. Aqui está um resumo de como o cache está funcionando:

1. **Tempo de Cache**: Os resultados são armazenados em cache por 60 segundos.

2. **Chaves de Cache**: As chaves de cache são geradas dinamicamente com base nos parâmetros da requisição, como paginação, ordenação e filtros, garantindo que cada combinação de parâmetros tenha seu próprio cache.

3. **Tags de Cache**: Utilizamos tags de cache para agrupar entradas relacionadas, facilitando a invalidação do cache quando necessário. Por exemplo, todas as categorias são armazenadas com a tag `categorias`.

4. **Recuperação do Cache**: Antes de realizar uma consulta ao banco de dados, a aplicação verifica se os dados já estão armazenados em cache. Se estiverem, os dados são retornados diretamente do cache.

5. **Armazenamento no Cache**: Se os dados não estiverem em cache, a consulta é realizada no banco de dados e os resultados são armazenados em cache para futuras requisições.

6. **Invalidação do Cache**: O cache é invalidado automaticamente após a criação, atualização ou exclusão de uma categoria, produto ou pedido, garantindo que os dados armazenados estejam sempre atualizados.

Essas práticas garantem uma resposta rápida para consultas frequentes, melhorando a experiência do usuário e reduzindo a carga no banco de dados.

## Branch v2

A versão 2 foi criada após a entrega do projeto no dia 12/09.

### Atualizações:
- Implementação de novas tentativas de processamento em caso de erro interno.
- Refatoração do código para aplicar os padrões de design (Repository e Services).