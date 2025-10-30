<?php
/**
 * FORNECEDOR - CHECK-IN DE CONVIDADOS
 * Sistema de controle de entrada via QR Code ou Código
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'fornecedor') {
    redirect('/fornecedor/login.php');
}

$db = Database::getInstance()->getConnection();
$fornecedorId = Session::getUserId();
$eventoId = Session::get('evento_id');
$categoria = Session::get('fornecedor_categoria');

// Verificar se é segurança
if ($categoria !== 'Segurança' && $categoria !== 'seguranca') {
    Session::setFlash('error', 'Acesso negado. Apenas a equipe de Segurança pode acessar esta área.');
    redirect('/fornecedor/dashboard.php');
}

$success = '';
$error = '';
$convite = null;

// Processar busca de convite
if (isPost() && isset($_POST['buscar_convite'])) {
    $codigo = strtoupper(trim(post('codigo_busca')));
    
    if (empty($codigo)) {
        $error = 'Digite um código de convite válido!';
    } else {
        $stmt = $db->prepare("
            SELECT * FROM convites 
            WHERE evento_id = ? 
            AND (codigo_convite = ? OR REPLACE(codigo_convite, '-', '') = ?)
        ");
        $stmt->execute([$eventoId, $codigo, $codigo]);
        $convite = $stmt->fetch();
        
        if (!$convite) {
            $error = 'Convite não encontrado! Verifique o código e tente novamente.';
        }
    }
}

// Processar toggle de presença
if (isPost() && isset($_POST['toggle_presenca'])) {
    $conviteId = post('convite_id');
    $pessoa = post('pessoa'); // 1 ou 2
    
    $stmt = $db->prepare("SELECT * FROM convites WHERE id = ? AND evento_id = ?");
    $stmt->execute([$conviteId, $eventoId]);
    $convite = $stmt->fetch();
    
    if ($convite) {
        $campo = 'presente_convidado' . $pessoa;
        $campoHora = 'hora_checkin' . $pessoa;
        $novoStatus = !$convite[$campo];
        
        $horaCheckin = $novoStatus ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("
            UPDATE convites 
            SET $campo = ?, $campoHora = ? 
            WHERE id = ?
        ");
        $stmt->execute([$novoStatus, $horaCheckin, $conviteId]);
        
        $nomeConvidado = $convite['nome_convidado' . $pessoa];
        $action = $novoStatus ? 'marcou presença' : 'removeu presença';
        
        // Log da ação
        logAccess('fornecedor', $fornecedorId, 'checkin_' . ($novoStatus ? 'in' : 'out'), 
                  "Check-in: $nomeConvidado ($action)");
        
        $success = $nomeConvidado . ' - ' . ($novoStatus ? 'Presença confirmada!' : 'Presença removida!');
        
        // Recarregar convite
        $stmt = $db->prepare("SELECT * FROM convites WHERE id = ?");
        $stmt->execute([$conviteId]);
        $convite = $stmt->fetch();
    } else {
        $error = 'Convite não encontrado!';
    }
}

// Estatísticas
$stmt = $db->prepare("SELECT COUNT(*) FROM convites WHERE evento_id = ?");
$stmt->execute([$eventoId]);
$totalConvites = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT SUM(
        CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END +
        CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END
    ) FROM convites WHERE evento_id = ?
");
$stmt->execute([$eventoId]);
$totalPresentes = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("
    SELECT SUM(
        CASE WHEN nome_convidado1 IS NOT NULL THEN 1 ELSE 0 END +
        CASE WHEN nome_convidado2 IS NOT NULL THEN 1 ELSE 0 END
    ) FROM convites WHERE evento_id = ?
");
$stmt->execute([$eventoId]);
$totalEsperado = $stmt->fetchColumn() ?: 0;

$taxaPresenca = $totalEsperado > 0 ? ($totalPresentes / $totalEsperado) * 100 : 0;

// Últimos check-ins
$stmt = $db->prepare("
    SELECT c.*, 
           GREATEST(
               COALESCE(c.hora_checkin1, '1970-01-01'),
               COALESCE(c.hora_checkin2, '1970-01-01')
           ) as ultima_checkin
    FROM convites c
    WHERE c.evento_id = ?
    AND (c.presente_convidado1 = 1 OR c.presente_convidado2 = 1)
    ORDER BY ultima_checkin DESC
    LIMIT 5
");
$stmt->execute([$eventoId]);
$ultimosCheckins = $stmt->fetchAll();

include '../includes/fornecedor_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">🔍 Check-in de Convidados</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Check-in</span>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <strong>Sucesso!</strong>
            <p class="alert-message"><?php echo $success; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">❌</div>
        <div class="alert-content">
            <strong>Erro!</strong>
            <p class="alert-message"><?php echo $error; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Estatísticas -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-ticket-perforated"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Convites</div>
                <div class="stat-value"><?php echo number_format($totalConvites); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Convidados Esperados</div>
                <div class="stat-value"><?php echo number_format($totalEsperado); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Presentes</div>
                <div class="stat-value"><?php echo number_format($totalPresentes); ?></div>
                <div class="stat-change positive">
                    <?php echo number_format($taxaPresenca, 1); ?>% do total
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Aguardando</div>
                <div class="stat-value"><?php echo number_format($totalEsperado - $totalPresentes); ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Scanner e Busca -->
        <div class="col-8">
            <!-- Scanner QR Code -->
            <div class="card mb-4">
                <div class="card-header" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        <i class="bi bi-qr-code-scan"></i> Scanner de QR Code
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            <strong>Como usar:</strong> Clique em "Ativar Câmera" e aponte para o QR Code do convite.
                        </div>
                    </div>

                    <div style="text-align: center; margin: 2rem 0;">
                        <div id="reader" style="width: 100%; max-width: 600px; margin: 0 auto; display: none;"></div>
                        
                        <div id="scanner-placeholder" style="padding: 3rem;">
                            <i class="bi bi-camera" style="font-size: 4rem; color: var(--gray-light);"></i>
                            <p class="text-muted mt-3">Scanner QR Code desativado</p>
                            <button onclick="startScanner()" class="btn btn-success btn-lg mt-2">
                                <i class="bi bi-camera-video"></i> Ativar Câmera
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Busca Manual -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔎 Busca Manual por Código</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="buscar_convite" value="1">
                        <div class="row">
                            <div class="col-9">
                                <input type="text" 
                                       name="codigo_busca" 
                                       class="form-control" 
                                       placeholder="Digite o código do convite (Ex: CNV-1234567)"
                                       style="font-size: 1.125rem; padding: 0.875rem;"
                                       required>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-primary btn-block" style="padding: 0.875rem;">
                                    <i class="bi bi-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Resultado da Busca -->
                    <?php if ($convite): ?>
                    <div style="background: #F0FDF4; border: 2px solid var(--success-color); border-radius: var(--border-radius); padding: 2rem;">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 style="margin: 0; color: var(--success-color);">
                                    <i class="bi bi-ticket-detailed"></i> Convite Encontrado
                                </h4>
                                <p style="margin: 0.5rem 0 0 0; color: var(--gray-medium);">
                                    Código: <strong><?php echo Security::clean($convite['codigo_convite']); ?></strong>
                                </p>
                            </div>
                            <span class="badge badge-<?php echo $convite['tipo_convidado']; ?>" style="font-size: 0.938rem;">
                                <?php echo strtoupper($convite['tipo_convidado']); ?>
                            </span>
                        </div>

                        <hr>

                        <!-- Convidado 1 -->
                        <div style="padding: 1.5rem; background: white; border-radius: var(--border-radius); margin-bottom: 1rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="flex: 1;">
                                    <h5 style="margin: 0 0 0.5rem 0;">
                                        <i class="bi bi-person"></i> 
                                        <?php echo Security::clean($convite['nome_convidado1']); ?>
                                    </h5>
                                    <?php if ($convite['telefone1']): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-phone"></i> <?php echo Security::clean($convite['telefone1']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" style="margin-left: 1rem;">
                                    <input type="hidden" name="toggle_presenca" value="1">
                                    <input type="hidden" name="convite_id" value="<?php echo $convite['id']; ?>">
                                    <input type="hidden" name="pessoa" value="1">
                                    
                                    <?php if ($convite['presente_convidado1']): ?>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Presente
                                        </button>
                                        <?php if ($convite['hora_checkin1']): ?>
                                        <small style="display: block; margin-top: 0.5rem; color: var(--gray-medium);">
                                            Check-in: <?php echo formatDateTime($convite['hora_checkin1']); ?>
                                        </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline">
                                            <i class="bi bi-circle"></i> Marcar Presença
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Convidado 2 (se existir) -->
                        <?php if ($convite['nome_convidado2']): ?>
                        <div style="padding: 1.5rem; background: white; border-radius: var(--border-radius);">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="flex: 1;">
                                    <h5 style="margin: 0 0 0.5rem 0;">
                                        <i class="bi bi-person"></i> 
                                        <?php echo Security::clean($convite['nome_convidado2']); ?>
                                    </h5>
                                    <?php if ($convite['telefone2']): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-phone"></i> <?php echo Security::clean($convite['telefone2']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                
                                <form method="POST" style="margin-left: 1rem;">
                                    <input type="hidden" name="toggle_presenca" value="1">
                                    <input type="hidden" name="convite_id" value="<?php echo $convite['id']; ?>">
                                    <input type="hidden" name="pessoa" value="2">
                                    
                                    <?php if ($convite['presente_convidado2']): ?>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Presente
                                        </button>
                                        <?php if ($convite['hora_checkin2']): ?>
                                        <small style="display: block; margin-top: 0.5rem; color: var(--gray-medium);">
                                            Check-in: <?php echo formatDateTime($convite['hora_checkin2']); ?>
                                        </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-outline">
                                            <i class="bi bi-circle"></i> Marcar Presença
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($convite['observacoes']): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #FEF3C7; border-radius: var(--border-radius);">
                            <strong style="color: #92400E;">📝 Observações:</strong>
                            <p style="margin: 0.5rem 0 0 0; color: #92400E;">
                                <?php echo nl2br(Security::clean($convite['observacoes'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-4">
            <!-- Últimos Check-ins -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⏱️ Últimos Check-ins</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimosCheckins)): ?>
                    <p class="text-muted text-center">Nenhum check-in realizado ainda.</p>
                    <?php else: ?>
                    <?php foreach ($ultimosCheckins as $checkin): ?>
                    <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div style="width: 40px; height: 40px; background: var(--success-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0;">
                                <i class="bi bi-check"></i>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <strong style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo Security::clean($checkin['nome_convidado1']); ?>
                                </strong>
                                <?php if ($checkin['nome_convidado2']): ?>
                                <small class="text-muted" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    + <?php echo Security::clean($checkin['nome_convidado2']); ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> <?php echo timeAgo($checkin['ultima_checkin']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progresso -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">📊 Progresso</h3>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--success-color);">
                            <?php echo number_format($taxaPresenca, 1); ?>%
                        </div>
                        <p style="margin: 0; color: var(--gray-medium);">Taxa de Presença</p>
                    </div>

                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar success" style="width: <?php echo $taxaPresenca; ?>%;"></div>
                    </div>

                    <div style="margin-top: 1rem; text-align: center;">
                        <strong><?php echo number_format($totalPresentes); ?></strong> de 
                        <strong><?php echo number_format($totalEsperado); ?></strong> confirmados
                    </div>
                </div>
            </div>

            <!-- Dicas -->
            <div class="card mt-3">
                <div class="card-header" style="background: #EFF6FF;">
                    <h3 class="card-title" style="color: var(--info-color); margin: 0;">
                        💡 Dicas de Check-in
                    </h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">Use o scanner QR para maior rapidez</li>
                        <li style="margin-bottom: 0.5rem;">Busca manual aceita código completo ou apenas números</li>
                        <li style="margin-bottom: 0.5rem;">Pode desmarcar presença se necessário</li>
                        <li>Verifique dados do convidado antes de confirmar</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTML5 QR Code Scanner -->
<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let html5QrcodeScanner = null;

function startScanner() {
    document.getElementById('scanner-placeholder').style.display = 'none';
    document.getElementById('reader').style.display = 'block';
    
    html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 10, 
            qrbox: {width: 250, height: 250},
            aspectRatio: 1.0
        },
        false
    );
    
    html5QrcodeScanner.render(onScanSuccess, onScanError);
}

function stopScanner() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear();
        html5QrcodeScanner = null;
    }
    document.getElementById('reader').style.display = 'none';
    document.getElementById('scanner-placeholder').style.display = 'block';
}

function onScanSuccess(decodedText, decodedResult) {
    // Parar scanner
    stopScanner();
    
    // Extrair código do QR (pode vir como URL ou código direto)
    let codigo = decodedText;
    
    // Se for URL, extrair código
    if (decodedText.includes('codigo=')) {
        const urlParams = new URLSearchParams(decodedText.split('?')[1]);
        codigo = urlParams.get('codigo');
    }
    
    // Preencher campo de busca e submeter
    document.querySelector('input[name="codigo_busca"]').value = codigo;
    document.querySelector('form[method="POST"]').submit();
}

function onScanError(errorMessage) {
    // Ignorar erros comuns de scanning
    console.log('QR Scan error: ' + errorMessage);
}

// Auto-foco no campo de busca
document.addEventListener('DOMContentLoaded', function() {
    const inputBusca = document.querySelector('input[name="codigo_busca"]');
    if (inputBusca && !<?php echo $convite ? 'true' : 'false'; ?>) {
        inputBusca.focus();
    }
    
    // Formatar código em uppercase automaticamente
    inputBusca.addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
});
</script>

<style>
#reader {
    border: 3px solid var(--success-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

#reader video {
    width: 100% !important;
    height: auto !important;
}
</style>

<?php include '../includes/fornecedor_footer.php'; ?>