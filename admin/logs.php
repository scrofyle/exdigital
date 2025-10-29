<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Logs do Sistema (Admin)
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
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';
$dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$busca = isset($_GET['busca']) ? $_GET['busca'] : '';

// Paginação
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$porPagina = 50;
$offset = ($pagina - 1) * $porPagina;

// Construir query
$query = "SELECT * FROM logs_acesso WHERE 1=1";
$params = [];

if ($tipo !== 'todos') {
    $query .= " AND usuario_tipo = ?";
    $params[] = $tipo;
}

if ($acao) {
    $query .= " AND acao LIKE ?";
    $params[] = "%$acao%";
}

if ($dataInicio) {
    $query .= " AND DATE(criado_em) >= ?";
    $params[] = $dataInicio;
}

if ($dataFim) {
    $query .= " AND DATE(criado_em) <= ?";
    $params[] = $dataFim;
}

if ($busca) {
    $query .= " AND (descricao LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// Contar total
$stmtCount = $db->prepare(str_replace("*", "COUNT(*) as total", $query));
$stmtCount->execute($params);
$total = $stmtCount->fetchColumn();
$totalPaginas = ceil($total / $porPagina);

// Buscar logs
$query .= " ORDER BY criado_em DESC LIMIT $porPagina OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT usuario_id) as usuarios_ativos,
        COUNT(DISTINCT ip_address) as ips_unicos,
        COUNT(DISTINCT DATE(criado_em)) as dias_ativos
    FROM logs_acesso
    WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch();

// Ações mais comuns
$stmt = $db->query("
    SELECT acao, COUNT(*) as total
    FROM logs_acesso
    WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY acao
    ORDER BY total DESC
    LIMIT 10
");
$acoesComuns = $stmt->fetchAll();

// Ação: Limpar logs antigos
if (isset($_GET['action']) && $_GET['action'] === 'limpar_logs' && Session::get('nivel_acesso') === 'super_admin') {
    $dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 90;
    try {
        $stmt = $db->prepare("DELETE FROM logs_acesso WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$dias]);
        $deletados = $stmt->rowCount();
        
        logAccess('admin', $userId, 'limpar_logs', "Logs mais antigos que $dias dias foram deletados ($deletados registros)");
        
        Session::setFlash('success', "$deletados logs foram deletados com sucesso!");
    } catch (PDOException $e) {
        Session::setFlash('error', 'Erro ao limpar logs');
        error_log("Erro ao limpar logs: " . $e->getMessage());
    }
    redirect('/admin/logs.php');
}

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">🕐 Logs do Sistema</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Logs</span>
            </div>
        </div>
        <?php if (Session::get('nivel_acesso') === 'super_admin'): ?>
        <div class="dropdown">
            <button class="btn btn-danger dropdown-toggle">
                🗑️ Limpar Logs
            </button>
            <div class="dropdown-menu" style="right: 0; left: auto;">
                <a href="?action=limpar_logs&dias=30" class="dropdown-item" onclick="return confirm('Deletar logs com mais de 30 dias?')">
                    Mais de 30 dias
                </a>
                <a href="?action=limpar_logs&dias=90" class="dropdown-item" onclick="return confirm('Deletar logs com mais de 90 dias?')">
                    Mais de 90 dias
                </a>
                <a href="?action=limpar_logs&dias=180" class="dropdown-item" onclick="return confirm('Deletar logs com mais de 180 dias?')">
                    Mais de 180 dias
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (Session::getFlash('success')): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('success'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Logs (30 dias)</div>
                <div class="stat-value"><?php echo number_format($stats['total_logs']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Usuários Ativos</div>
                <div class="stat-value"><?php echo number_format($stats['usuarios_ativos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">IPs Únicos</div>
                <div class="stat-value"><?php echo number_format($stats['ips_unicos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Dias com Atividade</div>
                <div class="stat-value"><?php echo number_format($stats['dias_ativos']); ?></div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 2rem;">
        <div class="col-9">
            <!-- Filtros -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row" style="align-items: end;">
                        <div class="col-2">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-control">
                                <option value="todos" <?php echo $tipo === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                <option value="admin" <?php echo $tipo === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="cliente" <?php echo $tipo === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                <option value="fornecedor" <?php echo $tipo === 'fornecedor' ? 'selected' : ''; ?>>Fornecedor</option>
                            </select>
                        </div>

                        <div class="col-3">
                            <label class="form-label">Ação</label>
                            <input type="text" name="acao" class="form-control" placeholder="Ex: login, criar_evento" value="<?php echo Security::clean($acao); ?>">
                        </div>

                        <div class="col-2">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?php echo $dataInicio; ?>">
                        </div>

                        <div class="col-2">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?php echo $dataFim; ?>">
                        </div>

                        <div class="col-3">
                            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                            <a href="logs.php" class="btn btn-secondary">🔄 Limpar</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Logs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Exibindo <?php echo number_format(count($logs)); ?> de <?php echo number_format($total); ?> registros
                    </h3>
                    <span style="color: var(--gray-medium); font-size: 0.875rem;">
                        Página <?php echo $pagina; ?> de <?php echo $totalPaginas; ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Tipo</th>
                                    <th>Usuário</th>
                                    <th>Ação</th>
                                    <th>Descrição</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        Nenhum log encontrado com os filtros aplicados
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;">
                                            <div><?php echo formatDate($log['criado_em'], 'd/m/Y'); ?></div>
                                            <small style="color: var(--gray-medium);"><?php echo date('H:i:s', strtotime($log['criado_em'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $badges = [
                                                'admin' => '<span class="badge badge-danger">Admin</span>',
                                                'cliente' => '<span class="badge badge-primary">Cliente</span>',
                                                'fornecedor' => '<span class="badge badge-info">Fornecedor</span>'
                                            ];
                                            echo $badges[$log['usuario_tipo']] ?? $log['usuario_tipo'];
                                            ?>
                                        </td>
                                        <td>
                                            <strong>ID: <?php echo $log['usuario_id']; ?></strong>
                                        </td>
                                        <td>
                                            <code style="font-size: 0.813rem; background: var(--gray-lighter); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                                <?php echo Security::clean($log['acao']); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <small><?php echo Security::clean($log['descricao']) ?: '-'; ?></small>
                                        </td>
                                        <td>
                                            <small style="font-family: monospace;"><?php echo Security::clean($log['ip_address']); ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($totalPaginas > 1): ?>
                    <div style="padding: 1rem; border-top: 1px solid var(--gray-lighter);">
                        <div class="pagination">
                            <?php if ($pagina > 1): ?>
                                <a href="?pagina=<?php echo $pagina - 1; ?>&tipo=<?php echo $tipo; ?>&acao=<?php echo $acao; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                                   class="pagination-item">← Anterior</a>
                            <?php endif; ?>

                            <?php 
                            $inicio = max(1, $pagina - 2);
                            $fim = min($totalPaginas, $pagina + 2);
                            
                            for ($i = $inicio; $i <= $fim; $i++): 
                            ?>
                                <a href="?pagina=<?php echo $i; ?>&tipo=<?php echo $tipo; ?>&acao=<?php echo $acao; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                                   class="pagination-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($pagina < $totalPaginas): ?>
                                <a href="?pagina=<?php echo $pagina + 1; ?>&tipo=<?php echo $tipo; ?>&acao=<?php echo $acao; ?>&data_inicio=<?php echo $dataInicio; ?>&data_fim=<?php echo $dataFim; ?>" 
                                   class="pagination-item">Próximo →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-3">
            <!-- Ações Mais Comuns -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔥 Ações Mais Comuns (7 dias)</h3>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($acoesComuns as $index => $acao): ?>
                    <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--gray-lighter); display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                                <?php echo $index + 1; ?>
                            </div>
                            <code style="font-size: 0.75rem;"><?php echo Security::clean($acao['acao']); ?></code>
                        </div>
                        <strong style="color: var(--primary-color);"><?php echo number_format($acao['total']); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Info -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">ℹ️ Informações</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0; font-size: 0.875rem;">
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Retenção:</strong> Logs são mantidos indefinidamente
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Privacidade:</strong> IPs são registrados para segurança
                        </li>
                        <li style="padding: 0.75rem 0;">
                            <strong>Limpeza:</strong> Super admins podem limpar logs antigos
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>