<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Meus Eventos - Lista Completa
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Filtros
$status = get('status', 'todos');
$busca = get('busca', '');

// Construir query
$query = "
    SELECT e.*, p.nome as plano_nome,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites,
           (SELECT SUM(CASE WHEN nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) 
            FROM convites WHERE evento_id = e.id) as total_convidados,
           (SELECT SUM(CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END + 
                          CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END)
            FROM convites WHERE evento_id = e.id) as total_presentes
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.cliente_id = ?
";

$params = [$clienteId];

if ($status !== 'todos') {
    $query .= " AND e.status = ?";
    $params[] = $status;
}

if ($busca) {
    $query .= " AND (e.nome_evento LIKE ? OR e.codigo_evento LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$query .= " ORDER BY e.data_evento DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$eventos = $stmt->fetchAll();

// Estatísticas gerais
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_eventos,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as eventos_ativos,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as eventos_concluidos,
        SUM(CASE WHEN pago = 0 THEN 1 ELSE 0 END) as eventos_pendentes
    FROM eventos
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
            <h1 class="page-title">Meus Eventos</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Meus Eventos</span>
            </div>
        </div>
        <a href="criar-evento.php" class="btn btn-primary">
            <i class="bi bi-plus-square"></i> Criar Novo Evento
        </a>
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
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eventos Concluídos</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_concluidos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pagamentos Pendentes</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_pendentes']); ?></div>
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
                           placeholder="Nome ou código do evento..."
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

                <div class="col-5">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Buscar</button>
                    <a href="meus-eventos.php" class="btn btn-secondary"><i class="bi bi-x-square"></i> Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Eventos -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                if ($busca || $status !== 'todos') {
                    echo count($eventos) . ' evento(s) encontrado(s)';
                } else {
                    echo 'Todos os Eventos';
                }
                ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <?php if (empty($eventos)): ?>
                <div class="text-center" style="padding: 3rem;">
                    <svg width="80" height="80" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--gray-light); margin-bottom: 1rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 style="color: var(--gray-medium); margin-bottom: 1rem;">
                        <?php echo $busca ? 'Nenhum evento encontrado' : 'Você ainda não tem eventos'; ?>
                    </h3>
                    <?php if (!$busca): ?>
                        <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                            Crie seu primeiro evento e comece a gerenciar seus convidados
                        </p>
                        <a href="criar-evento.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-plus-square"></i> Criar Primeiro Evento
                        </a>
                    <?php else: ?>
                        <a href="meus-eventos.php" class="btn btn-secondary">
                            Ver Todos os Eventos
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding: 1.5rem;">
                    <?php foreach ($eventos as $evento): ?>
                    <div class="evento-card" style="border: 1px solid var(--gray-light); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 1rem; transition: var(--transition-fast);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <h4 style="margin-bottom: 0.5rem; color: var(--primary-color);">
                                    <a href="evento-detalhes.php?id=<?php echo $evento['id']; ?>" style="color: inherit;">
                                        <?php echo Security::clean($evento['nome_evento']); ?>
                                    </a>
                                </h4>
                                <div style="display: flex; gap: 1.5rem; font-size: 0.875rem; color: var(--gray-medium); margin-bottom: 0.75rem;">
                                    <span>📅 <?php echo formatDate($evento['data_evento'], 'd/m/Y'); ?></span>
                                    <span>🕐 <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></span>
                                    <span>📦 <?php echo $evento['plano_nome']; ?></span>
                                    <span>🔢 <?php echo $evento['codigo_evento']; ?></span>
                                </div>
                                <?php if ($evento['local_nome']): ?>
                                    <div style="font-size: 0.875rem; color: var(--gray-dark);">
                                        📍 <?php echo Security::clean($evento['local_nome']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right;">
                                <?php echo getStatusLabel($evento['status'], 'evento'); ?>
                                <?php if (!$evento['pago']): ?>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="badge badge-warning">💳 Pendente</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid var(--gray-lighter);">
                            <div style="display: flex; gap: 2rem; font-size: 0.875rem;">
                                <div>
                                    <strong style="display: block; font-size: 1.25rem; color: var(--primary-color);">
                                        <?php echo $evento['total_convites'] ?? 0; ?>
                                    </strong>
                                    <span style="color: var(--gray-medium);">Convites</span>
                                </div>
                                <div>
                                    <strong style="display: block; font-size: 1.25rem; color: var(--success-color);">
                                        <?php echo $evento['total_convidados'] ?? 0; ?>
                                    </strong>
                                    <span style="color: var(--gray-medium);">Convidados</span>
                                </div>
                                <div>
                                    <strong style="display: block; font-size: 1.25rem; color: var(--info-color);">
                                        <?php echo $evento['total_presentes'] ?? 0; ?>
                                    </strong>
                                    <span style="color: var(--gray-medium);">Presentes</span>
                                </div>
                            </div>

                            <div style="display: flex; gap: 0.5rem;">
                                <?php if (!$evento['pago']): ?>
                                    <a href="processar-pagamento.php?evento=<?php echo $evento['id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Pagar">
                                        <i class="bi bi-cash-stack"></i> Pagar
                                    </a>
                                <?php endif; ?>
                                <a href="evento-detalhes.php?id=<?php echo $evento['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Ver Detalhes">
                                    <i class="bi bi-eye"></i> Ver
                                </a>
                                <a href="editar-evento.php?id=<?php echo $evento['id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Ver Detalhes">
                                    <i class="bi bi-brush"></i> Editar
                                </a>
                                <a href="relatorio-evento.php?id=<?php echo $evento['id']; ?>" 
                                   class="btn btn-sm btn-info" title="Relatório">
                                    <i class="bi bi-clipboard-data"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.evento-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}
</style>

<?php include '../includes/cliente_footer.php'; ?>