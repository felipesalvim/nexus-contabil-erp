<?php
session_start();
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado. Por favor, faça login no portal.");
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID do documento não informado.");
}

$rpa_id = intval($_GET['id']);

require_once __DIR__ . '/db.php';

// 3. Busca os dados do RPA
$stmt = $pdo->prepare("
    SELECT r.*, 
           p.nome_completo as prestador_nome, p.cpf as prestador_cpf, 
           t.razao_social as tomador_nome, t.cnpj as tomador_cnpj, t.cep_sede, t.cidade_sede
    FROM `rpa_emissões` r
    JOIN tomadores_empresas t ON r.tomador_id = t.id
    JOIN prestadores_autonomos p ON r.prestador_id = p.id
    WHERE r.id = ? AND t.cnpj = ?
");
$stmt->execute([$rpa_id, $_SESSION['user_cnpj']]);
$rpa = $stmt->fetch();

if (!$rpa) {
    die("Documento não encontrado ou você não tem permissão para acessá-lo.");
}

// 4. Formatação e Cálculos para o Anexo Técnico
function moedab($valor) { return 'R$ ' . number_format($valor, 2, ',', '.'); }
$dataEmissaoFormatada = date('d/m/Y', strtotime($rpa['data_emissao']));

// Cálculos reversos simples para exibir a base de cálculo no anexo
$base_irrf = $rpa['valor_servico_bruto'] - $rpa['valor_inss_retido']; // Simplificado sem dependentes do DB

// 5. Geração do QR Code
$hashAutenticacao = strtoupper(substr(md5(strtotime($rpa['data_emissao']) . $rpa['prestador_cpf']), 0, 12));

// CORRIGIDO: Aponta diretamente para a produção na HostGator com o hash correto
$urlValidacao = "https://contabil.nexusinnova.com.br/validar.php?doc=" . $hashAutenticacao;

$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($urlValidacao);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $qrCodeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$qrCodeImage = curl_exec($ch);
curl_close($ch);

if ($qrCodeImage) {
    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeImage);
    $imgQrCode = "<img src='{$qrCodeBase64}' width='90' height='90' style='border: 2px solid #0a4f4f; padding: 3px; border-radius: 4px;'>";
} else {
    $imgQrCode = "<div style='width: 90px; height: 90px; border: 1px solid #ccc;'>QR CODE</div>";
}

// 6. Montagem do HTML Premium para o PDF
$html_pdf = "
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 0px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #334155; margin: 40px 50px; }
        
        /* CABEÇALHO */
        .header { background-color: #0a4f4f; color: white; padding: 25px 30px; margin: -40px -50px 30px -50px; }
        .header table { width: 100%; }
        .brand h1 { margin: 0; color: #c8973a; font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .brand p { margin: 2px 0 0 0; font-size: 10px; color: #e2e8f0; text-transform: uppercase; letter-spacing: 1px; }
        .doc-info { text-align: right; }
        .doc-info h2 { margin: 0; font-size: 18px; color: white; text-transform: uppercase; }
        .doc-info p { margin: 5px 0 0 0; font-size: 11px; color: #cbd5e1; }
        
        /* BLOCOS DE INFORMAÇÃO */
        .info-grid { width: 100%; border-collapse: separate; border-spacing: 15px 0; margin: 0 -7px 25px -7px; }
        .info-box { background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 15px; vertical-align: top; }
        .box-title { color: #0a4f4f; font-size: 10px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px; }
        .info-label { font-size: 9px; color: #64748b; text-transform: uppercase; display: block; margin-top: 5px; }
        .info-value { font-size: 12px; color: #0f172a; font-weight: bold; display: block; }
        
        /* TABELA FINANCEIRA */
        .valores-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        .valores-table th { background-color: #0f172a; color: white; padding: 12px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .valores-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
        .text-right { text-align: right !important; }
        .text-red { color: #ef4444; font-weight: bold; }
        .destaque-liquido td { background-color: #0a4f4f; color: #c8973a; font-size: 14px; font-weight: bold; padding: 15px 12px; border: none; }
        .destaque-liquido .val-liquido { color: white; font-size: 16px; }

        /* RODAPÉ E ASSINATURA */
        .footer-table { width: 100%; margin-top: 20px; }
        .legal-text { font-size: 9px; color: #64748b; text-align: justify; margin-bottom: 20px; line-height: 1.5; }
        .assinatura-box { text-align: center; padding-top: 30px; }
        .linha-assinatura { border-top: 1px solid #0f172a; width: 80%; margin: 0 auto; padding-top: 5px; }
        
        /* PÁGINA 2 - ANEXO TÉCNICO */
        .page-break { page-break-before: always; }
        .anexo-title { color: #0a4f4f; border-bottom: 2px solid #c8973a; padding-bottom: 10px; margin-bottom: 20px; }
        .anexo-item { background: #f8fafc; border-left: 4px solid #0a4f4f; padding: 12px; margin-bottom: 15px; font-size: 11px; }
    </style>
</head>
<body>

<div class='header'>
        <table>
            <tr>
                <td class='brand'>
                    <img src='https://contabil.nexusinnova.com.br/logo-contabil-verde.png' alt='Nexus Contábil' style='max-height: 45px; margin-bottom: 5px; display: block;'>
                </td>
                <td class='doc-title doc-info'>
                    <h2>Recibo de Pagamento (RPA)</h2>
                    <p>Competência: {$rpa['mes_competencia']}/{$rpa['ano_competencia']} | Emissão: {$dataEmissaoFormatada}</p>
                </td>
            </tr>
        </table>
    </div>
    
    <table class='info-grid'>
        <tr>
            <td class='info-box' width='50%'>
                <div class='box-title'>Tomador dos Serviços (Empresa/OSC)</div>
                <span class='info-label'>Razão Social</span>
                <span class='info-value'>{$rpa['tomador_nome']}</span>
                <span class='info-label'>CNPJ</span>
                <span class='info-value'>{$rpa['tomador_cnpj']}</span>
            </td>
            <td class='info-box' width='50%'>
                <div class='box-title'>Profissional Autônomo (Prestador)</div>
                <span class='info-label'>Nome Completo</span>
                <span class='info-value'>{$rpa['prestador_nome']}</span>
                <span class='info-label'>CPF</span>
                <span class='info-value'>{$rpa['prestador_cpf']}</span>
            </td>
        </tr>
    </table>

    <table class='valores-table'>
        <tr>
            <th>Descrição do Evento</th>
            <th class='text-right' width='25%'>Vencimentos</th>
            <th class='text-right' width='25%'>Descontos</th>
        </tr>
        <tr>
            <td>Valor Bruto dos Serviços Prestados</td>
            <td class='text-right'>" . moedab($rpa['valor_servico_bruto']) . "</td>
            <td></td>
        </tr>
        <tr>
            <td>INSS - Retenção Previdenciária</td>
            <td></td>
            <td class='text-right text-red'>" . moedab($rpa['valor_inss_retido']) . "</td>
        </tr>
        <tr>
            <td>IRRF - Imposto de Renda Retido na Fonte</td>
            <td></td>
            <td class='text-right text-red'>" . moedab($rpa['valor_irrf_retido']) . "</td>
        </tr>
        <tr>
            <td>ISS - Imposto Sobre Serviços Municipal</td>
            <td></td>
            <td class='text-right text-red'>" . moedab($rpa['valor_iss_retido']) . "</td>
        </tr>
        <tr class='destaque-liquido'>
            <td colspan='2'>LÍQUIDO A PAGAR AO PROFISSIONAL</td>
            <td class='text-right val-liquido'>" . moedab($rpa['valor_liquido_pago']) . "</td>
        </tr>
    </table>

    <div class='legal-text'>
        <strong>DECLARAÇÃO E COMPLIANCE:</strong> Declaro ter recebido a importância líquida especificada acima, dando plena e irrevogável quitação. Reconheço que a presente contratação tem natureza estritamente cível e autônoma, não gerando qualquer vínculo empregatício nos termos do art. 3º da CLT. Estou ciente de que a empresa tomadora efetuou as retenções legais (Lei 8.212/91) e declaro concordância com o processamento dos meus dados para fins fiscais (LGPD - Lei 13.709/18).
    </div>

    <table class='footer-table'>
        <tr>
            <td width='25%'>
                {$imgQrCode}<br>
                <span style='font-size: 9px; color: #64748b; display: block; margin-top: 5px;'>Autenticação Eletrônica:<br><strong>{$hashAutenticacao}</strong></span>
            </td>
            <td width='75%' class='assinatura-box'>
                <div class='linha-assinatura'>
                    <strong>{$rpa['prestador_nome']}</strong><br>
                    <span style='font-size: 10px; color: #64748b;'>CPF: {$rpa['prestador_cpf']}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class='page-break'></div>
    
    <div class='header' style='padding: 15px 30px; margin-bottom: 30px;'>
        <table>
            <tr>
                <td class='brand'>
                    <img src='https://contabil.nexusinnova.com.br/logo-contabil-verde.png' alt='Nexus Contábil' style='max-height: 45px; margin-bottom: 5px; display: block;'>
                </td>
                <td class='doc-info'><h2>Anexo Técnico Tributário</h2></td>
            </tr>
        </table>
    </div>

    <h3 class='anexo-title'>Memória de Cálculo - eSocial (Eventos S-1200 e S-1210)</h3>
    <p style='font-size: 11px; margin-bottom: 20px;'>Este relatório detalha os parâmetros matemáticos aplicados pelo motor tributário da Nexus Contábil, garantindo a transparência das retenções perante o fisco e o profissional prestador.</p>

    <div class='anexo-item'>
        <strong>1. APURAÇÃO DA CONTRIBUIÇÃO PREVIDENCIÁRIA (INSS)</strong><br><br>
        Base de Cálculo Bruta: " . moedab($rpa['valor_servico_bruto']) . "<br>
        Alíquota Legal Aplicada: 11%<br>
        <strong>Retenção Efetiva de INSS: " . moedab($rpa['valor_inss_retido']) . "</strong>
    </div>

    <div class='anexo-item'>
        <strong>2. APURAÇÃO DO IMPOSTO DE RENDA (IRRF)</strong><br><br>
        Base de Cálculo para o IRRF (Bruto - INSS - Deduções Legais): " . moedab($base_irrf) . "<br>
        Enquadramento: Tabela Progressiva Mensal do Exercício.<br>
        <strong>IRRF Retido Final: " . moedab($rpa['valor_irrf_retido']) . "</strong>
    </div>

    <div class='anexo-item'>
        <strong>3. APURAÇÃO DO IMPOSTO MUNICIPAL (ISS)</strong><br><br>
        <strong>ISS Retido Final: " . moedab($rpa['valor_iss_retido']) . "</strong>
    </div>

    <p style='text-align: center; color: #94a3b8; font-size: 10px; margin-top: 50px;'>Documento gerado e validado eletronicamente por <strong>Nexus Contábil ERP</strong>.</p>

</body>
</html>
";

// 7. Instancia o Dompdf e força a exibição
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html_pdf);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nomeArquivo = "RPA_" . preg_replace('/[^A-Za-z0-9\-]/', '', $rpa['prestador_nome']) . "_" . $rpa['mes_competencia'] . ".pdf";

if (ob_get_contents()) ob_end_clean();

// False para abrir no navegador, True para forçar o download direto
$dompdf->stream($nomeArquivo, array("Attachment" => false));
?>