# prb_admin_php

> Boilerplate de PHP puro (MVC caseiro, zero framework, zero Composer) para novos projetos — painel `/admin` com login, usuários e configurações, instalador visual (`/install`), migrations numeradas e ambiente Docker de dev prontos pra uso.

[![PHP](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker Ready](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![Zero Dependencies](https://img.shields.io/badge/composer-zero%20dependencies-brightgreen)](#stack)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## O que é isso

Extraído dos projetos [`blog`](../blog) (paulorb.dev) e [`mydaily`](../mydaily) (Daily Diário), que compartilham a mesma base: um MVC hand-rolled pensado pra rodar em hospedagem compartilhada comum (sem SSH, sem Composer, sem Node). Este repositório é essa base isolada, pronta pra virar ponto de partida de um novo projeto — clone (ou copie a pasta), ajuste nome/identidade e comece a adicionar Controllers/Repositories/Views específicos do domínio novo.

## O que já vem pronto

- **Painel `/admin`**: login por sessão, CRUD de usuários, tela de configurações (chave/valor), dashboard com aviso de migrations pendentes.
- **Instalador visual (`/install`)**: confere requisitos do servidor, testa a conexão com o banco, roda as migrations com barra de progresso e cria a conta de administrador — nenhuma credencial pré-cadastrada no código.
- **Atualizações pelo próprio painel** (`/admin/atualizar`): roda só as migrations pendentes depois de um novo envio de arquivos, sem precisar de acesso SSH.
- **Router com pipeline de middleware** (`App\Core\Router` + `App\Core\Middleware`) — rotas autenticadas usam `RequireAuth::class`, mas dá pra escrever outras middlewares (papel, tenant, etc.) do mesmo jeito.
- **Migrations numeradas em SQL puro**, registradas em `database/migrations/*.sql` e aplicadas por `App\Core\Migrator`.
- **Ambiente Docker de dev** (PHP 7.2 + Apache + MySQL 8) espelhando o alvo de produção (hospedagem compartilhada travada em PHP 7.2).
- **Suite de testes própria** (`scripts/test_install.php`, sem PHPUnit) cobrindo `EnvWriter`, `Migrator`, `InstallState`, `Requirements`, `UserRepository` e o pipeline de middleware do `Router`.

## Stack

Sem Composer, sem framework, sem build step — de propósito, pro alvo de produção padrão (hospedagem compartilhada só-FTP) continuar funcionando.

| Camada | Tecnologia |
|---|---|
| Backend | PHP 7.2+ puro, MVC caseiro (`App\Controllers`, `App\Repositories`, `App\Core`) |
| Banco de dados | MySQL/MariaDB via PDO, migrations numeradas em SQL puro |
| Front-end | Bootstrap 5 + Font Awesome (CDN), JS vanilla |
| Deploy | FTP (upload direto de arquivos, sem SSH) |
| Dev local | Docker Compose (PHP + Apache + MySQL) |

## Como começar um projeto novo a partir daqui

1. Copie esta pasta (ou dê `git clone` se este repo virar remoto) com o nome do novo projeto.
2. Ajuste `docker-compose.yml` (nomes de container/rede/volume, porta externa) e `.env.docker`/`.env.example` (nome da aplicação, banco).
3. Rode `docker compose up -d --build` e acesse a porta configurada — cai no instalador.
4. Adicione os `Controllers`/`Repositories`/`Views`/migrations específicos do domínio novo, registrando as rotas em `routes.php`.
5. Leia o `CLAUDE.md` antes de mexer no núcleo (`App\Core`) — ele documenta as decisões de arquitetura e as pegadinhas já descobertas (PHP 7.2, PDO com prepares nativos, etc.).

## Instalação

### Hospedagem compartilhada (produção)

1. Copie `.env.install.example` para `.env.install` e troque `INSTALL_KEY` por um valor aleatório (ex: `openssl rand -hex 16`). Guarde essa chave.
2. Envie todos os arquivos e pastas pra raiz do FTP (ex: `public_html/`), incluindo o `.env.install`. **Não** envie `.env`.
3. Crie um banco MySQL vazio no painel da hospedagem. O instalador cria as tabelas, mas não o banco em si.
4. Acesse o site pelo navegador. Você cai no instalador, que pede a chave do passo 1, confere os requisitos do servidor, testa a conexão com o banco, grava o `.env`, roda as migrations com uma tela de progresso e, por último, pede pra você criar a conta de administrador.
5. Ao concluir, o instalador se desativa sozinho e apaga o `.env.install`.

> Se a raiz do site não tiver permissão de escrita, o instalador mostra o conteúdo do `.env` pra você enviar manualmente por FTP e continuar de onde parou.

**Atualizações:** depois de enviar arquivos novos que incluam migrations, entre em `/admin` — o painel avisa quantas estão pendentes e o botão **Atualizar banco** roda cada uma com a mesma tela de progresso, já atrás do login.

### Ambiente de desenvolvimento (Docker)

```bash
docker compose up -d --build   # build + sobe os containers em background
# primeiro acesso: http://localhost:8002 cai no instalador
```

O `.env.docker` (versão de dev, com credenciais de banco locais e descartáveis) já vem pronto no repositório. Ele é montado por cima do `.env` só dentro do container; o `.env` real da raiz (produção) nunca é tocado.

```bash
docker compose logs -f app                       # acompanhar logs
docker compose exec app php scripts/migrate.php  # rodar migrations via CLI
docker compose exec db mysql -uuser -ppassword app
docker compose down                              # parar (mantendo os dados)
docker compose down -v                           # parar e apagar os dados do banco
```

Sem Docker: `php -S localhost:8000 -t public public/dev-router.php`.

### Testes

```bash
docker compose exec app php scripts/test_install.php
```

Veja o `CLAUDE.md` para as ressalvas de permissão/usuário e banco de teste isolado.

## Acesso administrativo

Não existe usuário ou senha padrão em lugar nenhum do código. A conta de administrador é sempre criada durante o passo final do instalador (`/install/admin`). Depois disso, use `/admin` pra entrar.

## Licença

Distribuído sob a licença MIT — veja [`LICENSE`](LICENSE) para mais detalhes.
