<?php
/**
 * SEI Dashboard — modo MOCK
 * Usa sei_mock.json no lugar das chamadas reais ao mod-wssei.
 * Troque MOCK_MODE para false e configure as constantes abaixo para produção.
 */

define('MOCK_MODE',    true);
define('MOCK_FILE',    __DIR__ . '/sei_mock.json');

define('SEI_BASE_URL', 'https://sei.exemplo.gov.br/sei');
define('SEI_TOKEN',    'Bearer SEU_JWT_TOKEN_AQUI');
define('SEI_ORGAO',    '0');
define('SEI_UNIDADE',  '110000592');
define('API_TIMEOUT',  10);

// ─── MOCK LOADER ──────────────────────────────────────────────────────────────
function load_mock(): array {
    static $data = null;
    if ($data === null) {
        $raw  = file_get_contents(MOCK_FILE);
        $data = json_decode($raw, true) ?? [];
    }
    return $data;
}

// ─── CLIENTE (real ou mock) ───────────────────────────────────────────────────
function sei_get(string $endpoint, array $params = []): array {
    if (MOCK_MODE) {
        $mock = load_mock();
        // Mapeia o endpoint para a chave do JSON de mock
        if (str_contains($endpoint, 'processos')) {
            $sit = $params['situacao'] ?? 'A';
            return match($sit) {
                'C' => $mock['processos_concluidos'],
                'R' => $mock['processos_retornados'],
                default => $mock['processos_abertos'],
            };
        }
        if (str_contains($endpoint, 'documentos'))   return $mock['documentos'];
        if (str_contains($endpoint, 'tipos-processo')) return $mock['tipos_processo'];
        if (str_contains($endpoint, 'unidades'))     return $mock['unidades'];
        return [];
    }

    // ── Chamada real ──────────────────────────────────────────────────────────
    $url = SEI_BASE_URL . '/v1/' . ltrim($endpoint, '/');
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => API_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Authorization: ' . SEI_TOKEN,
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $code >= 400) return ['__error' => $err ?: "HTTP $code", '__code' => $code];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['__raw' => $raw];
}

// ─── COLETA DE DADOS ──────────────────────────────────────────────────────────
$processos_abertos = sei_get('processos', ['id_unidade' => SEI_UNIDADE, 'situacao' => 'A']);
$processos_concl   = sei_get('processos', ['id_unidade' => SEI_UNIDADE, 'situacao' => 'C']);
$processos_ret     = sei_get('processos', ['id_unidade' => SEI_UNIDADE, 'situacao' => 'R']);
$documentos        = sei_get('documentos', ['id_unidade' => SEI_UNIDADE]);
$tipos_proc        = sei_get('tipos-processo');
$unidades          = sei_get('unidades', ['id_orgao' => SEI_ORGAO]);

function count_result(array $r): int {
    if (isset($r['__error']))        return 0;
    if (isset($r['total_registros'])) return (int) $r['total_registros'];
    if (isset($r['registros']) && is_array($r['registros'])) return count($r['registros']);
    return is_array($r) ? count($r) : 0;
}

function get_registros(array $r): array {
    if (isset($r['registros']) && is_array($r['registros'])) return $r['registros'];
    if (is_array($r) && !isset($r['__error']) && !isset($r['total_registros'])) return array_values($r);
    return [];
}

$total_abertos    = count_result($processos_abertos);
$total_concluidos = count_result($processos_concl);
$total_retornados = count_result($processos_ret);
$total_tipos      = count_result($tipos_proc);
$total_unidades   = count_result($unidades);

$lista_processos = get_registros($processos_abertos);
$lista_docs      = get_registros($documentos);

$por_tipo = [];
foreach ($lista_processos as $p) {
    $tipo = $p['tipo_processo']['nome'] ?? ($p['especificacao'] ?? 'Sem tipo');
    $por_tipo[$tipo] = ($por_tipo[$tipo] ?? 0) + 1;
}
arsort($por_tipo);
$top_tipos = array_slice($por_tipo, 0, 6, true);

date_default_timezone_set('America/Sao_Paulo');
$agora = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SEI · Dashboard <?= MOCK_MODE ? '[MOCK]' : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:ital,wght@0,300;0,400;0,600;1,300&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg:         #0d1117;
    --surface:    #161b22;
    --surface2:   #1c2330;
    --border:     #2a3444;
    --border2:    #3d4f66;
    --text:       #cdd9e5;
    --text-muted: #768390;
    --text-dim:   #4a5568;
    --accent:     #2da44e;
    --accent2:    #388bfd;
    --warn:       #d29922;
    --danger:     #f85149;
    --mock:       #8b61c5;
    --mono:       'IBM Plex Mono', monospace;
    --sans:       'IBM Plex Sans', sans-serif;
    --r:          6px;
    --sidebar-w:  220px;
}

html, body { height: 100%; background: var(--bg); color: var(--text); font-family: var(--sans); font-size: 14px; }

.shell { display: flex; min-height: 100vh; }

.sidebar {
    width: var(--sidebar-w); background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    position: sticky; top: 0; height: 100vh; flex-shrink: 0;
}
.sidebar-logo {
    padding: 20px 18px 16px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
}
.sidebar-logo .emblem {
    width: 32px; height: 32px; background: var(--accent); border-radius: 4px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--mono); font-weight: 600; font-size: 13px; color: #fff; flex-shrink: 0;
}
.sidebar-logo .brand { line-height: 1.2; }
.sidebar-logo .brand strong { font-size: 15px; font-weight: 600; letter-spacing: .5px; }
.sidebar-logo .brand span { font-size: 11px; color: var(--text-muted); font-weight: 300; }
.sidebar-nav { padding: 12px 8px; flex: 1; }
.nav-label { font-size: 10px; font-weight: 600; letter-spacing: 1.2px; color: var(--text-dim); text-transform: uppercase; padding: 8px 10px 4px; }
.nav-item {
    display: flex; align-items: center; gap: 9px; padding: 7px 10px; border-radius: var(--r);
    color: var(--text-muted); font-size: 13px; text-decoration: none;
    transition: background .15s, color .15s;
}
.nav-item:hover, .nav-item.active { background: var(--surface2); color: var(--text); }
.nav-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--border2); flex-shrink: 0; }
.nav-item.active .nav-dot { background: var(--accent); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-dim); font-family: var(--mono); }

.main { flex: 1; display: flex; flex-direction: column; min-width: 0; }

.topbar {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 0 28px; height: 52px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.topbar-title { font-size: 13px; font-weight: 600; letter-spacing: .3px; }
.topbar-meta { display: flex; align-items: center; gap: 16px; font-size: 11px; color: var(--text-muted); font-family: var(--mono); }
.status-dot {
    width: 7px; height: 7px; border-radius: 50%; display: inline-block; margin-right: 5px;
    animation: pulse 2s infinite;
}
.status-dot.live  { background: var(--accent);  box-shadow: 0 0 6px var(--accent); }
.status-dot.mock  { background: var(--mock);     box-shadow: 0 0 6px var(--mock); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.mock-banner {
    background: rgba(139,97,197,.1);
    border-bottom: 1px solid rgba(139,97,197,.3);
    padding: 7px 28px;
    font-size: 11px; font-family: var(--mono);
    color: var(--mock);
    display: flex; align-items: center; gap: 8px;
}
.mock-banner strong { font-weight: 600; }

.content { padding: 24px 28px; flex: 1; }

.section-title {
    font-size: 11px; font-weight: 600; letter-spacing: 1.4px; text-transform: uppercase;
    color: var(--text-dim); margin-bottom: 14px; margin-top: 28px;
    display: flex; align-items: center; gap: 8px;
}
.section-title::after { content:''; flex:1; height:1px; background:var(--border); }
.section-title:first-child { margin-top: 0; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
.kpi {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--r);
    padding: 16px 18px; position: relative; overflow: hidden; transition: border-color .2s;
}
.kpi:hover { border-color: var(--border2); }
.kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--kpi-color, var(--accent)); }
.kpi-label { font-size: 10px; font-weight: 600; letter-spacing: .8px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
.kpi-value { font-family: var(--mono); font-size: 30px; font-weight: 600; line-height: 1; color: var(--kpi-color, var(--text)); }
.kpi-sub { font-size: 10px; color: var(--text-dim); margin-top: 5px; font-family: var(--mono); }

.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width:900px){.two-col{grid-template-columns:1fr}}

.panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); overflow: hidden; }
.panel-header {
    padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: 12px; font-weight: 600;
    display: flex; align-items: center; justify-content: space-between; letter-spacing: .2px;
}
.panel-header .badge {
    font-family: var(--mono); font-size: 10px; background: var(--surface2);
    border: 1px solid var(--border); border-radius: 3px; padding: 2px 7px; color: var(--text-muted);
}

.proc-table { width: 100%; border-collapse: collapse; }
.proc-table th {
    font-size: 10px; font-weight: 600; letter-spacing: .8px; text-transform: uppercase;
    color: var(--text-dim); text-align: left; padding: 8px 14px;
    border-bottom: 1px solid var(--border); background: var(--surface2);
}
.proc-table td { padding: 9px 14px; border-bottom: 1px solid var(--border); font-size: 12px; vertical-align: top; }
.proc-table tr:last-child td { border-bottom: none; }
.proc-table tr:hover td { background: var(--surface2); }
.proc-num  { font-family: var(--mono); font-size: 11px; color: var(--accent2); white-space: nowrap; }
.proc-tipo { font-size: 11px; color: var(--text); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.proc-spec { font-size: 10px; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 2px; }
.proc-data { font-family: var(--mono); font-size: 10px; color: var(--text-dim); white-space: nowrap; }

.tag { display:inline-flex; align-items:center; font-size:10px; font-family:var(--mono); padding:2px 6px; border-radius:3px; font-weight:600; letter-spacing:.3px; }
.tag-green  { background:rgba(45,164,78,.15);  color:var(--accent); }
.tag-blue   { background:rgba(56,139,253,.15); color:var(--accent2); }
.tag-yellow { background:rgba(210,153,34,.15); color:var(--warn); }
.tag-red    { background:rgba(248,81,73,.15);  color:var(--danger); }

.bar-list  { padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; }
.bar-row   { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.bar-name  { font-size: 11px; color: var(--text); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bar-count { font-family: var(--mono); font-size: 11px; color: var(--text-muted); width: 28px; text-align: right; flex-shrink: 0; }
.bar-track { height: 4px; background: var(--surface2); border-radius: 2px; overflow: hidden; }
.bar-fill  { height: 100%; border-radius: 2px; background: var(--accent); }

.doc-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid var(--border); }
.doc-item:last-child { border-bottom: none; }
.doc-item:hover { background: var(--surface2); }
.doc-icon { width:30px; height:30px; border-radius:4px; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:10px; font-family:var(--mono); color:var(--text-dim); flex-shrink:0; }
.doc-info { flex: 1; min-width: 0; }
.doc-name { font-size: 12px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.doc-meta { font-size: 10px; color: var(--text-muted); font-family: var(--mono); margin-top: 2px; }

.empty { padding: 32px 16px; text-align: center; color: var(--text-dim); font-size: 12px; }

@media(max-width:700px){ .sidebar{display:none} .content{padding:16px} .kpi-grid{grid-template-columns:repeat(2,1fr)} }
</style>
</head>
<body>
<div class="shell">

    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="emblem">SEI</div>
            <div class="brand">
                <strong>Dashboard</strong><br>
                <span>Módulo de Gestão</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-label">Visão Geral</div>
            <a class="nav-item active" href="#"><span class="nav-dot"></span> Início</a>
            <a class="nav-item" href="#"><span class="nav-dot"></span> Processos</a>
            <a class="nav-item" href="#"><span class="nav-dot"></span> Documentos</a>
            <div class="nav-label" style="margin-top:8px">Administração</div>
            <a class="nav-item" href="#"><span class="nav-dot"></span> Unidades</a>
            <a class="nav-item" href="#"><span class="nav-dot"></span> Usuários</a>
            <a class="nav-item" href="#"><span class="nav-dot"></span> Configurações</a>
        </nav>
        <div class="sidebar-footer">
            <div>unidade: <strong><?= htmlspecialchars(SEI_UNIDADE) ?></strong></div>
            <div style="margin-top:4px">modo: <strong style="color:<?= MOCK_MODE ? 'var(--mock)' : 'var(--accent)' ?>"><?= MOCK_MODE ? 'mock' : 'live' ?></strong></div>
        </div>
    </aside>

    <div class="main">

        <header class="topbar">
            <span class="topbar-title">Painel de Controle</span>
            <div class="topbar-meta">
                <span>
                    <span class="status-dot <?= MOCK_MODE ? 'mock' : 'live' ?>"></span>
                    <?= MOCK_MODE ? 'Mock' : 'Online' ?>
                </span>
                <span><?= $agora ?></span>
                <span style="color:var(--text-dim)">mod-wssei v1</span>
            </div>
        </header>

        <?php if (MOCK_MODE): ?>
        <div class="mock-banner">
            <strong>◈ MOCK MODE</strong> — dados fictícios de sei_mock.json · Para usar a API real, defina
            <code style="background:rgba(255,255,255,.06);padding:1px 5px;border-radius:3px">MOCK_MODE = false</code>
        </div>
        <?php endif; ?>

        <div class="content">

            <div class="section-title">Indicadores da Unidade</div>
            <div class="kpi-grid">
                <div class="kpi" style="--kpi-color:var(--accent)">
                    <div class="kpi-label">Em Aberto</div>
                    <div class="kpi-value"><?= $total_abertos ?></div>
                    <div class="kpi-sub">processos ativos</div>
                </div>
                <div class="kpi" style="--kpi-color:var(--text-muted)">
                    <div class="kpi-label">Concluídos</div>
                    <div class="kpi-value"><?= $total_concluidos ?></div>
                    <div class="kpi-sub">finalizados</div>
                </div>
                <div class="kpi" style="--kpi-color:var(--warn)">
                    <div class="kpi-label">Retornados</div>
                    <div class="kpi-value"><?= $total_retornados ?></div>
                    <div class="kpi-sub">aguardando ação</div>
                </div>
                <div class="kpi" style="--kpi-color:var(--accent2)">
                    <div class="kpi-label">Documentos</div>
                    <div class="kpi-value"><?= count($lista_docs) ?></div>
                    <div class="kpi-sub">últimos recebidos</div>
                </div>
                <div class="kpi" style="--kpi-color:var(--accent2)">
                    <div class="kpi-label">Tipos de Proc.</div>
                    <div class="kpi-value"><?= $total_tipos ?></div>
                    <div class="kpi-sub">catalogados</div>
                </div>
                <div class="kpi" style="--kpi-color:var(--accent)">
                    <div class="kpi-label">Unidades</div>
                    <div class="kpi-value"><?= $total_unidades ?></div>
                    <div class="kpi-sub">no órgão</div>
                </div>
            </div>

            <div class="section-title">Processos em Aberto</div>
            <div class="two-col">

                <div class="panel">
                    <div class="panel-header">
                        Recentes
                        <span class="badge"><?= count($lista_processos) ?></span>
                    </div>
                    <?php if (empty($lista_processos)): ?>
                        <div class="empty">Nenhum processo encontrado.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto">
                    <table class="proc-table">
                        <thead><tr><th>Número</th><th>Tipo / Especificação</th><th>Data</th><th>Sit.</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($lista_processos, 0, 12) as $p):
                            $num  = $p['numero'] ?? ($p['protocolo_formatado'] ?? '—');
                            $tipo = $p['tipo_processo']['nome'] ?? '—';
                            $spec = $p['especificacao'] ?? '';
                            $data = substr($p['data_autuacao'] ?? ($p['data_geracao'] ?? ''), 0, 10);
                            $sit  = $p['situacao'] ?? 'A';
                            $tag  = match($sit) {
                                'C'   => ['tag-blue',   'Concl.'],
                                'R'   => ['tag-yellow', 'Ret.'],
                                default => ['tag-green', 'Aberto'],
                            };
                        ?>
                        <tr>
                            <td><span class="proc-num"><?= htmlspecialchars($num) ?></span></td>
                            <td>
                                <div class="proc-tipo"><?= htmlspecialchars($tipo) ?></div>
                                <?php if ($spec): ?><div class="proc-spec"><?= htmlspecialchars($spec) ?></div><?php endif; ?>
                            </td>
                            <td><span class="proc-data"><?= htmlspecialchars($data) ?></span></td>
                            <td><span class="tag <?= $tag[0] ?>"><?= $tag[1] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        Distribuição por Tipo
                        <span class="badge"><?= count($top_tipos) ?> tipos</span>
                    </div>
                    <?php if (empty($top_tipos)): ?>
                        <div class="empty">Sem dados suficientes.</div>
                    <?php else: ?>
                    <div class="bar-list">
                        <?php $max = max($top_tipos); foreach ($top_tipos as $nome => $qtd): $pct = $max > 0 ? round(($qtd / $max) * 100) : 0; ?>
                        <div>
                            <div class="bar-row">
                                <span class="bar-name"><?= htmlspecialchars($nome) ?></span>
                                <span class="bar-count"><?= $qtd ?></span>
                            </div>
                            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-title">Documentos Recentes</div>
            <div class="panel">
                <div class="panel-header">
                    Últimos recebidos / gerados
                    <span class="badge"><?= count($lista_docs) ?></span>
                </div>
                <?php if (empty($lista_docs)): ?>
                    <div class="empty">Nenhum documento encontrado.</div>
                <?php else: foreach ($lista_docs as $d):
                    $dnum  = $d['numero'] ?? ($d['protocolo_formatado'] ?? '—');
                    $dtipo = $d['tipo_documento']['nome'] ?? ($d['tipo']['nome'] ?? 'DOC');
                    $ddesc = $d['descricao'] ?? ($d['nome'] ?? '');
                    $ddata = substr($d['data_geracao'] ?? '', 0, 10);
                    $dext  = strtoupper(substr($dtipo, 0, 3));
                    $assin = $d['assinado'] ?? false;
                ?>
                <div class="doc-item">
                    <div class="doc-icon"><?= htmlspecialchars($dext) ?></div>
                    <div class="doc-info">
                        <div class="doc-name"><?= htmlspecialchars($ddesc ?: $dtipo) ?></div>
                        <div class="doc-meta"><?= htmlspecialchars($dnum) ?> · <?= htmlspecialchars($dtipo) ?> · <?= htmlspecialchars($ddata) ?></div>
                    </div>
                    <span class="tag <?= $assin ? 'tag-green' : 'tag-yellow' ?>"><?= $assin ? 'Assinado' : 'Pendente' ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <div style="margin-top:32px;padding-top:16px;border-top:1px solid var(--border);font-size:11px;color:var(--text-dim);font-family:var(--mono);display:flex;justify-content:space-between">
                <span>SEI Dashboard · mod-wssei REST v1 <?= MOCK_MODE ? '· <span style="color:var(--mock)">MOCK</span>' : '' ?></span>
                <span>Gerado em <?= $agora ?></span>
            </div>

        </div>
    </div>
</div>
</body>
</html>
