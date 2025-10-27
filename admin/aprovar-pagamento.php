<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Aprovar/Rejeitar Pagamento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$pagamentoId = get('id');

if (!$pagamentoId) {
    Session::setFlash('error', 'Pagamento não especificado');
    redirect('/admin/pagamentos.php');
}

// Buscar pagamento
$stmt = $db->prepare("
    SELECT p.*, c.nome_completo as cliente_nome, c.email as cliente_email,
           pl.nome as plano_nome, e.nome_evento, e.codigo_evento, e.id as evento_id
    FROM pagamentos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN planos pl ON p.plano_id = pl.id
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE p.id = ?
");
$stmt->execute([$pagamentoId]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
    Session::setFlash('error', 'Pagamento não encontrado');
    redirect('/admin/pagamentos.php');
}

$errors = [];

// Processar ação
if (isPost()) {
    $acao = post('acao');
    $observacoes = post('observacoes');
    
    if (empty($acao)) {
        $errors['acao'] = 'Selecione uma ação';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if ($acao === 'aprovar') {
                // Aprovar pagamento
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET status = 'aprovado', 
                        data_aprovacao = NOW(),
                        observacoes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$observacoes, $pagamentoId]);
                
                // Ativar evento se existir
                if ($pagamento['evento_id']) {
                    $stmt = $db->prepare("
                        UPDATE eventos 
                        SET pago = 1, 
                            data_pagamento = NOW(),
                            status = 'ativo'
                        WHERE id = ?
                    ");
                    $stmt->execute([$pagamento['evento_id']]);
                }
                
                // Notificar cliente
                createNotification(
                    'cliente',
                    $pagamento['cliente_id'],
                    'Pagamento Aprovado! 🎉',
                    "Seu pagamento de " . formatMoney($pagamento['valor'], $pagamento['moeda']) . " foi aprovado com sucesso!",
                    'sucesso',
                    '/cliente/pagamentos.php'
                );
                
                logAccess('admin', $userId, 'aprovar_pagamento', "Pagamento aprovado: {$pagamento['referencia']}");
                
                $db->commit();
                Session::setFlash('success', 'Pagamento aprovado com sucesso!');
                
            } elseif ($acao === 'rejeitar') {
                // Rejeitar pagamento
                if (empty($observacoes)) {
                    $errors['observacoes'] = 'Informe o motivo da rejeição';
                    $db->rollBack();
                } else {
                    $stmt = $db->prepare("
                        UPDATE pagamentos 
                        SET status = 'rejeitado',
                            observacoes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$observacoes, $pagamentoId]);
                    
                    // Notificar cliente
                    createNotification(
                        'cliente',
                        $pagamento['cliente_id'],
                        'Pagamento Rejeitado',
                        "Seu pagamento foi rejeitado. Motivo: $observacoes. Entre em contato com o suporte.",
                        'alerta',
                        '/cliente/pagamentos.php'
                    );
                    
                    logAccess('admin', $userId, 'rejeitar_pagamento', "Pagamento rejeitado: {$pagamento['referencia']}");
                    
                    $db->commit();
                    Session::setFlash('success', 'Pagamento rejeitado.');
                }
            }
            
            if (empty($errors)) {
                redirect('/admin/pagamentos.php');
            }
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors['geral'] = 'Erro ao processar pagamento. Tente novamente.';
            error_log("Erro ao processar pagamento: " . $e->getMessage());
        }
    }
}

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Aprovar/Rejeitar Pagamento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="pagamentos.php">Pagamentos</a>
                <span class="breadcrumb-separator">/</span>
                <span>Processar</span>
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
                    <h3 class="card-title">Informações do Pagamento</h3>
                    <?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Referência</small>
                                <div><strong style="font-size: 1.25rem; color: var(--primary-color);"><?php echo $pagamento['referencia']; ?></strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Cliente</small>
                                <div><strong><?php echo Security::clean($pagamento['cliente_nome']); ?></strong></div>
                                <div><small><?php echo Security::clean($pagamento['cliente_email']); ?></small></div>
                            </div>

                            <?php if ($pagamento['nome_evento']): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Evento</small>
                                <div><strong><?php echo Security::clean($pagamento['nome_evento']); ?></strong></div>
                                <div><small><?php echo $pagamento['codigo_evento']; ?></small></div>
                            </div>
                            <?php endif; ?>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Plano</small>
                                <div><strong><?php echo $pagamento['plano_nome']; ?></strong></div>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Valor</small>
                                <div><strong style="font-size: 1.75rem; color: var(--success-color);">
                                    <?php if (isset($errors['acao'])): ?>
                                    <span class="form-error"><?php echo $errors['acao']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control <?php echo isset($errors['observacoes']) ? 'error' : ''; ?>" 
                                          rows="4" placeholder="Observações sobre a aprovação/rejeição (obrigatório para rejeição)"></textarea>
                                <?php if (isset($errors['observacoes'])): ?>
                                    <span class="form-error"><?php echo $errors['observacoes']; ?></span>
                                <?php else: ?>
                                    <span class="form-help">Para rejeição, informe o motivo</span>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg">
                                Processar Pagamento
                            </button>

                            <a href="pagamentos.php" class="btn btn-secondary btn-block mt-2">
                                Cancelar
                            </a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($pagamento['status'] !== 'aprovado' && $pagamento['status'] !== 'rejeitado'): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⚠️ Atenção</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; color: var(--gray-dark); margin-bottom: 1rem;">
                        <strong>Ao aprovar:</strong><br>
                        - O pagamento será marcado como aprovado<br>
                        - O evento será ativado automaticamente<br>
                        - O cliente receberá uma notificação
                    </p>
                    <p style="font-size: 0.875rem; color: var(--gray-dark);">
                        <strong>Ao rejeitar:</strong><br>
                        - O pagamento será marcado como rejeitado<br>
                        - É obrigatório informar o motivo<br>
                        - O cliente receberá uma notificação com o motivo
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?> echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                                </strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Método de Pagamento</small>
                                <div>
                                    <?php 
                                    $metodos = [
                                        'express' => '<span class="badge badge-danger">Express</span>',
                                        'referencia' => '<span class="badge badge-success">Referência</span>',
                                        'paypal' => '<span class="badge badge-info">PayPal</span>',
                                        'transferencia' => '<span class="badge badge-warning">Transferência</span>'
                                    ];
                                    echo $metodos[$pagamento['metodo_pagamento']] ?? ucfirst($pagamento['metodo_pagamento']);
                                    ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Data de Criação</small>
                                <div><strong><?php echo formatDateTime($pagamento['criado_em']); ?></strong></div>
                            </div>

                            <?php if ($pagamento['data_vencimento']): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Data de Vencimento</small>
                                <div><strong><?php echo formatDateTime($pagamento['data_vencimento']); ?></strong></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($pagamento['comprovante']): ?>
                    <hr style="margin: 1.5rem 0;">
                    <div>
                        <strong style="margin-bottom: 0.5rem; display: block;">Comprovante:</strong>
                        <a href="<?php echo asset('uploads/' . $pagamento['comprovante']); ?>" 
                           target="_blank" class="btn btn-info">
                            📄 Ver Comprovante
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($pagamento['observacoes']): ?>
                    <hr style="margin: 1.5rem 0;">
                    <div>
                        <strong style="margin-bottom: 0.5rem; display: block;">Observações do Cliente:</strong>
                        <p style="background: var(--gray-lighter); padding: 1rem; border-radius: var(--border-radius-sm);">
                            <?php echo nl2br(Security::clean($pagamento['observacoes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Processar Pagamento</h3>
                </div>
                <div class="card-body">
                    <?php if ($pagamento['status'] === 'aprovado'): ?>
                        <div class="alert alert-success">
                            <div class="alert-icon">✓</div>
                            <div class="alert-content">
                                <p class="alert-message">
                                    Este pagamento já foi aprovado em <?php echo formatDateTime($pagamento['data_aprovacao']); ?>
                                </p>
                            </div>
                        </div>
                    <?php elseif ($pagamento['status'] === 'rejeitado'): ?>
                        <div class="alert alert-danger">
                            <div class="alert-icon">✗</div>
                            <div class="alert-content">
                                <p class="alert-message">
                                    Este pagamento foi rejeitado.
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label form-label-required">Ação</label>
                                <select name="acao" class="form-control <?php echo isset($errors['acao']) ? 'error' : ''; ?>" required>
                                    <option value="">Selecione...</option>
                                    <option value="aprovar">✓ Aprovar Pagamento</option>
                                    <option value="rejeitar">✗ Rejeitar Pagamento</option>
                                </select>
                                <?php