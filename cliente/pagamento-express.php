<?php
/**
 * CLIENTE - PAGAMENTO VIA MULTICAIXA EXPRESS
 * Integração com API Multicaixa Express
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
           e.nome_evento, e.codigo_evento, c.nome_completo, c.email, c.telefone
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

// Buscar API Key
$stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'express_api_key'");
$stmt->execute();
$apiKey = $stmt->fetchColumn();

// Processar pagamento Express
$paymentUrl = null;
$paymentId = null;
$error = null;

if (isPost() && isset($_POST['iniciar_pagamento'])) {
    try {
        // Dados do pagamento
        $dadosPagamento = [
            'amount' => number_format($pagamento['valor'], 2, '.', ''),
            'currency' => $pagamento['moeda'],
            'reference' => $pagamento['referencia'],
            'description' => 'Pagamento ' . $pagamento['plano_nome'],
            'customer' => [
                'name' => $pagamento['nome_completo'],
                'email' => $pagamento['email'],
                'phone' => $pagamento['telefone']
            ],
            'callback_url' => SITE_URL . '/api/webhook-pagamento.php',
            'return_url' => SITE_URL . '/cliente/pagamentos.php'
        ];

        // Chamar API Multicaixa Express
        $ch = curl_init('https://api.multicaixa.com/v1/payments');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosPagamento));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $result = json_decode($response, true);
            
            if (isset($result['payment_url'])) {
                $paymentUrl = $result['payment_url'];
                $paymentId = $result['payment_id'] ?? null;

                // Atualizar pagamento com dados da API
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET status = 'processando', 
                        dados_pagamento = ?
                    WHERE id = ?
                ");
                $stmt->execute([json_encode($result), $pagamentoId]);

                // Redirecionar para página de pagamento
                header('Location: ' . $paymentUrl);
                exit;
            } else {
                $error = 'Erro ao gerar link de pagamento. Tente novamente.';
            }
        } else {
            $error = 'Erro na comunicação com Multicaixa Express. Código: ' . $httpCode;
        }

    } catch (Exception $e) {
        $error = 'Erro ao processar pagamento: ' . $e->getMessage();
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">💳 Pagamento Multicaixa Express</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <a href="pagamentos.php">Pagamentos</a>
            <span class="breadcrumb-separator">/</span>
            <span>Express</span>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">❌</div>
        <div class="alert-content">
            <strong>Erro!</strong>
            <p class="alert-message"><?php echo $error; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulário de Pagamento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #FE0000, #B80000); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        <i class="bi bi-credit-card"></i> Pagamento via Multicaixa Express
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Informação -->
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            <strong>Pagamento Rápido e Seguro</strong>
                            <p style="margin: 0;">
                                Você será redirecionado para a página segura do Multicaixa Express para completar o pagamento.
                            </p>
                        </div>
                    </div>

                    <!-- Resumo do Pagamento -->
                    <div style="background: #F8F9FA; padding: 2rem; border-radius: var(--border-radius); margin: 2rem 0;">
                        <div class="row">
                            <div class="col-6">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">PLANO</label>
                                <div style="font-size: 1.25rem; font-weight: 600;">
                                    <?php echo Security::clean($pagamento['plano_nome']); ?>
                                </div>
                            </div>

                            <div class="col-6 text-right">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">VALOR A PAGAR</label>
                                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                    <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($pagamento['evento_id']): ?>
                        <hr style="margin: 1.5rem 0;">
                        <div>
                            <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">EVENTO</label>
                            <div style="font-weight: 600;">
                                <?php echo Security::clean($pagamento['nome_evento']); ?>
                            </div>
                            <small class="text-muted"><?php echo Security::clean($pagamento['codigo_evento']); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Vantagens do Express -->
                    <h5 class="mb-3">✨ Vantagens do Multicaixa Express</h5>
                    <div class="row">
                        <div class="col-6">
                            <div style="padding: 1rem; background: #F0FDF4; border-left: 4px solid var(--success-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--success-color);">⚡ Pagamento Instantâneo</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Confirmação imediata após pagamento</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #EFF6FF; border-left: 4px solid var(--info-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--info-color);">🔒 100% Seguro</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Criptografia de ponta a ponta</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #FEF3C7; border-left: 4px solid var(--warning-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--warning-color);">💳 Múltiplas Opções</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Cartão, Multicaixa ou Saldo Express</p>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="padding: 1rem; background: #F5F3FF; border-left: 4px solid var(--primary-color); border-radius: var(--border-radius); margin-bottom: 1rem;">
                                <strong style="color: var(--primary-color);">📱 Mobile Friendly</strong>
                                <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">Pague direto do seu celular</p>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Pagamento -->
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="iniciar_pagamento" value="1">
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-success btn-lg" style="padding: 1rem 3rem;">
                                <i class="bi bi-credit-card"></i> Pagar Agora com Express
                            </button>
                            
                            <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--gray-medium);">
                                <i class="bi bi-shield-check"></i> Seus dados estão protegidos
                            </p>
                        </div>
                    </form>

                    <!-- Como Funciona -->
                    <hr class="my-4">
                    <h5 class="mb-3">📋 Como Funciona?</h5>
                    <div class="timeline">
                        <div class="timeline-item active">
                            <div class="timeline-marker">1</div>
                            <div class="timeline-content">
                                <strong>Clique em "Pagar Agora"</strong>
                                <small>Você será redirecionado para a página segura do Multicaixa</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker">2</div>
                            <div class="timeline-content">
                                <strong>Escolha o Método de Pagamento</strong>
                                <small>Cartão, Multicaixa Express ou Saldo</small>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker">3</div>
                            <div class="timeline-content">
                                <strong>Confirme o Pagamento</strong>
                                <small>Insira seus dados e confirme</small>
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
                    <h3 class="card-title">📋 Detalhes do Pedido</h3>
                </div>
                <div class="card-body">
                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Referência</small>
                        <div><strong><?php echo Security::clean($pagamento['referencia']); ?></strong></div>
                    </div>

                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                        <small class="text-muted">Plano</small>
                        <div><strong><?php echo Security::clean($pagamento['plano_nome']); ?></strong></div>
                        <small><?php echo Security::clean($pagamento['plano_descricao']); ?></small>
                    </div>

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

            <!-- Métodos Aceitos -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">💳 Métodos Aceitos</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div style="text-align: center; padding: 1rem; background: #F8F9FA; border-radius: var(--border-radius);">
                            <i class="bi bi-credit-card" style="font-size: 2rem; color: var(--primary-color);"></i>
                            <div style="margin-top: 0.5rem; font-size: 0.813rem; font-weight: 600;">Cartão de Débito</div>
                        </div>

                        <div style="text-align: center; padding: 1rem; background: #F8F9FA; border-radius: var(--border-radius);">
                            <i class="bi bi-credit-card-2-front" style="font-size: 2rem; color: var(--success-color);"></i>
                            <div style="margin-top: 0.5rem; font-size: 0.813rem; font-weight: 600;">Cartão de Crédito</div>
                        </div>

                        <div style="text-align: center; padding: 1rem; background: #F8F9FA; border-radius: var(--border-radius);">
                            <i class="bi bi-phone" style="font-size: 2rem; color: var(--danger-color);"></i>
                            <div style="margin-top: 0.5rem; font-size: 0.813rem; font-weight: 600;">Multicaixa Express</div>
                        </div>

                        <div style="text-align: center; padding: 1rem; background: #F8F9FA; border-radius: var(--border-radius);">
                            <i class="bi bi-wallet2" style="font-size: 2rem; color: var(--warning-color);"></i>
                            <div style="margin-top: 0.5rem; font-size: 0.813rem; font-weight: 600;">Saldo Express</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dúvidas -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">❓ Dúvidas?</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem;">Precisa de ajuda? Entre em contato:</p>
                    <p style="margin: 0; font-size: 0.875rem;">
                        <i class="bi bi-envelope"></i> <?php echo ADMIN_EMAIL; ?><br>
                        <i class="bi bi-phone"></i> +244 948 005 566
                    </p>

                    <hr class="my-3">

                    <div style="font-size: 0.813rem; color: var(--gray-medium);">
                        <strong>Segurança:</strong> Todos os pagamentos são processados através de conexão segura SSL/TLS.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    background: var(--primary-color);
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