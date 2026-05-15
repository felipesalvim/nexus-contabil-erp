# Nexus Contábil — Plataforma Web Completa

> Consultoria contábil estratégica com tecnologia SaaS para o **Terceiro Setor** e **Pequenas Empresas** em Fortaleza, CE.

![Status](https://img.shields.io/badge/status-production-brightgreen)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-Proprietary-red)

---

## Sumário

- [Visão Geral](#visão-geral)
- [Funcionalidades](#funcionalidades)
- [Arquitetura e Estrutura](#arquitetura-e-estrutura)
- [Stack Tecnológica](#stack-tecnológica)
- [Banco de Dados](#banco-de-dados)
- [Módulo: Calculadora RPA](#módulo-calculadora-rpa)
- [Portal do Cliente](#portal-do-cliente)
- [Painel Administrativo](#painel-administrativo)
- [Segurança](#segurança)
- [Configuração e Instalação](#configuração-e-instalação)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Estrutura de Arquivos](#estrutura-de-arquivos)

---

## Visão Geral

A **Nexus Contábil** é uma plataforma web full-stack desenvolvida para digitalizar e automatizar os serviços de um escritório de contabilidade. O sistema combina um **site institucional** com geração de conteúdo e captação de leads, uma **Calculadora de RPA** (Recibo de Pagamento a Autônomo) com emissão de PDF e envio por e-mail, e um **Portal do Cliente** protegido por autenticação, onde empresas podem acompanhar suas emissões, fazer download de documentos e abrir chamados de suporte.

O projeto foi desenvolvido inteiramente com tecnologias nativas e compostas de baixo custo operacional, adequadas ao ambiente de hospedagem compartilhada (cPanel).

---

## Funcionalidades

### 🌐 Site Institucional
- Página inicial com hero, proposta de valor e CTA
- Página "Quem Somos"
- Página de Soluções (segmentada por: Terceiro Setor / CEBAS / Pequenas Empresas)
- Blog com artigos técnicos sobre: CEBAS, MROSC, MEI, RPA
- Materiais educativos para download (com formulário de captura de lead integrado)
- Formulário de contato com envio de e-mail via PHPMailer + SMTP
- Política de Privacidade
- Open Graph configurado para compartilhamento social

### 🧮 Calculadora RPA (Recibo de Pagamento a Autônomo)
- Cálculo automático de **INSS** (com controle de teto e desconto já retido no mês)
- Cálculo de **IRRF** com tabela progressiva atualizada (Lei 15.270/2025)
- Aplicação de **redutor proporcional** para bases entre R$ 5.000 e R$ 7.350
- Cálculo de **ISS** por alíquota municipal configurável
- Dedução por **dependentes** (R$ 189,59/dependente)
- Exibe valor **bruto**, todos os **descontos** e valor **líquido final**
- **Geração de PDF** profissional com layout completo (via Dompdf)
- **Envio automático por e-mail** para prestador e tomador (via PHPMailer)
- **Salvamento no banco de dados** com histórico completo da emissão
- **Página de validação pública** do RPA via código hash

### 🔐 Portal do Cliente (Área Autenticada)
- Login seguro com e-mail e senha (hash `password_hash()`)
- Rate limiting por IP (bloqueio após 5 tentativas em 5 minutos)
- Regeneração de ID de sessão após autenticação (prevenção de Session Fixation)
- **Dashboard** com métricas do mês atual: total bruto, total líquido, quantidade de RPAs
- **Listagem de RPAs** emitidos com filtros e histórico completo
- **Download de documentos** (PDFs enviados pela contabilidade, segmentados por CNPJ)
- **Sistema de Chamados** com criação de tickets e acompanhamento de status

### 🛠️ Painel Administrativo
- Acesso exclusivo para administradores (sessão `admin_id`)
- Gerenciamento de chamados: visualização, resposta e atualização de status
- Criação de usuários do portal (vinculados por CNPJ)
- Upload de documentos para a área do cliente (segmentado por CNPJ do cliente)
- Proteção CSRF com token em todas as ações POST

---

## Arquitetura e Estrutura

O projeto segue uma arquitetura **MVC simplificada**, adequada para PHP sem framework. A separação de responsabilidades é feita por convenção de arquivo:

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENTE (Browser)                    │
└───────────────────┬────────────────────────────────────────┘
                    │ HTTP
        ┌───────────▼──────────────┐
        │   Site Institucional     │  HTML estático + JS
        │   (index, blog, etc.)    │
        └───────────┬──────────────┘
                    │ Form POST / AJAX
        ┌───────────▼──────────────┐
        │   Camada PHP (Backend)   │
        │  gerar_pdf.php           │  Lógica de negócio RPA
        │  login.php               │  Autenticação
        │  dashboard.php           │  Portal do cliente
        │  chamados.php            │  Tickets
        │  admin_*.php             │  Administração
        └───────────┬──────────────┘
                    │ PDO
        ┌───────────▼──────────────┐
        │   MySQL (db.php)         │  Conexão centralizada
        │   Credenciais via .env   │  Sem hardcode
        └──────────────────────────┘
```

---

## Stack Tecnológica

| Camada | Tecnologia |
|---|---|
| Frontend | HTML5, CSS3 (variáveis nativas), JavaScript vanilla |
| Backend | PHP 8.1+ |
| Banco de Dados | MySQL 8.0 via PDO |
| Geração de PDF | [Dompdf](https://github.com/dompdf/dompdf) `^3.1` |
| Envio de E-mail | [PHPMailer](https://github.com/PHPMailer/PHPMailer) `^7.0` |
| Gerenciamento de Dependências | Composer |
| Tipografia | Google Fonts: Playfair Display + DM Sans |
| Hospedagem-alvo | cPanel / Hospedagem Compartilhada |

---

## Banco de Dados

O banco de dados `nexus_rpa` centraliza todas as informações da plataforma. As tabelas principais são:

| Tabela | Descrição |
|---|---|
| `tomadores_empresas` | Cadastro das empresas clientes (CNPJ, razão social, endereço) |
| `prestadores_autonomos` | Cadastro dos autônomos que recebem RPA (CPF, nome, endereço) |
| `rpa_emissões` | Histórico de todos os RPAs gerados com valores e descontos detalhados |
| `usuarios_portal` | Credenciais de acesso ao portal do cliente (vinculado por CNPJ) |
| `chamados_portal` | Cabeçalho dos tickets de suporte abertos pelos clientes |
| `interacoes_chamado` | Mensagens e respostas dentro de cada chamado (relação 1:N) |
| `leads_materiais` | Leads capturados pelo formulário de download de materiais |
| `documentos_cliente` | Metadados dos arquivos enviados pela equipe para cada cliente |

---

## Módulo: Calculadora RPA

Este é o núcleo operacional da plataforma. O fluxo completo de uma emissão de RPA funciona da seguinte forma:

```
[Formulário HTML] → gerar_pdf.php
        │
        ├─ 1. Coleta e sanitiza dados do POST
        ├─ 2. Executa o motor de cálculo fiscal:
        │       ├─ INSS (11% sobre bruto, respeitando teto e retenção prévia)
        │       ├─ IRRF (tabela progressiva + redutor proporcional Lei 15.270/2025)
        │       ├─ ISS (alíquota municipal variável)
        │       └─ Dedução por dependentes
        ├─ 3. Inicia transação PDO (ACID):
        │       ├─ INSERT em `prestadores_autonomos` (upsert por CPF)
        │       ├─ INSERT em `tomadores_empresas` (upsert por CNPJ)
        │       └─ INSERT em `rpa_emissões` com todos os valores
        ├─ 4. Gera o PDF com Dompdf (layout HTML → PDF binário)
        ├─ 5. Envia o PDF por e-mail via PHPMailer (SMTP Gmail)
        │       ├─ Para o prestador (autônomo)
        │       └─ Para o tomador (empresa)
        └─ 6. Retorna o PDF inline para download no browser
```

### Validação de RPA

Cada RPA emitido gera um **código de validação público** no formato `{id}-{hash}`. A página `validar.php` permite que qualquer pessoa confirme a autenticidade do documento sem acesso ao sistema, comparando o hash recebido com `md5($id . 'SALT')`.

---

## Portal do Cliente

O portal é acessado via `area_cliente.html` e protegido por sessão PHP. Após o login, o cliente tem acesso a:

- **Dashboard (`dashboard.php`)**: visão geral dos RPAs do mês (total bruto, total líquido, quantidade)
- **Meus Documentos (`documentos.php`)**: listagem e download de arquivos enviados pela equipe, segregados por CNPJ
- **Chamados (`chamados.php`)**: abertura de tickets com seleção de departamento, acompanhamento do histórico e status

---

## Painel Administrativo

O painel admin é acessado por rota separada e exige sessão `admin_id`. Funcionalidades:

- **`admin_chamados.php`**: visualiza todos os chamados de todos os clientes, pode adicionar respostas e alterar status (Aberto → Em Andamento → Aguardando Cliente → Resolvido)
- **`admin_upload.php`**: realiza upload de documentos (PDFs) para a pasta do cliente, segmentada por CNPJ (`/uploads/{CNPJ}/`)
- **`criar_usuario.php`**: cria credenciais de acesso ao portal para novos clientes, com validação de e-mail, mínimo de 8 caracteres na senha e hash `password_hash()`

---

## Segurança

| Mecanismo | Implementação |
|---|---|
| Senhas | `password_hash()` / `password_verify()` — bcrypt nativo do PHP |
| SQL Injection | 100% Prepared Statements via PDO |
| XSS | `htmlspecialchars()` em todos os dados exibidos |
| CSRF | Token gerado com `random_bytes(32)` em todas as ações POST administrativas |
| Session Fixation | `session_regenerate_id(true)` imediatamente após login |
| Rate Limiting | Bloqueio por IP após 5 tentativas de login falhas (5 minutos) |
| Credenciais | Lidas exclusivamente do arquivo `.env` — nenhuma credencial hardcoded |
| Arquivos de Upload | Segregados por CNPJ, sem execução PHP na pasta `/uploads/` |

> ⚠️ **Importante**: o arquivo `.env` contém credenciais sensíveis e **jamais deve ser versionado**. Certifique-se de que ele está listado no `.gitignore`.

---

## Configuração e Instalação

### Pré-requisitos

- PHP 8.1 ou superior
- MySQL 8.0
- Composer
- Servidor web Apache ou Nginx (com `mod_rewrite` para Apache)
- Conta SMTP para envio de e-mails (Gmail com App Password recomendado)

### Passos

```bash
# 1. Clone o repositório
git clone https://github.com/felipesalvim/nexus-contabil.git
cd nexus-contabil

# 2. Instale as dependências PHP
composer install

# 3. Crie o arquivo de configuração
cp .env.example .env

# 4. Edite o .env com suas credenciais
nano .env

# 5. Importe o schema do banco de dados
mysql -u seu_usuario -p seu_banco < database/schema.sql

# 6. Configure as permissões da pasta de uploads
chmod 755 uploads/
```

---

## Variáveis de Ambiente

Crie um arquivo `.env` na raiz do projeto com base no exemplo abaixo:

```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=nome_do_banco
DB_USER=usuario_do_banco
DB_PASS=senha_do_banco

# SMTP (envio de e-mail)
SMTP_USER=seu_email@gmail.com
SMTP_PASS=sua_app_password_google
```

> Para Gmail, gere uma **App Password** em [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords) com a verificação em duas etapas ativa.

---

## Estrutura de Arquivos

```
nexus-contabil/
│
├── 📄 index.html                    # Página inicial (home)
├── 📄 quem_somos.html               # Sobre o escritório
├── 📄 solucoes.html                 # Soluções gerais
├── 📄 solucoes-cebas.html           # Soluções para CEBAS/OSC
├── 📄 solucoes-pequenas-empresas.html
├── 📄 blog.html                     # Listagem de artigos
├── 📄 artigo_rpa.html               # Artigo: O que é RPA
├── 📄 artigo_cebas.html             # Artigo: CEBAS 2026
├── 📄 artigo_mei.html               # Artigo: MEI → ME
├── 📄 artigo_mrosc.html             # Artigo: MROSC
├── 📄 materiais.html                # Hub de materiais para download
├── 📄 calculadora_rpa.html          # Formulário da Calculadora RPA
├── 📄 contato.html                  # Formulário de contato
├── 📄 area_cliente.html             # Tela de login do portal
├── 📄 politica_privacidade.html     # Política de privacidade (LGPD)
│
├── 🔧 gerar_pdf.php                 # Engine principal: cálculo, PDF, e-mail, DB
├── 🔧 login.php                     # Autenticação do portal do cliente
├── 🔧 dashboard.php                 # Dashboard pós-login
├── 🔧 documentos.php                # Download de documentos do cliente
├── 🔧 chamados.php                  # Abertura e acompanhamento de tickets
├── 🔧 validar.php                   # Validação pública de RPA por hash
├── 🔧 enviar_contato.php            # Processamento do formulário de contato
├── 🔧 capturar_lead.php             # Gravação de lead (download de material)
├── 🔧 baixar_doc.php                # Endpoint de download de documentos
├── 🔧 baixar_pdf.php                # Endpoint de download de RPAs em PDF
├── 🔧 db.php                        # Conexão centralizada PDO (lê do .env)
│
├── 🔧 admin_chamados.php            # Admin: gerenciamento de chamados
├── 🔧 admin_upload.php              # Admin: upload de documentos para clientes
├── 🔧 criar_usuario.php             # Admin: criação de usuários do portal
│
├── 📁 css/
│   └── style.css                    # Stylesheet global
├── 📁 js/
│   └── script.js                    # Scripts globais
│
├── 📁 PHPMailer/                    # Biblioteca de envio de e-mail
│   ├── Exception.php
│   ├── PHPMailer.php
│   └── SMTP.php
│
├── 📁 materiais/                    # PDFs públicos para download
│   ├── Guia_CEBAS_2026.pdf
│   ├── MROSC_Prestacao_Contas.pdf
│   └── Transicao_MEI_para_ME.pdf
│
├── 📁 uploads/                      # Documentos privados dos clientes
│   └── {CNPJ}/                      # Segregados por CNPJ
│       └── documento.pdf
│
├── 📁 vendor/                       # Dependências gerenciadas pelo Composer
│   └── dompdf/dompdf/               # Geração de PDF
│
├── 📄 composer.json
├── 📄 composer.lock
└── 📄 .env                          # ⚠️ NÃO versionar — credenciais locais
```

---

## Autor

Desenvolvido por **[@felipesalvim](https://github.com/felipesalvim)**

---

*Plataforma desenvolvida para uso interno do escritório Nexus Contábil — Fortaleza, CE.*