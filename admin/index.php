<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config.php';

$error = '';

// Logout
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (hash('sha256', $_POST['password']) === ADMIN_PASSWORD_HASH) {
        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_time']   = time();
        header('Location: index.php');
        exit;
    }
    $error = 'Senha incorreta.';
}

// Expirar sessão
if (!empty($_SESSION['admin_logged'])) {
    if (time() - ($_SESSION['admin_time'] ?? 0) > SESSION_LIFETIME) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php?expired=1');
        exit;
    }
    $_SESSION['admin_time'] = time();
}

$logged = !empty($_SESSION['admin_logged']);

// Buscar contatos
$contacts  = [];
$unread    = 0;
$total     = 0;
$db_exists = file_exists(DB_PATH);

if ($logged && $db_exists) {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $filter = $_GET['filter'] ?? 'all';
        $search = trim($_GET['q'] ?? '');

        $where  = [];
        $params = [];

        if ($filter === 'new')  { $where[] = "status = 'new'"; }
        if ($filter === 'read') { $where[] = "status = 'read'"; }

        if ($search !== '') {
            $where[]          = "(name LIKE :q OR email LIKE :q OR message LIKE :q OR service LIKE :q)";
            $params[':q']     = '%' . $search . '%';
        }

        $sql = "SELECT * FROM contacts";
        if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread = (int) $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'new'")->fetchColumn();
        $total  = (int) $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    } catch (Exception $e) {
        // silencioso — tabela ainda não existe
    }
}

$page_title = $logged
    ? ($unread > 0 ? "($unread) Painel Admin — Marcos Leandro" : "Painel Admin — Marcos Leandro")
    : "Admin — Marcos Leandro";

function badge(string $status): string {
    return $status === 'new'
        ? '<span class="badge badge-new">Novo</span>'
        : '<span class="badge badge-read">Lido</span>';
}

function esc(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($page_title) ?></title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root {
    --bg:       #080e1a;
    --surface:  #0e1828;
    --surface2: #131f32;
    --border:   rgba(63,172,255,.13);
    --blue:     #3facff;
    --blue-dim: rgba(63,172,255,.12);
    --text:     #e8edf5;
    --muted:    #7a8fa8;
    --danger:   #ff5f6b;
    --green:    #2ece87;
    --radius:   12px;
    --font: 'Segoe UI', system-ui, -apple-system, sans-serif;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
    min-height: 100vh;
  }

  /* ── Login ── */
  .login-wrap {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background:
      radial-gradient(ellipse 60% 50% at 50% -10%, rgba(63,172,255,.18) 0%, transparent 70%),
      var(--bg);
  }
  .login-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 48px 40px 40px;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 32px 80px rgba(0,0,0,.55);
  }
  .login-logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin-bottom: 36px;
  }
  .login-logo svg { width: 44px; height: 44px; }
  .login-logo span {
    font-size: 18px;
    font-weight: 700;
    color: var(--text);
    letter-spacing: -.01em;
  }
  .login-logo small {
    font-size: 12px;
    color: var(--muted);
    letter-spacing: .02em;
    text-transform: uppercase;
  }
  .login-card label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .05em;
    margin-bottom: 8px;
  }
  .login-card input[type=password] {
    width: 100%;
    background: var(--surface2);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    padding: 13px 16px;
    color: var(--text);
    font-size: 15px;
    font-family: var(--font);
    outline: none;
    transition: border-color .2s;
  }
  .login-card input[type=password]:focus { border-color: var(--blue); }
  .login-card input[type=password]::placeholder { color: var(--muted); }
  .login-btn {
    width: 100%;
    margin-top: 20px;
    padding: 13px;
    background: var(--blue);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-family: var(--font);
    transition: opacity .2s, transform .1s;
  }
  .login-btn:hover  { opacity: .9; }
  .login-btn:active { transform: scale(.98); }
  .login-error {
    margin-top: 14px;
    padding: 11px 14px;
    background: rgba(255,95,107,.12);
    border: 1px solid rgba(255,95,107,.3);
    border-radius: 8px;
    color: var(--danger);
    font-size: 13px;
    text-align: center;
  }
  .login-expired {
    margin-bottom: 16px;
    padding: 11px 14px;
    background: rgba(63,172,255,.1);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--blue);
    font-size: 13px;
    text-align: center;
  }

  /* ── Shell ── */
  .shell {
    display: grid;
    grid-template-columns: 230px 1fr;
    grid-template-rows: 100vh;
  }

  /* ── Sidebar ── */
  .sidebar {
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 28px 20px;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
  }
  .sidebar-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 4px;
    margin-bottom: 36px;
  }
  .sidebar-brand svg { width: 32px; height: 32px; flex-shrink: 0; }
  .sidebar-brand-text { line-height: 1.2; }
  .sidebar-brand-text strong {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: var(--text);
  }
  .sidebar-brand-text span {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
  }
  .sidebar-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--muted);
    padding: 0 8px;
    margin-bottom: 8px;
  }
  .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    transition: background .15s, color .15s;
    margin-bottom: 2px;
  }
  .nav-link:hover, .nav-link.active {
    background: var(--blue-dim);
    color: var(--blue);
  }
  .nav-link svg { width: 16px; height: 16px; flex-shrink: 0; }
  .nav-badge {
    margin-left: auto;
    background: var(--blue);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 20px;
    min-width: 18px;
    text-align: center;
  }
  .sidebar-footer {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid var(--border);
  }
  .logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: background .15s, color .15s;
    width: 100%;
    background: none;
    border: none;
    cursor: pointer;
    font-family: var(--font);
  }
  .logout-btn:hover {
    background: rgba(255,95,107,.1);
    color: var(--danger);
  }
  .logout-btn svg { width: 16px; height: 16px; }

  /* ── Main ── */
  .main {
    overflow-y: auto;
    padding: 36px 32px;
    display: flex;
    flex-direction: column;
    gap: 28px;
  }

  /* ── Header ── */
  .page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .page-header h1 {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -.02em;
    color: var(--text);
    line-height: 1.2;
  }
  .page-header p {
    font-size: 13px;
    color: var(--muted);
    margin-top: 4px;
  }

  /* ── Stats ── */
  .stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .stat-card .label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--muted);
  }
  .stat-card .value {
    font-size: 28px;
    font-weight: 800;
    letter-spacing: -.02em;
    color: var(--text);
    line-height: 1;
  }
  .stat-card .value.blue { color: var(--blue); }
  .stat-card .value.green { color: var(--green); }

  /* ── Toolbar ── */
  .toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .search-wrap {
    flex: 1;
    min-width: 200px;
    position: relative;
  }
  .search-wrap svg {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: var(--muted);
    pointer-events: none;
  }
  .search-input {
    width: 100%;
    background: var(--surface);
    border: 1.5px solid var(--border);
    border-radius: 8px;
    padding: 9px 14px 9px 36px;
    color: var(--text);
    font-size: 13.5px;
    font-family: var(--font);
    outline: none;
    transition: border-color .2s;
  }
  .search-input:focus { border-color: var(--blue); }
  .search-input::placeholder { color: var(--muted); }
  .filter-btns { display: flex; gap: 4px; }
  .filter-btn {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--muted);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: var(--font);
    transition: all .15s;
    text-decoration: none;
  }
  .filter-btn:hover { border-color: var(--blue); color: var(--blue); }
  .filter-btn.active {
    background: var(--blue);
    border-color: var(--blue);
    color: #fff;
  }

  /* ── Table ── */
  .table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }
  table {
    width: 100%;
    border-collapse: collapse;
  }
  thead th {
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--muted);
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }
  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .12s;
    cursor: pointer;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--blue-dim); }
  tbody tr.is-new td:first-child { border-left: 3px solid var(--blue); }
  tbody td {
    padding: 14px 16px;
    font-size: 13.5px;
    color: var(--text);
    vertical-align: middle;
  }
  td.td-name { font-weight: 600; }
  td.td-email { color: var(--muted); font-size: 13px; }
  td.td-service { color: var(--muted); font-size: 12.5px; }
  td.td-date { color: var(--muted); font-size: 12px; white-space: nowrap; }
  td.td-msg {
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--muted);
    font-size: 13px;
  }

  .badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
  }
  .badge-new  { background: rgba(63,172,255,.15); color: var(--blue); }
  .badge-read { background: rgba(122,143,168,.1);  color: var(--muted); }

  .empty-state {
    padding: 64px 24px;
    text-align: center;
    color: var(--muted);
  }
  .empty-state svg {
    width: 40px;
    height: 40px;
    margin-bottom: 12px;
    opacity: .4;
  }
  .empty-state p { font-size: 14px; }

  /* ── Modal ── */
  .modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(5,10,20,.75);
    backdrop-filter: blur(4px);
    z-index: 9000;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    width: 100%;
    max-width: 560px;
    box-shadow: 0 40px 100px rgba(0,0,0,.6);
    overflow: hidden;
    animation: modalIn .2s ease-out;
  }
  @keyframes modalIn {
    from { opacity: 0; transform: translateY(12px) scale(.98); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
  }
  .modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
  }
  .modal-head h2 { font-size: 16px; font-weight: 700; color: var(--text); }
  .modal-close {
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, color .15s;
    font-size: 18px;
    line-height: 1;
    font-family: var(--font);
  }
  .modal-close:hover { background: var(--border); color: var(--text); }
  .modal-body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
  .modal-field label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 5px;
  }
  .modal-field p {
    font-size: 14px;
    color: var(--text);
    line-height: 1.6;
    word-break: break-word;
  }
  .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .modal-foot {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: var(--surface2);
  }
  .btn {
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-family: var(--font);
    transition: opacity .15s, transform .1s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
  }
  .btn:hover { opacity: .85; }
  .btn:active { transform: scale(.97); }
  .btn-primary { background: var(--blue); color: #fff; }
  .btn-ghost { background: var(--surface); border: 1.5px solid var(--border); color: var(--text); }
  .btn-muted { background: var(--surface2); border: 1.5px solid var(--border); color: var(--muted); }

  @media (max-width: 768px) {
    .shell { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
    .sidebar { height: auto; position: static; flex-direction: row; flex-wrap: wrap; padding: 16px; }
    .sidebar-brand { margin-bottom: 0; }
    .sidebar-footer { margin-top: 0; padding-top: 0; border-top: none; }
    .main { padding: 20px 16px; }
    .stats { grid-template-columns: 1fr 1fr; }
    .modal-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<?php if (!$logged): ?>
<!-- ===== LOGIN ===== -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="44" height="44" rx="12" fill="#3facff" fill-opacity=".15"/>
        <path d="M22 10C15.373 10 10 15.373 10 22s5.373 12 12 12 12-5.373 12-12S28.627 10 22 10zm0 5a4 4 0 110 8 4 4 0 010-8zm0 17a9.33 9.33 0 01-8-4.53c.03-2.65 5.33-4.1 8-4.1 2.66 0 7.97 1.45 8 4.1A9.33 9.33 0 0122 32z" fill="#3facff"/>
      </svg>
      <span>Marcos Leandro</span>
      <small>Painel Administrativo</small>
    </div>

    <?php if (isset($_GET['expired'])): ?>
      <div class="login-expired">Sessão expirada. Faça login novamente.</div>
    <?php endif; ?>

    <form method="POST">
      <label for="pw">Senha de acesso</label>
      <input type="password" id="pw" name="password" placeholder="••••••••••" autofocus autocomplete="current-password">
      <button type="submit" class="login-btn">Entrar no painel</button>
    </form>

    <?php if ($error): ?>
      <div class="login-error"><?= esc($error) ?></div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ===== DASHBOARD ===== -->
<?php
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$read_count = $total - $unread;
?>
<div class="shell">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill="#3facff" fill-opacity=".15"/>
        <path d="M16 7C11.03 7 7 11.03 7 16s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 3.75a3 3 0 110 6 3 3 0 010-6zm0 12.75a7 7 0 01-6-3.4c.02-2 4-3.1 6-3.1 1.99 0 5.98 1.1 6 3.1a7 7 0 01-6 3.4z" fill="#3facff"/>
      </svg>
      <div class="sidebar-brand-text">
        <strong>Marcos Leandro</strong>
        <span>Admin</span>
      </div>
    </div>

    <div class="sidebar-label">Contatos</div>
    <a href="?filter=all" class="nav-link <?= $filter === 'all' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Todos
      <?php if ($total > 0): ?><span class="nav-badge"><?= $total ?></span><?php endif; ?>
    </a>
    <a href="?filter=new" class="nav-link <?= $filter === 'new' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      Não lidos
      <?php if ($unread > 0): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
    </a>
    <a href="?filter=read" class="nav-link <?= $filter === 'read' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Lidos
    </a>

    <div class="sidebar-footer">
      <a href="?logout=1" class="logout-btn">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Sair
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">

    <div class="page-header">
      <div>
        <h1>Contatos recebidos</h1>
        <p>Formulários de agendamento e newsletter enviados pelo site.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats">
      <div class="stat-card">
        <div class="label">Total</div>
        <div class="value"><?= $total ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Não lidos</div>
        <div class="value blue"><?= $unread ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Lidos</div>
        <div class="value green"><?= $read_count ?></div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <form method="GET" style="display:contents">
          <?php if ($filter !== 'all'): ?>
            <input type="hidden" name="filter" value="<?= esc($filter) ?>">
          <?php endif; ?>
          <input type="text" name="q" class="search-input" placeholder="Buscar por nome, e-mail, serviço..." value="<?= esc($search) ?>" autocomplete="off">
        </form>
      </div>
      <div class="filter-btns">
        <a href="?filter=all<?= $search ? '&q='.urlencode($search) : '' ?>"  class="filter-btn <?= $filter === 'all'  ? 'active' : '' ?>">Todos</a>
        <a href="?filter=new<?= $search ? '&q='.urlencode($search) : '' ?>"  class="filter-btn <?= $filter === 'new'  ? 'active' : '' ?>">Novos</a>
        <a href="?filter=read<?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-btn <?= $filter === 'read' ? 'active' : '' ?>">Lidos</a>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <?php if (empty($contacts)): ?>
        <div class="empty-state">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          <p><?= $total === 0 ? 'Nenhum contato recebido ainda.' : 'Nenhum resultado encontrado.' ?></p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Serviço</th>
              <th>Mensagem</th>
              <th>Data</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contacts as $c): ?>
            <tr class="<?= $c['status'] === 'new' ? 'is-new' : '' ?>"
                data-id="<?= (int)$c['id'] ?>"
                data-name="<?= esc($c['name']) ?>"
                data-email="<?= esc($c['email']) ?>"
                data-service="<?= esc($c['service']) ?>"
                data-date="<?= esc($c['date']) ?>"
                data-time="<?= esc($c['time']) ?>"
                data-message="<?= esc($c['message']) ?>"
                data-source="<?= esc($c['source']) ?>"
                data-status="<?= esc($c['status']) ?>"
                data-created="<?= esc($c['created_at']) ?>"
                onclick="openModal(this)">
              <td class="td-name"><?= esc($c['name']) ?></td>
              <td class="td-email"><?= esc($c['email']) ?></td>
              <td class="td-service"><?= esc($c['service'] ?: '—') ?></td>
              <td class="td-msg"><?= esc($c['message'] ?: '—') ?></td>
              <td class="td-date"><?= esc($c['created_at']) ?></td>
              <td><?= badge($c['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModalOutside(event)">
  <div class="modal" id="modal">
    <div class="modal-head">
      <h2 id="modalTitle">Detalhes do contato</h2>
      <button class="modal-close" onclick="closeModal()">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div class="modal-row">
        <div class="modal-field">
          <label>Nome</label>
          <p id="mName"></p>
        </div>
        <div class="modal-field">
          <label>E-mail</label>
          <p id="mEmail"></p>
        </div>
      </div>
      <div class="modal-row">
        <div class="modal-field">
          <label>Serviço</label>
          <p id="mService"></p>
        </div>
        <div class="modal-field">
          <label>Data / Hora preferida</label>
          <p id="mDatetime"></p>
        </div>
      </div>
      <div class="modal-field">
        <label>Mensagem</label>
        <p id="mMessage"></p>
      </div>
      <div class="modal-row">
        <div class="modal-field">
          <label>Origem</label>
          <p id="mSource"></p>
        </div>
        <div class="modal-field">
          <label>Recebido em</label>
          <p id="mCreated"></p>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <div id="mStatus"></div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost" onclick="closeModal()">Fechar</button>
        <button class="btn btn-primary" id="btnToggleRead" onclick="toggleRead()">Marcar como lido</button>
      </div>
    </div>
  </div>
</div>

<script>
var currentId = null;
var currentStatus = null;

function openModal(row) {
  currentId     = row.dataset.id;
  currentStatus = row.dataset.status;

  document.getElementById('mName').textContent    = row.dataset.name    || '—';
  document.getElementById('mEmail').textContent   = row.dataset.email   || '—';
  document.getElementById('mService').textContent = row.dataset.service || '—';
  document.getElementById('mMessage').textContent = row.dataset.message || '—';
  document.getElementById('mSource').textContent  = row.dataset.source === 'newsletter' ? 'Newsletter' : 'Agendamento';
  document.getElementById('mCreated').textContent = row.dataset.created || '—';

  var dt = [row.dataset.date, row.dataset.time].filter(Boolean).join(' às ');
  document.getElementById('mDatetime').textContent = dt || '—';

  var statusEl = document.getElementById('mStatus');
  statusEl.innerHTML = currentStatus === 'new'
    ? '<span class="badge badge-new">Novo</span>'
    : '<span class="badge badge-read">Lido</span>';

  var btn = document.getElementById('btnToggleRead');
  btn.textContent = currentStatus === 'new' ? 'Marcar como lido' : 'Marcar como não lido';
  btn.className   = currentStatus === 'new' ? 'btn btn-primary' : 'btn btn-muted';

  document.getElementById('modalOverlay').classList.add('open');

  if (currentStatus === 'new') {
    markRead(currentId, 'read', false);
  }
}

function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  currentId = null;
  currentStatus = null;
}

function closeModalOutside(e) {
  if (e.target === document.getElementById('modalOverlay')) closeModal();
}

function toggleRead() {
  if (!currentId) return;
  var newStatus = currentStatus === 'new' ? 'read' : 'new';
  markRead(currentId, newStatus, true);
}

function markRead(id, status, reloadAfter) {
  var fd = new FormData();
  fd.append('id', id);
  fd.append('status', status);

  fetch('../api/mark-read.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ok && reloadAfter) {
        window.location.reload();
      } else if (data.ok) {
        currentStatus = status;
        var row = document.querySelector('tr[data-id="' + id + '"]');
        if (row) {
          row.dataset.status = status;
          if (status === 'new') row.classList.add('is-new');
          else row.classList.remove('is-new');
          var badgeCell = row.querySelectorAll('td')[5];
          if (badgeCell) {
            badgeCell.innerHTML = status === 'new'
              ? '<span class="badge badge-new">Novo</span>'
              : '<span class="badge badge-read">Lido</span>';
          }
        }
      }
    })
    .catch(function() {});
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});
</script>
<?php endif; ?>

</body>
</html>
