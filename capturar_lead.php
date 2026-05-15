<?php
require_once __DIR__ . '/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = htmlspecialchars($_POST['nome'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $material = htmlspecialchars($_POST['material'] ?? 'NПлкo especificado');

    if (!empty($nome) && !empty($email)) {
        try {

            $stmt = $pdo->prepare("INSERT INTO leads_materiais (nome, email, material_baixado) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $email, $material]);
            
            echo "Sucesso";
        } catch (PDOException $e) {
            echo "Erro: " . $e->getMessage();
        }
    } else {
        echo "Dados incompletos";
    }
}
?>