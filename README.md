# SEI Dashboard

Dashboard web para o [Sistema Eletrônico de Informações (SEI)](https://www.gov.br/economia/pt-br/assuntos/processo-eletronico-nacional/conteudo/sistema-eletronico-de-informacoes-sei) consumindo a API REST do módulo [mod-wssei v2](https://github.com/pengovbr/mod-wssei).

**[→ Demo ao vivo](https://sei-dashboard-mock.vercel.app)**

---

## Funcionalidades

- **KPIs em tempo real** — processos abertos, concluídos, retornados, documentos, tipos de processo e unidades
- **Tabela de processos** — número, tipo, especificação, data e situação com highlight de busca
- **Filtros interativos** — busca por texto, tipo de processo, situação (Aberto/Concl./Ret.) e período
- **Gráfico de distribuição** — barras por tipo de processo, reativo aos filtros
- **Gráfico de evolução temporal** — processos por mês com toggle de séries
- **Lista de documentos** — últimos recebidos/gerados com status de assinatura
- **Proxy serverless** — autenticação e chamadas ao SEI ficam no servidor (sem expor credenciais no frontend)

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | HTML + CSS + JS vanilla |
| Gráficos | Chart.js 4.4 (CDN) |
| Backend | Vercel Serverless Function (Node.js) |
| API | mod-wssei v2 REST |
| Deploy | Vercel (static + functions) |

## Estrutura

```
sei-dashboard-mock/
├── api/
│   └── sei.js          # Proxy serverless — autentica e repassa chamadas ao SEI
├── index.html          # Dashboard (frontend completo em arquivo único)
├── vercel.json         # Configuração de roteamento do Vercel
├── .env.example        # Variáveis de ambiente necessárias
└── README.md
```

## Deploy

### 1. Fork / clone

```bash
git clone https://github.com/benmatos/sei-dashboard-mock
cd sei-dashboard-mock
```

### 2. Configure as variáveis no Vercel

Acesse **Vercel Dashboard → seu projeto → Settings → Environment Variables** e adicione:

| Variável | Descrição | Exemplo |
|---|---|---|
| `SEI_BASE_URL` | URL base da API mod-wssei v2 | `https://sei.orgao.gov.br/sei/modulos/wssei/controlador_ws.php/api/v2` |
| `SEI_USUARIO` | Login do usuário SEI | `joao.silva` |
| `SEI_SENHA` | Senha do usuário SEI | `***` |
| `SEI_ORGAO` | Código do órgão | `0` |

> **Como obter `SEI_BASE_URL` e `SEI_ORGAO`**
> Na barra lateral do SEI (menu esquerdo, rodapé) há um QR Code com o link do aplicativo móvel. Ele contém a URL e o código do órgão no formato:
> ```
> https://sei.orgao.gov.br/sei/modulos/wssei/controlador_ws.php/api/v2;siglaorgao:ORGAO;orgao:0;...
> ```
> A URL antes do `;` é o `SEI_BASE_URL`. O valor após `orgao:` é o `SEI_ORGAO`.

### 3. Redeploy

Após salvar as variáveis, clique em **Redeploy** no painel do Vercel. O dashboard estará consumindo a API real.

## Desenvolvimento local

```bash
# Instale a Vercel CLI
npm i -g vercel

# Crie o .env local
cp .env.example .env
# edite .env com suas credenciais

# Rode localmente (inclui as serverless functions)
vercel dev
```

Acesse `http://localhost:3000`.

## Parâmetro de unidade

A unidade padrão pode ser alterada via query string, sem necessidade de redeploy:

```
https://seu-dashboard.vercel.app/?unidade=110000999
```

## Como funciona o proxy

O arquivo `api/sei.js` é uma Vercel Serverless Function que:

1. Recebe chamadas do frontend em `GET /api/sei?_path=/processos&...`
2. Autentica no SEI via `POST /autenticar` com as credenciais das variáveis de ambiente
3. Cacheia o token JWT em memória por 50 minutos (reutiliza enquanto a instância estiver quente)
4. Repassa a requisição ao SEI com `Authorization: Bearer <token>`
5. Devolve a resposta ao frontend

As credenciais nunca chegam ao navegador.

## Endpoints consumidos

| Método | Path mod-wssei v2 | Uso |
|---|---|---|
| `POST` | `/autenticar` | Obtenção do token JWT |
| `GET` | `/processos` | Listagem por situação (A/C/R) |
| `GET` | `/listar-documentos` | Últimos documentos da unidade |
| `GET` | `/pesquisar-tipos-processo` | Total de tipos cadastrados |
| `GET` | `/listar-unidades` | Total de unidades do órgão |

## Compatibilidade

| Versão SEI | mod-wssei | Compatível |
|---|---|---|
| 4.0.x | 2.0.x | ✓ |
| 4.1.1 | 2.2.0 | ✓ |
| 5.0.x | 3.0.1+ | ✓ |

## Licença

MIT
