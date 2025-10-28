<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Aprovar/Rejeitar Pagamento (Admin)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$pagamentoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$pagamentoId) {
    Session::setFlash('error', 'Pagamento não especificado');
    redirect('/admin/pagamentos.php');
}

// Buscar pagamento completo
$stmt = $db->prepare("
    SELECT p.*, 
           c.nome_completo as cliente_nome, 
           c.email as cliente_email, 
           c.telefone as cliente_telefone,
           pl.nome as plano_nome,
           e.nome_evento, 
           e.codigo_evento, 
           e.data_evento, 
           e.id as evento_id
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

// Processar ação
if (isPost()) {
    $acao = post('acao');
    $observacao = post('observacao', '');
    
    if ($acao === 'aprovar' || $acao === 'rejeitar') {
        try {
            $db->beginTransaction();
            
            $novoStatus = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';
            $dataAprovacao = $acao === 'aprovar' ? date('Y-m-d H:i:s') : null;
            
            // Atualizar pagamento
            $stmt = $db->prepare("
                UPDATE pagamentos 
                SET status = ?, 
                    data_aprovacao = ?,
                    observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$novoStatus, $dataAprovacao, $observacao, $pagamentoId]);
            
            // Se aprovado, atualizar evento
            if ($acao === 'aprovar' && $pagamento['evento_id']) {
                $stmt = $db->prepare("
                    UPDATE eventos 
                    SET pago = 1, 
                        data_pagamento = ?,
                        status = 'ativo'
                    WHERE id = ?
                ");
                $stmt->execute([date('Y-m-d H:i:s'), $pagamento['evento_id']]);
                
                // Notificar cliente - Pagamento Aprovado
                createNotification(
                    'cliente',
                    $pagamento['cliente_id'],
                    'Pagamento Aprovado!',
                    'Seu pagamento para o evento "' . $pagamento['nome_evento'] . '" foi aprovado! O evento está agora ativo.',
                    'sucesso',
                    '/cliente/evento-detalhes.php?id=' . $pagamento['evento_id']
                );
            } else {
                // Notificar cliente - Pagamento Rejeitado
                $motivoRejeicao = $observacao ? $observacao : 'Entre em contato para mais informações.';
                createNotification(
                    'cliente',
                    $pagamento['cliente_id'],
                    'Pagamento Rejeitado',
                    'Seu pagamento foi rejeitado. Motivo: ' . $motivoRejeicao,
                    'alerta',
                    '/cliente/pagamentos.php'
                );
            }
            
            // Registrar log
            logAccess(
                'admin', 
                $userId, 
                'pagamento_' . $acao, 
                "Pagamento ID: $pagamentoId - Referência: {$pagamento['referencia']} - Cliente: {$pagamento['cliente_nome']}"
            );
            
            $db->commit();
            
            Session::setFlash('success', 'Pagamento ' . ($acao === 'aprovar' ? 'aprovado' : 'rejeitado') . ' com sucesso!');
            redirect('/admin/pagamentos.php');
            
        } catch (PDOException $e) {
            $db->rollBack();
            Session::setFlash('error', 'Erro ao processar pagamento. Tente novamente.');
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
                <span>Aprovar</span>
            </div>
        </div>
        <div>
            <?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?>
        </div>
    </div>

    <?php if ($pagamento['status'] === 'aprovado'): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <div class="alert-title">Pagamento Já Aprovado</div>
            <p class="alert-message">
                Este pagamento já foi aprovado 
                <?php if ($pagamento['data_aprovacao']): ?>
                    em <?php echo formatDateTime($pagamento['data_aprovacao']); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php elseif ($pagamento['status'] === 'rejeitado'): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">❌</div>
        <div class="alert-content">
            <div class="alert-title">Pagamento Rejeitado</div>
            <p class="alert-message">Este pagamento foi rejeitado anteriormente.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Informações do Pagamento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Detalhes do Pagamento</h3>
                </div>
                <div class="card-body">
                    <!-- Card Visual do Pagamento -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; border-radius: 12px; color: white; margin-bottom: 2rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">Referência do Pagamento</div>
                            <h2 style="color: white; font-size: 2rem; margin-bottom: 1rem; font-weight: 700;">
                                <?php echo Security::clean($pagamento['referencia']); ?>
                            </h2>
                            <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                                <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                            </div>
                            <div style="font-size: 0.875rem; opacity: 0.9;">
                                Método: <?php echo ucfirst($pagamento['metodo_pagamento']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Plano Contratado</small>
                                <div><strong><?php echo Security::clean($pagamento['plano_nome']); ?></strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Data do Pedido</small>
                                <div><?php echo formatDateTime($pagamento['criado_em']); ?></div>
                            </div>

                            <?php if ($pagamento['data_vencimento']): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Data de Vencimento</small>
                                <div><?php echo formatDateTime($pagamento['data_vencimento']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Status Atual</small>
                                <div><?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?></div>
                            </div>

                            <?php if ($pagamento['ip_address']): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">IP do Cliente</small>
                                <div><?php echo Security::clean($pagamento['ip_address']); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if ($pagamento['data_aprovacao']): ?>
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Data de Aprovação</small>
                                <div><?php echo formatDateTime($pagamento['data_aprovacao']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($pagamento['observacoes']): ?>
                    <hr style="margin: 1.5rem 0;">
                    <div>
                        <strong style="display: block; margin-bottom: 0.5rem;">Observações Anteriores:</strong>
                        <div style="background: var(--gray-lighter); padding: 1rem; border-radius: 8px;">
                            <?php echo nl2br(Security::clean($pagamento['observacoes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comprovante -->
            <?php if ($pagamento['comprovante']): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">📎 Comprovante de Pagamento</h3>
                </div>
                <div class="card-body">
                    <div style="text-align: center; padding: 2rem;">
                        <?php
                        $comprovanteExt = strtolower(pathinfo($pagamento['comprovante'], PATHINFO_EXTENSION));
                        $comprovantePath = url('uploads/' . $pagamento['comprovante']);
                        ?>
                        
                        <?php if (in_array($comprovanteExt, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                            <!-- Imagem -->
                            <img src="<?php echo $comprovantePath; ?>" 
                                 alt="Comprovante" 
                                 style="max-width: 100%; max-height: 600px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
                        <?php elseif ($comprovanteExt === 'pdf'): ?>
                            <!-- PDF -->
                            <div style="background: var(--gray-lighter); padding: 3rem; border-radius: 12px;">
                                <svg width="80" height="80" fill="var(--danger-color)" viewBox="0 0 24 24" style="margin-bottom: 1rem;">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/>
                                    <path d="M14 2v6h6M9 13h6M9 17h6M9 9h1"/>
                                </svg>
                                <h4>Documento PDF</h4>
                                <p style="color: var(--gray-medium);">Arquivo: <?php echo basename($pagamento['comprovante']); ?></p>
                            </div>
                        <?php else: ?>
                            <!-- Outro tipo de arquivo -->
                            <div style="background: var(--gray-lighter); padding: 3rem; border-radius: 12px;">
                                <svg width="80" height="80" fill="var(--primary-color)" viewBox="0 0 24 24" style="margin-bottom: 1rem;">
                                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <h4>Documento</h4>
                                <p style="color: var(--gray-medium);">Arquivo: <?php echo basename($pagamento['comprovante']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php echo $comprovantePath; ?>" 
                           target="_blank" 
                           class="btn btn-primary mt-3">
                            📥 Download Completo
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Cliente e Ações -->
        <div class="col-4">
            <!-- Informações do Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">👤 Cliente</h3>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="width: 80px; height: 80px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem; font-weight: 700;">
                            <?php echo strtoupper(substr($pagamento['cliente_nome'], 0, 1)); ?>
                        </div>
                        <h4 style="margin-bottom: 0.25rem;"><?php echo Security::clean($pagamento['cliente_nome']); ?></h4>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">📧 Email</small>
                        <div style="font-size: 0.875rem;"><?php echo Security::clean($pagamento['cliente_email']); ?></div>
                    </div>

                    <?php if ($pagamento['cliente_telefone']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">📱 Telefone</small>
                        <div><?php echo Security::clean($pagamento['cliente_telefone']); ?></div>
                    </div>
                    <?php endif; ?>

                    <hr style="margin: 1.5rem 0;">

                    <a href="clientes.php" class="btn btn-secondary btn-block btn-sm">
                        Ver Perfil Completo
                    </a>
                </div>
            </div>

            <!-- Evento Associado -->
            <?php if ($pagamento['evento_id']): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">🎉 Evento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Nome do Evento</small>
                        <div><strong><?php echo Security::clean($pagamento['nome_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Código</small>
                        <div><?php echo Security::clean($pagamento['codigo_evento']); ?></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Data do Evento</small>
                        <div><?php echo formatDate($pagamento['data_evento']); ?></div>
                    </div>

                    <hr style="margin: 1.5rem 0;">

                    <a href="ver-evento.php?id=<?php echo $pagamento['evento_id']; ?>" class="btn btn-primary btn-block btn-sm">
                        Ver Detalhes do Evento
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ações -->
            <?php if ($pagamento['status'] === 'pendente' || $pagamento['status'] === 'processando'): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⚡ Ações</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="formAcao">
                        <div class="form-group">
                            <label class="form-label">Observação (Opcional)</label>
                            <textarea name="observacao" class="form-control" rows="4" placeholder="Adicione uma observação sobre esta decisão..."></textarea>
                            <small class="form-help">Esta observação será salva no histórico do pagamento.</small>
                        </div>

                        <div style="display: grid; gap: 1rem; margin-top: 1rem;">
                            <button type="button" 
                                    onclick="if(confirm('✅ Confirmar APROVAÇÃO do pagamento?\n\nO evento será ativado e o cliente será notificado.')) { document.getElementById('acaoInput').value='aprovar'; document.getElementById('formAcao').submit(); }" 
                                    class="btn btn-success btn-block">
                                ✅ Aprovar Pagamento
                            </button>

                            <button type="button" 
                                    onclick="if(confirm('❌ Confirmar REJEIÇÃO do pagamento?\n\nO cliente será notificado sobre a rejeição.')) { document.getElementById('acaoInput').value='rejeitar'; document.getElementById('formAcao').submit(); }" 
                                    class="btn btn-danger btn-block">
                                ❌ Rejeitar Pagamento
                            </button>
                        </div>

                        <input type="hidden" name="acao" id="acaoInput" value="">
                    </form>

                    <hr style="margin: 1.5rem 0;">

                    <a href="pagamentos.php" class="btn btn-secondary btn-block">
                        ← Voltar para Pagamentos
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="card mt-3">
                <div class="card-body text-center">
                    <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                        Este pagamento já foi processado.
                    </p>
                    <a href="pagamentos.php" class="btn btn-secondary btn-block">
                        ← Voltar para Pagamentos
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>