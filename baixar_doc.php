<?php
session_start();

// 1. Verificações de Segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_cnpj'])) {
    die("Acesso negado. Por favor, faça login no portal.");
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID do documento não informado.");
}

$doc_id = intval($_GET['id']);

require_once __DIR__ . '/db.php';

// 3. Busca o documento
$stmt = $pdo->prepare("SELECT titulo, caminho_arquivo FROM documentos_portal WHERE id = ? AND cnpj_cliente = ?");
$stmt->execute([$doc_id, $_SESSION['user_cnpj']]);
$documento = $stmt->fetch();

if (!$documento) {
    die("Documento não encontrado na sua conta ou você não tem permissão para acessá-lo.");
}

// 4. Tratamento Híbrido do Caminho Físico (Funciona no Windows/XAMPP e no Linux/HostGator)
$caminho_arquivo_bd = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $documento['caminho_arquivo']);
$caminho_fisico = __DIR__ . DIRECTORY_SEPARATOR . $caminho_arquivo_bd;

// Verifica se o arquivo realmente existe na pasta
if (!file_exists($caminho_fisico) || !is_file($caminho_fisico)) {
    die("Erro 404: O arquivo não foi encontrado no servidor. Por favor, contate o suporte.");
}

// 5. Preparação para o Download (Remove acentos do nome para evitar erro no download)
$nome_limpo = preg_replace('/[^A-Za-z0-9\- \_]/', '', $documento['titulo']);
$nome_arquivo_download = $nome_limpo . ".pdf";

// 6. LIMPEZA DE BUFFER (A mágica que evita a tela branca ou PDF corrompido)
if (ob_get_level()) {
    ob_end_clean();
}

// 7. Força o Download Seguro via Headers do PHP
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nome_arquivo_download . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($caminho_fisico));

// 8. Lê e envia o arquivo para o navegador
readfile($caminho_fisico);
exit;
?>