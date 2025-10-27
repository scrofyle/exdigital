<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Dashboard do Cliente
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Buscar estatísticas do cliente
$stats = [];

// Total de eventos
$stmt = $db->prepare("SELECT COUNT(*) FROM eventos WHERE cliente_id = ?");
$stmt->execute([$clienteId]);
$stats['total_eventos'] = $stmt->fetchColumn();

// Eventos ativos
$stmt = $db->prepare("SELECT COUNT(*) FROM eventos WHERE cliente_id = ? AND status = 'ativo' AND pago = 1");
$stmt->execute([$clienteId]);
$stats['eventos_ativos'] = $stmt->fetchColumn();

// Próximo evento
$stmt = $db->prepare("
    SELECT * FROM eventos 
    WHERE cliente_id = ? 
    AND status IN ('ativo', 'em_andamento') 
    AND data_evento >= NOW()
    ORDER BY data_evento ASC 
    LIMIT 1
");
$stmt->execute([$clienteId]);
$proximoEvento = $stmt->fetch();

// Total de convites
$stmt = $db->prepare("
    SELECT COUNT(*) FROM convites c
    JOIN eventos e ON c.evento_id = e.id
    WHERE e.cliente_id = ?
");
$stmt->execute([$clienteId]);
$stats['total_convites'] = $stmt->fetchColumn();

// Total de convidados
$stmt = $db->prepare("
    SELECT SUM(CASE WHEN c.nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) as total
    FROM convites c
    JOIN eventos e ON c.evento_id = e.id
    WHERE e.cliente_id = ?
");
$stmt->execute([$clienteId]);
$stats['total_convidados'] = $stmt->fetchColumn() ?? 0;

// Eventos pendentes de pagamento
$stmt = $db->prepare("
    SELECT COUNT(*) FROM eventos 
    WHERE cliente_id = ? 
    AND pago = 0 
    AND status = 'rascunho'
");
$stmt->execute([$clienteId]);
$stats['eventos_pendentes'] = $stmt->fetchColumn();

// Total gasto
$stmt = $db->prepare("
    SELECT COALESCE(SUM(valor), 0) FROM pagamentos 
    WHERE cliente_id = ? 
    AND status = 'aprovado'
");
$stmt->execute([$clienteId]);
$stats['total_gasto'] = $stmt->fetchColumn();

// Meus eventos recentes
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.cliente_id = ?
    ORDER BY e.criado_em DESC
    LIMIT 5
");
$stmt->execute([$clienteId]);
$eventosRecentes = $stmt->fetchAll();

// Eventos próximos (próximos 30 dias)
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id AND (presente_convidado1 = 1 OR presente_convidado2 = 1)) as confirmados
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.cliente_id = ?
    AND e.status IN ('ativo', 'em_andamento')
    AND e.data_evento BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY e.data_evento ASC
    LIMIT 3
");
$stmt->execute([$clienteId]);
$eventosProximos = $stmt->fetchAll();

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Bem-vindo, <?php echo Security::clean(explode(' ', Session::get('user_name'))[0]); ?>! 👋</h1>
            <div class="page-breadcrumb">
                <span>Início</span>
                <span class="breadcrumb-separator">/</span>
                <span>Dashboard</span>
            </div>
        </div>
        <a href="criar-evento.php" class="btn btn-primary">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 0.5rem;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Criar Novo Evento
        </a>
    </div>

    <?php if ($stats['eventos_pendentes'] > 0): ?>
    <div class="alert alert-warning">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <div class="alert-title">Eventos Pendentes de Pagamento</div>
            <p class="alert-message">
                Você tem <?php echo $stats['eventos_pendentes']; ?> evento(s) aguardando pagamento para serem ativados.
                <a href="pagamentos.php" style="color: inherit; font-weight: 600; text-decoration: underline;">Pagar agora</a>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
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
                <div class="stat-change"><?php echo $stats['eventos_ativos']; ?> ativos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Convites</div>
                <div class="stat-value"><?php echo number_format($stats['total_convites']); ?></div>
                <div class="stat-change"><?php echo number_format($stats['total_convidados']); ?> convidados</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Próximo Evento</div>
                <div class="stat-value" style="font-size: 1.25rem;">
                    <?php if ($proximoEvento): ?>
                        <?php echo formatDate($proximoEvento['data_evento'], 'd/m'); ?>
                    <?php else: ?>
                        --
                    <?php endif; ?>
                </div>
                <div class="stat-change">
                    <?php if ($proximoEvento): ?>
                        <?php echo truncate($proximoEvento['nome_evento'], 20); ?>
                    <?php else: ?>
                        Nenhum evento próximo
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Investido</div>
                <div class="stat-value" style="font-size: 1.25rem;"><?php echo formatMoney($stats['total_gasto']); ?></div>
                <div class="stat-change">Em eventos</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Próximos Eventos -->
        <div class="col-8">
            <?php if (!empty($eventosProximos)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Próximos Eventos</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($eventosProximos as $evento): ?>
                    <div class="event-card" style="padding: 1.5rem; border-radius: var(--border-radius-sm); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div>
                                <h4 style="color: white; margin-bottom: 0.5rem;"><?php echo Security::clean($evento['nome_evento']); ?></h4>
                                <div style="display: flex; gap: 1rem; font-size: 0.875rem; opacity: 0.9;">
                                    <span>📅 <?php echo formatDate($evento['data_evento'], 'd/m/Y'); ?></span>
                                    <span>🕐 <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></span>
                                    <?php if ($evento['local_nome']): ?>
                                        <span>📍 <?php echo Security::clean($evento['local_nome']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge badge-info"><?php echo getEventType($evento['tipo_evento']); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 2rem; font-size: 0.875rem;">
                                <div>
                                    <strong style="display: block; font-size: 1.5rem;"><?php echo $evento['total_convites']; ?></strong>
                                    <span style="opacity: 0.8;">Convites</span>
                                </div>
                                <div>
                                    <strong style="display: block; font-size: 1.5rem;"><?php echo $evento['confirmados']; ?></strong>
                                    <span style="opacity: 0.8;">Confirmados</span>
                                </div>
                                <?php if ($evento['numero_convidados_esperado']): ?>
                                <div>
                                    <strong style="display: block; font-size: 1.5rem;"><?php echo $evento['numero_convidados_esperado']; ?></strong>
                                    <span style="opacity: 0.8;">Esperados</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="evento-detalhes.php?id=<?php echo $evento['id']; ?>" class="btn btn-sm" style="background: white; color: var(--primary-color);">
                                Ver Detalhes
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Meus Eventos Recentes -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Meus Eventos</h3>
                    <a href="meus-eventos.php" class="btn btn-sm btn-outline">Ver Todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Data</th>
                                    <th>Plano</th>
                                    <th>Convites</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($eventosRecentes)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div style="padding: 2rem;">
                                            <p style="font-size: 1.125rem; color: var(--gray-medium); margin-bottom: 1rem;">
                                                Você ainda não criou nenhum evento
                                            </p>
                                            <a href="criar-evento.php" class="btn btn-primary">
                                                Criar Primeiro Evento
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($eventosRecentes as $evento): ?>
                                    <tr>
                                        <td>

                                            <div>
                                               <a href="evento-detalhes.php?id=<?php echo $evento['id']; ?>"> <strong><?php echo Security::clean($evento['nome_evento']); ?></strong></a>
                                                <br>
                                                <small class="text-muted"><?php echo getEventType($evento['tipo_evento']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo formatDate($evento['data_evento']); ?></td>
                                        <td><span class="badge badge-info"><?php echo $evento['plano_nome']; ?></span></td>
                                        <td><?php echo $evento['total_convites']; ?></td>
                                        <td><?php echo getStatusLabel($evento['status'], 'evento'); ?></td>
                                        <td>
                                            <a href="evento-detalhes.php?id=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary">Ver</a>
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

        <!-- Ações Rápidas e Ajuda -->
        <div class="col-4">
            <!-- Ações Rápidas -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Ações Rápidas</h3>
                </div>
                <div class="card-body">
                    <a href="criar-evento.php" class="btn btn-primary btn-block mb-2">
                        ➕ Criar Evento
                    </a>
                    <a href="meus-eventos.php" class="btn btn-success btn-block mb-2">
                        🎉 Ver Meus Eventos
                    </a>
                    <a href="pagamentos.php" class="btn btn-warning btn-block mb-2">
                        💳 Pagamentos
                    </a>
                    <a href="perfil.php" class="btn btn-secondary btn-block">
                        👤 Editar Perfil
                    </a>
                </div>
            </div>

            <!-- Dicas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💡 Dicas</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Crie seu evento cedo</strong>
                            <small style="color: var(--gray-medium);">Quanto antes criar, mais tempo para organizar!</small>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Use o QR Code</strong>
                            <small style="color: var(--gray-medium);">Facilita o check-in dos convidados no evento</small>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Controle suas despesas</strong>
                            <small style="color: var(--gray-medium);">Registre todos os gastos para não perder o controle</small>
                        </li>
                        <li style="padding: 0.75rem 0;">
                            <strong style="display: block; margin-bottom: 0.25rem;">Adicione fornecedores</strong>
                            <small style="color: var(--gray-medium);">DJ, decoração e equipe podem se cadastrar</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/cliente_footer.php'; ?> 