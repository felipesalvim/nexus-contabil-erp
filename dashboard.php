<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: area_cliente.html");
    exit;
}

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

// Busca os RPAs emitidos por esta empresa
$stmt = $pdo->prepare("
    SELECT r.*, p.nome_completo as prestador_nome 
    FROM `rpa_emissões` r
    JOIN tomadores_empresas t ON r.tomador_id = t.id
    JOIN prestadores_autonomos p ON r.prestador_id = p.id
    WHERE t.cnpj = ?
    ORDER BY r.data_emissao DESC
");
$stmt->execute([$_SESSION['user_cnpj']]);
$rpas = $stmt->fetchAll();

// Cálculos para os Cards de Métricas (Mês atual)
$mesAtual = date('m');
$anoAtual = date('Y');
$totalBrutoMes = 0;
$totalLiquidoMes = 0;
$qtdRpasMes = 0;

foreach ($rpas as $r) {
    if ($r['mes_competencia'] == $mesAtual && $r['ano_competencia'] == $anoAtual) {
        $totalBrutoMes += $r['valor_servico_bruto'];
        $totalLiquidoMes += $r['valor_liquido_pago'];
        $qtdRpasMes++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente | Nexus Contábil</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --verde: #0a4f4f;
            --verde-claro: #147369;
            --dourado: #c8973a;
            --dourado-claro: #e6b758;
            --bg-fundo: #f8fafc;
            --borda: #e2e8f0;
            --texto-escuro: #0f172a;
            --texto-suave: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }
        body { background-color: var(--bg-fundo); color: var(--texto-escuro); display: flex; min-height: 100vh; }
        a { text-decoration: none; }

        /* SIDEBAR (MENU LATERAL) */
        .sidebar { width: 260px; background-color: var(--verde); color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; left: 0; top: 0; }
        .brand { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; }
        .brand h2 { font-family: 'Playfair Display', serif; font-size: 1.2rem; margin-bottom: 0.2rem; }
        .brand span { font-size: 0.7rem; color: var(--dourado-claro); letter-spacing: 1px; text-transform: uppercase; }
        
        .nav-menu { flex-grow: 1; padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 0.8rem 1rem; border-radius: 8px; font-weight: 500; transition: 0.3s; display: flex; align-items: center; gap: 0.8rem; }
        .nav-link:hover, .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { border-left: 4px solid var(--dourado); }
        
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .btn-logout { display: block; text-align: center; color: white; background: rgba(255,255,255,0.1); padding: 0.8rem; border-radius: 8px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); transition: 0.3s; }
        .btn-logout:hover { background: #ef4444; border-color: #ef4444; }

        /* CONTEÚDO PRINCIPAL */
        .main-content { flex-grow: 1; margin-left: 260px; padding: 2rem 3rem; }
        
        /* HEADER DO DASHBOARD */
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .welcome-text h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--verde); margin-bottom: 0.3rem; }
        .welcome-text p { color: var(--texto-suave); font-size: 0.95rem; }
        .user-profile { background: white; padding: 0.5rem 1rem; border-radius: 30px; border: 1px solid var(--borda); display: flex; align-items: center; gap: 0.8rem; font-weight: 600; font-size: 0.9rem; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .user-avatar { width: 35px; height: 35px; background: var(--dourado-claro); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        /* CARDS DE MÉTRICAS */
        .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 3rem; }
        .kpi-card { background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .kpi-card::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 4px; background: var(--verde-claro); }
        .kpi-card.destaque::before { background: var(--dourado); }
        .kpi-title { font-size: 0.8rem; font-weight: 700; color: var(--texto-suave); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px; }
        .kpi-value { font-size: 1.8rem; font-weight: 700; color: var(--texto-escuro); }
        .kpi-desc { font-size: 0.8rem; color: #10b981; margin-top: 0.5rem; font-weight: 600; }

        /* TABELA PREMIUM */
        .table-container { background: white; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); overflow: hidden; }
        .table-header { padding: 1.5rem; border-bottom: 1px solid var(--borda); display: flex; justify-content: space-between; align-items: center; }
        .table-header h3 { font-size: 1.2rem; color: var(--verde); }
        
        .rpa-table { width: 100%; border-collapse: collapse; }
        .rpa-table th { background: #f8fafc; padding: 1rem 1.5rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--texto-suave); font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid var(--borda); }
        .rpa-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--borda); font-size: 0.95rem; color: var(--texto-escuro); vertical-align: middle; }
        .rpa-table tr:hover { background-color: #f8fafc; }
        .rpa-table tr:last-child td { border-bottom: none; }
        
        .status-badge { display: inline-block; background: #dcfce7; color: #166534; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .btn-action { color: var(--dourado); font-weight: 600; font-size: 0.85rem; padding: 0.4rem 0.8rem; border: 1px solid var(--dourado); border-radius: 6px; transition: 0.3s; }
        .btn-action:hover { background: var(--dourado); color: white; }

        /* FORMATADOR DE VALORES */
        .val-liquido { color: #10b981; font-weight: 700; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .brand h2, .brand span, .nav-link span, .sidebar-footer { display: none; }
            .main-content { margin-left: 80px; padding: 1.5rem; }
            .kpi-grid { grid-template-columns: 1fr; }
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
                <h1>Painel de Gestão</h1>
                <p>Bem-vindo, <strong><?php echo htmlspecialchars($_SESSION['user_nome']); ?></strong> (CNPJ: <?php echo htmlspecialchars($_SESSION['user_cnpj']); ?>)</p>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><?php echo substr($_SESSION['user_nome'], 0, 1); ?></div>
                Conta Empresarial
            </div>
        </header>

        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-title">Emissões RPA (Mês Atual)</span>
                <span class="kpi-value"><?php echo str_pad($qtdRpasMes, 2, "0", STR_PAD_LEFT); ?></span>
                <span class="kpi-desc">Documentos gerados para o eSocial</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-title">Total Bruto Contratado</span>
                <span class="kpi-value">R$ <?php echo number_format($totalBrutoMes, 2, ',', '.'); ?></span>
                <span class="kpi-desc" style="color: var(--texto-suave);">Soma base para tributação</span>
            </div>
            <div class="kpi-card destaque">
                <span class="kpi-title">Total Líquido a Pagar</span>
                <span class="kpi-value">R$ <?php echo number_format($totalLiquidoMes, 2, ',', '.'); ?></span>
                <span class="kpi-desc">Valor exato transferido aos prestadores</span>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3>Histórico de Recibos Emitidos</h3>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="rpa-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Profissional Autônomo</th>
                            <th>Competência</th>
                            <th>Valor Bruto</th>
                            <th>Líquido Final</th>
                            <th>Situação</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($rpas)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 3rem; color: var(--texto-suave);">
                                    Nenhum RPA foi emitido por esta empresa até ao momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($rpas as $r): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($r['data_emissao'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($r['prestador_nome']); ?></strong></td>
                                <td><?php echo $r['mes_competencia'] . '/' . $r['ano_competencia']; ?></td>
                                <td>R$ <?php echo number_format($r['valor_servico_bruto'], 2, ',', '.'); ?></td>
                                <td class="val-liquido">R$ <?php echo number_format($r['valor_liquido_pago'], 2, ',', '.'); ?></td>
                                <td><span class="status-badge">Validado</span></td>
                                <td><a href="baixar_pdf.php?id=<?php echo $r['id']; ?>" target="_blank" class="btn-action">Baixar PDF</a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>