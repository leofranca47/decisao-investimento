# 📈 Assistente Pessoal de Decisão de Investimentos

Aplicação web pessoal para apoio à decisão de alocação mensal de aportes financeiros entre ações brasileiras, fundos imobiliários (FIIs), Tesouro Direto, ETFs internacionais e outras classes de ativos, integrando diagnóstico quantitativo determinístico no backend e inteligência artificial fundamentada (Google Gemini).

---

## 🛠️ Tecnologias & Arquitetura

- **Backend:** Laravel 11 (PHP 8.3)
- **Painel & Interface:** Filament v3 (recursos e páginas nativas em português)
- **Banco de Dados Principal:** PostgreSQL 16 com extensão vetorial **`pgvector`** (dimensões 768, índice HNSW de distância por cosseno e tipos `decimal/numeric` para precisão financeira estrita)
- **Ambiente Conteinerizado:** Docker Compose com 4 serviços:
  - `web`: Nginx 1.27 Alpine (porta 8080)
  - `app`: PHP 8.3 FPM com extensões PostgreSQL, `bcmath`, `intl`, `zip`, `pcntl`, `opcache`, `gd`
  - `db`: `pgvector/pgvector:pg16` (PostgreSQL 16 com pgvector nativo)
  - `queue`: Worker de tarefas assíncronas do Laravel
- **Inteligência Artificial & Busca Vetorial:** Camada desacoplada com múltiplos provedores configuráveis via interface (`AiProviderManager`), com adaptador oficial do **Google Gemini** em JSON Mode, geração de embeddings semânticos (`text-embedding-004`) e credenciais criptografadas em repouso.
- **Testes Automatizados:** Suíte com PHPUnit 11 executada em banco PostgreSQL dedicado (`decisao_investimento_testing`).

---

## 🚀 Como Inicializar o Projeto

### 1. Clonar e preparar o arquivo `.env`

```bash
cp .env.example .env
```

### 2. Iniciar os serviços com Docker Compose

```bash
docker compose up -d --build
```

### 3. Instalar dependências e chave da aplicação

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
```

### 4. Executar Migrations e Seeder Inicial

```bash
docker compose exec app php artisan migrate --seed
```

O seeder criará automaticamente:
- **Usuário Padrão:**
  - **E-mail:** `admin@invest.local`
  - **Senha:** `password`
- **Acesso ao Painel:** `http://localhost:8080/admin`
- Perfil inicial equilibrado (15 anos de horizonte, limite de 15% por ativo e 30% por setor).
- Metas de alocação por classe totalizando 100% (Ações BR: 35%, FIIs: 30%, Tesouro Direto: 20%, ETFs Internacionais: 15%).
- Objetivo financeiro inicial de Independência Financeira.

---

## 🧭 Roteiro de Uso do Sistema (Fluxo Mensal Completo)

1. **Ajuste de Metas e Perfil (Módulo Planejamento):**
   - Acesse **Planejamento ➔ Metas de Alocação** para ajustar seus percentuais por classe (a soma deve ser 100%).
   - Acesse **Planejamento ➔ Perfil de Investidor** para definir seu horizonte, reserva de emergência alvo e teto de concentração por ativo.
2. **Configuração da IA (Módulo Configurações):**
   - Acesse **Configurações ➔ Provedores de IA** e cadastre sua chave de API do Google Gemini.
   - Clique em **"Testar Conexão"** para validar a chave e a latência com a API oficial sem expor a credencial.
3. **Importação do Investidor10 (Módulo Carteira):**
   - Acesse **Carteira ➔ Importações de Carteira** e clique em **"Criar"**.
   - Envie o arquivo exportado do Investidor10 (`.csv` ou `.xlsx`) informando a data de referência.
   - O sistema detecta cabeçalhos, normaliza moedas e tickers, e salva o retrato imutável.
4. **Visualização do Diagnóstico Determinístico:**
   - Na tela da importação, visualize instantaneamente os cards de patrimônio, maior ativo e a tabela de desvios (gaps % e gaps financeiros em R$) calculados pelo backend, sem depender de IA.
5. **Solicitação de Análise Mensal (Módulo Análise Mensal):**
   - Acesse **Análise Mensal ➔ Nova Análise**.
   - Selecione a carteira importada, o modelo de IA e informe o valor disponível para aportar no mês, situação da reserva de emergência e dívidas.
   - A IA processa o diagnóstico e retorna uma proposta estruturada com valores por ativo, justificativas, riscos, fontes e alternativas descartadas.
6. **Decisão e Efetivação do Aporte (Módulo Decisões e Aportes):**
   - Na análise gerada, clique em **"Efetivar Aporte"** para registrar a compra das cotas ou ações no home broker.
   - Acesse **Decisões e Aportes ➔ Comparativo de Evolução** para ver a evolução patrimonial mês a mês com o efeito dos novos aportes estritamente separado do rendimento orgânico de mercado.
7. **Busca Semântica no Histórico (Módulo Análise Mensal):**
   - Acesse **Análise Mensal ➔ Busca Semântica (pgvector)** para pesquisar temas, justificativas e ideias de aportes passados (ex: *"recomendações focadas em dividendos"*, *"estratégia de reserva"*), ordenados por similaridade de cosseno via embeddings.

---

## 🧪 Execução de Testes Automatizados

A aplicação possui 31 testes automatizados cobrindo todas as regras de negócio, parsers CSV/XLSX, diagnóstico financeiro, segurança de credenciais, IA e pgvector:

```bash
docker compose exec app php artisan test
```

Os testes são executados contra o banco PostgreSQL dedicado `decisao_investimento_testing`, garantindo que os dados de desenvolvimento e produção local **nunca** sejam apagados.

---

## 🔒 Segurança & Boas Práticas

- **Criptografia em Repouso:** A coluna `api_key` na tabela `ai_provider_configurations` é criptografada com a chave `APP_KEY` do Laravel (`casts => ['api_key' => 'encrypted']`) e oculta da serialização JSON (`$hidden = ['api_key']`).
- **Arquivos Privados:** Planilhas e relatórios são salvos no storage privado do Laravel (`storage/app/private/imports/`) e nunca expostos na pasta pública do servidor web.
- **Precisão Financeira:** Todas as colunas financeiras utilizam tipos `decimal(14,2)` ou `decimal(14,4)` no PostgreSQL, prevenindo perdas de precisão por ponto flutuante.
- **Prevenção de Duplicatas:** Cada importação gera um hash SHA-256 para alertar sobre arquivos idênticos enviados em duplicidade.

---

## 🔧 Manutenção e Operação

### Visualizar Logs em Tempo Real
```bash
docker compose logs -f app
```

### Backup do Banco de Dados PostgreSQL
```bash
docker compose exec db pg_dump -U invest_user decisao_investimento > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restaurar Banco de Dados PostgreSQL
```bash
cat backup_arquivo.sql | docker compose exec -T db psql -U invest_user decisao_investimento
```
