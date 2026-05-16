# Nexus Contábil — Portal do Cliente & ERP Contábil

> **Sistema web completo** para escritórios de contabilidade: portal do cliente com gestão de RPAs, cofre digital de documentos, sistema de chamados e painel administrativo.

---

## 📋 Sumário

- [Visão Geral](#visão-geral)
- [Funcionalidades](#funcionalidades)
- [Arquitetura e Stack](#arquitetura-e-stack)
- [Estrutura de Arquivos](#estrutura-de-arquivos)
- [Banco de Dados](#banco-de-dados)
- [Instalação e Configuração](#instalação-e-configuração)
- [Segurança](#segurança)
- [Histórico de Correções](#histórico-de-correções)
- [Dependências](#dependências)
- [Ambiente de Produção](#ambiente-de-produção)
- [Fluxos do Sistema](#fluxos-do-sistema)
- [Roadmap](#roadmap)

---

## Visão Geral

O **Nexus Contábil** é uma plataforma SaaS voltada para escritórios de contabilidade que prestam serviços a empresas contratantes de profissionais autônomos (tomadores de serviços / OSCs). O sistema cobre dois eixos principais:

**Portal do Cliente** — interface de autoatendimento onde os clientes (empresas contratantes) acessam seus recibos RPA emitidos, baixam documentos fiscais e abrem chamados de suporte ao RH.

**Painel Administrativo** — área restrita ao contador/gestor para upload de documentos, gestão de chamados, cadastro de usuários e controle operacional.

O sistema está hospedado em:
```
https://contabil.nexusinnova.com.br
```

---

## Funcionalidades

### Portal do Cliente

| Módulo | Descrição |
|---|---|
| **Autenticação** | Login por e-mail + senha com rate limiting (5 tentativas → bloqueio de 5 min), `password_verify`, `session_regenerate_id` |
| **Dashboard (Painel Resumo)** | KPIs do mês: qtd. de RPAs emitidos, total bruto contratado, total líquido a pagar. Tabela completa do histórico de recibos com download individual |
| **Baixar RPA (PDF)** | Geração dinâmica via Dompdf com 2 páginas: Recibo principal + Anexo Técnico Tributário (memória de cálculo INSS/IRRF/ISS) com QR Code de autenticação |
| **Cofre de Documentos** | Acesso a documentos enviados pelo escritório, organizados por CNPJ (`baixar_doc.php`) |
| **Chamados (RH)** | Abertura de chamados por departamento com transação ACID (INSERT atômico em `chamados_portal` + `interacoes_chamado`); acompanhamento de histórico e status |
| **Validação de Documentos** | Página pública `validar.php` — checagem de autenticidade via hash MD5 de 12 caracteres gerado no momento da emissão |

### Painel Administrativo

| Módulo | Descrição |
|---|---|
| **Login Admin** | Autenticação separada (`admin_login.php` → `auth.php`) com `password_verify`, `session_regenerate_id` e COLLATE explícito na query |
| **Upload de Documentos** | Envio de PDFs por CNPJ do cliente (`admin_upload.php`); cria diretório `/uploads/{cnpj}/` automaticamente; valida extensão PDF |
| **Gestão de Chamados** | Listagem separada de chamados abertos/em andamento e resolvidos; atualização de status com timestamp automático (`admin_chamados.php`) |
| **Ver Chamado** | Visualização completa de interações de um chamado com formulário de resposta (`ver_chamado.php`) |
| **Gestão de Usuários** | CRUD completo de admins (`usuarios`) e usuários clientes (`usuarios_clientes`) com COLLATE explícito nas queries (`admin_usuarios.php`) |
| **Criar Admin** | Cadastro de novos administradores (`criar_admin.php`) |
| **Criar Usuário Portal** | Cadastro de clientes com CNPJ vinculado, CSRF token e validação de senha mínima (`criar_usuario.php`) |

### Site Institucional (HTML estático)

| Página | Conteúdo |
|---|---|
| `index.html` | Landing page principal da Nexus Contábil |
| `area_cliente.html` | Tela de login do portal do cliente |
| `calculadora_rpa.html` | Calculadora interativa de RPA (HTML/JS puro, sem dependências) |
| `solucoes.html` | Soluções contábeis gerais |
| `solucoes-pequenas-empresas.html` | Soluções para MEI/ME |
| `solucoes-cebas.html` | Soluções para entidades com CEBAS |
| `quem_somos.html` | Apresentação institucional |
| `contato.html` | Formulário de contato (leads salvos em `leads_contato`) |
| `blog.html` | Listagem de artigos |
| `politica_privacidade.html` | Política de privacidade (LGPD) |

### Artigos de Conteúdo

| Arquivo | Tema |
|---|---|
| `artigo_rpa.html` | Tudo sobre Recibo de Pagamento a Autônomo |
| `artigo_mei.html` | Transição MEI para ME |
| `artigo_mrosc.html` | Prestação de Contas MROSC |
| `artigo_cebas.html` | Guia CEBAS 2026 |

### Materiais Ricos para Download (Lead Capture)

| Arquivo | Descrição |
|---|---|
| `materiais/Transicao_MEI_para_ME.pdf` | Guia de transição MEI → ME |
| `materiais/MROSC_Prestacao_Contas.pdf` | Modelo de prestação de contas MROSC |
| `materiais/Guia_CEBAS_2026.pdf` | Guia CEBAS 2026 |

O download é condicionado ao preenchimento de formulário (`capturar_lead.php`), que registra nome, e-mail e material baixado na tabela `leads_materiais`.

---

## Arquitetura e Stack

```
┌─────────────────────────────────────────────────────┐
│                   FRONTEND                          │
│   HTML5 / CSS3 (custom) / JavaScript (vanilla)     │
│   Fontes: Playfair Display + DM Sans (Google CDN)  │
│   Design System: Verde (#0a4f4f) + Dourado (#c8973a)│
└────────────────────┬────────────────────────────────┘
                     │ HTTP / HTTPS
┌────────────────────▼────────────────────────────────┐
│                   BACKEND                           │
│   PHP 8.3 (LAMP Stack — HostGator compartilhado)   │
│   PDO + MySQL 5.7.44 (prepared statements)         │
│   PHPMailer ^7.0 (SMTP/Gmail)                      │
│   Dompdf ^3.1 (geração de PDF em memória)          │
└────────────────────┬────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────┐
│               BANCO DE DADOS                        │
│   MySQL 5.7.44-48 — omega381_nexus_rpa             │
│   Charset: utf8mb4 / Collate: utf8mb4_unicode_ci   │
│   Hospedagem: HostGator (cPanel — omega381)        │
└─────────────────────────────────────────────────────┘
```

**Stack resumida:**

| Item | Detalhe |
|---|---|
| **Linguagem** | PHP 8.3 (sem framework) |
| **Banco** | MySQL 5.7.44 via PDO com prepared statements |
| **PDF** | Dompdf `^3.1` (`isRemoteEnabled` + `isHtml5ParserEnabled`) |
| **E-mail** | PHPMailer `^7.0` |
| **QR Code** | `api.qrserver.com` (externo, via cURL) |
| **Autenticação** | `password_hash` / `password_verify` + sessões PHP nativas |
| **Hospedagem** | HostGator (servidor compartilhado, cPanel) |
| **Domínio** | `contabil.nexusinnova.com.br` |
| **SSL** | Let's Encrypt (`.well-known/acme-challenge/` presente) |
| **Timezone** | `America/Fortaleza` |

---

## Estrutura de Arquivos

```
nexus-contabil/
│
├── .env                              ← Credenciais do banco (NÃO versionar — lido por db.php)
├── .well-known/acme-challenge/       ← Renovação automática SSL Let's Encrypt
│
│── NÚCLEO / INFRAESTRUTURA ─────────────────────────────────────────────────
│
├── db.php                            ← Conexão PDO centralizada (parser .env próprio, sem lib)
├── auth.php                          ← Autenticação do painel admin (lê db.php, COLLATE corrigido)
├── login.php                         ← Login do portal do cliente (rate limiting 5 tentativas/5 min)
├── admin_header.php                  ← Layout/sidebar/CSS unificado do painel admin
├── admin_logout.php                  ← Encerramento de sessão admin
├── admin_login.php                   ← Tela de login do painel admin
│
│── PORTAL DO CLIENTE ───────────────────────────────────────────────────────
│
├── area_cliente.html                 ← Tela de login (frontend estático)
├── dashboard.php                     ← Painel resumo: KPIs do mês + tabela de RPAs
├── chamados.php                      ← Abertura e listagem de chamados (transação ACID)
├── ver_chamado.php                   ← Detalhes e interações de um chamado
├── baixar_pdf.php                    ← Geração de RPA em PDF via Dompdf (2 páginas)
├── baixar_doc.php                    ← Download de documentos do cofre digital
├── validar.php                       ← Validação pública de autenticidade via hash
│
│── PAINEL ADMINISTRATIVO ───────────────────────────────────────────────────
│
├── admin_upload.php                  ← Upload de PDFs para clientes (corrigido: bloco POST)
├── admin_chamados.php                ← Gestão e atualização de status de chamados
├── admin_usuarios.php                ← CRUD de admins e usuários clientes (COLLATE corrigido)
├── criar_admin.php                   ← Cadastro de novos administradores
├── criar_usuario.php                 ← Cadastro de clientes no portal (CSRF token)
│
│── SITE INSTITUCIONAL (HTML estático) ─────────────────────────────────────
│
├── index.html                        ← Landing page principal
├── solucoes.html
├── solucoes-pequenas-empresas.html
├── solucoes-cebas.html
├── quem_somos.html
├── contato.html
├── blog.html
├── calculadora_rpa.html              ← Calculadora RPA (JS puro, sem backend)
├── politica_privacidade.html
├── artigo_rpa.html
├── artigo_mei.html
├── artigo_mrosc.html
├── artigo_cebas.html
│
│── CAPTURA DE LEADS ────────────────────────────────────────────────────────
│
├── capturar_lead.php                 ← Registra lead na tabela leads_materiais
│
│── ASSETS ──────────────────────────────────────────────────────────────────
│
├── css/style.css
├── js/script.js
├── logo.png
├── logo-contabil.png
├── logo-contabil-verde.png
├── logo-contabil-azul.png
│
│── UPLOADS (gerados em runtime) ────────────────────────────────────────────
│
├── uploads/
│   └── {cnpj_sem_mascara}/           ← Criado automaticamente por admin_upload.php
│       └── arquivo.pdf
│
│── MATERIAIS RICOS ─────────────────────────────────────────────────────────
│
├── materiais/
│   ├── Guia_CEBAS_2026.pdf
│   ├── MROSC_Prestacao_Contas.pdf
│   └── Transicao_MEI_para_ME.pdf
│
│── DEPENDÊNCIAS ────────────────────────────────────────────────────────────
│
├── vendor/                           ← Gerenciado pelo Composer
│   └── phpmailer/phpmailer/
├── PHPMailer/                        ← Cópia local (redundante — preferir vendor/)
│   ├── PHPMailer.php
│   ├── SMTP.php
│   └── Exception.php
├── composer.json
└── composer.lock
```

---

## Banco de Dados

**Database:** `omega381_nexus_rpa`
**Servidor:** MySQL 5.7.44-48 | **PHPMyAdmin:** 5.2.2 | **PHP:** 8.3.31

### Schema completo das tabelas

| Tabela | Collate | Descrição |
|---|---|---|
| `usuarios` | `utf8mb4_general_ci` ⚠️ | Administradores do painel — criada **sem COLLATE explícito** (causa conflito 1267 com o restante do banco) |
| `usuarios_clientes` | `utf8mb4_unicode_ci` | Usuários do portal do cliente (nome, email, senha, cnpj_empresa) — usada por `admin_usuarios.php` |
| `usuarios_portal` | `utf8mb4_unicode_ci` | Segundo sistema de acesso ao portal (email_acesso, senha_hash, cnpj_cliente) — usada por `login.php` |
| `tomadores_empresas` | `utf8mb4_unicode_ci` | Empresas contratantes (cnpj UNIQUE, razao_social, cep_sede, cidade_sede, estado_sede) |
| `prestadores_autonomos` | `utf8mb4_unicode_ci` | Profissionais autônomos (nome_completo, cpf UNIQUE, email, cidade, estado) |
| `rpa_emissões` | `utf8mb4_unicode_ci` | Recibos emitidos — núcleo tributário do sistema |
| `documentos_portal` | `utf8mb4_unicode_ci` | Documentos enviados pelo escritório (titulo, categoria ENUM, caminho_arquivo, cnpj_cliente) |
| `chamados_portal` | `utf8mb4_unicode_ci` | Chamados de suporte (departamento ENUM, assunto, status ENUM, datas) |
| `interacoes_chamado` | `utf8mb4_unicode_ci` | Mensagens de cada chamado (chamado_id FK, autor ENUM Cliente/Equipe Nexus, mensagem) |
| `leads_contato` | `utf8mb4_unicode_ci` | Leads do formulário de contato (nome, email, telefone, mensagem, status ENUM) |
| `leads_materiais` | `utf8mb4_unicode_ci` | Leads de download de materiais (nome, email, material_baixado) |

### Estrutura detalhada: `rpa_emissões`

```sql
CREATE TABLE `rpa_emissões` (
  `id`                       int(11) NOT NULL AUTO_INCREMENT,
  `prestador_id`             int(11) NOT NULL,         -- FK → prestadores_autonomos
  `tomador_id`               int(11) NOT NULL,         -- FK → tomadores_empresas
  `data_emissao`             date NOT NULL,
  `mes_competencia`          varchar(2)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `ano_competencia`          varchar(4)  COLLATE utf8mb4_unicode_ci NOT NULL,
  `cidade_execucao_servico`  varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_servico_bruto`      decimal(10,2) DEFAULT NULL,
  `valor_inss_retido`        decimal(10,2) DEFAULT NULL,
  `valor_deducao_dependentes` decimal(10,2) DEFAULT NULL,
  `base_calculo_irrf`        decimal(10,2) DEFAULT NULL,  -- bruto − INSS − deduções
  `valor_irrf_retido`        decimal(10,2) DEFAULT NULL,
  `valor_iss_retido`         decimal(10,2) DEFAULT NULL,
  `valor_liquido_pago`       decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`prestador_id`) REFERENCES `prestadores_autonomos`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tomador_id`)   REFERENCES `tomadores_empresas`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### ENUMs definidos no banco

| Tabela | Coluna | Valores |
|---|---|---|
| `chamados_portal` | `departamento` | `Contábil`, `Fiscal`, `Departamento Pessoal`, `Legalização` |
| `chamados_portal` | `status` | `Aberto`, `Em Andamento`, `Aguardando Cliente`, `Resolvido` |
| `interacoes_chamado` | `autor` | `Cliente`, `Equipe Nexus` |
| `documentos_portal` | `categoria` | `RPA`, `Guias de Impostos`, `Balanços`, `Diversos` |
| `leads_contato` | `status` | `Novo`, `Em Atendimento`, `Concluído` |

### Cálculo Tributário (RPA)

| Tributo | Base de Cálculo | Alíquota / Regra |
|---|---|---|
| **INSS** | Valor bruto do serviço | 11% (fixo) |
| **IRRF** | `base_calculo_irrf` = Bruto − INSS − Deduções de dependentes | Tabela progressiva mensal |
| **ISS** | Valor bruto do serviço | Definida pelo município de prestação |
| **Líquido** | Bruto − INSS − IRRF − ISS | Valor efetivamente pago ao prestador |

### Relações e Foreign Keys

```
tomadores_empresas ──< rpa_emissões >── prestadores_autonomos
tomadores_empresas ──< chamados_portal ──< interacoes_chamado
tomadores_empresas ──< documentos_portal
tomadores_empresas ──< usuarios_portal   (via cnpj_cliente)
```

> ⚠️ **Problema de collation no banco:** A tabela `usuarios` foi criada sem `COLLATE` explícito, resultando em `utf8mb4_general_ci` (padrão do MySQL 5.7). Todas as demais tabelas usam `utf8mb4_unicode_ci`. Isso causa o erro 1267 em queries que cruzam `usuarios` com outras tabelas. **Correção definitiva:**
> ```sql
> ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> ```
> Enquanto o `ALTER` não for executado, os arquivos PHP corrigidos já aplicam `COLLATE utf8mb4_unicode_ci` inline nas queries afetadas.

---

## Instalação e Configuração

### Pré-requisitos

- PHP 8.1+ (produção usa 8.3.31)
- MySQL 5.7+ / MariaDB 10.4+
- Composer
- Apache com `mod_rewrite` e `.htaccess` habilitado

### Passo a Passo

**1. Upload dos arquivos**

```bash
# Via FTP/SFTP para o cPanel ou git clone no servidor
```

**2. Instalar dependências Composer**

```bash
composer install --no-dev --optimize-autoloader
```

Instala: `dompdf/dompdf ^3.1` e `phpmailer/phpmailer ^7.0`.

**3. Criar o arquivo `.env`**

Crie na raiz do projeto (nunca versionar, nunca commitar):

```env
DB_HOST=localhost
DB_NAME=omega381_nexus_rpa
DB_USER=seu_usuario_cpanel
DB_PASS=sua_senha_segura
```

> O `db.php` possui um parser próprio de `.env` — sem dependência de `vlucas/phpdotenv` ou similar.

**4. Importar o banco de dados**

```bash
mysql -u usuario -p omega381_nexus_rpa < nexus-contabil.sql
```

Ou via PHPMyAdmin → Importar o arquivo `.sql`.

**5. Corrigir collation da tabela `usuarios`**

Execute imediatamente após a importação:

```sql
ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**6. Configurar permissões de escrita**

```bash
chmod 755 uploads/
chmod 644 .env
```

**7. Criar o primeiro administrador**

Acesse `https://seu-dominio/criar_admin.php` e crie o admin inicial.
**Remova ou proteja o arquivo após o uso.**

**8. Cadastrar clientes no portal**

Acesse o painel admin → `criar_usuario.php` para cadastrar clientes com CNPJ vinculado.

---

## Segurança

### Mecanismos implementados

| Mecanismo | Onde |
|---|---|
| Credenciais via `.env` (nunca hardcoded) | `db.php` — todos os arquivos herdam via `require_once` |
| PDO com prepared statements | 100% das queries SQL |
| `password_hash(PASSWORD_DEFAULT)` / `password_verify` | `login.php`, `auth.php`, `criar_usuario.php`, `admin_usuarios.php` |
| `session_regenerate_id(true)` após login | `login.php`, `auth.php` |
| CSRF Token `bin2hex(random_bytes(32))` | `criar_usuario.php` |
| Rate limiting de login (5 tentativas → bloqueio 5 min) | `login.php` (via sessão PHP) |
| Validação de extensão (apenas PDF) | `admin_upload.php` |
| Sanitização com `htmlspecialchars` | Todos os outputs HTML |
| Verificação de sessão em páginas protegidas | `dashboard.php`, `chamados.php`, `ver_chamado.php`, todos `admin_*` |
| Transação ACID (BEGIN / COMMIT / ROLLBACK) | `chamados.php` — abertura de chamado em duas tabelas |
| Isolamento por CNPJ do cliente | `dashboard.php`, `baixar_pdf.php` — `WHERE t.cnpj = ?` |
| `COLLATE utf8mb4_unicode_ci` explícito em queries críticas | `auth.php`, `admin_usuarios.php` |

> Após as correções aplicadas, não há credenciais hardcoded em nenhum arquivo do projeto.

### Pontos de atenção pendentes

| Item | Risco | Recomendação |
|---|---|---|
| `criar_admin.php` sem autenticação | Qualquer pessoa pode criar admin se souber a URL | Remover após uso inicial ou proteger com IP restriction no `.htaccess` |
| `/uploads/` acessível via URL direta | Qualquer pessoa com o path pode baixar PDFs de outros clientes | Adicionar `.htaccess` na pasta + servir via `readfile()` com verificação de sessão |
| `/vendor/` exposto | Exposição de dependências | Bloquear no `.htaccess` raiz |
| `.env` na raiz web | Se mal configurado, pode ser acessado via URL | Bloquear no `.htaccess`: `<Files ".env"> Deny from all </Files>` |
| Rate limiting via sessão PHP | Não persiste entre processos/servidores; pode ser bypassado com novos cookies | Migrar para APCu ou tabela no banco para produção em escala |
| PHPMailer duplicado | `/PHPMailer/` local e `/vendor/phpmailer/` via Composer | Remover a cópia local; usar apenas o Composer |

---

## Histórico de Correções

Três bugs críticos identificados no `error_log` de produção (15–16/05/2026) foram corrigidos e os arquivos entregues prontos para substituição no servidor. A análise final foi feita com base no dump SQL real do banco (`nexus-contabil.sql`).

---

### ✅ 1. `auth.php` — Credenciais hardcoded + COLLATE ausente

**Sintoma:** Senha do banco de dados exposta diretamente no código-fonte. Risco crítico de segurança.

**Causa:** O arquivo `auth.php` abria sua própria conexão PDO com credenciais literais, ignorando o padrão `db.php` + `.env` adotado por todos os outros arquivos. Adicionalmente, a query na tabela `usuarios` (collate `general_ci`) sem COLLATE explícito estava sujeita ao erro 1267 em certas configurações de sessão MySQL.

```php
// ANTES — ❌ credenciais hardcoded
$host     = 'localhost';
$dbname   = 'omega381_nexus_rpa';
$username = 'omega381_nexus_rpa';
$password = 'Felipe1986@';
$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

// DEPOIS — ✅ conexão centralizada + COLLATE seguro
require_once __DIR__ . '/db.php';
$stmt = $pdo->prepare("
    SELECT id, email, senha FROM usuarios
    WHERE email COLLATE utf8mb4_unicode_ci = ? LIMIT 1
");
```

**Também corrigido:** adicionado `$_SESSION['admin_id']` para consistência com `admin_header.php`.

---

### ✅ 2. `admin_upload.php` — Tela branca em qualquer acesso GET

**Sintoma nos logs:**
```
PHP Warning:  Undefined variable $pdo in admin_upload.php on line 108
PHP Fatal error:  Call to a member function query() on null
```

**Causa:** O `require_once 'db.php'` estava corretamente no topo do arquivo e o `$pdo` existia. O bug era estrutural: o `}` de fechamento do bloco `if (POST)` estava faltando. A query de busca de clientes (`SELECT razao_social, cnpj FROM tomadores_empresas`) ficava dentro do condicional POST, tornando `$clientes` sempre vazia num acesso GET normal. Em versões anteriores do arquivo, o `$pdo` também ficava fora de escopo.

```php
// ANTES — ❌ query presa dentro do bloco POST, select vazio no GET
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['titulo'])) {
    // ... lógica de upload ...
    // } faltando aqui
// Busca a lista de clientes para popular o Select
$stmtClientes = $pdo->query("SELECT ...");   // ← dentro do if

// DEPOIS — ✅ bloco POST fechado corretamente; query sempre executada
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['titulo'])) {
    // ... lógica de upload ...
} // ← fechamento explícito

try {
    $stmtClientes = $pdo->query("SELECT razao_social, cnpj FROM tomadores_empresas ORDER BY razao_social ASC");
    $clientes     = $stmtClientes->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
}
```

---

### ✅ 3. `admin_usuarios.php` — Conflito de collation (erro 1267)

**Sintoma nos logs:**
```
PDOException: SQLSTATE[HY000]: General error: 1267
Illegal mix of collations (utf8mb4_general_ci,IMPLICIT)
and (utf8mb4_unicode_ci,IMPLICIT) for operation '='
```

**Causa raiz confirmada no dump SQL:** A tabela `usuarios` foi criada sem `COLLATE` explícito — no MySQL 5.7, isso resulta em `utf8mb4_general_ci` (collate padrão do servidor). Todas as outras 10 tabelas do banco usam `utf8mb4_unicode_ci` explícito. Qualquer query que misture `usuarios` com outra tabela via JOIN ou comparação dispara o erro 1267.

```sql
-- usuarios — utf8mb4_general_ci (implícito, sem declaração)
CREATE TABLE `usuarios` (
  `email` varchar(255) NOT NULL, ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Todas as demais — utf8mb4_unicode_ci (explícito)
CREATE TABLE `usuarios_clientes` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL, ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Solução aplicada no PHP** — COLLATE forçado nas colunas afetadas:

```sql
-- ANTES — ❌ conflito de collation implícito
SELECT id, email, criado_em FROM usuarios ORDER BY id DESC

-- DEPOIS — ✅ COLLATE explícito neutraliza o conflito
SELECT id, email COLLATE utf8mb4_unicode_ci AS email, criado_em
FROM usuarios ORDER BY id DESC
```

**Também corrigido no JOIN `usuarios_clientes ↔ tomadores_empresas`** (proteção preventiva):

```sql
JOIN tomadores_empresas t
  ON uc.cnpj_empresa COLLATE utf8mb4_unicode_ci = t.cnpj COLLATE utf8mb4_unicode_ci
```

> **Nota sobre `usuarios_clientes`:** O dump SQL confirmou que essa tabela existe com as colunas `nome`, `email`, `senha`, `cnpj_empresa` — é a tabela correta usada pelo `admin_usuarios.php`. Ela coexiste com `usuarios_portal` (`email_acesso`, `senha_hash`, `cnpj_cliente`), que é usada exclusivamente pelo `login.php` do portal. São sistemas de acesso independentes.

---

## Dependências

### Composer (`composer.json`)

```json
{
    "require": {
        "dompdf/dompdf": "^3.1",
        "phpmailer/phpmailer": "^7.0"
    }
}
```

| Pacote | Versão | Uso no projeto |
|---|---|---|
| `dompdf/dompdf` | ^3.1 | Geração do PDF do RPA em memória: Recibo + Anexo Técnico Tributário (2 páginas, com imagem remota via cURL) |
| `phpmailer/phpmailer` | ^7.0 | Envio de e-mails via SMTP (Gmail) — ex: confirmações de chamados, notificações |

> ⚠️ O projeto mantém uma cópia local do PHPMailer em `/PHPMailer/` **além** da versão do Composer em `/vendor/`. Recomenda-se remover a cópia local e usar exclusivamente o Composer para evitar conflitos de versão.

### Serviços externos

| Serviço | Endpoint | Uso |
|---|---|---|
| Google Fonts | `fonts.googleapis.com` | Playfair Display + DM Sans (carregadas no HTML) |
| QR Server | `api.qrserver.com/v1/create-qr-code/` | Geração de QR Code embutido no PDF do RPA via cURL |
| Let's Encrypt | `.well-known/acme-challenge/` | Renovação automática do certificado SSL |

---

## Ambiente de Produção

| Item | Detalhe |
|---|---|
| **Hospedagem** | HostGator (servidor compartilhado) |
| **cPanel user** | `omega381` |
| **Database** | `omega381_nexus_rpa` |
| **Servidor MySQL** | 5.7.44-48 |
| **PHP** | 8.3.31 |
| **PHPMyAdmin** | 5.2.2 |
| **Domínio** | `contabil.nexusinnova.com.br` |
| **Timezone** | `America/Fortaleza` (UTC-3) |
| **Path raiz** | `/home2/omega381/contabil.nexusinnova.com.br/` |

---

## Fluxos do Sistema

### Autenticação

```
[Portal do Cliente]
area_cliente.html ──POST──► login.php ──► dashboard.php
                                │ (falha / bloqueio)
                                └──► area_cliente.html?erro=1|bloqueado

[Painel Admin]
admin_login.php ──POST──► auth.php ──► admin_upload.php
                              │ (falha)
                              └──► admin_login.php?erro=1
```

### Emissão e Download de RPA

```
[Admin / ERP externo]
INSERT em rpa_emissões
        │
        ▼
[Cliente acessa dashboard.php]
Visualiza KPIs do mês + tabela de histórico
        │
        ▼
[Clica em "Baixar PDF"]
baixar_pdf.php?id={id}
        │
        ├── Verifica sessão + isolamento por CNPJ
        ├── Busca dados em rpa_emissões + tomadores + prestadores
        ├── Gera hash de autenticação (MD5 12 chars)
        ├── Busca QR Code via cURL → api.qrserver.com
        ├── Monta HTML completo (Recibo + Anexo Técnico)
        └── Dompdf renderiza e faz stream inline (A4, portrait)
                │
                ▼
        [Validação pública]
        validar.php?doc={hash_12chars}
```

### Abertura de Chamado (transação ACID)

```
chamados.php (POST)
        │
        ├── $pdo->beginTransaction()
        ├── INSERT INTO chamados_portal (cabeçalho)
        ├── lastInsertId() → $chamado_id
        ├── INSERT INTO interacoes_chamado (mensagem inicial)
        └── $pdo->commit()
            │ (erro em qualquer etapa)
            └── $pdo->rollBack() → nenhum dado órfão
```

### Upload de Documento para Cliente

```
admin_upload.php (POST)
        │
        ├── Valida extensão → apenas .pdf
        ├── Cria /uploads/{cnpj_sem_mascara}/ se não existir
        ├── move_uploaded_file() → salva no servidor
        └── INSERT INTO documentos_portal (titulo, categoria, caminho)
```

---

## Roadmap

| Status | Item |
|---|---|
| ✅ Resolvido | `auth.php` — credenciais hardcoded migradas para `.env` via `db.php` |
| ✅ Resolvido | `admin_upload.php` — bloco POST fechado; query de clientes executada no GET |
| ✅ Resolvido | `admin_usuarios.php` — COLLATE explícito; tabela `usuarios_clientes` confirmada |
| ⚠️ Urgente | Executar `ALTER TABLE usuarios CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` no banco |
| ⚠️ Urgente | Adicionar `.htaccess` bloqueando acesso direto a `/uploads/`, `/vendor/`, `.env` |
| ⚠️ Urgente | Proteger ou remover `criar_admin.php` após uso inicial |
| 🔧 Melhoria | Remover cópia local `/PHPMailer/` — usar exclusivamente o Composer |
| 🔧 Melhoria | Migrar rate limiting de sessão PHP para APCu ou tabela no banco |
| 🔧 Melhoria | Servir arquivos de `/uploads/` via PHP (`readfile()` + verificação de sessão) em vez de URL direta |
| 📋 Documentação | Criar `/database/schema.sql` com DDL completo e comentado |
| 📋 Documentação | Documentar variáveis de sessão usadas por módulo |

---

*Última atualização: 16/05/2026 — Nexus Contábil ERP | Nexus Innova*
*Análise baseada em: código-fonte ZIP + dump SQL `nexus-contabil.sql` (MySQL 5.7.44, PHPMyAdmin 5.2.2)*