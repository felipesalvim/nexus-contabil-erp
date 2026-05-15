<?php
// Carrega o PHPMailer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Verifica se os dados foram enviados por POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Recebe e higieniza os dados do formulário
    $nome = htmlspecialchars($_POST['nome'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');
    $telefone = htmlspecialchars($_POST['telefone'] ?? '');
    $assunto = htmlspecialchars($_POST['assunto'] ?? 'Contato via Site');
    $mensagem = htmlspecialchars($_POST['mensagem'] ?? '');

    require_once __DIR__ . '/db.php';

    // 3. Configuração do Disparo de E-mails (PHPMailer)
    $mail = new PHPMailer(true);

    try {
        // Configurações do Servidor SMTP (Google)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        // ATENÇÃO: Coloque o seu e-mail do Gmail aqui
        $mail->Username   = 'felipesilvaalvim@gmail.com';
        // A sua Senha de Aplicativo do Google que já configuramos no RPA
        $mail->Password   = 'seqkjgppknrlyftg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // ====================================================================
        // A) E-MAIL PARA O CLIENTE (Auto-resposta Premium)
        // ====================================================================
        $mail->setFrom('seu_email_aqui@gmail.com', 'Nexus Contábil');
        $mail->addAddress($email, $nome);
        $mail->isHTML(true);
        $mail->Subject = 'Recebemos o seu contato! - Nexus Contábil';

        $corpo_cliente = '
        <div style="font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; padding: 40px 10px; margin: 0;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                
                <div style="background-color: #0a4f4f; padding: 35px 40px; text-align: center; border-bottom: 4px solid #c8973a;">
                    <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 600; letter-spacing: 1px;">Nexus Contábil</h1>
                </div>

                <div style="padding: 40px; color: #334155; line-height: 1.7; font-size: 16px;">
                    <h2 style="color: #0a4f4f; font-size: 22px; margin-top: 0;">Olá, ' . $nome . '!</h2>
                    <p>Recebemos a sua mensagem com sucesso. É um grande prazer ter o seu interesse nas soluções de gestão da <strong>Nexus Contábil</strong>.</p>

                    <div style="background-color: #f1f5f9; border-left: 4px solid #c8973a; padding: 15px 20px; margin: 30px 0; border-radius: 0 8px 8px 0;">
                        <p style="margin: 0; font-size: 15px;"><strong>Assunto Registado:</strong> ' . $assunto . '</p>
                    </div>

                    <p>A nossa equipa de especialistas já está a analisar a sua solicitação. Entraremos em contato muito em breve através do número que nos forneceu: <strong>' . $telefone . '</strong>.</p>
                    
                    <p style="margin-top: 40px;">Atenciosamente,<br>
                    <strong style="color: #0a4f4f;">Equipa Comercial Nexus</strong></p>
                </div>

                <div style="background-color: #0f172a; padding: 20px 40px; text-align: center;">
                    <p style="color: #94a3b8; font-size: 13px; margin: 0;">
                        <strong>Nexus Contábil - Inteligência e Gestão</strong><br>
                        📍 Rua Dona Leopoldina, 800 - Centro, Fortaleza – CE
                    </p>
                </div>

            </div>
        </div>';

        $mail->Body = $corpo_cliente;
        $mail->send();

        // ====================================================================
        // B) E-MAIL PARA A EQUIPA NEXUS (Aviso de novo Lead)
        // ====================================================================
        $mail->clearAddresses(); // Limpa o destinatário anterior
        $mail->addAddress('seu_email_aqui@gmail.com', 'Equipe Comercial'); // E-mail da sua equipe
        $mail->Subject = "NOVO LEAD: $assunto";

        $corpo_equipe = "
        <h3>Novo Contato Recebido pelo Site</h3>
        <p><strong>Nome:</strong> $nome</p>
        <p><strong>E-mail:</strong> $email</p>
        <p><strong>Telefone:</strong> $telefone</p>
        <p><strong>Assunto:</strong> $assunto</p>
        <p><strong>Mensagem:</strong><br>$mensagem</p>
        ";

        $mail->Body = $corpo_equipe;
        $mail->send(); // Dispara para você

        // 4. Redirecionamento com Sucesso usando SweetAlert (Efeito Visual)
        echo "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <title>Enviando...</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            <style>body { background-color: #f8fafc; }</style>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Mensagem Enviada!',
                    text: 'A sua mensagem foi recebida e enviamos uma confirmação para o seu e-mail.',
                    confirmButtonColor: '#0a4f4f',
                    timer: 4000
                }).then(() => {
                    window.location.href = 'contato.html'; // Volta para a página de contato
                });
            </script>
        </body>
        </html>
        ";
        exit;
    } catch (Exception $e) {
        die("A mensagem foi salva, mas o e-mail falhou. Erro do Mailer: {$mail->ErrorInfo}");
    }
} else {
    // Se alguém tentar acessar o arquivo diretamente sem preencher o formulário
    header("Location: contato.html");
    exit;
}
