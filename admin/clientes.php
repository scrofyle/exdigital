<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Gestão de Clientes (Admin)
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

// Construir query
$query = "
    SELECT c.*,
           (SELECT COUNT(*) FROM eventos WHERE cliente_id = c.id) as total_eventos,
           (SELECT COUNT(*) FROM eventos WHERE cliente_id = c.id AND status = 'ativo') as eventos_ativos,
           (SELECT COUNT(*) FROM pagamentos WHERE cliente_id = c.id AND status = 'aprovado') as pagamentos_aprovados,
           (SELECT COALESCE(SUM(valor), 0) FROM pagamentos WHERE cliente_id = c.id AND status = 'aprovado') as total_gasto
    FROM clientes c
    WHERE 1=1
";

$params = [];

if ($status !== 'todos') {
    $query .= " AND c.status = ?";
    $params[] = $status;
}

if ($busca) {
    $query .= " AND (c.nome_completo LIKE ? OR c.email LIKE ? OR c.telefone LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$query .= " ORDER BY c.criado_em DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_clientes,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as clientes_ativos,
        SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) as clientes_inativos,
        SUM(CASE WHEN status = 'inadimplente' THEN 1 ELSE 0 END) as clientes_inadimplentes
    FROM clientes
");
$stats = $stmt->fetch();

// Ações
$action = get('action');
$clienteId = get('cliente_id');

if ($action && $clienteId) {
    try {
        if ($action === 'ativar') {
            $stmt = $db->prepare("UPDATE clientes SET status = 'ativo' WHERE id = ?");
            $stmt->execute([$clienteId]);
            
            createNotification('cliente', $clienteId, 'Conta Ativada', 'Sua conta foi ativada com sucesso!', 'success');
            
            Session::setFlash('success', 'Cliente ativado com sucesso!');
        } elseif ($action === 'suspender') {
            $stmt = $db->prepare("UPDATE clientes SET status = 'suspenso' WHERE id = ?");
            $stmt->execute([$clienteId]);
            
            createNotification('cliente', $clienteId, 'Conta Suspensa', 'Sua conta foi suspensa. Entre em contato com o suporte.', 'alerta');
            
            Session::setFlash('success', 'Cliente suspenso com sucesso!');
        } elseif ($action === 'deletar') {
            // Verificar se tem eventos
            $stmt = $db->prepare("SELECT COUNT(*) FROM eventos WHERE cliente_id = ?");
            $stmt->execute([$clienteId]);
            $totalEventos = $stmt->fetchColumn();
            
            if ($totalEventos > 0) {
                Session::setFlash('error', 'Não é possível deletar cliente com eventos cadastrados!');
            } else {
                $stmt = $db->prepare("DELETE FROM clientes WHERE id = ?");
                $stmt->execute([$clienteId]);
                
                Session::setFlash('success', 'Cliente deletado com sucesso!');
            }
        }
        
        logAccess('admin', $userId, 'gestao_cliente', "Ação: $action - Cliente ID: $clienteId");
        redirect('/admin/clientes.php');
        
    } catch (PDOException $e) {
        Session::setFlash('error', 'Erro ao executar ação. Tente novamente.');
        error_log("Erro na gestão de clientes: " . $e->getMessage());
    }
}

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Gestão de Clientes</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Clientes</span>
            </div>
        </div>
        <button onclick="openModal('addClienteModal')" class="btn btn-primary">
            ➕ Adicionar Cliente
        </button>
    </div>

    <?php if (Session::getFlash('success')): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✓</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('success'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (Session::getFlash('error')): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('error'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Clientes</div>
                <div class="stat-value"><?php echo number_format($stats['total_clientes']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Clientes Ativos</div>
                <div class="stat-value"><?php echo number_format($stats['clientes_ativos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Inadimplentes</div>
                <div class="stat-value"><?php echo number_format($stats['clientes_inadimplentes']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon secondary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Inativos</div>
                <div class="stat-value"><?php echo number_format($stats['clientes_inativos']); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row" style="align-items: end;">
                <div class="col-6">
                    <label class="form-label">Buscar Cliente</label>
                    <input type="text" name="busca" class="form-control" 
                           placeholder="Nome, email ou telefone..."
                           value="<?php echo Security::clean($busca); ?>">
                </div>
                
                <div class="col-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="todos" <?php echo $status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        <option value="suspenso" <?php echo $status === 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                        <option value="inadimplente" <?php echo $status === 'inadimplente' ? 'selected' : ''; ?>>Inadimplente</option>
                    </select>
                </div>

                <div class="col-3">
                    <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                    <a href="clientes.php" class="btn btn-secondary">🔄 Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Clientes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <?php 
                if ($busca || $status !== 'todos') {
                    echo count($clientes) . ' cliente(s) encontrado(s)';
                } else {
                    echo 'Todos os Clientes';
                }
                ?>
            </h3>
            <button onclick="exportTableToCSV('clientesTable', 'clientes.csv')" class="btn btn-sm btn-secondary">
                📥 Exportar CSV
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table" id="clientesTable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Contato</th>
                            <th>Eventos</th>
                            <th>Total Gasto</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 2rem;">
                                Nenhum cliente encontrado
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo Security::clean($cliente['nome_completo']); ?></strong>
                                        <?php if ($cliente['empresa']): ?>
                                            <br><small class="text-muted"><?php echo Security::clean($cliente['empresa']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo Security::clean($cliente['email']); ?></div>
                                    <?php if ($cliente['telefone']): ?>
                                        <div><small><?php echo Security::clean($cliente['telefone']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><strong><?php echo $cliente['total_eventos']; ?></strong> total</div>
                                    <div><small class="text-muted"><?php echo $cliente['eventos_ativos']; ?> ativos</small></div>
                                </td>
                                <td>
                                    <strong style="color: var(--success-color);">
                                        <?php echo formatMoney($cliente['total_gasto']); ?>
                                    </strong>
                                    <div><small class="text-muted"><?php echo $cliente['pagamentos_aprovados']; ?> pagamentos</small></div>
                                </td>
                                <td>
                                    <?php 
                                    $statusLabels = [
                                        'ativo' => '<span class="badge badge-success">Ativo</span>',
                                        'inativo' => '<span class="badge badge-secondary">Inativo</span>',
                                        'suspenso' => '<span class="badge badge-danger">Suspenso</span>',
                                        'inadimplente' => '<span class="badge badge-warning">Inadimplente</span>'
                                    ];
                                    echo $statusLabels[$cliente['status']] ?? $cliente['status'];
                                    ?>
                                </td>
                                <td>
                                    <?php echo formatDate($cliente['criado_em']); ?>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle">
                                            ⚙️
                                        </button>
                                        <div class="dropdown-menu">
                                            <a href="ver-cliente.php?id=<?php echo $cliente['id']; ?>" class="dropdown-item">
                                                <span>👁️</span> Ver Detalhes
                                            </a>
                                            <?php if ($cliente['status'] !== 'ativo'): ?>
                                            <a href="?action=ativar&cliente_id=<?php echo $cliente['id']; ?>" class="dropdown-item"
                                               onclick="return confirm('Deseja ativar este cliente?')">
                                                <span>✓</span> Ativar
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($cliente['status'] === 'ativo'): ?>
                                            <a href="?action=suspender&cliente_id=<?php echo $cliente['id']; ?>" class="dropdown-item"
                                               onclick="return confirm('Deseja suspender este cliente?')">
                                                <span>⛔</span> Suspender
                                            </a>
                                            <?php endif; ?>
                                            <div class="dropdown-divider"></div>
                                            <a href="?action=deletar&cliente_id=<?php echo $cliente['id']; ?>" class="dropdown-item" style="color: var(--danger-color);"
                                               onclick="return confirm('ATENÇÃO: Esta ação é irreversível!\n\nDeseja realmente deletar este cliente?')">
                                                <span>🗑️</span> Deletar
                                            </a>
                                        </div>
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

<!-- Modal Adicionar Cliente -->
<div class="modal-overlay" id="addClienteModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Adicionar Novo Cliente</h3>
            <button class="modal-close" onclick="closeModal('addClienteModal')">×</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                Os clientes podem se registrar diretamente no sistema através da página de registro.
            </p>
            <a href="../register.php" target="_blank" class="btn btn-primary btn-block">
                Abrir Página de Registro
            </a>
            <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--gray-medium);">
                <strong>Dica:</strong> Você pode enviar o link de registro para seus clientes.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('addClienteModal')" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>