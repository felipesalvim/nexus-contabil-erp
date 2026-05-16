<?php
$page_title = "Disponibilizar Documento | Gestão Nexus";
include_once __DIR__ . '/admin_header.php'; // Puxa layout, segurança, CSS e Sidebar unificados

// Conexão centralizada com o banco de dados
require_once __DIR__ . '/db.php';

$mensagem = "";

// =========================================================================
// PROCESSAMENTO DO UPLOAD (apenas quando há POST)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['titulo'])) {

    $cnpj_cliente     = htmlspecialchars($_POST['cnpj_cliente']);
    $titulo           = htmlspecialchars($_POST['titulo']);
    $categoria        = htmlspecialchars($_POST['categoria']);

    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {

        $pasta_destino  = "uploads/" . preg_replace("/[^0-9]/", "", $cnpj_cliente) . "/";
        if (!is_dir($pasta_destino)) {
            mkdir($pasta_destino, 0755, true);
        }

        $nome_ficheiro    = preg_replace("/[^a-zA-Z0-9.\-_]/", "_", basename($_FILES["arquivo"]["name"]));
        $caminho_completo = $pasta_destino . $nome_ficheiro;
        $tipo_ficheiro    = strtolower(pathinfo($caminho_completo, PATHINFO_EXTENSION));

        if ($tipo_ficheiro !== "pdf") {
            $mensagem = "<div class='alert erro'>Por favor, envie apenas ficheiros no formato PDF.</div>";
        } else {
            if (move_uploaded_file($_FILES["arquivo"]["tmp_name"], $caminho_completo)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO documentos_portal (cnpj_cliente, titulo, categoria, caminho_arquivo) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$cnpj_cliente, $titulo, $categoria, $caminho_completo]);
                    $mensagem = "<div class='alert sucesso'>Documento enviado! O cliente já pode aceder no seu Cofre Digital.</div>";
                } catch (Exception $e) {
                    $mensagem = "<div class='alert erro'>Erro ao gravar na base de dados: " . $e->getMessage() . "</div>";
                }
            } else {
                $mensagem = "<div class='alert erro'>Ocorreu um erro ao guardar o ficheiro no servidor.</div>";
            }
        }

    } else {
        $mensagem = "<div class='alert erro'>Nenhum ficheiro foi anexado ou o limite de tamanho foi excedido.</div>";
    }

} // fim do bloco POST — fechamento correto

// =========================================================================
// BUSCA A LISTA DE CLIENTES (sempre, independente de POST ou GET)
// Ficava dentro do if() acima — causava $pdo undefined em requisições GET
// =========================================================================
try {
    $stmtClientes = $pdo->query("SELECT razao_social, cnpj FROM tomadores_empresas ORDER BY razao_social ASC");
    $clientes     = $stmtClientes->fetchAll();
} catch (PDOException $e) {
    $clientes = [];
    $mensagem = "<div class='alert erro'>Erro ao carregar lista de clientes: " . $e->getMessage() . "</div>";
}

?>

    <style>
        /* Correção Global de Interface (Evita que o menu lateral quebre linha) */
        .nav-link { white-space: nowrap !important; }

        /* Estilização Premium do Formulário */
        .upload-form-container {
            display: flex;
            flex-direction: column;
            gap: 1.8rem;
        }
        .input-nexus {
            width: 100%;
            padding: 1.1rem 1.2rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            color: #334155;
            background-color: #f8fafc;
            transition: all 0.3s ease;
            box-sizing: border-box;
            outline: none;
        }
        .input-nexus:focus {
            border-color: var(--verde);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(10, 79, 79, 0.1);
        }

        /* Área de Upload (Drag & Drop visual) */
        .dropzone-nexus {
            border: 2px dashed var(--verde-claro);
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background-color: rgba(10, 79, 79, 0.02);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        .dropzone-nexus:hover {
            background-color: rgba(10, 79, 79, 0.05);
            border-color: var(--dourado);
            transform: translateY(-2px);
        }
        .dropzone-nexus input[type="file"] { display: none; }

        .file-icon {
            font-size: 3rem;
            color: var(--dourado);
            transition: transform 0.3s ease;
        }
        .dropzone-nexus:hover .file-icon {
            transform: scale(1.1);
        }
        .file-label-text {
            font-weight: 700;
            color: var(--verde);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }

        /* Botão Principal */
        .btn-nexus-primary {
            background-color: var(--verde);
            color: #ffffff;
            border: none;
            padding: 1.2rem;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(10, 79, 79, 0.15);
        }
        .btn-nexus-primary:hover {
            background-color: var(--dourado);
            box-shadow: 0 6px 20px rgba(200, 151, 58, 0.25);
            transform: translateY(-2px);
        }

        /* Alertas de Feedback */
        .alert { padding: 1.2rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; font-size: 0.95rem; animation: fadeInUp 0.4s ease; }
        .alert.sucesso { background: #dcfce7; color: #166534; border: 1px solid #22c55e; border-left: 5px solid #166534; }
        .alert.erro    { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; border-left: 5px solid #991b1b; }

        #nomeFicheiro {
            margin-top: 15px;
            font-size: 0.9rem;
            color: var(--verde);
            font-weight: bold;
            background: #ccfbf1;
            padding: 8px 16px;
            border-radius: 6px;
            display: none;
            border: 1px solid rgba(20, 115, 105, 0.2);
        }
    </style>

    <main class="main-content">
        <div class="form-card">
            <h1>Disponibilizar Documento</h1>
            <p class="subtitle">Envie um PDF para o Cofre Digital do cliente. Ele ficará disponível imediatamente no portal.</p>

            <?= $mensagem ?>

            <form action="" method="POST" enctype="multipart/form-data">
                <div class="upload-form-container">

                    <div class="form-group">
                        <label>Cliente / Empresa Destinatária</label>
                        <select name="cnpj_cliente" class="input-nexus" required>
                            <option value="">-- Selecione o Cliente --</option>
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?= htmlspecialchars($cli['cnpj']) ?>">
                                    <?= htmlspecialchars($cli['razao_social']) ?> (CNPJ: <?= htmlspecialchars($cli['cnpj']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Título do Documento</label>
                        <input type="text" name="titulo" class="input-nexus" required placeholder="Ex: Relatório Contábil Maio/2026...">
                    </div>

                    <div class="form-group">
                        <label>Categoria Oficial</label>
                        <select name="categoria" class="input-nexus" required>
                            <option value="Guias de Impostos">Guias de Impostos</option>
                            <option value="RPA">Recibo de Pagamento (RPA)</option>
                            <option value="Balanços">Balanços / Contábil</option>
                            <option value="Diversos">Diversos / Outros</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Anexar Ficheiro (Somente PDF)</label>
                        <div class="dropzone-nexus" onclick="document.getElementById('arquivoPdf').click();">
                            <span class="file-icon">📄</span>
                            <span class="file-label-text">Clique para selecionar o ficheiro PDF</span>
                            <input type="file" id="arquivoPdf" name="arquivo" accept="application/pdf" required onchange="mostrarNomeArquivo(this)">
                            <div id="nomeFicheiro"></div>
                        </div>
                    </div>

                </div>
                <button type="submit" class="btn-nexus-primary">Fazer Upload Seguro</button>
            </form>
        </div>
    </main>

    <script>
        function mostrarNomeArquivo(input) {
            const divNome = document.getElementById('nomeFicheiro');
            if (input.files && input.files[0]) {
                divNome.innerText = '✅ Ficheiro pronto: ' + input.files[0].name;
                divNome.style.display = 'inline-block';
            }
        }
    </script>

</body>
</html>