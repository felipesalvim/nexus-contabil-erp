<?php
session_start();

// ==========================================
// 1. CONFIGURAÇÃO DE SEGURANÇA (SENHA DA EQUIPA)
// ==========================================
$senha_equipe = "Nexus2026"; // <-- Senha de acesso ao painel (pode testar com esta)

// Lógica de Logout
if (isset($_GET['sair'])) {
    unset($_SESSION['admin_autenticado']);
    header("Location: admin_upload.php");
    exit;
}

// Lógica de Login
$erro_login = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['senha_acesso'])) {
    if ($_POST['senha_acesso'] === $senha_equipe) {
        $_SESSION['admin_autenticado'] = true;
        header("Location: admin_upload.php");
        exit;
    } else {
        $erro_login = "<div style='color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; font-size: 0.9rem;'>Senha incorreta. Acesso negado.</div>";
    }
}

// ==========================================
// 2. TELA DE LOGIN DO ADMIN
// ==========================================
if (!isset($_SESSION['admin_autenticado'])) {
    echo "
    <!DOCTYPE html>
    <html lang='pt-BR'>
    <head>
        <meta charset='UTF-8'>
        <title>Acesso Restrito | S&C Admin</title>
        <link href='https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Playfair+Display:wght@700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'DM Sans', sans-serif; background: #0f172a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #334155; }
            .login-box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.2); width: 100%; max-width: 350px; }
            .login-box h2 { font-family: 'Playfair Display', serif; color: #0a4f4f; text-align: center; margin-top: 0; font-size: 1.8rem; }
            input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1rem; box-sizing: border-box; margin-bottom: 15px; outline: none; transition: 0.3s; font-family: 'DM Sans', sans-serif;}
            input:focus { border-color: #c8973a; box-shadow: 0 0 0 3px rgba(200,151,58,0.1); }
            button { background: #c8973a; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 1px;}
            button:hover { background: #b38530; transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class='login-box'>
            <h2>Nexus Admin</h2>
            <p style='text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 25px;'>Insira a credencial da equipa para aceder ao Back-Office.</p>
            {$erro_login}
            <form method='POST'>
                <input type='password' name='senha_acesso' placeholder='Palavra-passe' required autofocus>
                <button type='submit'>Entrar no Painel</button>
            </form>
        </div>
    </body>
    </html>
    ";
    exit;
}

// ==========================================
// 3. LÓGICA DO PAINEL ADMIN (UPLOAD)
// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['titulo'])) {
    $cnpj_cliente = htmlspecialchars($_POST['cnpj_cliente']);
    $titulo = htmlspecialchars($_POST['titulo']);
    $categoria = htmlspecialchars($_POST['categoria']); // Agora os valores batem com o ENUM do banco
    
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
        // Cria a pasta estruturada por CNPJ do cliente
        $pasta_destino = "uploads/" . preg_replace("/[^0-9]/", "", $cnpj_cliente) . "/";
        if (!is_dir($pasta_destino)) { mkdir($pasta_destino, 0755, true); }

        $nome_ficheiro = preg_replace("/[^a-zA-Z0-9.-_]/", "_", basename($_FILES["arquivo"]["name"]));
        $caminho_completo = $pasta_destino . $nome_ficheiro;

        $tipo_ficheiro = strtolower(pathinfo($caminho_completo, PATHINFO_EXTENSION));
        
        if ($tipo_ficheiro != "pdf") {
            $mensagem = "<div class='alert erro'>Por favor, envie apenas ficheiros no formato PDF.</div>";
        } else {
            if (move_uploaded_file($_FILES["arquivo"]["tmp_name"], $caminho_completo)) {
                try {
                    // Correção: Removemos o campo 'competencia' que não existe no banco
                    $stmt = $pdo->prepare("INSERT INTO documentos_portal (cnpj_cliente, titulo, categoria, caminho_arquivo) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$cnpj_cliente, $titulo, $categoria, $caminho_completo]);
                    $mensagem = "<div class='alert sucesso'>Documento enviado! O cliente já pode aceder no seu Cofre Digital.</div>";
                } catch (Exception $e) {
                    $mensagem = "<div class='alert erro'>Erro ao gravar na base de dados: " . $e->getMessage() . "</div>";
                }
            } else {
                $mensagem = "<div class='alert erro'>Ocorreu um erro ao guardar o ficheiro no servidor.</div>";
            }
        }
    } else {
        $mensagem = "<div class='alert erro'>Nenhum ficheiro foi anexado ou o limite de tamanho foi excedido.</div>";
    }
}

// Busca a lista de clientes para popular o Select
$stmtClientes = $pdo->query("SELECT razao_social, cnpj FROM tomadores_empresas ORDER BY razao_social ASC");
$clientes = $stmtClientes->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin | Gestão Nexus</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --verde: #0a4f4f; --verde-claro: #147369; --dourado: #c8973a; --borda: #cbd5e1; --bg: #f8fafc; }
        body { font-family: 'DM Sans', sans-serif; background-color: var(--bg); color: #334155; margin: 0; display: flex; min-height: 100vh; }
        
        /* SIDEBAR UNIFICADA (Estilo Back-Office) */
        .admin-sidebar { width: 260px; background: #0f172a; color: white; padding: 2rem 1.5rem; display: flex; flex-direction: column; position: fixed; height: 100vh; left: 0; top: 0; }
        .admin-sidebar h2 { font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--dourado); margin-top: 0; margin-bottom: 0.2rem; }
        .admin-sidebar p { font-size: 0.75rem; color: #94a3b8; margin-bottom: 2rem; text-transform: uppercase; letter-spacing: 1px; }
        
        .nav-menu { flex-grow: 1; display: flex; flex-direction: column; gap: 0.5rem; }
        .nav-link { color: rgba(255,255,255,0.7); padding: 0.8rem 1rem; border-radius: 8px; font-weight: 500; transition: 0.3s; display: flex; align-items: center; gap: 0.8rem; text-decoration: none; }
        .nav-link:hover { background-color: rgba(255,255,255,0.1); color: white; }
        .nav-link.active { background-color: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--dourado); }

        .btn-sair { margin-top: auto; color: #ef4444; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px; text-align: center; border-radius: 6px; transition: 0.3s; font-weight: bold; }
        .btn-sair:hover { background: #ef4444; color: white; }

        /* MAIN CONTENT */
        .main-content { flex-grow: 1; margin-left: 260px; padding: 3rem; display: flex; justify-content: center; align-items: flex-start; }
        .form-card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); width: 100%; max-width: 700px; border: 1px solid var(--borda); }
        .form-card h1 { font-family: 'Playfair Display', serif; color: var(--verde); font-size: 1.8rem; margin-top: 0; margin-bottom: 0.5rem; }
        .form-card > p { color: #64748b; font-size: 0.95rem; margin-bottom: 2rem; }
        
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        label { font-size: 0.85rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select { padding: 1rem; border: 1px solid var(--borda); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; background: #f1f5f9; transition: 0.3s; outline: none; }
        input:focus, select:focus { border-color: var(--dourado); background: white; box-shadow: 0 0 0 3px rgba(200,151,58,0.1); }
        
        .file-upload-box { border: 2px dashed var(--verde-claro); padding: 2.5rem; text-align: center; border-radius: 8px; background: rgba(10, 79, 79, 0.02); cursor: pointer; transition: 0.3s; }
        .file-upload-box:hover { background: rgba(10, 79, 79, 0.05); border-color: var(--verde); }
        .file-upload-box input[type="file"] { display: none; }
        .file-upload-label { font-weight: 600; color: var(--verde); cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 0.8rem; }
        .file-icon { font-size: 2.5rem; color: var(--dourado); }

        .btn-submit { background: var(--verde); color: white; border: none; padding: 1.2rem; border-radius: 8px; font-size: 1.05rem; font-weight: 700; margin-top: 2rem; cursor: pointer; transition: 0.3s; width: 100%; text-transform: uppercase; letter-spacing: 1px; }
        .btn-submit:hover { background: var(--verde-claro); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(10,79,79,0.2); }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; }
        .alert.sucesso { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .alert.erro { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }

        #nomeFicheiro { margin-top: 15px; font-size: 0.9rem; color: #0a4f4f; font-weight: bold; background: #ccfbf1; display: inline-block; padding: 4px 10px; border-radius: 4px; display: none;}
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div style="text-align: center; padding: 20px 10px;">
            <a href="admin_dashboard.php">
                <img src="logo-contabil-verde.png" alt="Logo Nexus Contábil" style="max-width: 100%; max-height: 60px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto;">
            </a>
            <p style="margin: 0; font-size: 0.85rem; color: var(--dourado); text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">Módulo de Gestão</p>
        </div>
        
        <nav class="nav-menu">
            <a href="admin_chamados.php" class="nav-link">
                <i>🎧</i> <span>Gestão de Chamados</span>
            </a>
            <a href="admin_upload.php" class="nav-link active">
                <i>📁</i> <span>Enviar Documentos</span>
            </a>
        </nav>
        
        <div style="margin-top: auto; padding: 20px;">
            <a href="?sair=1" class="btn-sair">Sair do Painel</a>
        </div>
    </aside>

    <main class="main-content">
        <div class="form-card">
            <h1>Disponibilizar Documento</h1>
            <p>Envie um PDF para o Cofre Digital do cliente. Ele ficará disponível imediatamente no portal.</p>

            <?= $mensagem ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    
                    <div class="form-group">
                        <label>Cliente / Empresa Destinatária</label>
                        <select name="cnpj_cliente" required>
                            <option value="">-- Selecione o Cliente --</option>
                            <?php foreach($clientes as $cli): ?>
                                <option value="<?= htmlspecialchars($cli['cnpj']) ?>">
                                    <?= htmlspecialchars($cli['razao_social']) ?> (CNPJ: <?= htmlspecialchars($cli['cnpj']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Título do Documento</label>
                        <input type="text" name="titulo" required placeholder="Ex: Relatório Contábil Maio/2026...">
                    </div>

                    <div class="form-group">
                        <label>Categoria Oficial</label>
                        <select name="categoria" required>
                            <option value="Guias de Impostos">Guias de Impostos</option>
                            <option value="RPA">Recibo de Pagamento (RPA)</option>
                            <option value="Balanços">Balanços / Contábil</option>
                            <option value="Diversos">Diversos / Outros</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 1rem;">
                        <label>Anexar Ficheiro (Somente PDF)</label>
                        <div class="file-upload-box" onclick="document.getElementById('arquivoPdf').click();">
                            <label class="file-upload-label">
                                <span class="file-icon">📁</span>
                                Clique para selecionar o ficheiro PDF
                            </label>
                            <input type="file" id="arquivoPdf" name="arquivo" accept="application/pdf" required onchange="mostrarNomeArquivo(this)">
                            <div id="nomeFicheiro"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Fazer Upload Seguro</button>
            </form>
        </div>
    </main>

    <script>
        function mostrarNomeArquivo(input) {
            const divNome = document.getElementById('nomeFicheiro');
            if(input.files && input.files[0]) {
                divNome.innerText = '✓ Ficheiro pronto: ' + input.files[0].name;
                divNome.style.display = 'inline-block';
            }
        }
    </script>
</body>
</html>