/**
 * api/sei.js — Proxy mod-wssei v2
 *
 * Vercel serverless function: autentica no SEI e repassa chamadas GET/POST.
 * Variáveis de ambiente obrigatórias (Vercel Dashboard → Settings → Environment Variables):
 *
 *   SEI_BASE_URL  → ex: https://sei.exemplo.gov.br/sei/modulos/wssei/controlador_ws.php/api/v2
 *   SEI_USUARIO   → login do usuário SEI
 *   SEI_SENHA     → senha do usuário SEI
 *   SEI_ORGAO     → código do órgão (geralmente "0")
 *
 * Uso pelo frontend:
 *   GET /api/sei?_path=/processos&id_unidade=110000592&situacao=A
 *   GET /api/sei?_path=/unidades
 *   GET /api/sei?_path=/versao
 */

const SEI_BASE = process.env.SEI_BASE_URL?.replace(/\/$/, '');
const SEI_USER = process.env.SEI_USUARIO;
const SEI_PASS = process.env.SEI_SENHA;
const SEI_ORG  = process.env.SEI_ORGAO ?? '0';

// Cache de token em memória (warm instance)
let _cache = { token: null, ts: 0 };
const TOKEN_TTL_MS = 50 * 60 * 1000; // 50 min (token expira em ~60)

async function getToken() {
  if (_cache.token && Date.now() - _cache.ts < TOKEN_TTL_MS) {
    return _cache.token;
  }

  const url = `${SEI_BASE}/autenticar`;
  const res  = await fetch(url, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({
      usuario: SEI_USER,
      senha:   SEI_PASS,
      orgao:   SEI_ORG,
    }),
  });

  if (!res.ok) {
    const txt = await res.text();
    throw new Error(`Autenticação falhou (${res.status}): ${txt}`);
  }

  const data = await res.json();

  // mod-wssei v2 retorna { token: "..." } ou { data: { token: "..." } }
  const token = data?.token ?? data?.data?.token;
  if (!token) throw new Error('Token não encontrado na resposta: ' + JSON.stringify(data));

  _cache = { token, ts: Date.now() };
  return token;
}

export default async function handler(req, res) {
  // CORS — permite que o frontend (mesmo domínio Vercel) chame a função
  res.setHeader('Access-Control-Allow-Origin',  '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type,Authorization');
  if (req.method === 'OPTIONS') return res.status(204).end();

  // Validação de configuração
  if (!SEI_BASE || !SEI_USER || !SEI_PASS) {
    return res.status(503).json({
      error: 'not_configured',
      message: 'Variáveis SEI_BASE_URL, SEI_USUARIO e SEI_SENHA não configuradas.',
      hint:    'Vá em Vercel Dashboard → seu projeto → Settings → Environment Variables.',
    });
  }

  // Extrai _path e demais query params
  const { _path, ...params } = req.query;
  if (!_path) {
    return res.status(400).json({ error: 'missing_path', message: 'Parâmetro _path obrigatório.' });
  }

  try {
    const token = await getToken();
    const qs    = new URLSearchParams(params).toString();
    const url   = `${SEI_BASE}${_path.startsWith('/') ? '' : '/'}${_path}${qs ? '?' + qs : ''}`;

    const upstream = await fetch(url, {
      method:  req.method === 'POST' ? 'POST' : 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept':        'application/json',
        'Content-Type':  'application/json',
      },
      ...(req.method === 'POST' && req.body
        ? { body: JSON.stringify(req.body) }
        : {}),
    });

    const contentType = upstream.headers.get('content-type') ?? '';
    const raw  = await upstream.text();
    const body = contentType.includes('application/json') ? JSON.parse(raw) : { raw };

    // Repassa o status code do SEI (400, 404, etc.)
    return res.status(upstream.status).json(body);

  } catch (err) {
    console.error('[SEI proxy]', err);
    return res.status(502).json({
      error:   'proxy_error',
      message: err.message,
    });
  }
}
