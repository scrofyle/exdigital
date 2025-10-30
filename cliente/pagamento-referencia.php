<?php
/**
 * CLIENTE - PAGAMENTO POR REFERÊNCIA MULTICAIXA
 * Exibir referência e instruções de pagamento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Obter ID do pagamento
$pagamentoId = get('id');
if (!$pagamentoId) {
    Session::setFlash('error', 'Pagamento não encontrado!');
    redirect('/cliente/pagamentos.php');
}

// Buscar detalhes do pagamento
$stmt = $db->prepare("
    SELECT p.*, pl.nome as plano_nome, pl.descricao as plano_descricao,
           e.nome_evento, e.codigo_evento
    FROM pagamentos p
    JOIN planos pl ON p.plano_id = pl.id
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE p.id = ? AND p.cliente_id = ?
");
$stmt->execute([$pagamentoId, $clienteId]);
$pagamento = $stmt->fetch();

if (!$pagamento) {
    Session::setFlash('error', 'Pagamento não encontrado!');
    redirect('/cliente/pagamentos.php');
}

// Verificar se já foi pago
if ($pagamento['status'] === 'aprovado') {
    Session::setFlash('info', 'Este pagamento já foi aprovado!');
    redirect('/cliente/pagamentos.php');
}

// Buscar configurações
$stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
$stmt->execute(['multicaixa_entity']);
$entidade = $stmt->fetchColumn() ?: '11223';

// Gerar referência se não existir
$referencia = $pagamento['referencia'];
if (!$referencia) {
    $referencia = str_pad($pagamento['id'], 9, '0', STR_PAD_LEFT);
    $stmt = $db->prepare("UPDATE pagamentos SET referencia = ? WHERE id = ?");
    $stmt->execute([$referencia, $pagamentoId]);
}

// Data de validade (3 dias)
$dataValidade = date('d/m/Y', strtotime($pagamento['criado_em'] . ' +3 days'));

include '../includes/cliente_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">💳 Pagamento por Referência Multicaixa</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <a href="pagamentos.php">Pagamentos</a>
            <span class="breadcrumb-separator">/</span>
            <span>Referência</span>
        </div>
    </div>

    <div class="row">
        <!-- Referência -->
        <div class="col-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #FE0000, #B80000); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        <i class="bi bi-bank"></i> Referência Multicaixa Express
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Alerta -->
                    <div class="alert alert-warning">
                        <div class="alert-icon">⏰</div>
                        <div class="alert-content">
                            <strong>Validade:</strong> Esta referência expira em <?php echo $dataValidade; ?>
                        </div>
                    </div>

                    <!-- Dados da Referência -->
                    <div style="background: #F8F9FA; padding: 2rem; border-radius: var(--border-radius); text-align: center; margin: 2rem 0;">
                        <div style="margin-bottom: 2rem;">
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">ENTIDADE</label>
                            <div style="font-size: 2.5rem; font-weight: 700; color: var(--dark-color);">
                                <?php echo $entidade; ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 2rem;">
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">REFERÊNCIA</label>
                            <div style="font-size: 3rem; font-weight: 700; color: var(--primary-color); letter-spacing: 2px;">
                                <?php echo chunk_split($referencia, 3, ' '); ?>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">VALOR A PAGAR</label>
                            <div style="font-size: 2.5rem; font-weight: 700; color: var(--success-color);">
                                <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                            </div>
                        </div>

                        <button onclick="copyReferencia('<?php echo $referencia; ?>')" 
                                class="btn btn-primary btn-lg mt-4">
                            <i class="bi bi-clipboard"></i> Copiar Referência
                        </button>
                    </div>

                    <!-- Instruções -->
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            <h5>Como Pagar:</h5>
                            <ol style="margin: 0; padding-left: 1.5rem;">
                                <li>Use a <strong>Entidade</strong> e <strong>Referência</strong> acima</li>
                                <li>Pague em qualquer ATM Multicaixa ou aplicativo bancário</li>
                                <li>Após pagamento, aguarde até 24h para aprovação</li>
                                <li>Você receberá notificação por email quando aprovado</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Passo a Passo -->
                    <h5 class="mt-4 mb-3">📱 Passo a Passo - Multicaixa Express</h5>
                    <div class="row">
                        <div class="col-6">
                            <div style="padding: 1.5rem; background: #F8F9FA; border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <h6 style="color: var(--primary-color);">1️⃣ ATM Multicaixa</h6>
                                <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                                    <li>Insira o cartão</li>
                                    <li>Selecione "Pagamentos"</li>
                                    <li>Escolha "Serviços / Outros"</li>
                                    <li>Digite a Entidade: <strong><?php echo $entidade; ?></strong></li>
                                    <li>Digite a Referência: <strong><?php echo $referencia; ?></strong></li>
                                    <li>Confirme o valor e pague</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1.5rem; background: #F8F9FA; border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <h6 style="color: var(--primary-color);">2️⃣ App Multicaixa Express</h6>
                                <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                                    <li>Abra o app Multicaixa Express</li>
                                    <li>Faça login</li>
                                    <li>Vá em "Pagamentos"</li>
                                    <li>Selecione "Pagar Referência"</li>
                                    <li>Entidade: <strong><?php echo $entidade; ?></strong></li>
                                    <li>Referência: <strong><?php echo $referencia; ?></strong></li>
                                    <li>Confirme e pague</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="text-center mt-4">
                        <a href="pagamentos.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar aos Pagamentos
                        </a>
                        <button onclick="window.print()" class="btn btn-outline">
                            <i class="bi bi-printer"></i> Imprimir Referência
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações Laterais -->
        <div class="col-4">
            <!-- Resumo do Pedido -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Resumo do Pedido</h3>
                </div>
                <div class="card-body">
                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Plano</small>
                        <div><strong><?php echo Security::clean($pagamento['plano_nome']); ?></strong></div>
                    </div>

                    <?php if ($pagamento['evento_id']): ?>
                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Evento</small>
                        <div><strong><?php echo Security::clean($pagamento['nome_evento']); ?></strong></div>
                        <small><?php echo Security::clean($pagamento['codigo_evento']); ?></small>
                    </div>
                    <?php endif; ?>

                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Valor</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color);">
                            <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                        </div>
                    </div>

                    <div style="padding: 1rem 0;">
                        <small class="text-muted">Status</small>
                        <div><?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Dúvidas -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">❓ Precisa de Ajuda?</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem;">Se tiver dúvidas sobre o pagamento, entre em contato:</p>
                    <p style="margin: 0;">
                        <i class="bi bi-envelope"></i> <?php echo ADMIN_EMAIL; ?><br>
                        <i class="bi bi-phone"></i> +244 948 005 566
                    </p>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⏱️ Próximos Passos</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item active">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <strong>Referência Gerada</strong>
                                <small><?php echo formatDateTime($pagamento['criado_em']); ?></small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <strong>Efetuar Pagamento</strong>
                                <small>Use a referência em qualquer ATM</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <strong>Aprovação</strong>
                                <small>Até 24h após pagamento</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0;
    width: 2px;
    height: 100%;
    background: var(--gray-lighter);
}

.timeline-item.active::before {
    background: var(--primary-color);
}

.timeline-marker {
    position: absolute;
    left: -1.813rem;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--gray-light);
    border: 2px solid white;
}

.timeline-item.active .timeline-marker {
    background: var(--primary-color);
}

.timeline-content strong {
    display: block;
    margin-bottom: 0.25rem;
}

.timeline-content small {
    color: var(--gray-medium);
    font-size: 0.813rem;
}

@media print {
    .sidebar, .header, .page-breadcrumb, .btn, .card:not(:first-of-type) {
        display: none !important;
    }
}
</style>

<script>
function copyReferencia(referencia) {
    const textarea = document.createElement('textarea');
    textarea.value = referencia;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Referência copiada com sucesso!', 'success');
    } catch (err) {
        showNotification('Erro ao copiar referência', 'error');
    }
    
    document.body.removeChild(textarea);
}
</script>

<?php include '../includes/cliente_footer.php'; ?>