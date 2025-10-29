<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Relatório Completo do Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$eventoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventoId) {
    Session::setFlash('error', 'Evento não especificado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar dados completos do evento
$stmt = $db->prepare("
    SELECT e.*, c.nome_completo as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone,
           p.nome as plano_nome, p.max_convites, p.max_fornecedores,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites,
           (SELECT SUM(CASE WHEN nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) 
            FROM convites WHERE evento_id = e.id) as total_convidados,
           (SELECT SUM(CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END + 
                          CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END)
            FROM convites WHERE evento_id = e.id) as total_presentes,
           (SELECT COUNT(*) FROM fornecedores_evento WHERE evento_id = e.id) as total_fornecedores,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id) as total_despesas,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id AND status_pagamento = 'pago') as despesas_pagas,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id AND status_pagamento = 'pendente') as despesas_pendentes
    FROM eventos e
    JOIN clientes c ON e.cliente_id = c.id
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar convites por tipo
$stmt = $db->prepare("
    SELECT tipo_convidado, COUNT(*) as quantidade,
           SUM(CASE WHEN nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) as total_pessoas,
           SUM(CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END + 
               CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END) as confirmados
    FROM convites
    WHERE evento_id = ?
    GROUP BY tipo_convidado
    ORDER BY quantidade DESC
");
$stmt->execute([$eventoId]);
$convitesPorTipo = $stmt->fetchAll();

// Buscar fornecedores
$stmt = $db->prepare("
    SELECT * FROM fornecedores_evento 
    WHERE evento_id = ? 
    ORDER BY categoria ASC
");
$stmt->execute([$eventoId]);
$fornecedores = $stmt->fetchAll();

// Buscar despesas por categoria
$stmt = $db->prepare("
    SELECT c.nome as categoria, c.icone, c.cor,
           COUNT(d.id) as quantidade,
           COALESCE(SUM(d.valor), 0) as total
    FROM categorias_despesas c
    LEFT JOIN despesas_evento d ON c.id = d.categoria_id AND d.evento_id = ?
    GROUP BY c.id
    HAVING quantidade > 0
    ORDER BY total DESC
");
$stmt->execute([$eventoId]);
$despesasPorCategoria = $stmt->fetchAll();

// Buscar últimos 10 convites
$stmt = $db->prepare("
    SELECT * FROM convites 
    WHERE evento_id = ? 
    ORDER BY criado_em DESC 
    LIMIT 10
");
$stmt->execute([$eventoId]);
$ultimosConvites = $stmt->fetchAll();

include '../includes/cliente_header.php';
?>

<style>
@media print {
    .sidebar, .header, .page-header, .no-print {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
    }
    .content {
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        page-break-inside: avoid;
    }
}
</style>

<div class="content">
    <!-- Page Header -->
    <div class="page-header no-print">
        <div>
            <h1 class="page-title">Relatório do Evento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="meus-eventos.php">Meus Eventos</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Relatório</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Imprimir Relatório
            </button>
            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary">
                ← Voltar
            </a>
        </div>
    </div>

    <!-- Cabeçalho do Relatório -->
    <div class="card">
        <div class="card-body">
            <div style="text-align: center; padding: 2rem 0;">
                <h1 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                    <?php echo Security::clean($evento['nome_evento']); ?>
                </h1>
                <h3 style="color: var(--gray-medium); font-weight: normal; margin-bottom: 1.5rem;">
                    Relatório Completo do Evento
                </h3>
                <div style="display: inline-block; background: var(--gray-lighter); padding: 0.75rem 1.5rem; border-radius: 50px;">
                    <strong>Código:</strong> <?php echo $evento['codigo_evento']; ?>
                </div>
            </div>

            <hr style="margin: 2rem 0;">

            <!-- Informações Gerais -->
            <div class="row">
                <div class="col-6">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Tipo de Evento</small>
                        <div><strong><?php echo getEventType($evento['tipo_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Data</small>
                        <div><strong><?php echo formatDate($evento['data_evento'], 'd/m/Y'); ?></strong></div>
                    </div>

                    <?php if ($evento['hora_inicio']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Horário</small>
                        <div><strong>Das <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?> 
                        <?php if ($evento['hora_fim']): ?>
                            às <?php echo date('H:i', strtotime($evento['hora_fim'])); ?>
                        <?php endif; ?>
                        </strong></div>
                    </div>
                    <?php endif; ?>

                    <?php if ($evento['local_nome']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Local</small>
                        <div><strong><?php echo Security::clean($evento['local_nome']); ?></strong></div>
                        <?php if ($evento['local_endereco']): ?>
                            <div><small><?php echo Security::clean($evento['local_endereco']); ?></small></div>
                        <?php endif; ?>
                        <?php if ($evento['local_cidade']): ?>
                            <div><small><?php echo Security::clean($evento['local_cidade']); ?></small></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-6">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Organizador</small>
                        <div><strong><?php echo Security::clean($evento['cliente_nome']); ?></strong></div>
                        <div><small><?php echo Security::clean($evento['cliente_email']); ?></small></div>
                        <?php if ($evento['cliente_telefone']): ?>
                            <div><small><?php echo Security::clean($evento['cliente_telefone']); ?></small></div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Plano Contratado</small>
                        <div><strong><?php echo $evento['plano_nome']; ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Status</small>
                        <div><?php echo getStatusLabel($evento['status'], 'evento'); ?></div>
                    </div>

                    <div>
                        <small style="color: var(--gray-medium);">Data de Criação</small>
                        <div><strong><?php echo formatDate($evento['criado_em']); ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas Principais -->
    <div class="stats-grid" style="margin-top: 2rem;">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Convites Criados</div>
                <div class="stat-value"><?php echo number_format($evento['total_convites'] ?? 0); ?></div>
                <div class="stat-change">de <?php echo $evento['max_convites']; ?> disponíveis</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Convidados</div>
                <div class="stat-value"><?php echo number_format($evento['total_convidados'] ?? 0); ?></div>
                <div class="stat-change">
                    <?php echo number_format($evento['total_presentes'] ?? 0); ?> confirmados
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Despesas</div>
                <div class="stat-value" style="font-size: 1.25rem;"><?php echo formatMoney($evento['total_despesas']); ?></div>
                <div class="stat-change">
                    <?php echo formatMoney($evento['despesas_pagas']); ?> pago
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Fornecedores</div>
                <div class="stat-value"><?php echo number_format($evento['total_fornecedores'] ?? 0); ?></div>
                <div class="stat-change">de <?php echo $evento['max_fornecedores']; ?> disponíveis</div>
            </div>
        </div>
    </div>

    <!-- Convites por Tipo -->
    <?php if (!empty($convitesPorTipo)): ?>
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">📊 Convites por Tipo</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Convites</th>
                            <th>Total de Pessoas</th>
                            <th>Confirmados</th>
                            <th>Taxa de Confirmação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($convitesPorTipo as $tipo): ?>
                        <tr>
                            <td>
                                <?php 
                                $tipos = [
                                    'vip' => '⭐ VIP',
                                    'normal' => '👤 Normal',
                                    'familia' => '👨‍👩‍👧 Família',
                                    'amigo' => '🤝 Amigo',
                                    'trabalho' => '💼 Trabalho'
                                ];
                                echo $tipos[$tipo['tipo_convidado']] ?? ucfirst($tipo['tipo_convidado']);
                                ?>
                            </td>
                            <td><strong><?php echo $tipo['quantidade']; ?></strong></td>
                            <td><strong><?php echo $tipo['total_pessoas']; ?></strong></td>
                            <td><strong style="color: var(--success-color);"><?php echo $tipo['confirmados']; ?></strong></td>
                            <td>
                                <?php 
                                $taxa = $tipo['total_pessoas'] > 0 ? round(($tipo['confirmados'] / $tipo['total_pessoas']) * 100) : 0;
                                $cor = $taxa >= 70 ? 'success' : ($taxa >= 40 ? 'warning' : 'danger');
                                ?>
                                <span class="badge badge-<?php echo $cor; ?>"><?php echo $taxa; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fornecedores -->
    <?php if (!empty($fornecedores)): ?>
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">👔 Fornecedores Contratados</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Responsável</th>
                            <th>Empresa</th>
                            <th>Contato</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fornecedores as $forn): ?>
                        <tr>
                            <td><strong><?php echo Security::clean($forn['categoria']); ?></strong></td>
                            <td><?php echo Security::clean($forn['nome_responsavel']); ?></td>
                            <td><?php echo Security::clean($forn['empresa']) ?: '-'; ?></td>
                            <td>
                                <?php if ($forn['telefone']): ?>
                                    <div><?php echo Security::clean($forn['telefone']); ?></div>
                                <?php endif; ?>
                                <?php if ($forn['email']): ?>
                                    <div><small><?php echo Security::clean($forn['email']); ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($forn['valor_contratado']): ?>
                                    <strong style="color: var(--success-color);">
                                        <?php echo formatMoney($forn['valor_contratado']); ?>
                                    </strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Despesas por Categoria -->
    <?php if (!empty($despesasPorCategoria)): ?>
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">💰 Despesas por Categoria</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Valor Total</th>
                            <th>% do Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($despesasPorCategoria as $cat): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 30px; height: 30px; border-radius: 50%; background: <?php echo $cat['cor']; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem;">
                                        <?php echo $cat['icone']; ?>
                                    </div>
                                    <strong><?php echo $cat['categoria']; ?></strong>
                                </div>
                            </td>
                            <td><?php echo $cat['quantidade']; ?> item(s)</td>
                            <td>
                                <strong style="color: var(--success-color);">
                                    <?php echo formatMoney($cat['total']); ?>
                                </strong>
                            </td>
                            <td>
                                <?php 
                                $percentual = $evento['total_despesas'] > 0 ? round(($cat['total'] / $evento['total_despesas']) * 100, 1) : 0;
                                ?>
                                <strong><?php echo $percentual; ?>%</strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--gray-lighter); font-weight: bold;">
                            <td colspan="2">TOTAL</td>
                            <td><strong style="color: var(--primary-color); font-size: 1.125rem;">
                                <?php echo formatMoney($evento['total_despesas']); ?>
                            </strong></td>
                            <td>100%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Últimos Convites Adicionados -->
    <?php if (!empty($ultimosConvites)): ?>
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">📝 Últimos Convites Adicionados</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Convidado(s)</th>
                            <th>Tipo</th>
                            <th>Mesa</th>
                            <th>Presença</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosConvites as $conv): ?>
                        <tr>
                            <td><strong><?php echo $conv['codigo_convite']; ?></strong></td>
                            <td>
                                <div><?php echo Security::clean($conv['nome_convidado1']); ?></div>
                                <?php if ($conv['nome_convidado2']): ?>
                                    <div><small><?php echo Security::clean($conv['nome_convidado2']); ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $tipos = [
                                    'vip' => '<span class="badge badge-warning">VIP</span>',
                                    'normal' => '<span class="badge badge-secondary">Normal</span>',
                                    'familia' => '<span class="badge badge-info">Família</span>',
                                    'amigo' => '<span class="badge badge-success">Amigo</span>',
                                    'trabalho' => '<span class="badge badge-primary">Trabalho</span>'
                                ];
                                echo $tipos[$conv['tipo_convidado']] ?? '';
                                ?>
                            </td>
                            <td><?php echo $conv['mesa_numero'] ?: '-'; ?></td>
                            <td>
                                <?php 
                                $presentes = 0;
                                if ($conv['presente_convidado1']) $presentes++;
                                if ($conv['presente_convidado2']) $presentes++;
                                $total = $conv['nome_convidado2'] ? 2 : 1;
                                
                                if ($presentes == $total) {
                                    echo '<span class="badge badge-success">✓ ' . $presentes . '/' . $total . '</span>';
                                } elseif ($presentes > 0) {
                                    echo '<span class="badge badge-warning">' . $presentes . '/' . $total . '</span>';
                                } else {
                                    echo '<span class="badge badge-secondary">' . $presentes . '/' . $total . '</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo formatDate($conv['criado_em']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rodapé do Relatório -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-body text-center">
            <p style="color: var(--gray-medium); margin-bottom: 0.5rem;">
                Relatório gerado em <?php echo formatDateTime(date('Y-m-d H:i:s'), 'd/m/Y \à\s H:i'); ?>
            </p>
            <p style="color: var(--gray-medium); font-size: 0.875rem;">
                <?php echo SITE_NAME; ?> - Sistema Profissional de Gestão de Eventos
            </p>
        </div>
    </div>
</div>

<?php include '../includes/cliente_footer.php'; ?>