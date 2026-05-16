<?php

session_start();

// Verifica se os dados foram enviados via método POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Recebe e higieniza os dados de entrada
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $senha = $_POST['senha'] ?? '';

    // Se o e-mail for inválido ou os campos estiverem vazios, retorna com erro
    if (!$email || empty($senha)) {
        header("Location: admin_login.php?erro=1");
        exit;
    }

    // 2. Conexão centralizada via db.php (credenciais no .env — nunca hardcoded aqui)
    require_once __DIR__ . '/db.php';

    try {
        // 3. Consulta o usuário na tabela 'usuarios'
        //    COLLATE explícito porque a tabela foi criada sem collation (utf8mb4_general_ci)
        //    enquanto o restante do banco usa utf8mb4_unicode_ci — evita erro 1267.
        $stmt = $pdo->prepare("
            SELECT id, email, senha
            FROM usuarios
            WHERE email COLLATE utf8mb4_unicode_ci = ? LIMIT 1
        ");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // 4. Validação Criptográfica da Senha
        if ($usuario && password_verify($senha, $usuario['senha'])) {

            // Define as variáveis de sessão do painel administrativo
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $usuario['id'];
            $_SESSION['user_id']         = $usuario['id'];   // compatibilidade com admin_header.php
            $_SESSION['user_email']      = $usuario['email'];

            // Força a renovação do ID da sessão (evita fixação de sessão)
            session_regenerate_id(true);

            // Redireciona para o painel principal
            header("Location: admin_upload.php");
            exit;

        } else {
            // Senha incorreta ou usuário não encontrado — mensagem genérica (não revela qual falhou)
            header("Location: admin_login.php?erro=1");
            exit;
        }

    } catch (PDOException $e) {
        error_log('[auth.php] Falha na autenticação: ' . $e->getMessage());
        die("Erro interno de autenticação. Por favor, tente mais tarde.");
    }

} else {
    // Acesso direto ao arquivo via URL — redireciona para o formulário
    header("Location: admin_login.php");
    exit;
}