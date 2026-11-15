# Clínica — Plataforma de Gestão de Consultas Médicas

Projeto de grupo da unidade curricular **MODSI** (ISEP), tema 1: plataforma web de gestão de consultas médicas, com notificações, lista de espera e observações/documentos.

Stack: **Laravel 13 · PHP 8.4 · MySQL 8.0 · Blade + Bootstrap 5 · AJAX com `fetch`**

> README provisório. A versão final (issue #44) entra antes do congelamento do código, a 6 de dezembro.

## Requisitos

| Ferramenta | Versão | Notas |
|---|---|---|
| [Laravel Herd](https://herd.laravel.com/windows) | atual | Traz `php`, `composer`, `node` e `npm` |
| PHP | **8.4** | O `composer.json` aceita 8.3, mas as dependências instaladas exigem 8.4 (D01) |
| MySQL Community Server | 8.0 | |
| Node | 22 ou mais recente | |
| Git | qualquer | |

Confirmar num PowerShell novo:

```powershell
php -v; composer -V; node -v; npm -v; git --version; mysql --version
```

> **Rede do ISEP:** a rede da escola bloqueia o acesso do Composer ao Packagist. Corre o `composer install` com o hotspot do telemóvel ou em casa.

## Instalação

### 1. Base de dados (uma vez por máquina)

```powershell
mysql -u root -p
```

```sql
CREATE DATABASE clinica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clinica_app'@'localhost' IDENTIFIED BY 'escolhe_uma_password';
GRANT ALL PRIVILEGES ON clinica.* TO 'clinica_app'@'localhost';
FLUSH PRIVILEGES;
```

A aplicação usa o utilizador `clinica_app`, nunca o `root`.

### 2. Código

O projeto tem de ficar dentro da pasta do Herd para ser servido automaticamente:

```powershell
cd ~\Herd
git clone https://github.com/varzi0503/Clinica_MODSI.git clinica
cd clinica
copy .env.example .env
```

Abrir o `.env` e preencher **só** `DB_PASSWORD` com a password do `clinica_app`. Depois:

```powershell
composer setup
```

O `composer setup` faz o `composer install`, gera a `APP_KEY`, corre as migrations e faz `npm install` e `npm run build`. Os mesmos passos à mão:

```powershell
composer install
php artisan key:generate
npm install
npm run build
```

> **A `APP_KEY` é de cada máquina.** O `.env.example` traz a chave vazia. Se o teu `.env` foi copiado antes de 15 de novembro, tem a chave que esteve publicada no repositório: corre `php artisan key:generate` outra vez.

### 3. Tabelas e contas iniciais

```powershell
php artisan migrate:fresh --seed
```

| Conta | Perfil | Palavra-passe |
|---|---|---|
| `admin@example.com` | administrador | `password` |
| `test@example.com` | rececionista | `password` |

Os pacientes registam-se em `/registar`. Os dados de demonstração (profissionais, horários, pacientes, consultas) entram com a issue #37.

### 4. Abrir

http://clinica.test

## Comandos úteis

| Comando | Para quê |
|---|---|
| `php artisan migrate:fresh --seed` | Apagar e recriar a BD. Correr depois de puxar migrations ou seeders novos |
| `npm run build` | Recompilar CSS e JS. Correr depois de puxar alterações a `resources/` e antes dos testes |
| `php artisan test` | Correr os testes. Usam SQLite em memória, não tocam na BD `clinica` |
| `npm run dev` | Recompilar CSS e JS automaticamente enquanto desenvolves |
| `php artisan route:list` | Ver todas as rotas |

## Documentação

- [CONTRIBUTING.md](CONTRIBUTING.md): papéis, branches, commits e como se testa um PR.
- [docs/decisoes.md](docs/decisoes.md): decisões técnicas (D01 em diante) e as suas justificações.
- [docs/estado-projeto.md](docs/estado-projeto.md): o que está feito e o que falta.

## Ferramentas de IA

A implementação usa o Claude Code com o [Laravel Boost](https://laravel.com/docs/boost). O `CLAUDE.md` é gerado pelo Boost a partir de `.ai/guidelines/`; as regras do projeto estão em `.ai/rules/` e as skills em `.ai/skills/`. Depois de alterar alguma destas pastas, correr `php artisan boost:update`.
