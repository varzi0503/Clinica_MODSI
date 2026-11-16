# Clínica — Plataforma de Gestão de Consultas Médicas

Projeto de grupo da unidade curricular **MODSI** (ISEP), tema 1: plataforma web de gestão de consultas médicas, com notificações, lista de espera e observações/documentos.

Stack: **Laravel 13 · PHP 8.4 · MySQL 8.0 · Blade + Bootstrap 5 · AJAX com `fetch`**

## O que a aplicação faz

| Quem | O que faz |
|---|---|
| Visitante | Vê a informação da clínica, as especialidades, os profissionais e as vagas livres; regista-se como paciente |
| Paciente | Pede consultas (ou fica em lista de espera numa vaga ocupada), cancela até 24 horas antes, vê as próximas e as passadas, junta documentos, recebe notificações e edita os seus dados |
| Profissional (médico ou enfermeiro) | Define o horário semanal, vê a agenda, regista a consulta como realizada ou falta, cancela com motivo, escreve observações, junta documentos e vê o histórico dos seus pacientes |
| Rececionista | Confirma e rejeita pedidos, marca, cancela e reagenda consultas, vê a agenda geral, gere pacientes, profissionais e especialidades, e consulta as estatísticas |
| Administrador | Cria contas, atribui perfis, ativa e desativa contas |

A interface existe em português e em inglês (seletor no menu). As regras de acesso são verificadas no servidor: o perfil por middleware e a posse dos dados por policies.

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

> **Documentos até 5 MB.** O PHP vem configurado para aceitar ficheiros até 2 MB. Para carregar documentos maiores nas consultas, sobe o limite para pelo menos 5 MB: no Herd, nas definições, separador PHP, campo do tamanho máximo de upload; ou edita `upload_max_filesize` no `php.ini` da versão em uso (`%USERPROFILE%\.config\herd\bin\<versão>\php.ini`, que o comando `herd ini` abre). Depois reinicia os serviços do Herd. O `post_max_size` por omissão (8 MB) já chega.

### 3. Tabelas e contas iniciais

```powershell
php artisan migrate:fresh --seed
```

| Conta | Perfil | Palavra-passe |
|---|---|---|
| `admin@example.com` | administrador | `password` |
| `test@example.com` | rececionista | `password` |

Os pacientes registam-se em `/registar`. Os profissionais e as especialidades criam-se pela receção (menu Gestão). Os dados de demonstração (profissionais, horários, pacientes, consultas) entram com a issue #37.

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
| `php artisan consultas:expirar-espera` | Cancelar os pedidos em lista de espera cuja vaga já passou (D33). Corre-se à mão |

Os emails das notificações não são enviados: ficam escritos em `storage/logs/laravel.log` (`MAIL_MAILER=log`).

## Documentação

- [CONTRIBUTING.md](CONTRIBUTING.md): papéis, branches, commits e como se testa um PR.
- [docs/decisoes.md](docs/decisoes.md): decisões técnicas (D01 em diante) e as suas justificações.
- [docs/estado-projeto.md](docs/estado-projeto.md): o que está feito e o que falta.

## Ferramentas de IA

A implementação usa o Claude Code com o [Laravel Boost](https://laravel.com/docs/boost). O `CLAUDE.md` é gerado pelo Boost a partir de `.ai/guidelines/`; as regras do projeto estão em `.ai/rules/` e as skills em `.ai/skills/`. Depois de alterar alguma destas pastas, correr `php artisan boost:update`.
