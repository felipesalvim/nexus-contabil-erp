Com certeza! Remover informações sensíveis, como esquemas exatos de banco de dados, caminhos físicos do servidor, vulnerabilidades pendentes e detalhes internos de hospedagem, é uma prática essencial de segurança (prevenção contra *Information Disclosure*). Ao mesmo tempo, mantive toda a densidade técnica, as tabelas de funcionalidades e a arquitetura que valorizam o seu projeto para a banca e para o mercado.

Também limpei os conflitos de merge (aquelas marcações de erro do Git) e incluí a sua equipa completa, bem como o link do repositório.

Aqui está o seu `README.md` limpo, profissional e pronto para o GitHub. Basta copiar todo o bloco abaixo e substituir no seu ficheiro:

```markdown
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
- [Fluxos do Sistema](#fluxos-do-sistema)
- [Equipe do Projeto](#equipe-do-projeto)

---

## Visão Geral

O **Nexus Contábil** é uma plataforma SaaS voltada para escritórios de contabilidade que prestam serviços a empresas contratantes de profissionais autônomos (tomadores de serviços / OSCs). O sistema cobre dois eixos principais:

**Portal do Cliente** — interface de autoatendimento onde os clientes (empresas contratantes) acessam seus recibos RPA emitidos, baixam documentos fiscais e abrem chamados de suporte ao RH.

**Painel Administrativo** — área restrita ao contador/gestor para upload de documentos, gestão de chamados, cadastro de usuários e controle operacional.

🌐 **Acesso ao Sistema:** [https://contabil.nexusinnova.com.br](https://contabil.nexusinnova.com.br)
💻 **Repositório do Projeto:** [Nexus Contábil ERP no GitHub](https://github.com/felipesalvim/nexus-contabil-erp)

---

## Funcionalidades

### Portal do Cliente

| Módulo | Descrição |
|---|---|
| **Autenticação** | Login por e-mail e senha com rate limiting de segurança e controle rigoroso de sessão. |
| **Dashboard (Painel Resumo)** | KPIs do mês: qtd. de RPAs emitidos, total bruto contratado, total líquido a pagar. Tabela completa do histórico de recibos com download individual. |
| **Baixar RPA (PDF)** | Geração dinâmica de PDF via Dompdf com 2 páginas: Recibo principal + Anexo Técnico Tributário (memória de cálculo INSS/IRRF/ISS) com QR Code de autenticação. |
| **Cofre de Documentos** | Acesso a documentos enviados pelo escritório, organizados estruturalmente por CNPJ. |
| **Chamados (RH)** | Abertura de chamados por departamento com transação ACID no banco de dados; acompanhamento de histórico e status. |
| **Validação de Documentos** | Validação pública de autenticidade documental via hash criptográfico de 12 caracteres gerado no momento da emissão. |

### Painel Administrativo

| Módulo | Descrição |
|---|---|
| **Gateway de Autenticação** | Login isolado do painel administrativo com verificação de hashes (`password_verify`) e regeneração de ID de sessão. |
| **Upload de Documentos** | Envio e distribuição de PDFs diretamente para o Cofre Digital do cliente selecionado. |
| **Gestão de Chamados** | Controle, triagem e atualização de status de tickets de suporte abertos pelos clientes. |
| **Controle de Acesso (CRUD)** | Gestão completa de credenciais para administradores do sistema e perfis empresariais (clientes B2B). |

### Site Institucional & Captação

A plataforma também atua como canal de conversão, englobando:
* **Calculadora de RPA:** Ferramenta interativa desenvolvida em Vanilla JS para simulação de retenções tributárias.
* **Landing Pages:** Soluções segmentadas para MEI, Pequenas Empresas e entidades CEBAS.
* **Funil de Leads:** Captura de contatos institucionais e download de materiais ricos (e-books e guias) com armazenamento em banco de dados.

---

## Arquitetura e Stack

A aplicação foi construída priorizando performance nativa, baixo acoplamento e redução de Custo Total de Propriedade (TCO) em ambientes de hospedagem.

```text
┌─────────────────────────────────────────────────────┐
│                   FRONT-END                         │
│   HTML5 / CSS3 (Variáveis/Grid) / JavaScript Puro   │
│   Fontes: Playfair Display + DM Sans                │
│   Design System Corporativo Orientado a Conversão   │
└────────────────────┬────────────────────────────────┘
                     │ HTTP / HTTPS
┌────────────────────▼────────────────────────────────┐
│                   BACK-END                          │
│   PHP 8+ (Orientado a Objetos e Scripts Otimizados) │
│   PDO (PHP Data Objects)                            │
│   PHPMailer (SMTP Autenticado)                      │
│   Dompdf (Geração de PDFs em memória)               │
└────────────────────┬────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────┐
│               BANCO DE DADOS                        │
│   MySQL / MariaDB Relacional                        │
│   Charset/Collate padronizados (utf8mb4_unicode_ci) │
└─────────────────────────────────────────────────────┘

```

---

## Estrutura de Arquivos (Visão Macro)

```text
nexus-contabil/
│
├── .env                              ← Variáveis de ambiente (Segurança)
├── db.php                            ← Conexão PDO centralizada 
│
│── PORTAL DO CLIENTE ───────────────────────────────────────────────────────
│
├── dashboard.php                     ← Cofre Digital e Dashboard
├── chamados.php                      ← Abertura de chamados e suporte
├── baixar_pdf.php                    ← Geração de RPA (Dompdf)
├── validar.php                       ← Motor de validação anti-fraude documental
│
│── PAINEL ADMINISTRATIVO ───────────────────────────────────────────────────
│
├── admin_header.php                  ← Centralizador de Segurança e Layout
├── admin_upload.php                  ← Envio de documentos ao Cofre
├── admin_chamados.php                ← Resolução de tickets de clientes
├── admin_usuarios.php                ← CRUD de permissões e acessos
│
│── SITE E ASSETS ───────────────────────────────────────────────────────────
│
├── index.html                        ← Site institucional e Funil
├── css/ & js/                        # Estilos globais e scripts de UI
├── materiais/                        # PDFs para captação de leads
└── vendor/                           # Dependências via Composer

```

---

## Banco de Dados

A modelagem de dados foi estruturada utilizando **Modelo Relacional** rigoroso, com chaves estrangeiras (Foreign Keys) para garantir a integridade referencial dos dados (`ON DELETE CASCADE`).

### Entidades Principais:

* **`tomadores_empresas`**: Cadastro corporativo das empresas contratantes.
* **`prestadores_autonomos`**: Profissionais que prestam serviços às empresas.
* **`rpa_emissões`**: Núcleo tributário contendo histórico de competências, valor bruto, INSS, IRRF, ISS e valor líquido.
* **`documentos_portal`**: Metadados e caminhos dos arquivos do Cofre Digital.
* **`chamados_portal` & `interacoes_chamado**`: Arquitetura relacional do sistema de Help Desk.
* **`usuarios` & `usuarios_clientes**`: Entidades isoladas para gestão de credenciais administrativas e corporativas.

---

## Instalação e Configuração

### Pré-requisitos

* PHP 8.1+
* MySQL 5.7+ / MariaDB 10.4+
* Composer
* Servidor Web (Apache/Nginx)

### Passo a Passo

1. **Clone o repositório:**
```bash
git clone [https://github.com/felipesalvim/nexus-contabil-erp.git](https://github.com/felipesalvim/nexus-contabil-erp.git)

```


2. **Instale as dependências:**
```bash
composer install --no-dev --optimize-autoloader

```


3. **Configuração de Ambiente:**
Crie um arquivo `.env` na raiz do projeto contendo as credenciais do banco:
```env
DB_HOST=localhost
DB_NAME=nome_do_banco
DB_USER=usuario_do_banco
DB_PASS=senha_segura

```


4. **Importação de Dados:**
Importe o schema inicial para o seu gerenciador MySQL.
5. **Permissões:**
Certifique-se de que o diretório `/uploads/` possui permissão de escrita para o servidor web.

---

## Segurança

O projeto segue rigorosas diretrizes de engenharia de software para garantir o *compliance* de dados sensíveis:

* **Proteção de Dados em Repouso:** Algoritmos de hash unidirecionais nativos (`password_hash` com BCRYPT) para todas as senhas.
* **Defesa contra SQL Injection:** Implementação 100% baseada em *Prepared Statements* via PDO.
* **Proteção CSRF:** Tokens criptográficos gerados via `random_bytes()` em formulários sensíveis.
* **Isolamento de Diretórios:** Proteção de arquivos críticos (`.env`, `/vendor/`) contra acesso web direto.
* **Isolamento B2B:** Verificação algorítmica rigorosa para garantir que os usuários acessem apenas os documentos fiscais atrelados ao seu próprio CNPJ.

---

## Equipe do Projeto

Projeto acadêmico desenvolvido no curso de **Análise e Desenvolvimento de Sistemas** (Faculdade CDL) pela equipe:

* **Erivania Ferreira Dias**
* **Felipe Silva Alvim**
* **Ismael Oliveira Silva**
* **Sarah Ribeiro Marques**

*Nexus Contábil ERP | Fortaleza, CE.*

```

```
