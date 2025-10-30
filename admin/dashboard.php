<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Dashboard do Administrador - Versão Completa
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$userName = Session::get('user_name');
$nivelAcesso = Session::get('nivel_acesso');

// Buscar estatísticas gerais
$stats = [];

// Total de clientes
$stmt = $db->query("SELECT COUNT(*) as total FROM clientes WHERE status = 'ativo'");
$stats['total_clientes'] = $stmt->fetchColumn();

// Total de eventos
$stmt = $db->query("SELECT COUNT(*) as total FROM eventos");
$stats['total_eventos'] = $stmt->fetchColumn();

// Eventos ativos
$stmt = $db->query("SELECT COUNT(*) as total FROM eventos WHERE status = 'ativo'");
$stats['eventos_ativos'] = $stmt->fetchColumn();

// Eventos hoje
$stmt = $db->query("SELECT COUNT(*) as total FROM eventos WHERE DATE(data_evento) = CURDATE()");
$stats['eventos_hoje'] = $stmt->fetchColumn();

// Total de convites
$stmt = $db->query("SELECT COUNT(*) as total FROM convites");
$stats['total_convites'] = $stmt->fetchColumn();

// Pagamentos pendentes
$stmt = $db->query("SELECT COUNT(*) as total FROM pagamentos WHERE status = 'pendente'");
$stats['pagamentos_pendentes'] = $stmt->fetchColumn();

// Receita total (pagamentos aprovados)
$stmt = $db->query("SELECT COALESCE(SUM(valor), 0) as total FROM pagamentos WHERE status = 'aprovado'");
$stats['receita_total'] = $stmt->fetchColumn();

// Receita do mês
$stmt = $db->query("
    SELECT COALESCE(SUM(valor), 0) as total 
    FROM pagamentos 
    WHERE status = 'aprovado' 
    AND MONTH(data_aprovacao) = MONTH(CURDATE())
    AND YEAR(data_aprovacao) = YEAR(CURDATE())
");
$stats['receita_mes'] = $stmt->fetchColumn();

// Eventos recentes
$stmt = $db->prepare("
    SELECT e.*, c.nome_completo as cliente_nome, c.email as cliente_email,
           p.nome as plano_nome
    FROM eventos e
    JOIN clientes c ON e.cliente_id = c.id
    JOIN planos p ON e.plano_id = p.id
    ORDER BY e.criado_em DESC
    LIMIT 10
");
$stmt->execute();
$eventosRecentes = $stmt->fetchAll();

// Pagamentos recentes
$stmt = $db->prepare("
    SELECT p.*, c.nome_completo as cliente_nome, pl.nome as plano_nome
    FROM pagamentos p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN planos pl ON p.plano_id = pl.id
    ORDER BY p.criado_em DESC
    LIMIT 5
");
$stmt->execute();
$pagamentosRecentes = $stmt->fetchAll();

// Gráfico de eventos por mês (últimos 6 meses)
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(data_evento, '%Y-%m') as mes,
        COUNT(*) as total
    FROM eventos
    WHERE data_evento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_evento, '%Y-%m')
    ORDER BY mes ASC
");
$graficoEventos = $stmt->fetchAll();

// Clientes mais recentes
$stmt = $db->query("
    SELECT id, nome_completo, email, telefone, criado_em, status
    FROM clientes
    ORDER BY criado_em DESC
    LIMIT 5
");
$clientesRecentes = $stmt->fetchAll();

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">📊 Dashboard</h1>
        <div class="page-breadcrumb">
            <span>Início</span>
            <span class="breadcrumb-separator">/</span>
            <span>Dashboard</span>
        </div>
    </div>

    <!-- Welcome Message -->
    <div class="card mb-4" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
        <div class="card-body">
            <h3 style="color: white; margin-bottom: 0.5rem;">👋 Bem-vindo, <?php echo Security::clean($userName); ?>!</h3>
            <p style="margin: 0; opacity: 0.9;">Aqui está o resumo das atividades do sistema hoje.</p>
        </div>
    </div>

    <!-- Stats Grid -->
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
                <div class="stat-change positive">↑ Ativos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eventos Ativos</div>
                <div class="stat-value"><?php echo number_format($stats['eventos_ativos']); ?></div>
                <div class="stat-change">de <?php echo number_format($stats['total_eventos']); ?> total</div>
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
                <div class="stat-change">Em andamento</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Convites</div>
                <div class="stat-value"><?php echo number_format($stats['total_convites']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pagamentos Pendentes</div>
                <div class="stat-value"><?php echo number_format($stats['pagamentos_pendentes']); ?></div>
                <div class="stat-change">Aguardando</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Receita Total</div>
                <div class="stat-value"><?php echo formatMoney($stats['receita_total']); ?></div>
            </div>
        </div>
    </div>

    <!-- Receita do Mês -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card" style="background: linear-gradient(135deg, #10B981, #059669); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 style="color: white; margin-bottom: 0.5rem;">💰 Receita do Mês</h5>
                            <h2 style="color: white; margin: 0;"><?php echo formatMoney($stats['receita_mes']); ?></h2>
                        </div>
                        <div style="font-size: 3rem; opacity: 0.3;">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eventos Recentes & Pagamentos Pendentes -->
    <div class="row mt-4">
        <!-- Eventos Recentes -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📅 Eventos Recentes</h3>
                    <a href="eventos.php" class="btn btn-sm btn-outline">Ver Todos</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nome do Evento</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($eventosRecentes)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Nenhum evento encontrado</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($eventosRecentes as $evento): ?>
                                    <tr>
                                        <td><strong><?php echo Security::clean($evento['codigo_evento']); ?></strong></td>
                                        <td>
                                            <strong><?php echo Security::clean($evento['nome_evento']); ?></strong><br>
                                            <small class="text-muted"><?php echo getEventType($evento['tipo_evento']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo Security::clean($evento['cliente_nome']); ?><br>
                                            <small class="text-muted"><?php echo Security::clean($evento['cliente_email']); ?></small>
                                        </td>
                                        <td><?php echo formatDate($evento['data_evento']); ?></td>
                                        <td><?php echo getStatusLabel($evento['status'], 'evento'); ?></td>
                                        <td>
                                            <a href="ver-evento.php?id=<?php echo $evento['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
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

        <!-- Pagamentos Recentes -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💳 Pagamentos Recentes</h3>
                    <a href="pagamentos.php" class="btn btn-sm btn-outline">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($pagamentosRecentes)): ?>
                        <p class="text-muted text-center">Nenhum pagamento encontrado</p>
                    <?php else: ?>
                        <?php foreach ($pagamentosRecentes as $pag): ?>
                        <div class="payment-item" style="padding: 1rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?php echo Security::clean($pag['cliente_nome']); ?></strong><br>
                                    <small class="text-muted"><?php echo Security::clean($pag['plano_nome']); ?></small>
                                </div>
                                <div>
                                    <?php echo getStatusLabel($pag['status'], 'pagamento'); ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted" style="font-size: 0.813rem;">
                                    <?php echo formatDateTime($pag['criado_em']); ?>
                                </span>
                                <strong><?php echo formatMoney($pag['valor'], $pag['moeda']); ?></strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Clientes Recentes -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">👥 Novos Clientes</h3>
                    <a href="clientes.php" class="btn btn-sm btn-outline">Ver Todos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($clientesRecentes)): ?>
                        <p class="text-muted text-center">Nenhum cliente encontrado</p>
                    <?php else: ?>
                        <?php foreach ($clientesRecentes as $cliente): ?>
                        <div class="client-item" style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?php echo asset('images/default-avatar.png'); ?>" 
                                     alt="Avatar" 
                                     style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--primary-color);">
                                <div style="flex: 1;">
                                    <strong style="display: block;"><?php echo Security::clean($cliente['nome_completo']); ?></strong>
                                    <small class="text-muted"><?php echo timeAgo($cliente['criado_em']); ?></small>
                                </div>
                                <?php echo getStatusLabel($cliente['status'], 'evento'); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Eventos -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📈 Eventos nos Últimos 6 Meses</h3>
                </div>
                <div class="card-body">
                    <canvas id="eventosChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚡ Ações Rápidas</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-3">
                            <a href="clientes.php" class="btn btn-outline btn-block" style="padding: 1.5rem;">
                                <i class="bi bi-person-plus" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                <strong>Adicionar Cliente</strong>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="eventos.php" class="btn btn-outline btn-block" style="padding: 1.5rem;">
                                <i class="bi bi-calendar-event" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                <strong>Ver Eventos</strong>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="pagamentos.php" class="btn btn-outline btn-block" style="padding: 1.5rem;">
                                <i class="bi bi-credit-card" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                <strong>Pagamentos</strong>
                            </a>
                        </div>
                        <div class="col-3">
                            <a href="relatorios.php" class="btn btn-outline btn-block" style="padding: 1.5rem;">
                                <i class="bi bi-file-earmark-text" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                                <strong>Relatórios</strong>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script para Gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('eventosChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($graficoEventos as $item) {
                        $data = DateTime::createFromFormat('Y-m', $item['mes']);
                        echo "'" . $data->format('M/Y') . "',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Eventos',
                    data: [<?php foreach ($graficoEventos as $item) echo $item['total'] . ','; ?>],
                    borderColor: 'rgb(108, 99, 255)',
                    backgroundColor: 'rgba(108, 99, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include '../includes/admin_footer.php'; ?>