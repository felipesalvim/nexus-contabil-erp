<?php

session_start();

// --- 1. AUTENTICAÇÃO: apenas admins podem criar usuários ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

require 'db.php';

// --- 2. GERAÇÃO DO TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Valida token CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("Requisição inválida (CSRF).");
    }

    $nome  = trim($_POST['nome']  ?? '');
    $email = trim($_POST['email'] ?? '');
    $cnpj  = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $senha_plana = $_POST['senha'] ?? '';

    // Validações básicas
    if (empty($nome) || empty($email) || empty($cnpj) || empty($senha_plana)) {
        $mensagem = "<div class='msg erro'>Todos os campos são obrigatórios.</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "<div class='msg erro'>E-mail inválido.</div>";
    } elseif (strlen($senha_plana) < 8) {
        $mensagem = "<div class='msg erro'>A senha deve ter ao menos 8 caracteres.</div>";
    } else {
        $senha_hash = password_hash($senha_plana, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios_portal (nome, email, senha, cnpj_vinculado) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$nome, $email, $senha_hash, $cnpj]);
            $mensagem = "<div class='msg sucesso'>Usuário cadastrado com sucesso!</div>";
        } catch (PDOException $e) {
            error_log("Erro ao criar usuário: " . $e->getMessage());
            $mensagem = "<div class='msg erro'>Erro ao cadastrar. Verifique se o e-mail já está em uso.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Cliente no Portal</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 40px; display: flex; justify-content: center; }
        .form-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 14px; color: #334155; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        button { background: #0a4f4f; color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .msg { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .msg.sucesso { background: #e6ffed; color: #166534; border: 1px solid #166534; }
        .msg.erro    { background: #fef2f2; color: #991b1b; border: 1px solid #ef4444; }
    </style>
</head>
<body>
<div class="form-box">
    <h2 style="margin-top:0;color:#0a4f4f;">Cadastrar Novo Cliente</h2>
    <?= $mensagem ?>
    <form method="POST">
        <!-- CSRF token -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="form-group">
            <label>Nome do Cliente / Empresa</label>
            <input type="text" name="nome" required placeholder="Ex: Associação Esperança">
        </div>
        <div class="form-group">
            <label>CNPJ Vinculado</label>
            <input type="text" name="cnpj" required placeholder="00.000.000/0001-00">
        </div>
        <div class="form-group">
            <label>E-mail de Login</label>
            <input type="email" name="email" required placeholder="contato@empresa.com">
        </div>
        <div class="form-group">
            <label>Senha de Acesso (mínimo 8 caracteres)</label>
            <input type="password" name="senha" required minlength="8" placeholder="Defina uma senha segura">
        </div>
        <button type="submit">Cadastrar no Portal</button>
    </form>
</div>
</body>
</html>