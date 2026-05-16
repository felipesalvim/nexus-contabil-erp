<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Barreira de Segurança Centralizada
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Define a página atual para marcar o menu lateral como ativo
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Painel Admin | Nexus Contábil' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --verde: #0a4f4f;
            --verde-claro: #147369;
            --dourado: #c8973a;
            --dourado-hover: #b0822e;
            --borda: #cbd5e1;
            --bg: #f8fafc;
            --texto: #334155;
            --texto-claro: #64748b;
            --card-bg: #ffffff;
            
            /* Efeitos de Feedback Visual (Glow) */
            --glow-focus: rgba(10, 79, 79, 0.15);
            --glow-dourado: rgba(200, 151, 58, 0.2);
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg);
            color: var(--texto);
            margin: 0;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* SIDEBAR UNIFICADA CORRIGIDA */
        .admin-sidebar {
            width: 260px;
            background: #0f172a;
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: var(--transition-smooth);
            box-sizing: border-box;
        }
        
        .nav-menu {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-top: 1.5rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 0.9rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition-smooth);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            font-size: 0.95rem;
            border-left: 4px solid transparent; /* CORREÇÃO DO DESALINHAMENTO */
            white-space: nowrap;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.08);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background-color: rgba(255,255,255,0.12);
            color: white;
            border-left: 4px solid var(--dourado);
            font-weight: 600;
        }

        /* ESTILO DOS ÍCONES VETORIAIS (SVG) */
        .nav-icon {
            width: 20px;
            height: 20px;
            stroke: rgba(255,255,255,0.7);
            transition: var(--transition-smooth);
        }

        .nav-link:hover .nav-icon, .nav-link.active .nav-icon {
            stroke: var(--dourado); /* Ícone fica dourado quando ativo */
        }

        .btn-sair {
            margin-top: auto;
            color: #ef4444;
            text-decoration: none;
            font-size: 0.9rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            transition: var(--transition-smooth);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-sair:hover { background: #ef4444; color: white; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2); }

        /* CONTEÚDO PRINCIPAL E MOBILE */
        .main-content {
            flex-grow: 1;
            margin-left: 260px;
            padding: 3rem;
            min-height: 100vh;
            box-sizing: border-box;
            transition: var(--transition-smooth);
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .mobile-header {
            display: none;
            background: #0f172a;
            color: white;
            padding: 1rem 1.5rem;
            position: fixed;
            top: 0; left: 0; right: 0; height: 60px;
            z-index: 999;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }

        .hamburger-btn { background: none; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 0; display: flex; align-items: center; }

        /* FORMULÁRIOS E TABELAS */
        .form-card, .table-card { background: var(--card-bg); padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.03); width: 100%; border: 1px solid rgba(226, 232, 240, 0.8); box-sizing: border-box; }
        .form-card h1, .table-card h1 { font-family: 'Playfair Display', serif; color: var(--verde); font-size: 1.9rem; margin-top: 0; margin-bottom: 0.5rem; font-weight: 700; }
        .subtitle { color: var(--texto-claro); font-size: 0.95rem; margin-bottom: 2rem; margin-top: 0; }
        label { font-size: 0.8rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 0.4rem; display: block; }
        input, select, textarea { width: 100%; padding: 0.9rem 1.1rem; border: 1px solid var(--borda); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; background: #f8fafc; transition: var(--transition-smooth); outline: none; box-sizing: border-box; color: var(--texto); }
        input:focus, select:focus, textarea:focus { border-color: var(--verde); background: #ffffff; box-shadow: 0 0 0 4px var(--glow-focus); }

        /* CORREÇÃO DOS BOTÕES DA TABELA (Impede quebra de linha) */
        td { white-space: nowrap; }

        .btn-action-primary { background: var(--verde); color: white; border: none; padding: 1.1rem; border-radius: 8px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); width: 100%; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 12px rgba(10, 79, 79, 0.15); }
        .btn-action-primary:hover { background: var(--verde-claro); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(10, 79, 79, 0.25); }

        @media (max-width: 992px) {
            .admin-sidebar { left: -260px; }
            .admin-sidebar.open { left: 0; box-shadow: 5px 0 25px rgba(0,0,0,0.2); }
            .mobile-header { display: flex; }
            .main-content { margin-left: 0; padding: 6rem 1.5rem 3rem 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="mobile-header">
        <button class="hamburger-btn" id="menuToggle">☰</button>
        <span style="font-family: 'Playfair Display', serif; font-weight: bold; font-size: 1.1rem; letter-spacing: 0.5px;">Nexus Módulo Admin</span>
        <div style="width: 28px;"></div>
    </div>

    <aside class="admin-sidebar" id="sidebar">
        <div style="text-align: center; padding: 10px 0 20px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
            <a href="admin_upload.php" style="text-decoration: none; display: block;">
                <img src="logo-contabil-verde.png" alt="Logo Nexus Contábil" style="max-width: 100%; max-height: 55px; margin-bottom: 8px; display: block; margin-left: auto; margin-right: auto;">
            </a>
            <p style="margin: 0; font-size: 0.8rem; color: var(--dourado); text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold;">Módulo de Gestão</p>
        </div>
        
        <nav class="nav-menu">
            <a href="admin_usuarios.php" class="nav-link <?= ($pagina_atual == 'admin_usuarios.php') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <span>Gestão de Usuários</span>
            </a>

            <a href="admin_chamados.php" class="nav-link <?= ($pagina_atual == 'admin_chamados.php') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
                <span>Gestão de Chamados</span>
            </a>

            <a href="admin_upload.php" class="nav-link <?= ($pagina_atual == 'admin_upload.php') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>Enviar Documentos</span>
            </a>
        </nav>
        
        <div style="padding-top: 20px;">
            <a href="admin_logout.php" class="btn-sair">Sair do Painel</a>
        </div>
    </aside>

    <script>
        // Script do Menu Responsivo Hambúrguer
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');

        if(menuToggle && sidebar) {
            menuToggle.addEventListener('click', (e) => {
                sidebar.classList.toggle('open');
                e.stopPropagation();
            });

            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && !sidebar.contains(e.target) && sidebar.classList.contains('open')) {
                    sidebar.classList.remove('open');
                }
            });
        }
    </script>