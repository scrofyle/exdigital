<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Gestão de Pagamentos (Admin)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

// Filtros
$status = get('status', 'todos');
$metodo = get('metodo', 'todos');
$busca = get('busca', '');

// Construir query
$query = "
    SELECT p.*, c.nome_completo as cliente_nome, c.email as cliente_email,
           pl.nome as plano_nome, e.nome_evento, e.codigo_evento
    FROM pagamentos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN planos pl ON p.plano_id = pl.id
    LEFT JOIN eventos e ON p.evento_id = e.id
    WHERE 1=1
";

$params = [];

if ($status !== 'todos') {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($metodo !== 'todos') {
    $query .= " AND p.metodo_pagamento = ?";
    $params[] = $metodo;
}

if ($busca) {
    $query .= " AND (p.referencia LIKE ? OR c.nome_completo LIKE ? OR e.nome_evento LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$query .= " ORDER BY p.criado_em DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pagamentos = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_pagamentos,
        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pagamentos_pendentes,
        SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as pagamentos_aprovados,
        SUM(CASE WHEN status = 'rejeitado' THEN 1 ELSE 0 END) as pagamentos_rejeitados,
        SUM(CASE WHEN status = 'aprovado' THEN valor ELSE 0 END) as receita_total,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as valor_pendente
    FROM pagamentos
");
$stats = $stmt->fetch();

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Gestão de Pagamentos</h1>
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
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pendentes</div>
                <div class="stat-value"><?php echo number_format($stats['pagamentos_pendentes']); ?></div>
                <div class="stat-change"><?php echo formatMoney($stats['valor_pendente']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Receita Total</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($stats['receita_total']); ?></div>
                <div class="stat-change"><?php echo $stats['pagamentos_aprovados']; ?> aprovados</div>
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
                <div class="stat-value"><?php echo number_format($stats['pagamentos_rejeitados']); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row" style="align-items: end;">
                <div class="col-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" 
                           placeholder="Referência, cliente ou evento..."
                           value="<?php echo Security::clean($busca); ?>">
                </div>
                
                <div class="col-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="todos" <?php echo $status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="pendente" <?php echo $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                        <option value="processando" <?php echo $status === 'processando' ? 'selected' : ''; ?>>Processando</option>
                        <option value="aprovado" <?php echo $status === 'aprovado' ? 'selected' : ''; ?>>Aprovado</option>
                        <option value="rejeitado" <?php echo $status === 'rejeitado' ? 'selected' : ''; ?>>Rejeitado</option>
                        <option value="cancelado" <?php echo $status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>

                <div class="col-2">
                    <label class="form-label">Método</label>
                    <select name="metodo" class="form-control">
                        <option value="todos" <?php echo $metodo === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="express" <?php echo $metodo === 'express' ? 'selected' : ''; ?>>Express</option>
                        <option value="referencia" <?php echo $metodo === 'referencia' ? 'selected' : ''; ?>>Referência</option>
                        <option value="paypal" <?php echo $metodo === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="transferencia" <?php echo $metodo === 'transferencia' ? 'selected' : ''; ?>>Transferência</option>
                    </select>
                </div>

                <div class="col-3">
                    <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                    <a href="pagamentos.php" class="btn btn-secondary">🔄 Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Pagamentos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                if ($busca || $status !== 'todos' || $metodo !== 'todos') {
                    echo count($pagamentos) . ' pagamento(s) encontrado(s)';
                } else {
                    echo 'Todos os Pagamentos';
                }
                ?>
            </h3>
            <button onclick="exportTableToCSV('pagamentosTable', 'pagamentos.csv')" class="btn btn-sm btn-secondary">
                📥 Exportar CSV
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="pagamentosTable">
                    <thead>
                        <tr>
                            <th>Referência</th>
                            <th>Cliente</th>
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
                        <?php if (empty($pagamentos)): ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 2rem;">
                                Nenhum pagamento encontrado
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($pagamentos as $pagamento): ?>
                            <tr>
                                <td><strong><?php echo $pagamento['referencia']; ?></strong></td>
                                <td>
                                    <div><?php echo Security::clean($pagamento['cliente_nome']); ?></div>
                                    <small class="text-muted"><?php echo Security::clean($pagamento['cliente_email']); ?></small>
                                </td>
                                <td>
                                    <?php if ($pagamento['nome_evento']): ?>
                                        <div><?php echo Security::clean($pagamento['nome_evento']); ?></div>
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
                                    <div><?php echo formatDate($pagamento['criado_em']); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($pagamento['criado_em'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($pagamento['status'] === 'pendente' || $pagamento['status'] === 'processando'): ?>
                                        <a href="aprovar-pagamento.php?id=<?php echo $pagamento['id']; ?>" 
                                           class="btn btn-sm btn-success" title="Aprovar/Rejeitar">
                                            ✓
                                        </a>
                                    <?php elseif ($pagamento['status'] === 'aprovado'): ?>
                                        <span class="text-muted" title="Pagamento aprovado">✓</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($pagamento['comprovante']): ?>
                                        <a href="<?php echo asset('/uploads/' . $pagamento['comprovante']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-info" title="Ver Comprovante">
                                            📄
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>