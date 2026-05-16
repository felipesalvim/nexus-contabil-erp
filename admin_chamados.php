<?php
$page_title = "Gestão de Chamados | Gestão Nexus";
include_once __DIR__ . '/admin_header.php'; // Puxa layout, segurança, CSS e Sidebar unificados

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

$mensagem = "";

// Lógica de Atualização de Status
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['chamado_id'])) {
    $chamado_id = intval($_POST['chamado_id']);
    $novo_status = htmlspecialchars($_POST['novo_status']);

    try {
        // CORRIGIDO: Usa chamados_portal e data_ultima_atualizacao
        $stmt = $pdo->prepare("UPDATE chamados_portal SET status = ?, data_ultima_atualizacao = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$novo_status, $chamado_id]);
        $mensagem = "<div class='alert-toast sucesso'>Status do chamado #" . str_pad($chamado_id, 4, "0", STR_PAD_LEFT) . " atualizado com sucesso!</div>";
    } catch (Exception $e) {
        $mensagem = "<div class='alert-toast erro'>Erro ao atualizar status: " . $e->getMessage() . "</div>";
    }
}

// Busca chamados em aberto ou em andamento
try {
    // CORRIGIDO: Tabelas e colunas corretas do seu banco de dados
    $stmtAbertos = $pdo->query("
        SELECT c.*, t.razao_social as cliente_nome 
        FROM chamados_portal c 
        JOIN tomadores_empresas t ON c.cnpj_cliente = t.cnpj 
        WHERE c.status IN ('Aberto', 'Em Andamento', 'Aguardando Cliente') 
        ORDER BY c.data_ultima_atualizacao DESC
    ");
    $chamados_abertos = $stmtAbertos->fetchAll();

    // Busca chamados resolvidos
    $stmtResolvidos = $pdo->query("
        SELECT c.*, t.razao_social as cliente_nome 
        FROM chamados_portal c 
        JOIN tomadores_empresas t ON c.cnpj_cliente = t.cnpj 
        WHERE c.status = 'Resolvido' 
        ORDER BY c.data_ultima_atualizacao DESC
    ");
    $chamados_resolvidos = $stmtResolvidos->fetchAll();
} catch (PDOException $e) {
    // Caso haja novo erro no banco, mostra na tela em vez de tela branca
    die("<div class='main-content'><h1>Erro de Banco de Dados</h1><p>" . $e->getMessage() . "</p></div>");
}
?>

    <style>
        /* Estilizações exclusivas para Tabelas e Elementos Dinâmicos */
        .secao-titulo {
            font-size: 1.25rem;
            color: var(--verde);
            font-weight: 700;
            margin: 2rem 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-left: 4px solid var(--dourado);
            padding-left: 10px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--borda);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        th {
            background-color: #0f172a;
            color: white;
            padding: 1.1rem 1rem;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1.1rem 1rem;
            border-bottom: 1px solid var(--borda);
            background: #ffffff;
            transition: var(--transition-smooth);
        }

        tr:hover td {
            background-color: #f8fafc;
            color: #000000;
        }

        /* Badges de Status Modernizadas */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge.aberto { background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; }
        .badge.andamento { background: #e0f2fe; color: #1d4ed8; border: 1px solid #bae6fd; }
        .badge.aguardando { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .badge.resolvido { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

        .select-status-table {
            padding: 0.5rem;
            font-size: 0.85rem;
            background: #ffffff;
            border: 1px solid var(--borda);
            border-radius: 6px;
            width: auto;
            cursor: pointer;
        }

        .btn-update-table {
            background: #0f172a;
            color: white;
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition-smooth);
        }
        .btn-update-table:hover {
            background: var(--dourado);
            box-shadow: 0 0 0 3px var(--glow-dourado);
        }

        .alert-toast {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            animation: fadeInUp 0.3s ease;
        }
        .alert-toast.sucesso { background: #dcfce7; color: #166534; border-left: 5px solid #166534; }
        .alert-toast.erro { background: #fee2e2; color: #991b1b; border-left: 5px solid #991b1b; }

        .nenhum-chamado {
            text-align: center;
            padding: 3rem !important;
            color: var(--texto-claro);
            font-style: italic;
        }
    </style>

    <main class="main-content">
        <div class="table-card">
            <h1>Painel de Suporte Técnico</h1>
            <p class="subtitle">Gerencie as solicitações de atendimento e suporte abertas pelas empresas parceiras.</p>

            <?= $mensagem ?>

            <div class="secao-titulo">📥 Chamados Pendentes e Em Atendimento</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="8%">ID</th>
                            <th width="25%">Cliente</th>
                            <th width="22%">Assunto</th>
                            <th width="15%">Atualização</th>
                            <th width="12%">Status</th>
                            <th width="18%">Alterar Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($chamados_abertos)): ?>
                            <tr><td colspan="6" class="nenhum-chamado">Nenhum chamado pendente no momento. Bom trabalho!</td></tr>
                        <?php else: ?>
                            <?php foreach($chamados_abertos as $chamado): ?>
                                <tr>
                                    <td><strong>#<?= str_pad($chamado['id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($chamado['cliente_nome']) ?><br>
                                        <span style="font-size: 0.75rem; color: var(--texto-claro); font-family: monospace;"><?= htmlspecialchars($chamado['cnpj_cliente']) ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:600; color:var(--verde);"><?= htmlspecialchars($chamado['assunto']) ?></span>
                                        <br><small style="color:var(--texto-claro);"><?= htmlspecialchars($chamado['departamento']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($chamado['data_ultima_atualizacao'])) ?></td>
                                    <td>
                                        <?php 
                                        $classeBadge = 'aberto';
                                        if($chamado['status'] == 'Em Andamento') $classeBadge = 'andamento';
                                        if($chamado['status'] == 'Aguardando Cliente') $classeBadge = 'aguardando';
                                        ?>
                                        <span class="badge <?= $classeBadge ?>">
                                            <?= htmlspecialchars($chamado['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="" method="POST" style="display:flex; gap:5px; align-items:center;">
                                            <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                                            <select name="novo_status" class="select-status-table">
                                                <option value="Aberto" <?= ($chamado['status'] == 'Aberto') ? 'selected' : '' ?>>Aberto</option>
                                                <option value="Em Andamento" <?= ($chamado['status'] == 'Em Andamento') ? 'selected' : '' ?>>Em Andamento</option>
                                                <option value="Aguardando Cliente" <?= ($chamado['status'] == 'Aguardando Cliente') ? 'selected' : '' ?>>Aguardando Cliente</option>
                                                <option value="Resolvido">Resolvido</option>
                                            </select>
                                            <button type="submit" class="btn-update-table">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="secao-titulo" style="margin-top:3rem;">✅ Histórico de Chamados Resolvidos</div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th width="8%">ID</th>
                            <th width="25%">Cliente</th>
                            <th width="40%">Assunto / Depto</th>
                            <th width="15%">Encerrado Em</th>
                            <th width="12%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($chamados_resolvidos)): ?>
                            <tr><td colspan="5" class="nenhum-chamado">Nenhum chamado resolvido no histórico recente.</td></tr>
                        <?php else: ?>
                            <?php foreach($chamados_resolvidos as $chamado): ?>
                                <tr>
                                    <td><span style="color:var(--texto-claro);">#<?= str_pad($chamado['id'], 4, "0", STR_PAD_LEFT) ?></span></td>
                                    <td><?= htmlspecialchars($chamado['cliente_nome']) ?></td>
                                    <td>
                                        <span style="font-weight:600; color:var(--texto-claro); text-decoration:line-through;"><?= htmlspecialchars($chamado['assunto']) ?></span>
                                        <br><small style="color:var(--texto-claro);"><?= htmlspecialchars($chamado['departamento']) ?></small>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($chamado['data_ultima_atualizacao'])) ?></td>
                                    <td><span class="badge resolvido">Resolvido</span></td>
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