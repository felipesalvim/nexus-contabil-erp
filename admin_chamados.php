<?php
session_start();

// --- 1. AUTENTICAÇÃO: bloqueia acesso sem login de admin ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

require 'db.php'; // Conexão sem credenciais hardcoded

// --- 2. GERAÇÃO / VALIDAÇÃO DO TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem_alerta = "";

// --- 3. PROCESSAR MUDANÇA DE STATUS (com validação CSRF e whitelist) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['atualizar_status'])) {

    // Valida token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("Requisição inválida (CSRF).");
    }

    $chamado_id = intval($_POST['chamado_id']);

    // Whitelist de status permitidos — nunca use valor bruto do usuário
    $statusPermitidos = ['Aberto', 'Em Andamento', 'Aguardando Cliente', 'Resolvido'];
    $novo_status = in_array($_POST['novo_status'], $statusPermitidos) ? $_POST['novo_status'] : 'Aberto';

    try {
        $stmt = $pdo->prepare(
            "UPDATE chamados_portal
             SET status = ?, data_ultima_atualizacao = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->execute([$novo_status, $chamado_id]);
        $mensagem_alerta = "<div class='alert sucesso'>Status do Chamado #" . str_pad($chamado_id, 4, "0", STR_PAD_LEFT) . " atualizado para '$novo_status'!</div>";
    } catch (Exception $e) {
        error_log("Erro ao atualizar status: " . $e->getMessage());
        $mensagem_alerta = "<div class='alert erro'>Erro ao atualizar status.</div>";
    }
}

// --- 4. BUSCAR TODOS OS CHAMADOS ---
$stmtLista = $pdo->query("
    SELECT c.*, t.razao_social
    FROM chamados_portal c
    JOIN tomadores_empresas t ON c.cnpj_cliente = t.cnpj
    ORDER BY c.data_ultima_atualizacao DESC
");
$chamados = $stmtLista->fetchAll();

function getStatusBadge($status) {
    $map = [
        'Aberto'            => '<span class="badge badge-aberto">Aberto</span>',
        'Em Andamento'      => '<span class="badge badge-andamento">Em Andamento</span>',
        'Aguardando Cliente'=> '<span class="badge badge-aberto" style="background:#fef3c7;color:#92400e;">Aguardando Cliente</span>',
        'Resolvido'         => '<span class="badge badge-concluido">Resolvido</span>',
    ];
    return $map[$status] ?? '<span class="badge">' . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - Chamados | Nexus Contábil</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --verde: #0f172a; --verde-claro: #1e293b; --dourado: #c8973a; --bg-fundo: #f1f5f9; --borda: #e2e8f0; --texto-escuro: #0f172a; --texto-suave: #64748b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
        body { background-color: var(--bg-fundo); color: var(--texto-escuro); display: flex; min-height: 100vh; }
        a { text-decoration: none; }
        .sidebar { width: 260px; background-color: var(--verde); color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; }
        .brand { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; }
        .brand h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; color: var(--dourado); }
        .nav-menu { flex-grow: 1; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 0.8rem 1rem; border-radius: 8px; font-weight: 500; transition: 0.3s; display: flex; align-items: center; gap: 0.8rem; }
        .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--dourado); }
        .main-content { flex-grow: 1; margin-left: 260px; padding: 2rem 3rem; }
        .top-header { margin-bottom: 2rem; border-bottom: 2px solid var(--borda); padding-bottom: 1.5rem; }
        .welcome-text h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--verde); }
        .table-container { background: white; border-radius: 16px; border: 1px solid var(--borda); overflow: hidden; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { background: #f8fafc; padding: 1rem 1.5rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--texto-suave); font-weight: 700; border-bottom: 1px solid var(--borda); }
        .admin-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--borda); font-size: 0.9rem; vertical-align: middle; }
        .admin-table tr:hover { background-color: #f8fafc; }
        .status-form { display: flex; align-items: center; gap: 0.5rem; }
        .status-select { padding: 0.4rem; border-radius: 6px; border: 1px solid var(--borda); font-size: 0.85rem; }
        .btn-salvar { background: var(--verde-claro); color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; font-size: 0.8rem; cursor: pointer; }
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-aberto { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-andamento { background: #e0f2fe; color: #1d4ed8; border: 1px solid #bae6fd; }
        .badge-concluido { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; }
        .alert.sucesso { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .alert.erro { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="brand">
        <h2>Nexus Contábil</h2>
        <span style="font-size:.7rem;color:#94a3b8;letter-spacing:1px;text-transform:uppercase;">Gestão de Back-Office</span>
    </div>
    <nav class="nav-menu">
        <a href="#" class="nav-link active"><i>🎧</i> <span>Gestão de Chamados</span></a>
        <a href="admin_upload.php" class="nav-link"><i>📁</i> <span>Enviar Documentos</span></a>
    </nav>
    <div style="padding:1.5rem;border-top:1px solid rgba(255,255,255,0.1);">
        <a href="admin_logout.php" style="display:block;text-align:center;color:white;background:rgba(255,255,255,0.1);padding:.8rem;border-radius:8px;font-weight:600;">Sair do Admin</a>
    </div>
</aside>

<main class="main-content">
    <header class="top-header">
        <div class="welcome-text">
            <h1>Visão Geral do Help Desk</h1>
            <p style="color:var(--texto-suave);">Gerencie, responda e altere o status das solicitações dos clientes.</p>
        </div>
    </header>

    <?= $mensagem_alerta ?>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Protocolo</th>
                    <th>Cliente (Empresa)</th>
                    <th>Assunto / Depto</th>
                    <th>Status Atual</th>
                    <th>Alterar Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chamados)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:2rem;">Nenhum chamado aberto.</td></tr>
                <?php else: ?>
                    <?php foreach ($chamados as $ticket): ?>
                    <tr>
                        <td><strong>#<?= str_pad($ticket['id'], 4, "0", STR_PAD_LEFT) ?></strong><br>
                            <span style="font-size:.75rem;color:#64748b;"><?= date('d/m H:i', strtotime($ticket['data_ultima_atualizacao'])) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($ticket['razao_social']) ?></strong><br>
                            <span style="font-size:.75rem;color:#64748b;font-family:monospace;"><?= htmlspecialchars($ticket['cnpj_cliente']) ?></span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($ticket['assunto']) ?></strong><br>
                            <span style="font-size:.8rem;color:#64748b;"><?= htmlspecialchars($ticket['departamento']) ?></span>
                        </td>
                        <td><?= getStatusBadge($ticket['status']) ?></td>
                        <td>
                            <!-- CSRF token incluído em TODOS os formulários POST -->
                            <form method="POST" class="status-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="chamado_id" value="<?= intval($ticket['id']) ?>">
                                <select name="novo_status" class="status-select">
                                    <option value="Aberto"             <?= $ticket['status'] === 'Aberto'             ? 'selected' : '' ?>>Aberto</option>
                                    <option value="Em Andamento"       <?= $ticket['status'] === 'Em Andamento'       ? 'selected' : '' ?>>Em Andamento</option>
                                    <option value="Aguardando Cliente" <?= $ticket['status'] === 'Aguardando Cliente' ? 'selected' : '' ?>>Aguardando Cliente</option>
                                    <option value="Resolvido"          <?= $ticket['status'] === 'Resolvido'          ? 'selected' : '' ?>>Resolvido</option>
                                </select>
                                <button type="submit" name="atualizar_status" class="btn-salvar">Salvar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>