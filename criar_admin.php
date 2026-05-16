<?php
// Credenciais de conexão do banco de dados
$host = 'localhost';
$dbname = 'omega381_nexus_rpa';
$username = 'omega381_nexus_rpa';
$password = 'Felipe1986@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // CONFIGURAÇÃO DO SEU USUÁRIO ADMINISTRADOR
    // ==========================================
    $email_admin = 'felipe@nexusinnova.com.br'; // O e-mail que você usará para logar
    $senha_desejada = 'Nexus2026';     // Altere para a senha que você deseja usar
    
    // Gera o hash seguro compatível com password_verify()
    $senha_criptografada = password_hash($senha_desejada, PASSWORD_DEFAULT);

    // IMPORTANTE: Altere 'usuarios' para o nome exato da sua tabela de administradores
    // e ajuste os nomes das colunas ('email', 'senha') se forem diferentes no seu banco.
    $tabela_admin = 'usuarios'; 

    $stmt = $pdo->prepare("INSERT INTO $tabela_admin (email, senha) VALUES (?, ?)");
    $stmt->execute([$email_admin, $senha_criptografada]);

    echo "<div style='font-family: sans-serif; padding: 20px; border: 2px solid #10b981; background: #dcfce7; border-radius: 8px; max-width: 500px; margin: 50px auto;'>";
    echo "<h2 style='color: #166534; margin-top:0;'>✓ Administrador criado com sucesso!</h2>";
    echo "<p><b>E-mail de acesso:</b> " . htmlspecialchars($email_admin) . "</p>";
    echo "<p style='color: #991b1b; font-weight: bold;'>⚠️ CRÍTICO: Delete o arquivo 'criar_admin.php' do seu servidor imediatamente por motivos de segurança.</p>";
    echo "</div>";

} catch (PDOException $e) {
    die("<div style='color:red; font-family:sans-serif; margin: 50px auto; max-width:500px;'><b>Erro ao salvar no banco:</b> " . $e->getMessage() . "</div>");
}
?>