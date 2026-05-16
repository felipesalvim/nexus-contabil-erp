<?php
session_start();

// Se o utilizador já estiver logado, redireciona diretamente para o painel
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_upload.php"); // <-- Alterado aqui!
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo | Nexus Contábil</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --verde-escuro: #0a4f4f;
            --dourado: #c8973a;
            --fundo: #f8fafc;
            --texto: #334155;
            --borda: #e2e8f0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--fundo);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--texto);
        }

        .login-container {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--borda);
        }

        .login-header {
            background-color: var(--verde-escuro);
            padding: 30px 20px;
            text-align: center;
            border-bottom: 4px solid var(--dourado);
        }

        .login-header img {
            max-height: 50px;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .login-header p {
            margin: 0;
            color: #e2e8f0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 500;
        }

        .login-body {
            padding: 40px 30px;
        }

        .login-body h2 {
            margin: 0 0 25px 0;
            font-family: 'Playfair Display', serif;
            color: var(--verde-escuro);
            font-size: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            font-family: 'DM Sans', sans-serif;
            color: #0f172a;
            border: 1px solid var(--borda);
            border-radius: 6px;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dourado);
            box-shadow: 0 0 0 3px rgba(200, 151, 58, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background-color: var(--dourado);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #b0822e;
        }

        .login-footer {
            background-color: #f1f5f9;
            padding: 15px;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Estilo para mensagens de erro */
        .alert-error {
            background-color: #fef2f2;
            color: #ef4444;
            border: 1px solid #f87171;
            padding: 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <img src="logo-contabil-verde.png" alt="Logo Nexus Contábil">
            <p>Módulo de Gestão</p>
        </div>

        <div class="login-body">
            <h2>Acesso Restrito</h2>

            <?php if (isset($_GET['erro'])): ?>
                <div class="alert-error">
                    E-mail ou senha incorretos. Por favor, tente novamente.
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST">
                <div class="form-group">
                    <label for="email">E-mail Corporativo</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="admin@nexusinnova.com.br" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="senha">Senha de Acesso</label>
                    <input type="password" id="senha" name="senha" class="form-control" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-login">Entrar no Sistema</button>
            </form>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Nexus Contábil ERP. Todos os direitos reservados.
        </div>
    </div>

</body>
</html>