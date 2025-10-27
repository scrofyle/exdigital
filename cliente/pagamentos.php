<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Histórico de Pagamentos do Cliente
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Buscar pagamentos
$stmt = $db->prepare("
    SELECT p.*, pl.nome as plano_nome, e.nome_evento, e.codigo_evento
    FROM pagamentos p
    JOIN planos pl ON p.plano_id = pl.id
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE p.cliente_id = ?
    ORDER BY p.criado_em DESC
");
$stmt->execute([$clienteId]);
$pagamentos = $stmt->fetchAll();

// Estatísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_pagamentos,
        SUM(CASE WHEN status = 'aprovado' THEN valor ELSE 0 END) as total_pago,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as total_pendente,
        SUM(CASE WHEN status = 'rejeitado' THEN valor ELSE 0 END) as total_rejeitado
    FROM pagamentos
    WHERE cliente_id = ?
");
$stmt->execute([$clienteId]);
$stats = $stmt->fetch();

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Meus Pagamentos</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Pagamentos</span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Pagamentos</div>
                <div class="stat-value"><?php echo number_format($stats['total_pagamentos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Pago</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($stats['total_pago']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pendentes</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($stats['total_pendente']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Rejeitados</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($stats['total_rejeitado']); ?></div>
            </div>
        </div>
    </div>

    <!-- Lista de Pagamentos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Histórico Completo</h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pagamentos)): ?>
                <div class="text-center" style="padding: 3rem;">
                    <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--gray-light); margin-bottom: 1rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <h3 style="color: var(--gray-medium); margin-bottom: 1rem;">
                        Nenhum pagamento encontrado
                    </h3>
                    <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                        Crie um evento para começar
                    </p>
                    <a href="criar-evento.php" class="btn btn-primary btn-lg">
                        ➕ Criar Evento
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Referência</th>
                                <th>Evento</th>
                                <th>Plano</th>
                                <th>Valor</th>
                                <th>Método</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $pagamento['referencia']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($pagamento['nome_evento']): ?>
                                        <div>
                                            <?php echo Security::clean($pagamento['nome_evento']); ?>
                                        </div>
                                        <small class="text-muted"><?php echo $pagamento['codigo_evento']; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $pagamento['plano_nome']; ?></td>
                                <td>
                                    <strong style="color: var(--success-color);">
                                        <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php 
                                    $metodos = [
                                        'express' => '<span class="badge badge-danger">Express</span>',
                                        'referencia' => '<span class="badge badge-success">Referência</span>',
                                        'paypal' => '<span class="badge badge-info">PayPal</span>',
                                        'transferencia' => '<span class="badge badge-warning">Transferência</span>',
                                        'multicaixa' => '<span class="badge badge-primary">Multicaixa</span>'
                                    ];
                                    echo $metodos[$pagamento['metodo_pagamento']] ?? ucfirst($pagamento['metodo_pagamento']);
                                    ?>
                                </td>
                                <td><?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?></td>
                                <td>
                                    <div><?php echo formatDate($pagamento['criado_em'], 'd/m/Y'); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($pagamento['criado_em'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($pagamento['status'] === 'pendente' && $pagamento['metodo_pagamento'] === 'referencia'): ?>
                                        <a href="pagamento-referencia.php?id=<?php echo $pagamento['id']; ?>" 
                                           class="btn btn-sm btn-info" title="Ver Referência">
                                            👁️
                                        </a>
                                    <?php elseif ($pagamento['status'] === 'aprovado'): ?>
                                        <span class="text-muted" title="Pagamento aprovado">✓</span>
                                    <?php elseif ($pagamento['status'] === 'rejeitado'): ?>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="alert('Pagamento rejeitado. Entre em contato com o suporte.')" 
                                                title="Pagamento rejeitado">
                                            ℹ️
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informações Adicionais -->
    <?php if (!empty($pagamentos)): ?>
    <div class="row mt-3">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💡 Informações Úteis</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Pagamentos Pendentes:</strong><br>
                            <small style="color: var(--gray-medium);">
                                Aguarde até 24h para confirmação. Para transferências, envie o comprovante.
                            </small>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Pagamentos Rejeitados:</strong><br>
                            <small style="color: var(--gray-medium);">
                                Entre em contato com o suporte para mais informações.
                            </small>
                        </li>
                        <li style="padding: 0.75rem 0;">
                            <strong>Reembolsos:</strong><br>
                            <small style="color: var(--gray-medium);">
                                Solicite reembolso em até 7 dias após o pagamento.
                            </small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📞 Suporte</h3>
                </div>
                <div class="card-body">
                    <p style="margin-bottom: 1rem;">
                        Precisa de ajuda com algum pagamento?
                    </p>
                    <div style="background: var(--gray-lighter); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1rem;">
                        <strong>Email:</strong><br>
                        <a href="mailto:extensangola@gmail.com">extensangola@gmail.com</a>
                    </div>
                    <div style="background: var(--gray-lighter); padding: 1rem; border-radius: var(--border-radius-sm);">
                        <strong>WhatsApp:</strong><br>
                        <a href="https://wa.me/244948005566" target="_blank">+244 948 00 55 66</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/cliente_footer.php'; ?>