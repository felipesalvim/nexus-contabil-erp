<?php
$page_title = "Gestão de Usuários | Gestão Nexus";
include_once __DIR__ . '/admin_header.php'; // Herda segurança, sidebar e estilos globais
require_once __DIR__ . '/db.php';

$mensagem     = "";
$erro_critico = null;
$tab    = $_GET['tab']    ?? 'admins';
$action = $_GET['action'] ?? 'list';
$edit_data = null;

// ==========================================
// LÓGICA DE PROCESSAMENTO BACKEND (CRUD)
// ==========================================
try {

    // ----- 1. EXCLUIR USUÁRIO ADMINISTRATIVO -----
    if (isset($_GET['delete_admin'])) {
        $id = intval($_GET['delete_admin']);
        if ($id === (int) $_SESSION['user_id']) {
            $mensagem = "<div class='alert erro'>Segurança: Você não pode excluir a sua própria conta logada.</div>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $mensagem = "<div class='alert sucesso'>Usuário administrativo excluído com sucesso!</div>";
        }
    }

    // ----- 2. EXCLUIR USUÁRIO CLIENTE -----
    // Tabela: usuarios_clientes (nome, email, senha, cnpj_empresa) — confirmado no banco
    if (isset($_GET['delete_cliente'])) {
        $id = intval($_GET['delete_cliente']);
        $stmt = $pdo->prepare("DELETE FROM usuarios_clientes WHERE id = ?");
        $stmt->execute([$id]);
        $mensagem = "<div class='alert sucesso'>Usuário do cliente excluído com sucesso!</div>";
    }

    // ----- 3. SALVAR / ATUALIZAR (POST) -----
    if ($_SERVER["REQUEST_METHOD"] == "POST") {

        // — Admin —
        if (isset($_POST['salvar_admin'])) {
            $id    = intval($_POST['id'] ?? 0);
            $email = htmlspecialchars(trim($_POST['email']));
            $senha = $_POST['senha'] ?? '';

            if ($id > 0) {
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET email = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$email, $senha_hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                    $stmt->execute([$email, $id]);
                }
                $mensagem = "<div class='alert sucesso'>Usuário administrativo atualizado!</div>";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha) VALUES (?, ?)");
                $stmt->execute([$email, $senha_hash]);
                $mensagem = "<div class='alert sucesso'>Novo administrador cadastrado com sucesso!</div>";
            }
            $action = 'list';
        }

        // — Cliente —
        // Tabela: usuarios_clientes (nome, email, senha, cnpj_empresa)
        if (isset($_POST['salvar_cliente'])) {
            $id           = intval($_POST['id'] ?? 0);
            $nome         = htmlspecialchars(trim($_POST['nome']));
            $email        = htmlspecialchars(trim($_POST['email']));
            $cnpj_empresa = htmlspecialchars($_POST['cnpj_empresa']);
            $senha        = $_POST['senha'] ?? '';

            if ($id > 0) {
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios_clientes SET nome = ?, email = ?, cnpj_empresa = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $cnpj_empresa, $senha_hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios_clientes SET nome = ?, email = ?, cnpj_empresa = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $cnpj_empresa, $id]);
                }
                $mensagem = "<div class='alert sucesso'>Usuário do cliente atualizado!</div>";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios_clientes (nome, email, senha, cnpj_empresa) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $email, $senha_hash, $cnpj_empresa]);
                $mensagem = "<div class='alert sucesso'>Novo usuário de cliente cadastrado!</div>";
            }
            $action = 'list';
        }
    }

    // ----- 4. CAPTURA DADOS PARA EDIÇÃO -----
    if ($action == 'edit') {
        $id = intval($_GET['id'] ?? 0);
        if ($tab == 'admins') {
            $stmt = $pdo->prepare("SELECT id, email FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("SELECT id, nome, email, cnpj_empresa FROM usuarios_clientes WHERE id = ?");
            $stmt->execute([$id]);
            $edit_data = $stmt->fetch();
        }
    }

    // ----- 5. LISTAGENS -----

    // CORRIGIDO: COLLATE explícito na coluna email de 'usuarios' porque essa tabela foi criada
    // sem COLLATE (resulta em utf8mb4_general_ci no MySQL 5.7), enquanto o resto do banco
    // usa utf8mb4_unicode_ci — o conflito gerava o erro 1267 "Illegal mix of collations".
    $admins_lista = $pdo->query("
        SELECT id,
               email COLLATE utf8mb4_unicode_ci AS email,
               criado_em
        FROM usuarios
        ORDER BY id DESC
    ")->fetchAll();

    // JOIN entre usuarios_clientes e tomadores_empresas — ambas utf8mb4_unicode_ci, sem conflito.
    // COLLATE adicionado por segurança para garantir consistência mesmo que o schema mude.
    $clientes_lista = $pdo->query("
        SELECT
            uc.id,
            uc.nome,
            uc.email,
            uc.cnpj_empresa,
            t.razao_social AS empresa_nome
        FROM usuarios_clientes uc
        JOIN tomadores_empresas t
          ON uc.cnpj_empresa COLLATE utf8mb4_unicode_ci = t.cnpj COLLATE utf8mb4_unicode_ci
        ORDER BY uc.id DESC
    ")->fetchAll();

    $empresas_select = $pdo->query("
        SELECT razao_social, cnpj FROM tomadores_empresas ORDER BY razao_social ASC
    ")->fetchAll();

} catch (PDOException $e) {
    $erro_critico = $e->getMessage();
}
?>

    <style>
        .tabs-container { display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--borda); padding-bottom: 0.5rem; }
        .tab-btn { background: none; border: none; padding: 0.8rem 1.5rem; font-size: 1rem; font-weight: 600; color: var(--texto-claro); cursor: pointer; transition: var(--transition-smooth); text-decoration: none; border-radius: 6px 6px 0 0; }
        .tab-btn:hover { color: var(--verde); background: rgba(10, 79, 79, 0.02); }
        .tab-btn.active { color: var(--verde); border-bottom: 4px solid var(--dourado); background: rgba(10, 79, 79, 0.04); }

        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .btn-novo { background-color: var(--verde); color: white; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; transition: var(--transition-smooth); box-shadow: 0 4px 10px rgba(10,79,79,0.1); }
        .btn-novo:hover { background-color: var(--verde-claro); transform: translateY(-2px); }
        .table-container { width: 100%; overflow-x: auto; border-radius: 8px; border: 1px solid var(--borda); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem; }
        th { background-color: #0f172a; color: white; padding: 1.1rem 1rem; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; }
        td { padding: 1.1rem 1rem; border-bottom: 1px solid var(--borda); background: #ffffff; transition: var(--transition-smooth); }
        tr:hover td { background-color: #f8fafc; }
        .btn-table { padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-block; transition: var(--transition-smooth); border: none; cursor: pointer; }
        .btn-table.edit   { background-color: #e0f2fe; color: #0369a1; margin-right: 5px; }
        .btn-table.edit:hover   { background-color: #0369a1; color: white; }
        .btn-table.delete { background-color: #fee2e2; color: #b91c1c; }
        .btn-table.delete:hover { background-color: #b91c1c; color: white; }
        .alert { padding: 1.1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; animation: fadeInUp 0.3s ease; }
        .alert.sucesso { background: #dcfce7; color: #166534; border-left: 5px solid #166534; }
        .alert.erro    { background: #fee2e2; color: #991b1b; border-left: 5px solid #991b1b; }

        .form-grid { display: flex; flex-direction: column; gap: 1.2rem; max-width: 600px; margin-top: 1rem; }
        .btn-cancelar { background: #cbd5e1; color: #334155; text-decoration: none; padding: 1.1rem; border-radius: 8px; font-size: 1rem; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: 1px; display: block; }
        .btn-cancelar:hover { background: #94a3b8; }
    </style>

    <main class="main-content">
        <div class="table-card">
            <h1>Controle de Credenciais e Acesso</h1>
            <p class="subtitle">Gerencie quem possui acesso administrativo ao ERP e quais contas estão autorizadas no Portal do Cliente.</p>

            <?php if ($erro_critico): ?>
                <div style="background-color:#fef2f2; border:1px solid #f87171; border-left:6px solid #ef4444; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h3 style="color:#b91c1c; margin-top:0;">Falha de Estrutura de Base de Dados</h3>
                    <p style="color:#7f1d1d; margin-bottom:10px;">O sistema detectou um erro. Verifique abaixo:</p>
                    <code style="background:#ffffff; padding:10px; display:block; border-radius:4px; color:#000; font-family:monospace; font-size:0.9rem;">
                        <?= htmlspecialchars($erro_critico) ?>
                    </code>
                </div>
            <?php else: ?>

                <?= $mensagem ?>

                <?php if ($action == 'list'): ?>
                <div class="tabs-container">
                    <a href="?tab=admins"   class="tab-btn <?= ($tab == 'admins')   ? 'active' : '' ?>">Painel Administrativo</a>
                    <a href="?tab=clientes" class="tab-btn <?= ($tab == 'clientes') ? 'active' : '' ?>">Painel do Cliente</a>
                </div>
                <?php endif; ?>

                <?php if ($action == 'list'): ?>

                    <?php if ($tab == 'admins'): ?>
                        <div class="header-actions">
                            <h2 style="font-size:1.3rem; color:var(--verde); margin:0;">Contas Administrativas</h2>
                            <a href="?tab=admins&action=new" class="btn-novo">+ Novo Administrador</a>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th width="10%">ID</th>
                                        <th width="55%">E-mail de Login</th>
                                        <th width="20%">Criado Em</th>
                                        <th width="15%">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins_lista as $adm): ?>
                                        <tr>
                                            <td><strong>#<?= $adm['id'] ?></strong></td>
                                            <td><?= htmlspecialchars($adm['email']) ?></td>
                                            <td><?= date('d/m/Y', strtotime($adm['criado_em'])) ?></td>
                                            <td>
                                                <a href="?tab=admins&action=edit&id=<?= $adm['id'] ?>" class="btn-table edit">Editar</a>
                                                <a href="?tab=admins&delete_admin=<?= $adm['id'] ?>" class="btn-table delete" onclick="return confirm('Tem certeza que deseja remover este administrador?')">Excluir</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>
                        <div class="header-actions">
                            <h2 style="font-size:1.3rem; color:var(--verde); margin:0;">Usuários Vinculados a Empresas</h2>
                            <a href="?tab=clientes&action=new" class="btn-novo">+ Novo Usuário Cliente</a>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th width="25%">Nome do Usuário</th>
                                        <th width="30%">E-mail Corporativo</th>
                                        <th width="30%">Empresa (Vínculo)</th>
                                        <th width="15%">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($clientes_lista)): ?>
                                        <tr><td colspan="4" style="text-align:center; color:var(--texto-claro); font-style:italic; padding:2rem;">Nenhum usuário de cliente registrado ainda.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($clientes_lista as $cli): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($cli['nome']) ?></strong></td>
                                                <td><?= htmlspecialchars($cli['email']) ?></td>
                                                <td>
                                                    <?= htmlspecialchars($cli['empresa_nome']) ?><br>
                                                    <small style="color:var(--texto-claro); font-family:monospace;"><?= htmlspecialchars($cli['cnpj_empresa']) ?></small>
                                                </td>
                                                <td>
                                                    <a href="?tab=clientes&action=edit&id=<?= $cli['id'] ?>" class="btn-table edit">Editar</a>
                                                    <a href="?tab=clientes&delete_cliente=<?= $cli['id'] ?>" class="btn-table delete" onclick="return confirm('Tem certeza que deseja remover o acesso deste cliente?')">Excluir</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <h2 style="font-size:1.3rem; color:var(--verde); margin-bottom:1.5rem;">
                        <?= ($action == 'edit') ? 'Editar Registro' : 'Registrar Novo' ?> — <?= ($tab == 'admins') ? 'Administrativo' : 'Cliente' ?>
                    </h2>

                    <?php if ($tab == 'admins'): ?>
                        <form action="?tab=admins" method="POST" class="form-grid">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                            <div class="form-group">
                                <label>E-mail Corporativo</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" required placeholder="exemplo@nexusinnova.com.br">
                            </div>
                            <div class="form-group">
                                <label>Senha de Acesso <?= ($action == 'edit') ? '<small style="color:var(--dourado); text-transform:none;">(Deixe em branco para manter a atual)</small>' : '' ?></label>
                                <input type="password" name="senha" <?= ($action == 'new') ? 'required' : '' ?> placeholder="Mínimo 8 caracteres" minlength="8">
                            </div>
                            <button type="submit" name="salvar_admin" class="btn-action-primary">Salvar Credenciais</button>
                            <a href="?tab=admins&action=list" class="btn-cancelar">Voltar</a>
                        </form>

                    <?php else: ?>
                        <form action="?tab=clientes" method="POST" class="form-grid">
                            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                            <div class="form-group">
                                <label>Nome Completo do Usuário</label>
                                <input type="text" name="nome" value="<?= htmlspecialchars($edit_data['nome'] ?? '') ?>" required placeholder="Ex: Roberto Alencar">
                            </div>
                            <div class="form-group">
                                <label>E-mail de Login do Cliente</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" required placeholder="financeiro@empresa.com">
                            </div>
                            <div class="form-group">
                                <label>Empresa Autorizada (Cofre Digital)</label>
                                <select name="cnpj_empresa" required>
                                    <option value="">-- Selecione a Empresa --</option>
                                    <?php foreach ($empresas_select as $emp): ?>
                                        <option value="<?= htmlspecialchars($emp['cnpj']) ?>"
                                            <?= (isset($edit_data['cnpj_empresa']) && $edit_data['cnpj_empresa'] == $emp['cnpj']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['razao_social']) ?> (CNPJ: <?= htmlspecialchars($emp['cnpj']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Senha de Acesso <?= ($action == 'edit') ? '<small style="color:var(--dourado); text-transform:none;">(Deixe em branco para manter a atual)</small>' : '' ?></label>
                                <input type="password" name="senha" <?= ($action == 'new') ? 'required' : '' ?> placeholder="Mínimo 8 caracteres" minlength="8">
                            </div>
                            <button type="submit" name="salvar_cliente" class="btn-action-primary">Salvar Usuário Cliente</button>
                            <a href="?tab=clientes&action=list" class="btn-cancelar">Voltar</a>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>

</body>
</html>