<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Editar Convite
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$conviteId = get('id');

if (!$conviteId) {
    Session::setFlash('error', 'Convite não especificado');
    redirect('/cliente/dashboard.php');
}

// Buscar convite e verificar se pertence ao cliente
$stmt = $db->prepare("
    SELECT c.*, e.nome_evento, e.cliente_id, e.id as evento_id
    FROM convites c
    JOIN eventos e ON c.evento_id = e.id
    WHERE c.id = ? AND e.cliente_id = ?
");
$stmt->execute([$conviteId, $clienteId]);
$convite = $stmt->fetch();

if (!$convite) {
    Session::setFlash('error', 'Convite não encontrado');
    redirect('/cliente/dashboard.php');
}

$errors = [];

if (isPost()) {
    $nomeConvidado1 = post('nome_convidado1');
    $telefone1 = post('telefone1');
    $email1 = post('email1');
    $nomeConvidado2 = post('nome_convidado2');
    $telefone2 = post('telefone2');
    $email2 = post('email2');
    $tipoConvidado = post('tipo_convidado', 'normal');
    $mesaNumero = post('mesa_numero');
    $observacoes = post('observacoes');
    
    // Validações
    if (empty($nomeConvidado1)) {
        $errors['nome1'] = 'Nome do primeiro convidado é obrigatório';
    }
    
    if ($email1 && !Security::validateEmail($email1)) {
        $errors['email1'] = 'Email inválido';
    }
    
    if ($email2 && !Security::validateEmail($email2)) {
        $errors['email2'] = 'Email inválido';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE convites SET
                    nome_convidado1 = ?, telefone1 = ?, email1 = ?,
                    nome_convidado2 = ?, telefone2 = ?, email2 = ?,
                    tipo_convidado = ?, mesa_numero = ?, observacoes = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $nomeConvidado1,
                $telefone1,
                $email1,
                $nomeConvidado2,
                $telefone2,
                $email2,
                $tipoConvidado,
                $mesaNumero,
                $observacoes,
                $conviteId
            ]);
            
            // Registrar log
            logAccess('cliente', $clienteId, 'editar_convite', "Convite editado: $nomeConvidado1");
            
            Session::setFlash('success', 'Convite atualizado com sucesso!');
            redirect('/cliente/evento-detalhes.php?id=' . $convite['evento_id']);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao atualizar convite. Tente novamente.';
            error_log("Erro ao editar convite: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar Convite</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $convite['evento_id']; ?>">
                    <?php echo truncate($convite['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Editar Convite</span>
            </div>
        </div>
    </div>

    <?php if (isset($errors['geral'])): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo $errors['geral']; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dados dos Convidados</h3>
                    <span class="badge badge-secondary">Código: <?php echo $convite['codigo_convite']; ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        
                        <!-- Convidado 1 -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">👤 Convidado Principal</h4>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Nome Completo</label>
                            <input type="text" name="nome_convidado1" 
                                   class="form-control <?php echo isset($errors['nome1']) ? 'error' : ''; ?>" 
                                   placeholder="Ex: João Silva"
                                   value="<?php echo Security::clean($convite['nome_convidado1']); ?>" required>
                            <?php if (isset($errors['nome1'])): ?>
                                <span class="form-error"><?php echo $errors['nome1']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone1" class="form-control" 
                                           placeholder="+244 900 000 000"
                                           value="<?php echo Security::clean($convite['telefone1']); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email1" 
                                           class="form-control <?php echo isset($errors['email1']) ? 'error' : ''; ?>" 
                                           placeholder="joao@email.com"
                                           value="<?php echo Security::clean($convite['email1']); ?>">
                                    <?php if (isset($errors['email1'])): ?>
                                        <span class="form-error"><?php echo $errors['email1']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="presente1" name="presente1" 
                                       <?php echo $convite['presente_convidado1'] ? 'checked' : ''; ?> disabled>
                                <label for="presente1">
                                    Marcado como presente
                                    <?php if ($convite['hora_checkin1']): ?>
                                        <small style="color: var(--gray-medium);">
                                            (Check-in: <?php echo formatDateTime($convite['hora_checkin1']); ?>)
                                        </small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>

                        <hr style="margin: 2rem 0;">

                        <!-- Convidado 2 (Opcional) -->
                        <h4 style="margin-bottom: 1rem; color: var(--gray-dark);">👥 Segundo Convidado <span style="font-size: 0.875rem; font-weight: normal; color: var(--gray-medium);">(Opcional)</span></h4>
                        
                        <div class="form-group">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="nome_convidado2" class="form-control" 
                                   placeholder="Ex: Maria Silva"
                                   value="<?php echo Security::clean($convite['nome_convidado2']); ?>">
                            <span class="form-help">Deixe em branco se for apenas 1 pessoa</span>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone2" class="form-control" 
                                           placeholder="+244 900 000 000"
                                           value="<?php echo Security::clean($convite['telefone2']); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email2" 
                                           class="form-control <?php echo isset($errors['email2']) ? 'error' : ''; ?>" 
                                           placeholder="maria@email.com"
                                           value="<?php echo Security::clean($convite['email2']); ?>">
                                    <?php if (isset($errors['email2'])): ?>
                                        <span class="form-error"><?php echo $errors['email2']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($convite['nome_convidado2']): ?>
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="presente2" name="presente2" 
                                       <?php echo $convite['presente_convidado2'] ? 'checked' : ''; ?> disabled>
                                <label for="presente2">
                                    Marcado como presente
                                    <?php if ($convite['hora_checkin2']): ?>
                                        <small style="color: var(--gray-medium);">
                                            (Check-in: <?php echo formatDateTime($convite['hora_checkin2']); ?>)
                                        </small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <hr style="margin: 2rem 0;">

                        <!-- Informações Adicionais -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--gray-dark);">ℹ️ Informações Adicionais</h4>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Tipo de Convidado</label>
                                    <select name="tipo_convidado" class="form-control">
                                        <option value="normal" <?php echo $convite['tipo_convidado'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                        <option value="vip" <?php echo $convite['tipo_convidado'] === 'vip' ? 'selected' : ''; ?>>VIP</option>
                                        <option value="familia" <?php echo $convite['tipo_convidado'] === 'familia' ? 'selected' : ''; ?>>Família</option>
                                        <option value="amigo" <?php echo $convite['tipo_convidado'] === 'amigo' ? 'selected' : ''; ?>>Amigo</option>
                                        <option value="trabalho" <?php echo $convite['tipo_convidado'] === 'trabalho' ? 'selected' : ''; ?>>Trabalho</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Número da Mesa</label>
                                    <input type="text" name="mesa_numero" class="form-control" 
                                           placeholder="Ex: Mesa 5"
                                           value="<?php echo Security::clean($convite['mesa_numero']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3" 
                                      placeholder="Observações adicionais sobre este convite..."><?php echo Security::clean($convite['observacoes']); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                ✓ Salvar Alterações
                            </button>
                            <a href="evento-detalhes.php?id=<?php echo $convite['evento_id']; ?>" class="btn btn-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Informações -->
        <div class="col-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">📋 Informações do Convite</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Código do Convite</small>
                        <div>
                            <strong style="font-size: 1.25rem; color: var(--primary-color);">
                                <?php echo $convite['codigo_convite']; ?>
                            </strong>
                            <button onclick="copyToClipboard('<?php echo $convite['codigo_convite']; ?>')" 
                                    class="btn btn-sm btn-secondary" style="padding: 0.25rem 0.5rem; margin-left: 0.5rem;">
                                📋
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Evento</small>
                        <div><strong><?php echo Security::clean($convite['nome_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Criado em</small>
                        <div><?php echo formatDateTime($convite['criado_em']); ?></div>
                    </div>

                    <?php if ($convite['atualizado_em'] && $convite['atualizado_em'] != $convite['criado_em']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Última atualização</small>
                        <div><?php echo formatDateTime($convite['atualizado_em']); ?></div>
                    </div>
                    <?php endif; ?>

                    <hr style="margin: 1rem 0;">

                    <button onclick="showQRCode('<?php echo $convite['codigo_convite']; ?>')" 
                            class="btn btn-info btn-block">
                        📱 Ver QR Code
                    </button>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header" style="background: var(--danger-color); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">⚠️ Zona de Perigo</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--gray-dark); font-size: 0.875rem; margin-bottom: 1rem;">
                        A exclusão deste convite é permanente e não pode ser desfeita.
                    </p>
                    <a href="deletar-convite.php?id=<?php echo $convite['id']; ?>" 
                       class="btn btn-danger btn-block"
                       onclick="return confirm('Tem certeza que deseja excluir este convite?\n\nConvidado(s): <?php echo $convite['nome_convidado1']; ?><?php echo $convite['nome_convidado2'] ? ' e ' . $convite['nome_convidado2'] : ''; ?>\n\nEsta ação não pode ser desfeita!');">
                        🗑️ Deletar Convite
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💡 Dica</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; color: var(--gray-dark);">
                        As informações de presença (check-in) não podem ser editadas manualmente. 
                        Use o QR Code no dia do evento para registrar a presença.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div class="modal-overlay" id="qrCodeModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">QR Code do Convite</h3>
            <button class="modal-close" onclick="closeModal('qrCodeModal')">×</button>
        </div>
        <div class="modal-body text-center">
            <div id="qrCodeContainer" style="padding: 2rem;">
                <!-- QR Code será inserido aqui -->
            </div>
            <p style="margin-top: 1rem; color: var(--gray-medium);">
                Código: <strong><?php echo $convite['codigo_convite']; ?></strong>
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('qrCodeModal')" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
function showQRCode(codigo) {
    const container = document.getElementById('qrCodeContainer');
    const url = '<?php echo SITE_URL; ?>/api/verificar-convite.php?codigo=' + codigo;
    
    container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}" alt="QR Code" style="max-width: 100%;">`;
    
    openModal('qrCodeModal');
}
</script>

<?php include '../includes/cliente_footer.php'; ?>