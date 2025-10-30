<?php
/**
 * CLIENTE - PAGAMENTO VIA PAYPAL
 * Integração com PayPal Checkout
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
           e.nome_evento, e.codigo_evento, c.nome_completo, c.email
    FROM pagamentos p
    JOIN planos pl ON p.plano_id = pl.id
    JOIN clientes c ON p.cliente_id = c.id
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

// Buscar credenciais PayPal
$stmt = $db->prepare("SELECT chave, valor FROM configuracoes WHERE chave IN ('paypal_client_id', 'paypal_secret')");
$stmt->execute();
$config = [];
foreach ($stmt->fetchAll() as $row) {
    $config[$row['chave']] = $row['valor'];
}

$paypalClientId = $config['paypal_client_id'] ?? '';
$paypalSecret = $config['paypal_secret'] ?? '';

// Converter AOA para USD (taxa fixa para exemplo - em produção usar API de câmbio)
$taxaCambio = 0.0012; // 1 AOA = 0.0012 USD (aproximado)
$valorUSD = $pagamento['moeda'] === 'AOA' 
    ? round($pagamento['valor'] * $taxaCambio, 2) 
    : $pagamento['valor'];

include '../includes/cliente_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">💰 Pagamento via PayPal</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <a href="pagamentos.php">Pagamentos</a>
            <span class="breadcrumb-separator">/</span>
            <span>PayPal</span>
        </div>
    </div>

    <div class="row">
        <!-- Área de Pagamento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #0070BA, #1E3A8A); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        <i class="bi bi-paypal"></i> Pagar com PayPal
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Informação sobre Conversão -->
                    <?php if ($pagamento['moeda'] === 'AOA'): ?>
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            <strong>Conversão de Moeda</strong>
                            <p style="margin: 0;">
                                Valor original: <strong><?php echo formatMoney($pagamento['valor'], 'AOA'); ?></strong><br>
                                Valor em USD: <strong>$<?php echo number_format($valorUSD, 2); ?> USD</strong><br>
                                <small>Taxa de câmbio aproximada: 1 AOA = 0.0012 USD</small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Resumo do Pagamento -->
                    <div style="background: #F8F9FA; padding: 2rem; border-radius: var(--border-radius); margin: 2rem 0; text-align: center;">
                        <div style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">PLANO SELECIONADO</label>
                            <div style="font-size: 1.5rem; font-weight: 700;">
                                <?php echo Security::clean($pagamento['plano_nome']); ?>
                            </div>
                            <small class="text-muted"><?php echo Security::clean($pagamento['plano_descricao']); ?></small>
                        </div>

                        <?php if ($pagamento['evento_id']): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">EVENTO</label>
                            <div style="font-weight: 600;">
                                <?php echo Security::clean($pagamento['nome_evento']); ?>
                            </div>
                            <small class="text-muted"><?php echo Security::clean($pagamento['codigo_evento']); ?></small>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">VALOR A PAGAR</label>
                            <div style="font-size: 2.5rem; font-weight: 700; color: #0070BA;">
                                $<?php echo number_format($valorUSD, 2); ?> USD
                            </div>
                        </div>
                    </div>

                    <!-- Vantagens do PayPal -->
                    <h5 class="mb-3">✨ Por que usar PayPal?</h5>
                    <div class="row mb-4">
                        <div class="col-6">
                            <div style="padding: 1rem; background: #EFF6FF; border-left: 4px solid #0070BA; border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: #0070BA;">🌍 Aceito Mundialmente</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Mais de 400 milhões de usuários</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #F0FDF4; border-left: 4px solid var(--success-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--success-color);">🔒 Segurança Total</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Proteção ao comprador garantida</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #FEF3C7; border-left: 4px solid var(--warning-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--warning-color);">⚡ Pagamento Rápido</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Confirmação em segundos</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #F5F3FF; border-left: 4px solid var(--primary-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--primary-color);">💳 Várias Opções</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Cartão, saldo ou crédito PayPal</p>
                            </div>
                        </div>
                    </div>

                    <!-- Botão PayPal -->
                    <div id="paypal-button-container" style="margin: 2rem 0;"></div>

                    <!-- Aviso de Segurança -->
                    <div class="alert alert-success">
                        <div class="alert-icon">🔒</div>
                        <div class="alert-content">
                            <strong>Pagamento 100% Seguro</strong>
                            <p style="margin: 0;">
                                Seus dados financeiros são processados diretamente pelo PayPal. 
                                Não armazenamos informações do seu cartão.
                            </p>
                        </div>
                    </div>

                    <!-- Como Funciona -->
                    <hr class="my-4">
                    <h5 class="mb-3">📋 Como Funciona?</h5>
                    <div class="timeline">
                        <div class="timeline-item active">
                            <div class="timeline-marker">1</div>
                            <div class="timeline-content">
                                <strong>Clique no Botão PayPal</strong>
                                <small>Será aberta uma janela segura do PayPal</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker">2</div>
                            <div class="timeline-content">
                                <strong>Faça Login ou Pague como Convidado</strong>
                                <small>Use sua conta PayPal ou pague com cartão</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker">3</div>
                            <div class="timeline-content">
                                <strong>Confirme o Pagamento</strong>
                                <small>Revise os detalhes e confirme</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker">4</div>
                            <div class="timeline-content">
                                <strong>Aprovação Instantânea</strong>
                                <small>Receba confirmação imediata por email</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="pagamentos.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar aos Pagamentos
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Informações Laterais -->
        <div class="col-4">
            <!-- Detalhes do Pedido -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Resumo do Pedido</h3>
                </div>
                <div class="card-body">
                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Referência</small>
                        <div><strong><?php echo Security::clean($pagamento['referencia']); ?></strong></div>
                    </div>

                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Plano</small>
                        <div><strong><?php echo Security::clean($pagamento['plano_nome']); ?></strong></div>
                    </div>

                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Valor Original</small>
                        <div><strong><?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?></strong></div>
                    </div>

                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Valor em USD</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #0070BA;">
                            $<?php echo number_format($valorUSD, 2); ?>
                        </div>
                    </div>

                    <div style="padding: 1rem 0;">
                        <small class="text-muted">Status</small>
                        <div><?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Formas de Pagamento no PayPal -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">💳 Opções no PayPal</h3>
                </div>
                <div class="card-body">
                    <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-paypal" style="font-size: 1.5rem; color: #0070BA;"></i>
                            <div>
                                <strong>Saldo PayPal</strong><br>
                                <small class="text-muted">Use seu saldo disponível</small>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-credit-card" style="font-size: 1.5rem; color: var(--primary-color);"></i>
                            <div>
                                <strong>Cartão de Crédito</strong><br>
                                <small class="text-muted">Visa, Mastercard, Amex</small>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-credit-card-2-front" style="font-size: 1.5rem; color: var(--success-color);"></i>
                            <div>
                                <strong>Cartão de Débito</strong><br>
                                <small class="text-muted">Débito direto da conta</small>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 0.75rem 0;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-bank" style="font-size: 1.5rem; color: var(--warning-color);"></i>
                            <div>
                                <strong>Conta Bancária</strong><br>
                                <small class="text-muted">Link direto com banco</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Proteção ao Comprador -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">🛡️ Proteção PayPal</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                        <strong>Proteção ao Comprador:</strong> Se algo der errado, você pode abrir uma disputa e ser reembolsado.
                    </p>
                    
                    <div style="font-size: 0.813rem; color: var(--gray-medium); line-height: 1.6;">
                        ✓ Reembolso total se não receber o serviço<br>
                        ✓ Suporte 24/7 do PayPal<br>
                        ✓ Criptografia SSL de nível bancário<br>
                        ✓ Monitoramento de fraudes
                    </div>
                </div>
            </div>

            <!-- Dúvidas -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">❓ Precisa de Ajuda?</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem;">Entre em contato conosco:</p>
                    <p style="margin: 0; font-size: 0.875rem;">
                        <i class="bi bi-envelope"></i> <?php echo ADMIN_EMAIL; ?><br>
                        <i class="bi bi-phone"></i> +244 948 005 566<br>
                        <i class="bi bi-whatsapp"></i> WhatsApp: +244 948 005 566
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PayPal SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypalClientId; ?>&currency=USD"></script>

<script>
// Renderizar botão PayPal
paypal.Buttons({
    style: {
        layout: 'vertical',
        color: 'blue',
        shape: 'rect',
        label: 'paypal'
    },
    
    // Criar ordem
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                reference_id: '<?php echo $pagamento['referencia']; ?>',
                description: '<?php echo addslashes($pagamento['plano_nome']); ?>',
                amount: {
                    currency_code: 'USD',
                    value: '<?php echo number_format($valorUSD, 2, '.', ''); ?>'
                }
            }]
        });
    },
    
    // Aprovar pagamento
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            // Mostrar loading
            showLoading();
            
            // Enviar para servidor
            fetch('<?php echo SITE_URL; ?>/api/webhook-pagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    payment_id: <?php echo $pagamentoId; ?>,
                    paypal_order_id: data.orderID,
                    paypal_payer_id: data.payerID,
                    payment_details: details
                })
            })
            .then(response => response.json())
            .then(result => {
                hideLoading();
                
                if (result.success) {
                    showNotification('Pagamento aprovado com sucesso!', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo SITE_URL; ?>/cliente/pagamentos.php';
                    }, 2000);
                } else {
                    showNotification('Erro ao processar pagamento', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                showNotification('Erro ao comunicar com servidor', 'error');
                console.error('Error:', error);
            });
        });
    },
    
    // Cancelar pagamento
    onCancel: function(data) {
        showNotification('Pagamento cancelado', 'info');
    },
    
    // Erro no pagamento
    onError: function(err) {
        showNotification('Erro ao processar pagamento. Tente novamente.', 'error');
        console.error('PayPal Error:', err);
    }
    
}).render('#paypal-button-container');
</script>

<style>
.timeline {
    position: relative;
    padding-left: 3rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -2.188rem;
    top: 1.5rem;
    width: 2px;
    height: 100%;
    background: var(--gray-lighter);
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-marker {
    position: absolute;
    left: -2.75rem;
    top: 0;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: var(--gray-light);
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.timeline-item.active .timeline-marker {
    background: #0070BA;
}

.timeline-content strong {
    display: block;
    margin-bottom: 0.25rem;
    font-size: 0.938rem;
}

.timeline-content small {
    color: var(--gray-medium);
    font-size: 0.813rem;
}
</style>

<?php include '../includes/cliente_footer.php'; ?>