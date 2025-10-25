<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Processar Pagamento do Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$eventoId = get('evento');

if (!$eventoId) {
    Session::setFlash('error', 'Evento não especificado');
    redirect('/cliente/dashboard.php');
}

// Buscar evento e verificar se pertence ao cliente
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome, p.preco_aoa, p.preco_usd, p.max_convites
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/cliente/dashboard.php');
}

// Verificar se já está pago
if ($evento['pago']) {
    Session::setFlash('info', 'Este evento já está pago');
    redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
}

// Buscar pagamento pendente
$stmt = $db->prepare("
    SELECT * FROM pagamentos 
    WHERE evento_id = ? AND cliente_id = ? 
    ORDER BY criado_em DESC 
    LIMIT 1
");
$stmt->execute([$eventoId, $clienteId]);
$pagamento = $stmt->fetch();

$errors = [];
$success = '';

// Processar método de pagamento escolhido
if (isPost()) {
    $metodoPagamento = post('metodo_pagamento');
    
    if (empty($metodoPagamento)) {
        $errors['metodo'] = 'Selecione um método de pagamento';
    } else {
        try {
            if ($metodoPagamento === 'referencia') {
                // Gerar referência para pagamento
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET metodo_pagamento = 'referencia', status = 'pendente',
                        dados_pagamento = ?
                    WHERE id = ?
                ");
                
                // Gerar entidade e referência Multicaixa (simulado)
                $entidade = '11604'; // Entidade fictícia para exemplo
                $referencia = substr($pagamento['referencia'], -9);
                
                $dadosPagamento = json_encode([
                    'entidade' => $entidade,
                    'referencia' => $referencia,
                    'valor' => $pagamento['valor']
                ]);
                
                $stmt->execute([$dadosPagamento, $pagamento['id']]);
                
                Session::setFlash('success', 'Referência gerada com sucesso! Use os dados para efetuar o pagamento.');
                redirect('/cliente/pagamento-referencia.php?id=' . $pagamento['id']);
                
            } elseif ($metodoPagamento === 'express') {
                // Redirecionar para API Express (simulado)
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET metodo_pagamento = 'express', status = 'processando'
                    WHERE id = ?
                ");
                $stmt->execute([$pagamento['id']]);
                
                Session::setFlash('info', 'Redirecionando para Express...');
                redirect('/cliente/pagamento-express.php?id=' . $pagamento['id']);
                
            } elseif ($metodoPagamento === 'paypal') {
                // Redirecionar para PayPal (simulado)
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET metodo_pagamento = 'paypal', status = 'processando'
                    WHERE id = ?
                ");
                $stmt->execute([$pagamento['id']]);
                
                Session::setFlash('info', 'Redirecionando para PayPal...');
                redirect('/cliente/pagamento-paypal.php?id=' . $pagamento['id']);
                
            } elseif ($metodoPagamento === 'transferencia') {
                // Enviar comprovante de transferência
                if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                    $upload = uploadFile($_FILES['comprovante'], 'comprovantes');
                    
                    if ($upload['success']) {
                        $stmt = $db->prepare("
                            UPDATE pagamentos 
                            SET metodo_pagamento = 'transferencia', 
                                status = 'processando',
                                comprovante = ?,
                                dados_pagamento = ?
                            WHERE id = ?
                        ");
                        
                        $dadosPagamento = json_encode([
                            'observacao' => post('observacao_transferencia', '')
                        ]);
                        
                        $stmt->execute([$upload['path'], $dadosPagamento, $pagamento['id']]);
                        
                        // Notificar administradores
                        $stmtAdmins = $db->query("SELECT id FROM administradores WHERE nivel_acesso_id IN (1, 2) AND status = 'ativo'");
                        $admins = $stmtAdmins->fetchAll();
                        
                        foreach ($admins as $admin) {
                            createNotification(
                                'admin',
                                $admin['id'],
                                'Novo comprovante de pagamento',
                                "Cliente enviou comprovante para o evento: {$evento['nome_evento']}",
                                'info',
                                '/admin/pagamentos.php'
                            );
                        }
                        
                        Session::setFlash('success', 'Comprovante enviado! Aguarde a aprovação do administrador.');
                        redirect('/cliente/pagamentos.php');
                    } else {
                        $errors['comprovante'] = $upload['message'];
                    }
                } else {
                    $errors['comprovante'] = 'Envie o comprovante de transferência';
                }
            }
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao processar pagamento. Tente novamente.';
            error_log("Erro ao processar pagamento: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Processar Pagamento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="meus-eventos.php">Meus Eventos</a>
                <span class="breadcrumb-separator">/</span>
                <span>Pagamento</span>
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
        <!-- Informações do Evento -->
        <div class="col-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Resumo do Evento</h3>
                </div>
                <div class="card-body">
                    <h4 style="margin-bottom: 1rem; color: var(--primary-color);">
                        <?php echo Security::clean($evento['nome_evento']); ?>
                    </h4>
                    
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Tipo</small>
                        <div><strong><?php echo getEventType($evento['tipo_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Data</small>
                        <div><strong><?php echo formatDateTime($evento['data_evento'], 'd/m/Y \à\s H:i'); ?></strong></div>
                    </div>

                    <?php if ($evento['local_nome']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Local</small>
                        <div><strong><?php echo Security::clean($evento['local_nome']); ?></strong></div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Plano Escolhido</small>
                        <div><strong><?php echo $evento['plano_nome']; ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Convites Inclusos</small>
                        <div><strong><?php echo number_format($evento['max_convites']); ?> convites</strong></div>
                    </div>

                    <hr style="margin: 1.5rem 0;">

                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.5rem;">
                        <strong>Total:</strong>
                        <strong style="color: var(--success-color);">
                            <?php echo formatMoney($evento['preco_aoa']); ?>
                        </strong>
                    </div>

                    <div style="text-align: center; margin-top: 0.5rem;">
                        <small style="color: var(--gray-medium);">
                            ou <?php echo formatMoney($evento['preco_usd'], 'USD'); ?>
                        </small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔒 Pagamento Seguro</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; color: var(--gray-dark);">
                        Seus dados estão protegidos e todas as transações são seguras e criptografadas.
                    </p>
                </div>
            </div>
        </div>

        <!-- Métodos de Pagamento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Escolha o Método de Pagamento</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <!-- Referência Multicaixa -->
                        <label class="payment-method-card" onclick="selectPayment('referencia')">
                            <input type="radio" name="metodo_pagamento" value="referencia" id="referencia" required>
                            <div class="payment-method-content">
                                <div class="payment-method-icon">
                                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4>Referência Multicaixa</h4>
                                    <p>Gere uma referência e pague em qualquer caixa ATM ou banco</p>
                                    <span class="badge badge-success">Recomendado para Angola</span>
                                </div>
                            </div>
                        </label>

                        <!-- Express -->
                        <label class="payment-method-card" onclick="selectPayment('express')">
                            <input type="radio" name="metodo_pagamento" value="express" id="express" required>
                            <div class="payment-method-content">
                                <div class="payment-method-icon" style="background: linear-gradient(135deg, #FF6B6B, #C44569);">
                                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4>Express</h4>
                                    <p>Pagamento rápido via Express (carteira digital)</p>
                                    <span class="badge badge-warning">Popular em Angola</span>
                                </div>
                            </div>
                        </label>

                        <!-- PayPal -->
                        <label class="payment-method-card" onclick="selectPayment('paypal')">
                            <input type="radio" name="metodo_pagamento" value="paypal" id="paypal" required>
                            <div class="payment-method-content">
                                <div class="payment-method-icon" style="background: linear-gradient(135deg, #00457C, #0070BA);">
                                    <svg width="40" height="40" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.93 4.778-4.005 7.201-9.138 7.201h-2.19a.563.563 0 0 0-.556.479l-1.187 7.527h-.506l-.24 1.516a.56.56 0 0 0 .554.647h3.882c.46 0 .85-.334.922-.788.06-.26.76-4.852.76-4.852.072-.453.462-.788.922-.788h.58c3.76 0 6.705-1.528 7.565-5.946.36-1.847.174-3.388-.720-4.456z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4>PayPal</h4>
                                    <p>Pagamento internacional seguro via PayPal</p>
                                    <span class="badge badge-info">Internacional</span>
                                </div>
                            </div>
                        </label>

                        <!-- Transferência Bancária -->
                        <label class="payment-method-card" onclick="selectPayment('transferencia')">
                            <input type="radio" name="metodo_pagamento" value="transferencia" id="transferencia" required>
                            <div class="payment-method-content">
                                <div class="payment-method-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4>Transferência Bancária</h4>
                                    <p>Transfira e envie o comprovante para aprovação</p>
                                    <span class="badge badge-secondary">Aprovação manual</span>
                                </div>
                            </div>
                        </label>

                        <!-- Campos para Transferência -->
                        <div id="transferencia-fields" style="display: none; margin-top: 2rem; padding: 1.5rem; background: var(--gray-lighter); border-radius: var(--border-radius);">
                            <h5 style="margin-bottom: 1rem;">Dados para Transferência</h5>
                            <div style="background: white; padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1rem;">
                                <p><strong>Banco:</strong> BAI - Banco Angolano de Investimentos</p>
                                <p><strong>Titular:</strong> Gestão Eventos Pro, Lda</p>
                                <p><strong>IBAN:</strong> AO06.0006.0000.1234.5678.9012.3</p>
                                <p><strong>Valor:</strong> <span style="color: var(--success-color); font-size: 1.25rem; font-weight: 600;"><?php echo formatMoney($evento['preco_aoa']); ?></span></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Comprovante de Transferência</label>
                                <input type="file" name="comprovante" class="form-control" accept="image/*,application/pdf" id="comprovanteInput">
                                <span class="form-help">Formatos aceitos: JPG, PNG, PDF (máx. 5MB)</span>
                                <?php if (isset($errors['comprovante'])): ?>
                                    <span class="form-error"><?php echo $errors['comprovante']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Observação (opcional)</label>
                                <textarea name="observacao_transferencia" class="form-control" rows="3" placeholder="Informações adicionais sobre a transferência..."></textarea>
                            </div>

                            <div class="alert alert-info">
                                <div class="alert-icon">ℹ️</div>
                                <div class="alert-content">
                                    <p class="alert-message">
                                        Após enviar o comprovante, aguarde até 24 horas para aprovação. 
                                        Você receberá uma notificação quando o pagamento for aprovado.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($errors['metodo'])): ?>
                            <span class="form-error"><?php echo $errors['metodo']; ?></span>
                        <?php endif; ?>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Prosseguir com Pagamento
                            </button>
                            <a href="meus-eventos.php" class="btn btn-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Informações Adicionais -->
            <div class="card mt-3">
                <div class="card-body">
                    <h5 style="margin-bottom: 1rem;">❓ Dúvidas Frequentes</h5>
                    <details style="margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem 0;">Quando meu evento será ativado?</summary>
                        <p style="color: var(--gray-dark); padding: 0.5rem 0;">
                            Seu evento será ativado automaticamente após a confirmação do pagamento. 
                            Para referência e transferência, pode levar até 24 horas.
                        </p>
                    </details>
                    <details style="margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem 0;">Posso cancelar depois de pagar?</summary>
                        <p style="color: var(--gray-dark); padding: 0.5rem 0;">
                            Sim, você pode solicitar reembolso em até 7 dias após o pagamento, 
                            desde que o evento não tenha sido realizado.
                        </p>
                    </details>
                    <details>
                        <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem 0;">O pagamento é seguro?</summary>
                        <p style="color: var(--gray-dark); padding: 0.5rem 0;">
                            Sim! Todos os pagamentos são processados através de gateways seguros e criptografados.
                        </p>
                    </details>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-method-card {
    display: block;
    border: 2px solid var(--gray-light);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: var(--transition-fast);
}

.payment-method-card:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
}

.payment-method-card input[type="radio"] {
    display: none;
}

.payment-method-card input[type="radio"]:checked + .payment-method-content {
    border-left: 4px solid var(--primary-color);
}

.payment-method-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding-left: 1rem;
    border-left: 4px solid transparent;
    transition: var(--transition-fast);
}

.payment-method-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--border-radius-sm);
    background: linear-gradient(135deg, var(--success-color), #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.payment-method-content h4 {
    margin-bottom: 0.5rem;
    font-size: 1.125rem;
}

.payment-method-content p {
    margin: 0;
    color: var(--gray-medium);
    font-size: 0.875rem;
}

details summary {
    color: var(--dark-color);
}

details[open] summary {
    margin-bottom: 0.5rem;
}
</style>

<script>
function selectPayment(method) {
    // Mostrar/ocultar campos de transferência
    const transferenciaFields = document.getElementById('transferencia-fields');
    if (method === 'transferencia') {
        transferenciaFields.style.display = 'block';
    } else {
        transferenciaFields.style.display = 'none';
    }
}

// Verificar se transferência está selecionada ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const transferenciaRadio = document.getElementById('transferencia');
    if (transferenciaRadio && transferenciaRadio.checked) {
        selectPayment('transferencia');
    }
});
</script>

<?php include '../includes/cliente_footer.php'; ?>