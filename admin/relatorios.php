<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Relatórios e Estatísticas (Admin)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

// Período do relatório
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';
$dataInicio = date('Y-m-d', strtotime("-$periodo days"));
$dataFim = date('Y-m-d');

// Estatísticas Gerais
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_eventos,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as eventos_ativos,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) as eventos_concluidos,
        SUM(CASE WHEN pago = 1 THEN 1 ELSE 0 END) as eventos_pagos
    FROM eventos
");
$statsEventos = $stmt->fetch();

$stmt = $db->query("
    SELECT 
        COUNT(*) as total_clientes,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as clientes_ativos,
        COUNT(DISTINCT id) as clientes_unicos
    FROM clientes
");
$statsClientes = $stmt->fetch();

$stmt = $db->query("
    SELECT 
        COUNT(*) as total_pagamentos,
        SUM(CASE WHEN status = 'aprovado' THEN valor ELSE 0 END) as receita_total,
        SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END) as valor_pendente,
        AVG(CASE WHEN status = 'aprovado' THEN valor ELSE NULL END) as ticket_medio
    FROM pagamentos
");
$statsPagamentos = $stmt->fetch();

$stmt = $db->query("SELECT COUNT(*) as total FROM convites");
$statsConvites = $stmt->fetch();

// Receita por mês (últimos 6 meses)
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(data_aprovacao, '%Y-%m') as mes,
        SUM(valor) as total,
        COUNT(*) as quantidade
    FROM pagamentos
    WHERE status = 'aprovado'
    AND data_aprovacao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(data_aprovacao, '%Y-%m')
    ORDER BY mes ASC
");
$receitaPorMes = $stmt->fetchAll();

// Eventos por tipo
$stmt = $db->query("
    SELECT 
        tipo_evento,
        COUNT(*) as quantidade
    FROM eventos
    GROUP BY tipo_evento
    ORDER BY quantidade DESC
");
$eventosPorTipo = $stmt->fetchAll();

// Top 5 Clientes
$stmt = $db->query("
    SELECT 
        c.nome_completo,
        c.email,
        COUNT(e.id) as total_eventos,
        COALESCE(SUM(p.valor), 0) as total_gasto
    FROM clientes c
    LEFT JOIN eventos e ON c.id = e.cliente_id
    LEFT JOIN pagamentos p ON e.id = p.evento_id AND p.status = 'aprovado'
    GROUP BY c.id
    ORDER BY total_gasto DESC
    LIMIT 5
");
$topClientes = $stmt->fetchAll();

// Planos mais vendidos
$stmt = $db->query("
    SELECT 
        p.nome,
        COUNT(e.id) as total_vendas,
        SUM(pg.valor) as receita_total
    FROM planos p
    LEFT JOIN eventos e ON p.id = e.plano_id
    LEFT JOIN pagamentos pg ON e.id = pg.evento_id AND pg.status = 'aprovado'
    GROUP BY p.id
    ORDER BY total_vendas DESC
");
$planosMaisVendidos = $stmt->fetchAll();

// Taxa de conversão
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_eventos,
        SUM(CASE WHEN pago = 1 THEN 1 ELSE 0 END) as eventos_pagos
    FROM eventos
");
$conversao = $stmt->fetch();
$taxaConversao = $conversao['total_eventos'] > 0 ? ($conversao['eventos_pagos'] / $conversao['total_eventos']) * 100 : 0;

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">📊 Relatórios e Estatísticas</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Relatórios</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Imprimir
            </button>
            <button onclick="exportarRelatorio()" class="btn btn-primary">
                📥 Exportar CSV
            </button>
        </div>
    </div>

    <!-- Filtro de Período -->
    <div class="card mb-3">
        <div class="card-body" style="padding: 1rem;">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <strong>Período:</strong>
                <div class="btn-group" style="display: flex; gap: 0.5rem;">
                    <a href="?periodo=7" class="btn btn-sm <?php echo $periodo === '7' ? 'btn-primary' : 'btn-outline'; ?>">
                        7 dias
                    </a>
                    <a href="?periodo=30" class="btn btn-sm <?php echo $periodo === '30' ? 'btn-primary' : 'btn-outline'; ?>">
                        30 dias
                    </a>
                    <a href="?periodo=90" class="btn btn-sm <?php echo $periodo === '90' ? 'btn-primary' : 'btn-outline'; ?>">
                        90 dias
                    </a>
                    <a href="?periodo=365" class="btn btn-sm <?php echo $periodo === '365' ? 'btn-primary' : 'btn-outline'; ?>">
                        1 ano
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Principais -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Receita Total</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?php echo formatMoney($statsPagamentos['receita_total']); ?></div>
                <div class="stat-change"><?php echo $statsPagamentos['total_pagamentos']; ?> pagamentos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Eventos</div>
                <div class="stat-value"><?php echo number_format($statsEventos['total_eventos']); ?></div>
                <div class="stat-change"><?php echo $statsEventos['eventos_ativos']; ?> ativos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Clientes</div>
                <div class="stat-value"><?php echo number_format($statsClientes['total_clientes']); ?></div>
                <div class="stat-change"><?php echo $statsClientes['clientes_ativos']; ?> ativos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ticket Médio</div>
                <div class="stat-value" style="font-size: 1.25rem;"><?php echo formatMoney($statsPagamentos['ticket_medio']); ?></div>
                <div class="stat-change">Por evento</div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 2rem;">
        <!-- Receita por Mês -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📈 Receita dos Últimos 6 Meses</h3>
                </div>
                <div class="card-body">
                    <canvas id="receitaChart" style="max-height: 300px;"></canvas>
                </div>
            </div>

            <!-- Eventos por Tipo -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">🎉 Eventos por Tipo</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>Percentual</th>
                                    <th>Visualização</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalTipos = array_sum(array_column($eventosPorTipo, 'quantidade'));
                                foreach ($eventosPorTipo as $tipo): 
                                    $percentual = $totalTipos > 0 ? ($tipo['quantidade'] / $totalTipos) * 100 : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo getEventType($tipo['tipo_evento']); ?></strong></td>
                                    <td><?php echo number_format($tipo['quantidade']); ?></td>
                                    <td><?php echo number_format($percentual, 1); ?>%</td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" style="width: <?php echo $percentual; ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Planos Mais Vendidos -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⭐ Planos Mais Vendidos</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Plano</th>
                                    <th>Vendas</th>
                                    <th>Receita</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planosMaisVendidos as $plano): ?>
                                <tr>
                                    <td><strong><?php echo Security::clean($plano['nome']); ?></strong></td>
                                    <td><?php echo number_format($plano['total_vendas']); ?></td>
                                    <td>
                                        <strong style="color: var(--success-color);">
                                            <?php 
                                                if(isset($plano['receita_total']) == null){
                                                    echo formatMoney('0.00');
                                                }else{
                                            echo formatMoney($plano['receita_total']); }?>
                                        </strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar com métricas -->
        <div class="col-4">
            <!-- Taxa de Conversão -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🎯 Taxa de Conversão</h3>
                </div>
                <div class="card-body text-center">
                    <div style="font-size: 4rem; font-weight: 700; color: var(--primary-color); margin: 2rem 0;">
                        <?php echo number_format($taxaConversao, 1); ?>%
                    </div>
                    <p style="color: var(--gray-medium);">
                        <?php echo $conversao['eventos_pagos']; ?> de <?php echo $conversao['total_eventos']; ?> eventos foram pagos
                    </p>
                    <div class="progress" style="height: 20px; margin-top: 1rem;">
                        <div class="progress-bar success" style="width: <?php echo $taxaConversao; ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Valor Pendente -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⏳ Valor Pendente</h3>
                </div>
                <div class="card-body text-center">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); margin: 2rem 0;">
                        <?php echo formatMoney($statsPagamentos['valor_pendente']); ?>
                    </div>
                    <p style="color: var(--gray-medium); font-size: 0.875rem;">
                        Aguardando aprovação
                    </p>
                </div>
            </div>

            <!-- Top 5 Clientes -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">👑 Top 5 Clientes</h3>
                </div>
                <div class="card-body p-0">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($topClientes as $index => $cliente): ?>
                        <div style="padding: 1rem; border-bottom: 1px solid var(--gray-lighter);">
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                                <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div style="flex: 1;">
                                    <strong style="display: block;"><?php echo Security::clean($cliente['nome_completo']); ?></strong>
                                    <small style="color: var(--gray-medium);"><?php echo Security::clean($cliente['email']); ?></small>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                                <span><?php echo $cliente['total_eventos']; ?> eventos</span>
                                <strong style="color: var(--success-color);">
                                    <?php echo formatMoney($cliente['total_gasto']); ?>
                                </strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gráfico de Receita
const ctx = document.getElementById('receitaChart');
const receitaData = <?php echo json_encode($receitaPorMes); ?>;

const meses = receitaData.map(item => {
    const [ano, mes] = item.mes.split('-');
    const data = new Date(ano, mes - 1);
    return data.toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
});

const valores = receitaData.map(item => parseFloat(item.total));

new Chart(ctx, {
    type: 'line',
    data: {
        labels: meses,
        datasets: [{
            label: 'Receita (AOA)',
            data: valores,
            borderColor: 'rgb(59, 93, 188)',
            backgroundColor: 'rgba(59, 93, 188, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('pt-BR') + ' Kz';
                    }
                }
            }
        }
    }
});

// Função de exportar (simulação)
function exportarRelatorio() {
    alert('Funcionalidade de exportação em desenvolvimento.\n\nOs dados seriam exportados para CSV com todas as estatísticas exibidas.');
}
</script>

<style>
@media print {
    .sidebar, .header, .page-header .btn, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
    }
}
</style>

<?php include '../includes/admin_footer.php'; ?>