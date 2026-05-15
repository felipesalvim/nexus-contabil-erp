<?php

session_start();
require 'vendor/autoload.php';
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: area_cliente.html");
    exit;
}

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// --- RATE LIMITING simples via sessão (sem Redis / sem APCu) ---
// Para produção, use APCu, Redis ou uma tabela de tentativas no banco.
$ip = $_SERVER['REMOTE_ADDR'];
$chave_tentativas = 'login_attempts_' . md5($ip);
$chave_bloqueio   = 'login_blocked_'  . md5($ip);

if (!isset($_SESSION[$chave_tentativas])) {
    $_SESSION[$chave_tentativas] = 0;
    $_SESSION[$chave_bloqueio]   = 0;
}

// Verifica se o IP está bloqueado (5 min após 5 falhas)
if ($_SESSION[$chave_bloqueio] > time()) {
    $restante = ceil(($_SESSION[$chave_bloqueio] - time()) / 60);
    header("Location: area_cliente.html?erro=bloqueado&min=$restante");
    exit;
}

// Busca usuário (com JOIN para razao_social)
$stmt = $pdo->prepare("
    SELECT u.id, u.cnpj_cliente, u.senha_hash, t.razao_social
    FROM usuarios_portal u
    JOIN tomadores_empresas t ON u.cnpj_cliente = t.cnpj
    WHERE u.email_acesso = ?
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($senha, $user['senha_hash'])) {
    // Login bem-sucedido — zera tentativas e regenera sessão
    $_SESSION[$chave_tentativas] = 0;
    $_SESSION[$chave_bloqueio]   = 0;

    // Previne fixação de sessão
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_nome'] = $user['razao_social'];
    $_SESSION['user_cnpj'] = $user['cnpj_cliente'];

    header("Location: dashboard.php");
    exit;
} else {
    // Falha: incrementa contador
    $_SESSION[$chave_tentativas]++;

    if ($_SESSION[$chave_tentativas] >= 5) {
        // Bloqueia por 5 minutos
        $_SESSION[$chave_bloqueio]   = time() + 300;
        $_SESSION[$chave_tentativas] = 0;
    }

    // Mensagem genérica — não revela se o e-mail existe
    header("Location: area_cliente.html?erro=1");
    exit;
}