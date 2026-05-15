<?php

/**
 * Conexão centralizada com o banco de dados.
 *
 * As credenciais são lidas do arquivo .env na raiz do projeto.
 * NUNCA coloque usuário/senha diretamente neste arquivo.
 *
 * Uso em qualquer arquivo PHP:
 *   require_once __DIR__ . '/db.php';
 *   // $pdo já está disponível
 */

// -----------------------------------------------------------------------
// 1. Carrega o .env (parser simples, sem dependência externa)
// -----------------------------------------------------------------------
$env_path = __DIR__ . '/.env';

if (!file_exists($env_path)) {
    error_log('[db.php] Arquivo .env não encontrado em ' . $env_path);
    http_response_code(500);
    die('Erro de configuração do servidor.');
}

$lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue; // ignora linhas em branco e comentários
    }
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// -----------------------------------------------------------------------
// 2. Cria a conexão PDO
// -----------------------------------------------------------------------
$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_name = $_ENV['DB_NAME'] ?? '';
$db_user = $_ENV['DB_USER'] ?? '';
$db_pass = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[db.php] Falha na conexão: ' . $e->getMessage());
    http_response_code(500);
    die('Erro de conexão com o banco de dados.');
}
