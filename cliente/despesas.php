<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Gestão de Despesas do Evento
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
    redirect('/cliente/meus-eventos.php');
}

// Buscar evento
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id) as total_despesas,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id AND status_pagamento = 'pago') as total_pago,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id AND status_pagamento = 'pendente') as total_pendente
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar despesas agrupadas por categoria
$stmt = $db->prepare("
    SELECT d.*, c.nome as categoria_nome, c.icone, c.cor
    FROM despesas_evento d
    JOIN categorias_despesas c ON d.categoria_id = c.id
    WHERE d.evento_id = ?
    ORDER BY d.data_vencimento ASC, d.criado_em DESC
");
$stmt->execute([$eventoId]);
$despesas = $stmt->fetchAll();

// Agrupar por categoria
$despesasPorCategoria = [];
$stmt = $db->prepare("
    SELECT c.*, 
           COALESCE(SUM(d.valor), 0) as total,
           COUNT(d.id) as quantidade
    FROM categorias_despesas c
    LEFT JOIN despesas_evento d ON c.id = d.categoria_id AND d.evento_id = ?
    GROUP BY c.id
    HAVING quantidade > 0
    ORDER BY total DESC
");
$stmt->execute([$eventoId]);
$categorias = $stmt->fetchAll();

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Despesas do Evento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Despesas</span>
            </div>
        </div>
        <a href="adicionar-despesa.php?evento=<?php echo $eventoId; ?>" class="btn btn-primary">
            <i class="bi bi-plus-square"></i> Adicionar Despesa
        </a>
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
                <div class="stat-label">Total de Despesas</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($evento['total_despesas']); ?></div>
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
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($evento['total_pago']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Pendente</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($evento['total_pendente']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Itens</div>
                <div class="stat-value"><?php echo count($despesas); ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Lista de Despesas -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Todas as Despesas</h3>
                    <button onclick="exportTableToCSV('despesasTable', 'despesas_<?php echo $evento['codigo_evento']; ?>.csv')" 
                            class="btn btn-sm btn-secondary">
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($despesas)): ?>
                        <div class="text-center" style="padding: 3rem;">
                            <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--gray-light); margin-bottom: 1rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <h3 style="color: var(--gray-medium); margin-bottom: 1rem;">
                                Nenhuma despesa registrada
                            </h3>
                            <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                                Comece a controlar suas despesas do evento
                            </p>
                            <a href="adicionar-despesa.php?evento=<?php echo $eventoId; ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-square"></i> Adicionar Primeira Despesa
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="despesasTable">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Descrição</th>
                                        <th>Fornecedor</th>
                                        <th>Valor</th>
                                        <th>Vencimento</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($despesas as $despesa): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="width: 30px; height: 30px; border-radius: 50%; background: <?php echo $despesa['cor']; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                                                    <?php echo $despesa['icone']; ?>
                                                </div>
                                                <strong><?php echo $despesa['categoria_nome']; ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo Security::clean($despesa['descricao']); ?></td>
                                        <td><?php echo Security::clean($despesa['fornecedor']) ?: '-'; ?></td>
                                        <td>
                                            <strong style="color: var(--success-color);">
                                                <?php echo formatMoney($despesa['valor']); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($despesa['data_vencimento']): ?>
                                                <?php echo formatDate($despesa['data_vencimento']); ?>
                                                <?php 
                                                $diasRestantes = ceil((strtotime($despesa['data_vencimento']) - time()) / (60 * 60 * 24));
                                                if ($diasRestantes < 0 && $despesa['status_pagamento'] !== 'pago'):
                                                ?>
                                                    <br><small style="color: var(--danger-color);">Vencido</small>
                                                <?php elseif ($diasRestantes >= 0 && $diasRestantes <= 7 && $despesa['status_pagamento'] !== 'pago'): ?>
                                                    <br><small style="color: var(--warning-color);"><?php echo $diasRestantes; ?> dias</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusLabel($despesa['status_pagamento'], 'despesa'); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 0.25rem;">
                                                <button onclick="editarDespesa(<?php echo $despesa['id']; ?>)" 
                                                        class="btn btn-sm btn-primary" title="Editar">
                                                    <i class="bi bi-brush"></i>
                                                </button>
                                                <a href="deletar-despesa.php?id=<?php echo $despesa['id']; ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Tem certeza que deseja excluir esta despesa?')" 
                                                   title="Deletar">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>