<?php
// Exige o autoloader do Composer para carregar o Dompdf e PHPMailer
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// =========================================================================
// 1. CONFIGURAÇÃO DO BANCO DE DADOS
// =========================================================================
require_once __DIR__ . '/db.php';

// =========================================================================
// 2. COLETA DE DADOS DO FRONTEND
// =========================================================================
$nomePrestador = $_POST['nome_prestador'] ?? '';
$cpfPrestador = preg_replace('/[^0-9]/', '', $_POST['cpf_prestador'] ?? ''); 
$emailPrestador = $_POST['email_prestador'] ?? '';
$cepPrestador = preg_replace('/[^0-9]/', '', $_POST['cep_prestador'] ?? '');
$cidadePrestador = $_POST['cidade_prestador'] ?? '';

$empresaTomador = $_POST['empresa_tomador'] ?? '';
$cnpjTomador = preg_replace('/[^0-9]/', '', $_POST['cnpj_tomador'] ?? '');   
$cepTomador = preg_replace('/[^0-9]/', '', $_POST['cep_tomador'] ?? '');
$cidadeTomador = $_POST['cidade_tomador'] ?? '';
$enderecoTomador = $_POST['endereco_tomador'] ?? 'Endereço não informado';

$descricaoServico = $_POST['descricao_servico'] ?? '';
$cidadeISS = $_POST['cidade_iss'] ?? 'Município de Prestação';
$valorBruto = floatval($_POST['valor_bruto'] ?? 0);
$inssJaRetidoMes = floatval($_POST['inss_retido_mes'] ?? 0);
$qtdDependentes = intval($_POST['dependentes'] ?? 0);
$aliquotaISS = floatval($_POST['aliquota_iss'] ?? 0) / 100;

// =========================================================================
// 3. MOTOR DE CÁLCULO
// =========================================================================
$tetoINSS = 8475.55;
$tetoDescontoINSS = 932.31;

$inssCalculado = $valorBruto * 0.11;
$espacoNoTeto = $tetoDescontoINSS - $inssJaRetidoMes;
$inssRPA = max(0, min($inssCalculado, $espacoNoTeto));

$deducaoDependentes = $qtdDependentes * 189.59;
$baseIRRF = $valorBruto - $inssRPA - $deducaoDependentes;
$irrf = 0;
$statusIRRF = '';
$redutorAplicado = 0;
$irrfTabela = 0;

if ($baseIRRF <= 5000.00) {
    $irrf = 0;
    $statusIRRF = 'Isento (Base até R$ 5.000,00)';
} elseif ($baseIRRF > 5000.00 && $baseIRRF <= 7350.00) {
    $irrfTabela = ($baseIRRF * 0.275) - 908.73;
    $redutorAplicado = 978.62 - (0.133145 * $baseIRRF);
    $irrf = max(0, $irrfTabela - $redutorAplicado);
    $statusIRRF = 'Redutor Proporcional Aplicado (Lei 15.270/2025)';
} else {
    $irrf = max(0, ($baseIRRF * 0.275) - 908.73);
    $statusIRRF = 'Tabela Progressiva Padrão (27,5%)';
}

$iss = $valorBruto * $aliquotaISS;
$valorLiquido = $valorBruto - $inssRPA - $irrf - $iss;

// =========================================================================
// 4. SALVAMENTO NO BANCO DE DADOS
// =========================================================================
try {
    $pdo->beginTransaction();

    $partesCidadePrestador = explode('/', $cidadePrestador);
    $cidadeLimpaP = trim($partesCidadePrestador[0]);
    $ufLimpaP = isset($partesCidadePrestador[1]) ? trim($partesCidadePrestador[1]) : 'CE';

    $partesCidadeTomador = explode('/', $cidadeTomador);
    $cidadeLimpaT = trim($partesCidadeTomador[0]);
    $ufLimpaT = isset($partesCidadeTomador[1]) ? trim($partesCidadeTomador[1]) : 'CE';

    $stmtPrestador = $pdo->prepare("INSERT INTO prestadores_autonomos (nome_completo, cpf, email, cep_domicilio, cidade_domicilio, estado_domicilio) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $stmtPrestador->execute([$nomePrestador, $cpfPrestador, $emailPrestador, $cepPrestador, $cidadeLimpaP, $ufLimpaP]);
    $prestadorId = $pdo->lastInsertId();

    $cepT_db = !empty($cepTomador) ? $cepTomador : '00000000'; 
    $stmtTomador = $pdo->prepare("INSERT INTO tomadores_empresas (razao_social, cnpj, cep_sede, cidade_sede, estado_sede) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $stmtTomador->execute([$empresaTomador, $cnpjTomador, $cepT_db, $cidadeLimpaT, $ufLimpaT]);
    $tomadorId = $pdo->lastInsertId();

    $stmtRpa = $pdo->prepare("INSERT INTO rpa_emissões (prestador_id, tomador_id, data_emissao, mes_competencia, ano_competencia, cidade_execucao_servico, valor_servico_bruto, valor_inss_retido, valor_deducao_dependentes, base_calculo_irrf, valor_irrf_retido, valor_iss_retido, valor_liquido_pago) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtRpa->execute([$prestadorId, $tomadorId, date('m'), date('Y'), $cidadeISS, $valorBruto, $inssRPA, $deducaoDependentes, $baseIRRF, $irrf, $iss, $valorLiquido]);

    $rpaIdCriado = $pdo->lastInsertId();
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Erro Crítico no Banco de Dados.");
}

// =========================================================================
// 5. GERAÇÃO DO HTML PARA O PDF
// =========================================================================
function moedab($valor) { return 'R$ ' . number_format($valor, 2, ',', '.'); }
$dataAtual = date('d/m/Y');

$hashSeguranca = strtoupper(substr(md5($rpaIdCriado . 'S&C2026'), 0, 8));
$hashAutenticacao = $rpaIdCriado . "-" . $hashSeguranca;
// Detecta automaticamente o domínio de produção
$protocolo    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dominio      = $_SERVER['HTTP_HOST'] ?? 'contabil.nexusinnova.com.br';
$urlValidacao = $protocolo . '://' . $dominio . '/validar.php?doc=' . $hashAutenticacao;
$qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . urlencode($urlValidacao);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $qrCodeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$qrCodeImage = curl_exec($ch);
curl_close($ch);

if ($qrCodeImage) {
    $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($qrCodeImage);
    $imgQrCode = "<img src='{$qrCodeBase64}' width='80' height='80' style='border: 1px solid #ccc; padding: 2px;'>";
} else {
    $imgQrCode = "<div style='width: 80px; height: 80px; border: 1px solid #ccc;'>[QR]</div>";
}

$memorialIRRF_Html = "";
if ($baseIRRF > 5000 && $baseIRRF <= 7350) {
    $memorialIRRF_Html .= "Fórmula Aplicada = 978,62 - (0,133145 * Base IRRF)<br><strong>IRRF Retido Final = " . moedab($irrf) . "</strong>";
} elseif ($baseIRRF > 7350) {
    $memorialIRRF_Html .= "Cálculo = (Base IRRF * 27,5%) - R$ 908,73<br><strong>IRRF Retido Final = " . moedab($irrf) . "</strong>";
} else {
    $memorialIRRF_Html .= "Rendimento isento.<br><strong>IRRF Retido Final = R$ 0,00</strong>";
}

$html_pdf = "
<!DOCTYPE html>
<html>
<head>
    <style>
        @page { margin: 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1e293b; line-height: 1.4; }
        .header { width: 100%; border-bottom: 3px solid #0a4f4f; padding-bottom: 10px; margin-bottom: 20px; }
        .logo-box { font-size: 20px; font-weight: 900; color: #0a4f4f; }
        .section-title { background-color: #f8fafc; color: #0a4f4f; padding: 6px 10px; font-weight: bold; border-left: 4px solid #c8973a; font-size: 10px; text-transform: uppercase; margin-bottom: 8px; }
        .dados-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .dados-table td { padding: 8px; border: 1px solid #e2e8f0; }
        .label { font-size: 8px; color: #64748b; text-transform: uppercase; display: block; font-weight: bold; }
        .dado-valor { font-size: 11px; font-weight: bold; }
        .valores-table { width: 100%; border-collapse: collapse; }
        .valores-table th, .valores-table td { border: 1px solid #cbd5e1; padding: 10px; }
        .valores-table th { background-color: #0a4f4f; color: white; font-size: 10px; text-align: left;}
        .text-right { text-align: right !important; }
        .destaque-liquido td { background-color: #f8fafc; font-size: 13px; font-weight: bold; }
        .destaque-liquido .text-right { color: #0a4f4f; font-size: 15px; }
        .assinatura-box { text-align: center; padding-top: 20px; }
        .linha-assinatura { border-top: 1px solid #000; width: 300px; margin: 0 auto; padding-top: 5px; }
    </style>
</head>
<body>
    <table class='header'><tr>
        <td class='logo-box'>NEXUS CONTÁBIL<br><span style='font-size:10px; font-weight:normal;'>TECNOLOGIA E COMPLIANCE</span></td>
        <td style='text-align: right;'><h1>Recibo de Pagamento (RPA)</h1><p style='margin:0; font-size:10px;'>Competência: " . date('m/Y') . " | Emissão: {$dataAtual}</p></td>
    </tr></table>

    <div class='section-title'>Tomador dos Serviços</div>
    <table class='dados-table'>
        <tr><td width='65%'><span class='label'>Razão Social</span><span class='dado-valor'>{$empresaTomador}</span></td><td width='35%'><span class='label'>CNPJ</span><span class='dado-valor'>" . $_POST['cnpj_tomador'] . "</span></td></tr>
        <tr><td colspan='2'><span class='label'>Cidade/UF</span><span class='dado-valor'>{$cidadeTomador}</span></td></tr>
    </table>

    <div class='section-title'>Profissional Autônomo</div>
    <table class='dados-table'>
        <tr><td width='65%'><span class='label'>Nome Completo</span><span class='dado-valor'>{$nomePrestador}</span></td><td width='35%'><span class='label'>CPF</span><span class='dado-valor'>" . $_POST['cpf_prestador'] . "</span></td></tr>
        <tr><td colspan='2'><span class='label'>Serviço Prestado</span><span class='dado-valor'>{$descricaoServico}</span></td></tr>
    </table>

    <div class='section-title'>Demonstrativo de Cálculo</div>
    <table class='valores-table'>
        <tr><th>Descrição do Evento</th><th class='text-right'>Vencimentos</th><th class='text-right'>Descontos</th></tr>
        <tr><td>Valor Bruto</td><td class='text-right'>" . moedab($valorBruto) . "</td><td></td></tr>
        <tr><td>INSS (Retenção na Fonte)</td><td></td><td class='text-right' style='color:red;'>" . moedab($inssRPA) . "</td></tr>
        <tr><td>IRRF (Imposto de Renda)</td><td></td><td class='text-right' style='color:red;'>" . moedab($irrf) . "</td></tr>
        <tr><td>ISS Municipal</td><td></td><td class='text-right' style='color:red;'>" . moedab($iss) . "</td></tr>
        <tr class='destaque-liquido'><td colspan='2'>LÍQUIDO A PAGAR</td><td class='text-right'>" . moedab($valorLiquido) . "</td></tr>
    </table>

    <table style='width: 100%; margin-top: 40px;'><tr>
        <td width='25%'>{$imgQrCode}<br><span style='font-size:7px; color:#666;'>Cód: {$hashAutenticacao}</span></td>
        <td width='75%' class='assinatura-box'>
            <p style='font-size:11px; margin-bottom: 25px;'>Recebi de <strong>{$empresaTomador}</strong>, a importância líquida supra.</p>
            <div class='linha-assinatura'><strong>{$nomePrestador}</strong><br><span style='font-size:9px;'>CPF: " . $_POST['cpf_prestador'] . "</span></div>
        </td>
    </tr></table>
    
    <div style='page-break-before: always;'></div>
    
    <table class='header'>
        <tr>
            <td class='logo-box'>NEXUS CONTÁBIL<br><span style='font-size:10px; font-weight:normal; color:#64748b;'>ANEXO TÉCNICO</span></td>
            <td style='text-align: right;'>
                <h2 style='margin-bottom: 2px;'>Memória de Cálculo</h2>
                <p style='margin:0; font-size:10px; color:#64748b;'>Referência: Ano-Calendário 2026</p>
            </td>
        </tr>
    </table>

    <p style='font-size: 11px; color: #475569; margin-bottom: 20px; text-align: justify; line-height: 1.5;'>
        Este documento é parte integrante do Recibo de Pagamento Autônomo (Cód: <strong>{$hashAutenticacao}</strong>). Ele detalha os parâmetros e fórmulas matemáticas aplicadas pelo motor tributário da Nexus Contábil para a apuração das retenções legais. Elaborado para fins de transparência fiscal e conformidade com as exigências do eSocial (Eventos S-1200 e S-1210).
    </p>

    <div style='background:#f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-left: 4px solid #0a4f4f; margin-bottom:15px; border-radius: 4px;'>
        <h3 style='margin-top:0; font-size:12px; color:#0a4f4f; text-transform: uppercase;'>1. Apuração da Contribuição Previdenciária (INSS)</h3>
        <p style='font-size:10px; color:#64748b; margin-bottom: 8px;'>Alíquota legal aplicável: 11%. Teto máximo de desconto em 2026: R$ 932,31.</p>
        <div style='font-size:11px; font-family: \"Courier New\", Courier, monospace; background: white; padding: 10px; border: 1px solid #e2e8f0;'>
            Base de Cálculo Bruta: " . moedab($valorBruto) . "<br>
            INSS Teórico (11%): " . moedab($inssCalculado) . "<br>
            (-) INSS já retido no mês (Outras fontes): " . moedab($inssJaRetidoMes) . "<br>
            (=) Limite disponível no Teto Previdenciário: " . moedab($espacoNoTeto) . "<br>
            <span style='display:block; margin-top:5px; padding-top:5px; border-top:1px dashed #ccc;'><strong>Retenção Efetiva Aplicada: " . moedab($inssRPA) . "</strong></span>
        </div>
    </div>

    <div style='background:#f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-left: 4px solid #c8973a; margin-bottom:15px; border-radius: 4px;'>
        <h3 style='margin-top:0; font-size:12px; color:#0a4f4f; text-transform: uppercase;'>2. Apuração do Imposto de Renda (IRRF)</h3>
        <p style='font-size:10px; color:#64748b; margin-bottom: 8px;'>Dedução legal: R$ 189,59 por dependente ({$qtdDependentes} informado). <br>Enquadramento: {$statusIRRF}.</p>
        <div style='font-size:11px; font-family: \"Courier New\", Courier, monospace; background: white; padding: 10px; border: 1px solid #e2e8f0;'>
            Base IRRF = Bruto - INSS Retido - Dedução Dependentes<br>
            Base IRRF = " . moedab($valorBruto) . " - " . moedab($inssRPA) . " - " . moedab($deducaoDependentes) . "<br>
            <strong>Base de Cálculo Efetiva = " . moedab($baseIRRF) . "</strong><br><br>
            {$memorialIRRF_Html}
        </div>
    </div>

    <div style='background:#f8fafc; padding: 15px; border: 1px solid #cbd5e1; border-left: 4px solid #64748b; margin-bottom:15px; border-radius: 4px;'>
        <h3 style='margin-top:0; font-size:12px; color:#0a4f4f; text-transform: uppercase;'>3. Apuração do Imposto Municipal (ISS)</h3>
        <p style='font-size:10px; color:#64748b; margin-bottom: 8px;'>Retenção de ISSQN configurada para o município de {$cidadeISS}.</p>
        <div style='font-size:11px; font-family: \"Courier New\", Courier, monospace; background: white; padding: 10px; border: 1px solid #e2e8f0;'>
            Alíquota Aplicada: " . ($aliquotaISS * 100) . "%<br>
            Cálculo: " . moedab($valorBruto) . " x " . ($aliquotaISS * 100) . "%<br>
            <span style='display:block; margin-top:5px; padding-top:5px; border-top:1px dashed #ccc;'><strong>ISS Retido Final: " . moedab($iss) . "</strong></span>
        </div>
    </div>

    <div style='text-align: center; margin-top: 30px; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px;'>
        Memorial gerado eletronicamente pelo sistema Nexus Contábil.<br>
        Valide a autenticidade deste documento lendo o QR Code da página anterior.
    </div>
</body>
</html>";

// =========================================================================
// 6. RENDERIZAÇÃO DO DOMPDF
// =========================================================================
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html_pdf);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Guarda o PDF na memória para poder enviar no e-mail e mostrar na tela
$pdfString = $dompdf->output();
$nomeArquivoPDF = "RPA_{$cpfPrestador}_" . date('Ymd') . ".pdf";

// =========================================================================
// 7. E-MAIL COM TEMPLATE PREMIUM (HTML RESPONSIVO)
// =========================================================================
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;       
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($_ENV['SMTP_USER'] ?? '', 'Nexus Contábil');
    $mail->addAddress($emailPrestador, $nomePrestador);
    $mail->addStringAttachment($pdfString, $nomeArquivoPDF, 'base64', 'application/pdf');

    $mail->isHTML(true);
    $mail->Subject = "Recibo de Pagamento (RPA) Emitido - Nexus Contábil";

    $valorBrutoEmail = moedab($valorBruto);
    $valorLiquidoEmail = moedab($valorLiquido);

    $corpoEmail = "
    <div style='background-color: #f4f7f6; padding: 40px 20px; font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; color: #334155; line-height: 1.6;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
            
            <div style='background-color: #0a4f4f; padding: 30px; text-align: center; border-bottom: 4px solid #c8973a;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 1px;'>NEXUS CONTÁBIL</h1>
                <p style='color: #c8973a; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Documento Oficial eSocial</p>
            </div>

            <div style='padding: 40px 30px;'>
                <h2 style='color: #0a4f4f; font-size: 20px; margin-top: 0;'>Olá, {$nomePrestador}.</h2>
                <p>O seu <strong>Recibo de Pagamento Autônomo (RPA)</strong> referente aos serviços técnicos prestados para a empresa <strong>{$empresaTomador}</strong> foi processado e encontra-se em anexo a este e-mail.</p>
                
                <div style='background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin: 30px 0;'>
                    <h3 style='margin-top: 0; color: #0a4f4f; font-size: 14px; text-transform: uppercase; border-bottom: 1px solid #cbd5e1; padding-bottom: 10px;'>Resumo do Documento</h3>
                    <table style='width: 100%; font-size: 14px; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Valor Bruto:</td>
                            <td style='padding: 8px 0; text-align: right; font-weight: 600;'>{$valorBrutoEmail}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Líquido a Receber:</td>
                            <td style='padding: 8px 0; text-align: right; font-weight: 700; color: #0a4f4f; font-size: 16px;'>{$valorLiquidoEmail}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #64748b;'>Cód. Validação:</td>
                            <td style='padding: 8px 0; text-align: right; font-family: monospace; font-size: 12px;'>{$hashAutenticacao}</td>
                        </tr>
                    </table>
                </div>

                <p style='font-size: 13px; color: #64748b;'>O arquivo PDF em anexo contém a assinatura eletrônica e o memorial de cálculo detalhado das retenções federais (INSS, IRRF) e municipais (ISS).</p>
    ";

    // Gatilho de Upsell
    if ($irrf > 0 || $inssRPA > 300) {
        $corpoEmail .= "
                <div style='background-color: #fffbeb; border-left: 4px solid #d97706; padding: 20px; margin-top: 30px; border-radius: 0 8px 8px 0;'>
                    <h4 style='color: #b45309; margin: 0 0 10px 0; font-size: 15px;'>💡 Diagnóstico de Redução Tributária</h4>
                    <p style='color: #92400e; font-size: 13px; margin: 0 0 15px 0; line-height: 1.5;'>Notamos que você teve retenções significativas neste serviço. Através da abertura de um CNPJ (Simples Nacional), sua carga tributária poderia ser drasticamente reduzida, aumentando o seu lucro líquido mensal em futuros projetos.</p>
                    <a href='https://wa.me/5585998261414?text=Olá!%20Recebi%20meu%20RPA%20e%20gostaria%20de%20uma%20consultoria%20sobre%20abertura%20de%20CNPJ%20para%20reduzir%20impostos.' style='display: inline-block; background-color: #d97706; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 13px; font-weight: bold;'>Falar com um Consultor Tributário</a>
                </div>
        ";
    }

    $corpoEmail .= "
            </div>
            <div style='background-color: #f1f5f9; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'>
                <p style='margin: 0; font-size: 11px; color: #94a3b8;'>Este é um documento eletrônico gerado pelo sistema <strong>Nexus Contábil</strong>.</p>
                <p style='margin: 5px 0 0 0; font-size: 11px; color: #94a3b8;'>Em total conformidade com a LGPD, seus dados comerciais estão protegidos.</p>
            </div>
        </div>
    </div>
    ";

    $mail->Body = $corpoEmail;
    $mail->send();
} catch (Exception $e) { }

// =========================================================================
// 8. DOWNLOAD / EXIBIÇÃO NO NAVEGADOR
// =========================================================================

// Limpeza agressiva do buffer para evitar a tela branca ou PDF corrompido
while (ob_get_level()) {
    ob_end_clean();
}

// O método nativo stream do Dompdf já cuida dos headers de forma segura
$dompdf->stream($nomeArquivoPDF, array("Attachment" => false));
exit;