# 📈 Assistente Pessoal de Decisão de Investimentos

Aplicação web pessoal para apoio à decisão de alocação mensal de aportes financeiros entre ações brasileiras, fundos imobiliários (FIIs), Tesouro Direto, ETFs internacionais e outras classes, integrando diagnóstico quantitativo determinístico no backend e inteligência artificial fundamentada (Google Gemini).

---

## 🚀 Tecnologias

- **Backend:** Laravel 11 (PHP 8.3)
- **Painel & Interface:** Filament v3
- **Banco de Dados Principal:** PostgreSQL 16 (com tipos `numeric/decimal` para precisão financeira e `jsonb` para metadados)
- **Ambiente Conteinerizado:** Docker Compose (`app`, `web` Nginx 1.27, `db` PostgreSQL 16, `queue`)
- **Provedores de IA:** Arquitetura desacoplada e configurável via interface Filament com criptografia de credenciais em repouso
- **Testes Automatizados:** PHPUnit 11

---

## 📋 Pré-requisitos

- [Docker](https://docs.docker.com/engine/install/) (versão 24+)
- [Docker Compose](https://docs.docker.com/compose/install/) (v2+)

Não é necessário ter PHP, Composer ou PostgreSQL instalados no host.

---

## 🛠️ Inicialização Rápida

### 1. Clonar e preparar variáveis de ambiente

```bash
cp .env.example .env
```

### 2. Iniciar os serviços com Docker Compose

```bash
docker compose up -d --build
```

Os serviços serão iniciados:
- **web:** `http://localhost:8080` (Nginx)
- **app:** PHP 8.3 FPM com extensões PostgreSQL, `bcmath`, `intl`, `zip`, `pcntl`, `opcache`
- **db:** PostgreSQL 16 (porta 5432 na rede interna do Docker)
- **queue:** Worker de filas assíncronas do Laravel

### 3. Instalar dependências e gerar chave da aplicação

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### 4. Executar Migrations e Seeds iniciais

```bash
docker compose exec app php artisan migrate --seed
```

> **Credenciais do Usuário Padrão gerado pelo Seed:**
> - **E-mail:** `admin@invest.local`
> - **Senha:** `password`
> - **Acesso ao Painel:** `http://localhost:8080/admin`

Para criar um novo usuário administrador interativamente:
```bash
docker compose exec app php artisan make:filament-user
```

---

## 🧪 Execução de Testes Automatizados

Para executar a suíte completa de testes no container:

```bash
docker compose exec app php artisan test
```

Para rodar um teste específico:

```bash
docker compose exec app php artisan test --filter=AllocationTargetTest
```

---

## 📊 Estrutura e Funcionalidades (Fase 1)

1. **Objetivos Financeiros (`FinancialGoal`):** Cadastro de objetivos com valor alvo, valor acumulado, prazo, prioridade e necessidade de liquidez.
2. **Perfil de Investidor (`InvestorProfile`):** Configuração de perfil de risco, horizonte predominante, meta de reserva de emergência, limites de concentração por ativo/setor e classes permitidas.
3. **Metas de Alocação (`AllocationTarget`):** Metas percentuais por classe de ativos com validação de soma totalizando 100% (`AllocationTargetService`).
4. **Provedores de IA (`AiProviderConfiguration`):** Cadastro e gerenciamento de múltiplos provedores e modelos (iniciando com Google Gemini), com armazenamento seguro da chave de API criptografada em repouso (`Crypt::encryptString`) e exibição mascarada na UI.

---

## 🔧 Comandos Operacionais & Manutenção

### Visualizar Logs em tempo real

```bash
# Todos os serviços
docker compose logs -f

# Apenas a aplicação Laravel
docker compose logs -f app

# Worker de fila
docker compose logs -f queue
```

### Parar os containers preservando os dados

```bash
docker compose down
```

### Backup do Banco de Dados PostgreSQL

```bash
docker compose exec db pg_dump -U invest_user decisao_investimento > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restauração do Banco de Dados PostgreSQL

```bash
cat backup_arquivo.sql | docker compose exec -T db psql -U invest_user decisao_investimento
```

---

## 🔒 Segurança

- As chaves de API cadastradas na interface são gravadas com criptografia de envelope do Laravel (`casts => ['api_key' => 'encrypted']`) e mascaradas na listagem e edição.
- A porta do PostgreSQL não é exposta publicamente no host, operando exclusivamente na bridge interna do Docker Compose.
- Valores monetários e percentuais utilizam representação decimal exata, prevenindo distorções de ponto flutuante.
