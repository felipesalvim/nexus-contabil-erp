<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: area_cliente.html");
    exit;
}

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

// Busca os documentos vinculados ao CNPJ do cliente logado
$stmt = $pdo->prepare("SELECT * FROM documentos_portal WHERE cnpj_cliente = ? ORDER BY data_upload DESC");
$stmt->execute([$_SESSION['user_cnpj']]);
$documentos = $stmt->fetchAll();

// Função para renderizar as cores das categorias (Atualizada para bater com o ENUM do Banco de Dados)
function getBadgeCategoria($cat) {
    switch ($cat) {
        case 'Guias de Impostos': return '<span class="badge-cat badge-impostos">Guias e Impostos</span>';
        case 'RPA': return '<span class="badge-cat badge-rh">RPA / DP</span>';
        case 'Balanços': return '<span class="badge-cat badge-contabil">Contábil</span>';
        default: return '<span class="badge-cat">Diversos</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cofre de Documentos | Nexus Contábil</title>
    
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

        /* SIDEBAR */
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
        
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem; }
        .welcome-text h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--verde); margin-bottom: 0.3rem; }
        .welcome-text p { color: var(--texto-suave); font-size: 0.95rem; }
        
        /* HEADER DO COFRE */
        .cofre-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .search-box { display: flex; gap: 1rem; align-items: center; width: 100%; max-width: 400px; }
        .search-box input { width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--borda); border-radius: 8px; font-family: 'DM Sans', sans-serif; outline: none; }
        .search-box input:focus { border-color: var(--dourado); }
        
        /* TABELA DE DOCUMENTOS */
        .table-container { background: white; border-radius: 16px; border: 1px solid var(--borda); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); overflow: hidden; }
        .doc-table { width: 100%; border-collapse: collapse; }
        .doc-table th { background: #f8fafc; padding: 1rem 1.5rem; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: var(--texto-suave); font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid var(--borda); }
        .doc-table td { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--borda); font-size: 0.95rem; color: var(--texto-escuro); vertical-align: middle; }
        .doc-table tr:hover { background-color: #f8fafc; }
        
        /* BADGES (Etiquetas coloridas) */
        .badge-cat { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .badge-impostos { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-rh { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .badge-contabil { background: #fef3c7; color: #92400e; border: 1px solid #fde047; }
        .badge-societario { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        .doc-title { font-weight: 600; color: var(--verde); display: flex; align-items: center; gap: 0.8rem; }
        .doc-icon { font-size: 1.5rem; color: var(--dourado); }

        .btn-download { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--verde-claro); color: white; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; transition: 0.3s; border: none; cursor: pointer; }
        .btn-download:hover { background: var(--verde); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(10,79,79,0.2); color: white; }

        @media (max-width: 1024px) {
            .sidebar { width: 80px; }
            .brand h2, .brand span, .nav-link span, .sidebar-footer { display: none; }
            .main-content { margin-left: 80px; padding: 1.5rem; }
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
            <a href="dashboard.php" class="nav-link">
                <i>📊</i> <span>Painel Resumo</span>
            </a>
            <a href="documentos.php" class="nav-link active">
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
                <h1>Cofre de Documentos</h1>
                <p>Gestão eletrónica segura de todos os arquivos da sua empresa.</p>
            </div>
        </header>

        <div class="cofre-controls">
            <div class="search-box">
                <input type="text" id="pesquisaDoc" placeholder="Pesquisar por nome ou categoria..." onkeyup="filtrarTabela()">
            </div>
            <div style="font-size: 0.9rem; color: var(--texto-suave);">
                Mostrando arquivos para o CNPJ: <strong><?php echo htmlspecialchars($_SESSION['user_cnpj']); ?></strong>
            </div>
        </div>

        <div class="table-container">
            <table class="doc-table" id="tabelaDocumentos">
                <thead>
                    <tr>
                        <th>Nome do Arquivo</th>
                        <th>Categoria</th>
                        <th>Data de Envio</th>
                        <th style="text-align: right;">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($documentos)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 3rem; color: var(--texto-suave);">
                                Nenhum documento foi disponibilizado no seu cofre ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($documentos as $doc): ?>
                        <tr>
                            <td>
                                <div class="doc-title">
                                    <span class="doc-icon">📄</span>
                                    <?php echo htmlspecialchars($doc['titulo']); ?>
                                </div>
                            </td>
                            <td><?php echo getBadgeCategoria($doc['categoria']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($doc['data_upload'])); ?></td>
                            <td style="text-align: right;">
                                <a href="baixar_doc.php?id=<?php echo $doc['id']; ?>" class="btn-download">
                                    ↓ Baixar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <script>
        function filtrarTabela() {
            let input = document.getElementById("pesquisaDoc");
            let filter = input.value.toUpperCase();
            let table = document.getElementById("tabelaDocumentos");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let tdNome = tr[i].getElementsByTagName("td")[0];
                let tdCat = tr[i].getElementsByTagName("td")[1]; // Atualizado para a nova posição (índice 1)
                if (tdNome || tdCat) {
                    let txtValueNome = tdNome.textContent || tdNome.innerText;
                    let txtValueCat = tdCat.textContent || tdCat.innerText;
                    if (txtValueNome.toUpperCase().indexOf(filter) > -1 || txtValueCat.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }
    </script>
</body>
</html>