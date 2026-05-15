<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: area_cliente.html");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID do chamado não especificado.");
}

$chamado_id = intval($_GET['id']);
$cnpj_cliente = $_SESSION['user_cnpj'];

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

$mensagem_alerta = "";

// 1. Processar envio de nova resposta
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nova_mensagem'])) {
    $nova_msg = htmlspecialchars($_POST['nova_mensagem']);
    
    if (trim($nova_msg) != '') {
        try {
            // Insere na tabela correta: interacoes_chamado
            $stmt = $pdo->prepare("INSERT INTO interacoes_chamado (chamado_id, autor, mensagem) VALUES (?, 'Cliente', ?)");
            $stmt->execute([$chamado_id, $nova_msg]);
            
            // Atualiza o status do chamado e a data de modificação
            $pdo->prepare("UPDATE chamados_portal SET status = 'Aberto', data_ultima_atualizacao = CURRENT_TIMESTAMP WHERE id = ?")->execute([$chamado_id]);
            
            $mensagem_alerta = "<div class='alert sucesso'>Resposta enviada com sucesso!</div>";
        } catch (Exception $e) {
            $mensagem_alerta = "<div class='alert erro'>Erro ao enviar: " . $e->getMessage() . "</div>";
        }
    }
}

// 2. Processar encerramento do chamado (Marcar como Resolvido)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['encerrar_chamado'])) {
    try {
        $pdo->prepare("UPDATE chamados_portal SET status = 'Resolvido', data_ultima_atualizacao = CURRENT_TIMESTAMP WHERE id = ? AND cnpj_cliente = ?")->execute([$chamado_id, $cnpj_cliente]);
        $mensagem_alerta = "<div class='alert sucesso'>O chamado foi marcado como resolvido. Obrigado!</div>";
    } catch (Exception $e) {
        $mensagem_alerta = "<div class='alert erro'>Erro ao encerrar chamado.</div>";
    }
}

// 3. Buscar os dados do Chamado (Segurança: Garante que pertence ao CNPJ logado)
$stmt = $pdo->prepare("SELECT * FROM chamados_portal WHERE id = ? AND cnpj_cliente = ?");
$stmt->execute([$chamado_id, $cnpj_cliente]);
$chamado = $stmt->fetch();

if (!$chamado) {
    die("Chamado não encontrado ou você não tem permissão para acessá-lo.");
}

// 4. Buscar as respostas na tabela correta (interacoes_chamado)
$stmtResp = $pdo->prepare("SELECT * FROM interacoes_chamado WHERE chamado_id = ? ORDER BY data_interacao ASC");
$stmtResp->execute([$chamado_id]);
$respostas = $stmtResp->fetchAll();

// Funções visuais
function getStatusBadge($status) {
    switch ($status) {
        case 'Aberto': return '<span class="badge badge-aberto">Aberto</span>';
        case 'Em Andamento': return '<span class="badge badge-andamento">Em Andamento</span>';
        case 'Aguardando Cliente': return '<span class="badge badge-aberto">Aguardando Cliente</span>';
        case 'Resolvido': return '<span class="badge badge-concluido">Resolvido</span>';
        default: return '<span class="badge">'.$status.'</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acompanhar Chamado #<?php echo str_pad($chamado['id'], 4, "0", STR_PAD_LEFT); ?> | Nexus Contábil</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --verde: #0a4f4f; --verde-claro: #147369; --dourado: #c8973a; --bg-fundo: #f8fafc; --borda: #e2e8f0; --texto-escuro: #0f172a; --texto-suave: #64748b; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
        body { background-color: var(--bg-fundo); color: var(--texto-escuro); display: flex; min-height: 100vh; }
        a { text-decoration: none; }

        /* SIDEBAR */
        .sidebar { width: 260px; background-color: var(--verde); color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; left: 0; top: 0; }
        .brand { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; }
        .brand h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin-bottom: 0.2rem; }
        .brand span { font-size: 0.7rem; color: var(--dourado); letter-spacing: 1px; text-transform: uppercase; }
        .nav-menu { flex-grow: 1; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 0.8rem 1rem; border-radius: 8px; font-weight: 500; transition: 0.3s; display: flex; align-items: center; gap: 0.8rem; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { border-left: 4px solid var(--dourado); }
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .btn-logout { display: block; text-align: center; color: white; background: rgba(255,255,255,0.1); padding: 0.8rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); transition: 0.3s; }
        
        /* MAIN CONTENT */
        .main-content { flex-grow: 1; margin-left: 260px; padding: 2rem 3rem; max-width: 1000px; }
        
        .header-chamado { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 2px solid var(--borda); padding-bottom: 1.5rem; }
        .header-chamado h1 { font-family: 'Playfair Display', serif; font-size: 1.8rem; color: var(--verde); margin-bottom: 0.5rem; }
        .btn-voltar { color: var(--texto-suave); font-size: 0.9rem; font-weight: 600; display: inline-block; margin-bottom: 1rem; transition: 0.3s; }
        .btn-voltar:hover { color: var(--verde); }

        /* TIMELINE / CHAT */
        .chat-box { display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 2.5rem; }
        
        .msg-card { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--borda); box-shadow: 0 4px 10px rgba(0,0,0,0.02); position: relative; }
        .msg-header { display: flex; justify-content: space-between; margin-bottom: 1rem; border-bottom: 1px solid var(--bg-fundo); padding-bottom: 0.5rem; }
        .msg-autor { font-weight: 700; color: var(--verde); font-size: 0.95rem; }
        .msg-data { font-size: 0.8rem; color: var(--texto-suave); }
        .msg-body { font-size: 0.95rem; color: var(--texto-escuro); line-height: 1.6; white-space: pre-wrap; }

        /* Diferenciação Visual para Equipa de Suporte */
        .msg-suporte { background: #f0fdf4; border-color: #bbf7d0; border-left: 4px solid #22c55e; }
        .msg-suporte .msg-autor { color: #166534; }
        .msg-suporte .msg-header { border-bottom-color: #bbf7d0; }

        /* FORMULÁRIO DE RESPOSTA */
        .reply-box { background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid var(--borda); box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        textarea { width: 100%; padding: 1rem; border: 1px solid var(--borda); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; background: var(--bg-fundo); outline: none; resize: vertical; min-height: 100px; margin-bottom: 1rem; }
        textarea:focus { border-color: var(--dourado); background: white; box-shadow: 0 0 0 3px rgba(200,151,58,0.1); }
        
        .action-buttons { display: flex; justify-content: space-between; align-items: center; }
        .btn-enviar { background: var(--verde); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-enviar:hover { background: var(--verde-claro); }
        
        .btn-encerrar { background: transparent; color: #ef4444; border: 1px solid #ef4444; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-encerrar:hover { background: #ef4444; color: white; }

        .status-fechado { background: #f8fafc; padding: 2rem; text-align: center; border-radius: 12px; border: 1px dashed var(--borda); color: var(--texto-suave); font-weight: 600; }

        /* BADGES */
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-aberto { background: #fef9c3; color: #b45309; border: 1px solid #fde047; }
        .badge-andamento { background: #e0f2fe; color: #1d4ed8; border: 1px solid #bae6fd;}
        .badge-concluido { background: #dcfce7; color: #166534; border: 1px solid #86efac;}

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; }
        .alert.sucesso { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .alert.erro { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

        @media (max-width: 992px) {
            .sidebar { width: 80px; }
            .brand h2, .brand span, .nav-link span, .sidebar-footer { display: none; }
            .main-content { margin-left: 80px; padding: 1.5rem; }
            .action-buttons { flex-direction: column; gap: 1rem; }
            .btn-enviar, .btn-encerrar { width: 100%; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand" style="text-align: center;">
            <a href="dashboard.php">
                <img src="logo-contabil-verde.png" alt="Logo Nexus Contábil" style="max-width: 100%; max-height: 60px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto;">
            </a>
            <span style="display: block; font-size: 0.85rem; color: var(--dourado); text-transform: uppercase; letter-spacing: 1px;">Portal do Cliente</span>
        </div>
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-link active">
                <i>📊</i> <span>Painel Resumo</span>
            </a>
            <a href="documentos.php" class="nav-link">
                <i>📁</i> <span>Cofre de Documentos</span>
            </a>
            <a href="chamados.php" class="nav-link">
                <i>🎧</i> <span>Abrir Chamado (RH)</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="area_cliente.html" class="btn-logout">Encerrar Sessão</a>
        </div>
    </aside>

    <main class="main-content">
        <a href="chamados.php" class="btn-voltar">← Voltar para Chamados</a>
        
        <?= $mensagem_alerta ?>

        <div class="header-chamado">
            <div>
                <h1><?php echo htmlspecialchars($chamado['assunto']); ?></h1>
                <p style="color: var(--texto-suave); font-size: 0.9rem;">
                    Protocolo: <strong>#<?php echo str_pad($chamado['id'], 4, "0", STR_PAD_LEFT); ?></strong> | 
                    Departamento: <strong><?php echo htmlspecialchars($chamado['departamento']); ?></strong>
                </p>
            </div>
            <div>
                <?php echo getStatusBadge($chamado['status']); ?>
            </div>
        </div>

        <div class="chat-box">
            <?php if (empty($respostas)): ?>
                <p style="color: var(--texto-suave); text-align: center; padding: 2rem;">Ainda não existem interações neste chamado.</p>
            <?php else: ?>
                <?php foreach($respostas as $index => $resp): ?>
                    <div class="msg-card <?php echo $resp['autor'] == 'Equipe Nexus' ? 'msg-suporte' : ''; ?>">
                        <div class="msg-header">
                            <span class="msg-autor">
                                <?php 
                                    if ($resp['autor'] == 'Equipe Nexus') {
                                        echo "S&C Equipa <span style='font-size: 0.75rem; background: #22c55e; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 5px;'>Suporte</span>";
                                    } else {
                                        echo htmlspecialchars($_SESSION['user_nome']); 
                                        if ($index == 0) echo " <span style='font-weight: normal; color: var(--texto-suave); font-size: 0.8rem;'>(Abertura)</span>";
                                    }
                                ?> 
                            </span>
                            <span class="msg-data"><?php echo date('d/m/Y H:i', strtotime($resp['data_interacao'])); ?></span>
                        </div>
                        <div class="msg-body"><?php echo nl2br(htmlspecialchars($resp['mensagem'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($chamado['status'] != 'Resolvido'): ?>
            <div class="reply-box">
                <form method="POST">
                    <textarea name="nova_mensagem" placeholder="Escreva uma nova resposta ou adicione mais detalhes aqui..." required></textarea>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn-enviar">Enviar Resposta</button>
                    </div>
                </form>

                <form method="POST" style="margin-top: 15px; border-top: 1px solid var(--bg-fundo); padding-top: 15px; text-align: right;">
                    <button type="submit" name="encerrar_chamado" class="btn-encerrar" onclick="return confirm('Tem a certeza que deseja encerrar e marcar este chamado como resolvido?');">Marcar como Resolvido / Cancelar Pedido</button>
                </form>
            </div>
        <?php else: ?>
            <div class="status-fechado">
                🔒 Este chamado foi marcado como concluído e não aceita novas interações. <br>
                <span style="font-weight: normal; font-size: 0.9rem;">Caso necessite de ajuda sobre este assunto, por favor abra um novo chamado informando o protocolo anterior.</span>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>