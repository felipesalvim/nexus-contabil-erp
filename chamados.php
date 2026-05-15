<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: area_cliente.html");
    exit;
}

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

$mensagem_alerta = "";
$cnpj_cliente = $_SESSION['user_cnpj'];

// =========================================================================
// 1. LÓGICA DE CRIAÇÃO DO CHAMADO (COM TRANSAÇÃO ACID)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assunto'])) {
    $departamento = $_POST['departamento'];
    $assunto = htmlspecialchars($_POST['assunto']);
    $mensagem = htmlspecialchars($_POST['mensagem']);

    if (!empty($assunto) && !empty($mensagem)) {
        try {
            // Inicia a Transação (Garante que ou salva nas duas tabelas, ou em nenhuma)
            $pdo->beginTransaction();

            // Passo A: Insere o cabeçalho do chamado
            $stmt1 = $pdo->prepare("INSERT INTO chamados_portal (cnpj_cliente, departamento, assunto, status) VALUES (?, ?, ?, 'Aberto')");
            $stmt1->execute([$cnpj_cliente, $departamento, $assunto]);
            
            // Passo B: Puxa o ID gerado automaticamente
            $chamado_id = $pdo->lastInsertId();

            // Passo C: Insere a mensagem inicial na tabela de interações vinculada ao ID
            $stmt2 = $pdo->prepare("INSERT INTO interacoes_chamado (chamado_id, autor, mensagem) VALUES (?, 'Cliente', ?)");
            $stmt2->execute([$chamado_id, $mensagem]);

            // Confirma a gravação (Commit)
            $pdo->commit();
            $mensagem_alerta = "<div class='alert sucesso'>Chamado aberto com sucesso! A nossa equipa técnica responderá em breve.</div>";
        } catch (Exception $e) {
            // Em caso de erro, desfaz a operação para não gerar dados órfãos
            $pdo->rollBack();
            $mensagem_alerta = "<div class='alert erro'>Erro ao abrir o chamado: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensagem_alerta = "<div class='alert erro'>Por favor, preencha todos os campos obrigatórios.</div>";
    }
}

// =========================================================================
// 2. BUSCA A LISTAGEM DE CHAMADOS DO CLIENTE
// =========================================================================
$stmtLista = $pdo->prepare("SELECT * FROM chamados_portal WHERE cnpj_cliente = ? ORDER BY data_ultima_atualizacao DESC");
$stmtLista->execute([$cnpj_cliente]);
$chamados = $stmtLista->fetchAll();

// Função para renderizar status visual
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
    <title>Central de Ajuda | Nexus Contábil</title>
    
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
        .btn-logout:hover { background: #ef4444; border-color: #ef4444; }

        /* MAIN CONTENT */
        .main-content { flex-grow: 1; margin-left: 260px; padding: 2rem 3rem; }
        .top-header { margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .welcome-text h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--verde); margin-bottom: 0.3rem; }
        .welcome-text p { color: var(--texto-suave); font-size: 0.95rem; }

        /* GRID: FORMULÁRIO (Esquerda) e LISTA (Direita) */
        .grid-container { display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; align-items: start; }

        /* FORMULÁRIO DE ABERTURA */
        .form-card { background: white; padding: 2rem; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .form-card h3 { font-family: 'Playfair Display', serif; color: var(--verde); margin-bottom: 1.5rem; font-size: 1.3rem; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--texto-suave); margin-bottom: 0.5rem; text-transform: uppercase; }
        .form-control { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--borda); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; background: var(--bg-fundo); outline: none; transition: 0.3s; }
        .form-control:focus { border-color: var(--dourado); background: white; box-shadow: 0 0 0 3px rgba(200,151,58,0.1); }
        textarea.form-control { resize: vertical; min-height: 120px; }
        .btn-submit { width: 100%; background: var(--verde); color: white; border: none; padding: 1rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s; font-size: 1rem; margin-top: 0.5rem; }
        .btn-submit:hover { background: var(--verde-claro); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(10,79,79,0.2); }

        /* LISTA DE CHAMADOS */
        .list-card { background: white; padding: 2rem; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .list-card h3 { font-family: 'Playfair Display', serif; color: var(--verde); margin-bottom: 1.5rem; font-size: 1.3rem; }
        
        .ticket-item { display: flex; justify-content: space-between; align-items: center; padding: 1.2rem 0; border-bottom: 1px solid var(--borda); transition: 0.3s; }
        .ticket-item:last-child { border-bottom: none; padding-bottom: 0; }
        .ticket-info h4 { font-size: 1rem; color: var(--texto-escuro); margin-bottom: 0.3rem; }
        .ticket-info p { font-size: 0.85rem; color: var(--texto-suave); }
        .ticket-action { display: flex; flex-direction: column; align-items: flex-end; gap: 0.8rem; }
        .btn-ver { background: transparent; color: var(--dourado); font-weight: 700; font-size: 0.85rem; border: 1px solid var(--dourado); padding: 0.4rem 1rem; border-radius: 6px; transition: 0.3s; }
        .btn-ver:hover { background: var(--dourado); color: white; }

        /* BADGES */
        .badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .badge-aberto { background: #fef9c3; color: #b45309; border: 1px solid #fde047; }
        .badge-andamento { background: #e0f2fe; color: #1d4ed8; border: 1px solid #bae6fd;}
        .badge-concluido { background: #dcfce7; color: #166534; border: 1px solid #86efac;}

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; }
        .alert.sucesso { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .alert.erro { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .brand h2, .brand span, .nav-link span, .sidebar-footer { display: none; }
            .main-content { margin-left: 80px; padding: 1.5rem; }
            .grid-container { grid-template-columns: 1fr; }
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
        
        <header class="top-header">
            <div class="welcome-text">
                <h1>Central de Ajuda (Help Desk)</h1>
                <p>Abra chamados para a nossa equipa contábil e acompanhe o histórico.</p>
            </div>
        </header>

        <?= $mensagem_alerta ?>

        <div class="grid-container">
            <div class="form-card">
                <h3>Abrir Novo Chamado</h3>
                <form method="POST" action="chamados.php">
                    <div class="form-group">
                        <label>Departamento</label>
                        <select name="departamento" class="form-control" required>
                            <option value="Contábil">Contábil</option>
                            <option value="Fiscal">Fiscal</option>
                            <option value="Departamento Pessoal">Departamento Pessoal</option>
                            <option value="Legalização">Legalização Societária</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Assunto Resumido</label>
                        <input type="text" name="assunto" class="form-control" required placeholder="Ex: Emissão de RPA em atraso">
                    </div>
                    
                    <div class="form-group">
                        <label>Mensagem / Detalhes</label>
                        <textarea name="mensagem" class="form-control" required placeholder="Descreva a sua solicitação com o máximo de detalhes possível..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Enviar Solicitação</button>
                </form>
            </div>

            <div class="list-card">
                <h3>O Meu Histórico</h3>
                
                <?php if(empty($chamados)): ?>
                    <p style="text-align: center; color: var(--texto-suave); padding: 2rem;">A sua empresa não possui nenhum chamado registado.</p>
                <?php else: ?>
                    <div class="ticket-list">
                        <?php foreach($chamados as $ticket): ?>
                            <div class="ticket-item">
                                <div class="ticket-info">
                                    <h4><?php echo htmlspecialchars($ticket['assunto']); ?></h4>
                                    <p>
                                        <strong>Protocolo:</strong> #<?php echo str_pad($ticket['id'], 4, "0", STR_PAD_LEFT); ?> | 
                                        <strong>Depto:</strong> <?php echo htmlspecialchars($ticket['departamento']); ?> | 
                                        <strong>Atualizado:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket['data_ultima_atualizacao'])); ?>
                                    </p>
                                </div>
                                <div class="ticket-action">
                                    <?php echo getStatusBadge($ticket['status']); ?>
                                    <a href="ver_chamado.php?id=<?php echo $ticket['id']; ?>" class="btn-ver">Ver Histórico</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

</body>
</html>