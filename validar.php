<?php
$doc = $_GET['doc'] ?? '';
$doc = trim(htmlspecialchars($doc));
$valido = false;
$rpa = null;

if (!empty($doc)) {
    require_once __DIR__ . '/db.php';
    try {
        $partes = explode('-', $doc, 2);
        if (count($partes) === 2 && is_numeric($partes[0])) {
            $rpaId        = (int) $partes[0];
            $hashRecebido = strtoupper($partes[1]);
            $hashEsperado = strtoupper(substr(md5($rpaId . 'S&C2026'), 0, 8));
            if ($hashRecebido === $hashEsperado) {
                $stmt = $pdo->prepare("
                    SELECT r.*, p.nome_completo as prestador_nome, p.cpf as prestador_cpf,
                           t.razao_social as tomador_nome, t.cnpj as tomador_cnpj
                    FROM `rpa_emissões` r
                    JOIN prestadores_autonomos p ON r.prestador_id = p.id
                    JOIN tomadores_empresas t ON r.tomador_id = t.id
                    WHERE r.id = ? LIMIT 1
                ");
                $stmt->execute([$rpaId]);
                $row = $stmt->fetch();
                if ($row) { $rpa = $row; $valido = true; }
            }
        }
    } catch (PDOException $e) {}
}

function fmt_cpf($v)  { return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $v); }
function fmt_cnpj($v) { return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v); }
function fmt_brl($v)  { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificação de Autenticidade | Nexus Contábil</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:ital,wght@0,400;0,600;1,400&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --verde:#0a4f4f;--verde-mid:#0d6b6b;--ouro:#c8973a;--ouro-pale:#f5e9d0;
  --ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--bg:#f0f2f0;--white:#ffffff;
  --ok:#059669;--err:#dc2626;
}
body{
  font-family:'DM Sans',sans-serif;background:var(--bg);min-height:100vh;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:2rem 1rem;
  background-image:
    repeating-linear-gradient(0deg,transparent,transparent 39px,rgba(10,79,79,.04) 39px,rgba(10,79,79,.04) 40px),
    repeating-linear-gradient(90deg,transparent,transparent 39px,rgba(10,79,79,.04) 39px,rgba(10,79,79,.04) 40px);
}
.card{
  width:100%;max-width:460px;background:var(--white);border-radius:4px;
  overflow:hidden;
  box-shadow:0 2px 4px rgba(0,0,0,.06),0 12px 40px rgba(0,0,0,.12);
  animation:rise .5s cubic-bezier(.22,1,.36,1) both;
}
@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}

/* header */
.hd{background:var(--verde);padding:1.4rem 1.6rem;display:flex;align-items:center;gap:.9rem;border-bottom:3px solid var(--ouro)}
.hd img{height:38px;filter:brightness(0) invert(1)}
.hd-t{flex:1}
.hd-name{font-family:'Crimson Pro',serif;font-size:1.3rem;font-weight:600;color:#fff;line-height:1.1}
.hd-sub{font-size:.68rem;color:var(--ouro);text-transform:uppercase;letter-spacing:.12em;margin-top:2px;font-weight:600}
.shield{width:36px;height:36px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--ouro);font-size:1rem;flex-shrink:0}

/* status */
.st{padding:1.8rem 1.4rem;text-align:center;position:relative;overflow:hidden}
.st.ok{background:var(--ok)} .st.err{background:var(--err)}
.st::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(-45deg,rgba(255,255,255,.03) 0,rgba(255,255,255,.03) 1px,transparent 1px,transparent 12px)}
.st-icon{width:60px;height:60px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.35);display:flex;align-items:center;justify-content:center;margin:0 auto .9rem;font-size:1.7rem;color:#fff;position:relative;animation:pop .4s .2s cubic-bezier(.34,1.56,.64,1) both}
@keyframes pop{from{opacity:0;transform:scale(.5)}to{opacity:1;transform:scale(1)}}
.st-title{font-family:'Crimson Pro',serif;font-size:1.6rem;font-weight:600;color:#fff;position:relative}
.st-desc{font-size:.86rem;color:rgba(255,255,255,.85);margin-top:.35rem;position:relative}

/* dados */
.dados{padding:0 1.4rem 1.4rem}
.cod-box{margin:1.3rem 0 1.1rem;background:var(--ouro-pale);border:1px solid var(--ouro);border-radius:4px;padding:.85rem 1rem}
.cod-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ouro);font-weight:700;margin-bottom:.3rem}
.cod-val{font-family:'DM Mono',monospace;font-size:.98rem;color:var(--verde);font-weight:500;letter-spacing:.05em;word-break:break-all}

.grid{border:1px solid var(--line);border-radius:4px;overflow:hidden}
.cell{padding:.85rem 1rem;border-bottom:1px solid var(--line)}
.cell:last-child{border-bottom:none}
.cell-2{display:grid;grid-template-columns:1fr 1fr}
.cell-2 .cell{border-right:1px solid var(--line);border-bottom:none}
.cell-2 .cell:last-child{border-right:none}
.c-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:700;display:block;margin-bottom:.25rem}
.c-val{font-size:.9rem;color:var(--ink);font-weight:500;line-height:1.3}
.c-sub{font-size:.76rem;color:var(--muted);margin-top:2px;display:block}

.valor-row{background:var(--verde);padding:.95rem 1.1rem;display:flex;align-items:center;justify-content:space-between;margin-top:1.1rem;border-radius:4px}
.v-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.6);font-weight:600}
.v-num{font-family:'Crimson Pro',serif;font-size:1.65rem;font-weight:600;color:var(--ouro)}

/* erro */
.erro-body{padding:1.8rem 1.4rem;text-align:center}
.erro-cod{font-family:'DM Mono',monospace;background:#fef2f2;border:1px solid #fecaca;color:var(--err);padding:.4rem .8rem;border-radius:4px;display:inline-block;font-size:.93rem;margin:.7rem 0 1rem}
.erro-body p{font-size:.88rem;color:var(--muted);line-height:1.6}
.erro-btn{display:inline-flex;align-items:center;gap:.4rem;margin-top:1.2rem;background:var(--verde);color:#fff;text-decoration:none;padding:.7rem 1.3rem;border-radius:4px;font-size:.84rem;font-weight:600}

/* footer */
.ft{background:var(--ink);padding:.9rem 1.2rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.ft-txt{font-size:.7rem;color:#94a3b8;line-height:1.4}
.ft-badge{font-size:.6rem;background:rgba(200,151,58,.15);color:var(--ouro);border:1px solid rgba(200,151,58,.3);padding:.28rem .55rem;border-radius:2px;white-space:nowrap;font-weight:700;letter-spacing:.05em;text-transform:uppercase}
</style>
</head>
<body>
<div class="card">

  <div class="hd">
    <div class="hd-t">
      <div class="hd-name">Nexus Contábil</div>
      <div class="hd-sub">Verificação de Autenticidade</div>
    </div>
    <div class="shield">&#9919;</div>
  </div>

  <?php if ($valido && $rpa): ?>

  <div class="st ok">
    <div class="st-icon">&#10003;</div>
    <div class="st-title">Documento Autêntico</div>
    <p class="st-desc">Este RPA possui registro válido e íntegro em nossa base de dados.</p>
  </div>

  <div class="dados">
    <div class="cod-box">
      <div class="cod-lbl">Código de autenticidade</div>
      <div class="cod-val"><?= htmlspecialchars($doc) ?></div>
    </div>

    <div class="grid">
      <div class="cell-2">
        <div class="cell">
          <span class="c-lbl">Data de emissão</span>
          <span class="c-val"><?= date('d/m/Y', strtotime($rpa['data_emissao'])) ?></span>
        </div>
        <div class="cell">
          <span class="c-lbl">Competência</span>
          <span class="c-val"><?= str_pad($rpa['mes_competencia'],2,'0',STR_PAD_LEFT).'/'.$rpa['ano_competencia'] ?></span>
        </div>
      </div>
      <div class="cell">
        <span class="c-lbl">Profissional autônomo</span>
        <span class="c-val"><?= htmlspecialchars($rpa['prestador_nome']) ?></span>
        <span class="c-sub">CPF: <?= fmt_cpf($rpa['prestador_cpf']) ?></span>
      </div>
      <div class="cell">
        <span class="c-lbl">Empresa tomadora</span>
        <span class="c-val"><?= htmlspecialchars($rpa['tomador_nome']) ?></span>
        <span class="c-sub">CNPJ: <?= fmt_cnpj($rpa['tomador_cnpj']) ?></span>
      </div>
    </div>

    <div class="valor-row">
      <div class="v-lbl">Valor líquido comprovado</div>
      <div class="v-num"><?= fmt_brl($rpa['valor_liquido_pago']) ?></div>
    </div>
  </div>

  <?php else: ?>

  <div class="st err">
    <div class="st-icon">&#10007;</div>
    <div class="st-title">Documento Inválido</div>
    <p class="st-desc">Não foi possível validar a autenticidade deste documento.</p>
  </div>

  <div class="erro-body">
    <?php if (!empty($doc)): ?>
      <p>O código</p>
      <div class="erro-cod"><?= htmlspecialchars($doc) ?></div>
      <p>não consta em nossos registros ou o documento pode ter sido adulterado.</p>
    <?php else: ?>
      <p>Nenhum código foi fornecido para validação.<br>Certifique-se de escanear o QR Code corretamente.</p>
    <?php endif; ?>
    <a href="https://wa.me/5585998261414" class="erro-btn">&#128222; Falar com a Nexus Contábil</a>
  </div>

  <?php endif; ?>

  <div class="ft">
    <div class="ft-txt">Nexus Contábil ERP &mdash; Segurança e conformidade<br>para o Terceiro Setor e Pequenas Empresas</div>
    <div class="ft-badge">&#128274; Verificado</div>
  </div>

</div>
</body>
</html>
