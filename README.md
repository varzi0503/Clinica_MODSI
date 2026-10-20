# Clínica — Plataforma de Gestão de Consultas Médicas

Projeto de grupo da unidade curricular **Modelação e Simulação Industrial (MODSI)** — Mestrado em Engenharia Eletrotécnica e de Computadores, ISEP.

Stack: **Laravel 13 · PHP 8.4 · MySQL 8 · Blade + Bootstrap · AJAX**

---

## Requisitos

| Ferramenta | Versão | Como instalar (Windows) |
|---|---|---|
| Laravel Herd | gratuito | https://herd.laravel.com/windows — traz `php`, `composer`, `laravel`, `node`, `npm` |
| MySQL Community Server | 8.0 ou 8.4 | https://dev.mysql.com/downloads/mysql (MSI) |
| Git | qualquer | https://git-scm.com |

Confirmar num PowerShell novo:

```powershell
php -v; composer -V; node -v; npm -v; git --version; mysql --version
```

> **Rede do ISEP:** a rede da escola bloqueia o acesso do Composer ao Packagist. Corre `composer install` com o hotspot do telemóvel ou em casa.

---

## Instalação

### 1. Base de dados (uma vez por máquina)

```powershell
mysql -u root -p
```

```sql
CREATE DATABASE clinica      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE clinica_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'clinica_app'@'localhost' IDENTIFIED BY 'escolhe_uma_password';
GRANT ALL PRIVILEGES ON clinica.*      TO 'clinica_app'@'localhost';
GRANT ALL PRIVILEGES ON clinica_test.* TO 'clinica_app'@'localhost';
FLUSH PRIVILEGES;
```

A aplicação usa o utilizador `clinica_app`, nunca o `root`.

### 2. Código

O projeto tem de ficar dentro da pasta do Herd para ser servido automaticamente:

```powershell
cd ~\Herd
git clone https://github.com/varzi0503/Clinica_MODSI.git clinica
cd clinica

composer install
npm install
npm run build

copy .env.example .env
php artisan key:generate
```

Abrir o `.env` e preencher **só** `DB_PASSWORD` com a password do `clinica_app`.

### 3. Tabelas e dados de demonstração

```powershell
php artisan migrate --seed
```

### 4. Abrir

http://clinica.test

---

## Comandos úteis

| Comando | Para quê |
|---|---|
| `php artisan migrate:fresh --seed` | Apagar e recriar a BD com os dados de demonstração |
| `php artisan test` | Correr os testes (usam a BD `clinica_test`) |
| `npm run dev` | Recompilar CSS/JS automaticamente enquanto desenvolves |
| `php artisan route:list` | Ver todas as rotas |

---

## Como contribuir

Ver [CONTRIBUTING.md](CONTRIBUTING.md). Resumo: uma issue → um branch → um pull request revisto por outro elemento. Ninguém faz push direto para o `main`.
