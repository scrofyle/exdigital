<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Gestão de Eventos (Admin)
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
$busca = get('busca', '');
$data = get('data', '');

// Construir query
$query = "
    SELECT e.*, c.nome_completo as cliente_nome, c.email as cliente_email,
           p.nome as plano_nome,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites,
           (SELECT SUM(CASE WHEN nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) 
            FROM convites WHERE evento_id = e.id) as total_convidados
    FROM eventos e
    JOIN clientes c ON e.cliente_id = c.id
    JOIN planos p ON e.plano_id = p.id
    WHERE 1=1
";

$params = [];

if ($status !== 'todos') {
    $query .= " AND e.status = ?";
    $params[] = $status;
}

if ($busca) {
    $query .= " AND (e.nome_evento LIKE ? OR e.codigo_evento LIKE ? OR c.nome_completo LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

if ($data) {
    $query .= " AND DATE(e.data_evento) = ?";
    $params[] = $data;
}

$query .= " ORDER BY e.data_evento DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_eventos,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as eventos_ativos,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as eventos_concluidos,
        SUM(CASE WHEN pago = 0 THEN 1 ELSE 0 END) as eventos_pendentes,
        SUM(CASE WHEN DATE(data_evento) = CURDATE() THEN 1 ELSE 0 END) as eventos_hoje
    FROM eventos
");
$stats = $stmt->fetch();

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Gestão de Eventos</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Eventos</span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Eventos</div>
                <div class="stat-value"><?php echo number_format($stats['total_eventos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eventos Ativos</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_ativos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eventos Hoje</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_hoje']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Concluídos</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_concluidos']); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row" style="align-items: end;">
                <div class="col-4">
                    <label class="form-label">Buscar Evento</label>
                    <input type="text" name="busca" class="form-control" 
                           placeholder="Nome, código ou cliente..."
                           value="<?php echo Security::clean($busca); ?>">
                </div>
                
                <div class="col-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="todos" <?php echo $status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="rascunho" <?php echo $status === 'rascunho' ? 'selected' : ''; ?>>Rascunho</option>
                        <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="em_andamento" <?php echo $status === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                        <option value="concluido" <?php echo $status === 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                        <option value="cancelado" <?php echo $status === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>

                <div class="col-2">
                    <label class="form-label">Data</label>
                    <input type="date" name="data" class="form-control" value="<?php echo Security::clean($data); ?>">
                </div>

                <div class="col-3">
                    <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                    <a href="eventos.php" class="btn btn-secondary">🔄 Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Eventos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                if ($busca || $status !== 'todos' || $data) {
                    echo count($eventos) . ' evento(s) encontrado(s)';
                } else {
                    echo 'Todos os Eventos';
                }
                ?>
            </h3>
            <button onclick="exportTableToCSV('eventosTable', 'eventos.csv')" class="btn btn-sm btn-secondary">
                📥 Exportar CSV
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="eventosTable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Evento</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Plano</th>
                            <th>Convites</th>
                            <th>Pagamento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($eventos)): ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 2rem;">
                                Nenhum evento encontrado
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($eventos as $evento): ?>
                            <tr>
                                <td><strong><?php echo $evento['codigo_evento']; ?></strong></td>
                                <td>
                                    <div>
                                        <strong><?php echo Security::clean($evento['nome_evento']); ?></strong>
                                        <br><small class="text-muted"><?php echo getEventType($evento['tipo_evento']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo Security::clean($evento['cliente_nome']); ?></div>
                                    <small class="text-muted"><?php echo Security::clean($evento['cliente_email']); ?></small>
                                </td>
                                <td>
                                    <div><?php echo formatDate($evento['data_evento']); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></small>
                                </td>
                                <td><span class="badge badge-info"><?php echo $evento['plano_nome']; ?></span></td>
                                <td>
                                    <strong><?php echo $evento['total_convites'] ?? 0; ?></strong> convites
                                    <br><small class="text-muted"><?php echo $evento['total_convidados'] ?? 0; ?> convidados</small>
                                </td>
                                <td>
                                    <?php if ($evento['pago']): ?>
                                        <span class="badge badge-success">✓ Pago</span>
                                        <?php if ($evento['data_pagamento']): ?>
                                            <br><small class="text-muted"><?php echo formatDate($evento['data_pagamento']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo getStatusLabel($evento['status'], 'evento'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <a href="ver-evento.php?id=<?php echo $evento['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Ver Detalhes">
                                            👁️
                                        </a>
                                        <a href="editar-evento.php?id=<?php echo $evento['id']; ?>" 
                                           class="btn btn-sm btn-secondary" title="Editar">
                                            ✏️
                                        </a>
                                    </div>
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