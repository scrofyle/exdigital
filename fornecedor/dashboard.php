<?php
/**
 * FORNECEDOR - DASHBOARD
 * Painel principal do fornecedor
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'fornecedor') {
    redirect('/fornecedor/login.php');
}

$db = Database::getInstance()->getConnection();
$fornecedorId = Session::getUserId();
$eventoId = Session::get('evento_id');
$categoria = Session::get('fornecedor_categoria');

// Buscar informações do fornecedor
$stmt = $db->prepare("
    SELECT f.*, e.nome_evento, e.data_evento, e.local_nome, 
           e.local_endereco, e.hora_inicio, e.hora_fim, e.codigo_evento,
           c.nome_completo as cliente_nome, c.telefone as cliente_telefone
    FROM fornecedores_evento f
    JOIN eventos e ON f.evento_id = e.id
    JOIN clientes c ON e.cliente_id = c.id
    WHERE f.id = ?
");
$stmt->execute([$fornecedorId]);
$fornecedor = $stmt->fetch();

// Estatísticas da equipe
$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM equipe_fornecedor WHERE fornecedor_id = ?
");
$stmt->execute([$fornecedorId]);
$totalEquipe = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COUNT(*) as total FROM equipe_fornecedor 
    WHERE fornecedor_id = ? AND presente = 1
");
$stmt->execute([$fornecedorId]);
$equipePresenteTotal = $stmt->fetchColumn();

// Estatísticas de convites (se for segurança)
$totalConvites = 0;
$convidadosPresentes = 0;
$taxaPresenca = 0;

if ($categoria === 'Segurança' || $categoria === 'seguranca') {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM convites WHERE evento_id = ?");
    $stmt->execute([$eventoId]);
    $totalConvites = $stmt->fetchColumn();
    
    $stmt = $db->prepare("
        SELECT SUM(
            CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END +
            CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END
        ) as total
        FROM convites WHERE evento_id = ?
    ");
    $stmt->execute([$eventoId]);
    $convidadosPresentes = $stmt->fetchColumn() ?: 0;
    
    // Calcular taxa de presença
    $stmt = $db->prepare("
        SELECT SUM(
            CASE WHEN nome_convidado1 IS NOT NULL THEN 1 ELSE 0 END +
            CASE WHEN nome_convidado2 IS NOT NULL THEN 1 ELSE 0 END
        ) as total_esperado
        FROM convites WHERE evento_id = ?
    ");
    $stmt->execute([$eventoId]);
    $totalEsperado = $stmt->fetchColumn() ?: 1;
    $taxaPresenca = ($convidadosPresentes / $totalEsperado) * 100;
}

// Membros da equipe
$stmt = $db->prepare("
    SELECT * FROM equipe_fornecedor 
    WHERE fornecedor_id = ? 
    ORDER BY presente DESC, nome_completo ASC
    LIMIT 10
");
$stmt->execute([$fornecedorId]);
$equipeMembers = $stmt->fetchAll();

// Verificar se o evento é hoje
$dataEvento = new DateTime($fornecedor['data_evento']);
$hoje = new DateTime();
$isEventoHoje = $dataEvento->format('Y-m-d') === $hoje->format('Y-m-d');
$diasParaEvento = $hoje->diff($dataEvento)->days;
$eventoPassou = $dataEvento < $hoje;

include '../includes/fornecedor_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">👋 Olá, <?php echo Security::clean($fornecedor['nome_responsavel']); ?>!</h1>
        <div class="page-breadcrumb">
            <span>Dashboard</span>
        </div>
    </div>

    <!-- Status do Evento -->
    <?php if ($isEventoHoje): ?>
    <div class="alert alert-success">
        <div class="alert-icon">🎉</div>
        <div class="alert-content">
            <strong>O evento é HOJE!</strong>
            <p class="alert-message">Prepare sua equipe. O evento começa às <?php echo date('H:i', strtotime($fornecedor['hora_inicio'])); ?></p>
        </div>
    </div>
    <?php elseif (!$eventoPassou): ?>
    <div class="alert alert-info">
        <div class="alert-icon">📅</div>
        <div class="alert-content">
            <strong>Faltam <?php echo $diasParaEvento; ?> dia(s) para o evento</strong>
            <p class="alert-message">Data: <?php echo formatDate($fornecedor['data_evento']); ?> às <?php echo date('H:i', strtotime($fornecedor['hora_inicio'])); ?></p>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <strong>Evento Finalizado</strong>
            <p class="alert-message">O evento ocorreu em <?php echo formatDate($fornecedor['data_evento']); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Membros da Equipe</div>
                <div class="stat-value"><?php echo number_format($totalEquipe); ?></div>
                <div class="stat-change">Cadastrados</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Equipe Presente</div>
                <div class="stat-value"><?php echo number_format($equipePresenteTotal); ?></div>
                <div class="stat-change">de <?php echo number_format($totalEquipe); ?> total</div>
            </div>
        </div>

        <?php if ($categoria === 'Segurança' || $categoria === 'seguranca'): ?>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-ticket-perforated"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Convites</div>
                <div class="stat-value"><?php echo number_format($totalConvites); ?></div>
                <div class="stat-change">Convites emitidos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Convidados Presentes</div>
                <div class="stat-value"><?php echo number_format($convidadosPresentes); ?></div>
                <div class="stat-change"><?php echo number_format($taxaPresenca, 1); ?>% confirmados</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row mt-4">
        <!-- Informações do Evento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📅 Informações do Evento</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-calendar-event"></i> Nome do Evento
                                </label>
                                <div style="font-weight: 600; font-size: 1.125rem;">
                                    <?php echo Security::clean($fornecedor['nome_evento']); ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-clock"></i> Data e Horário
                                </label>
                                <div style="font-weight: 600;">
                                    <?php echo formatDate($fornecedor['data_evento']); ?>
                                </div>
                                <small>
                                    <?php echo date('H:i', strtotime($fornecedor['hora_inicio'])); ?> 
                                    - 
                                    <?php echo date('H:i', strtotime($fornecedor['hora_fim'])); ?>
                                </small>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-person"></i> Cliente
                                </label>
                                <div style="font-weight: 600;">
                                    <?php echo Security::clean($fornecedor['cliente_nome']); ?>
                                </div>
                                <small>
                                    <i class="bi bi-phone"></i> <?php echo Security::clean($fornecedor['cliente_telefone']); ?>
                                </small>
                            </div>
                        </div>

                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-geo-alt"></i> Local
                                </label>
                                <div style="font-weight: 600;">
                                    <?php echo Security::clean($fornecedor['local_nome'] ?: 'Não informado'); ?>
                                </div>
                                <?php if ($fornecedor['local_endereco']): ?>
                                <small><?php echo Security::clean($fornecedor['local_endereco']); ?></small>
                                <?php endif; ?>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-hash"></i> Código do Evento
                                </label>
                                <div style="font-weight: 600; font-family: monospace; font-size: 1.125rem; color: var(--primary-color);">
                                    <?php echo Security::clean($fornecedor['codigo_evento']); ?>
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                                    <i class="bi bi-briefcase"></i> Sua Função
                                </label>
                                <div>
                                    <span class="badge badge-primary" style="font-size: 0.938rem; padding: 0.5rem 1rem;">
                                        <?php echo Security::clean($fornecedor['categoria']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Minha Equipe -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">👥 Minha Equipe</h3>
                    <a href="equipe.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Gerenciar Equipe
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($equipeMembers)): ?>
                    <div class="text-center" style="padding: 2rem;">
                        <i class="bi bi-people" style="font-size: 3rem; color: var(--gray-light);"></i>
                        <p class="text-muted mt-3">Nenhum membro cadastrado ainda.</p>
                        <a href="equipe.php" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Adicionar Primeiro Membro
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Função</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipeMembers as $membro): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo Security::clean($membro['nome_completo']); ?></strong>
                                    </td>
                                    <td><?php echo Security::clean($membro['funcao'] ?: '-'); ?></td>
                                    <td><?php echo Security::clean($membro['telefone'] ?: '-'); ?></td>
                                    <td>
                                        <?php if ($membro['presente']): ?>
                                            <span class="badge badge-success">
                                                <i class="bi bi-check-circle"></i> Presente
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="bi bi-clock"></i> Aguardando
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($membro['hora_checkin']): ?>
                                            <small><?php echo formatDateTime($membro['hora_checkin']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar com Ações -->
        <div class="col-4">
            <!-- Ações Rápidas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">⚡ Ações Rápidas</h3>
                </div>
                <div class="card-body">
                    <a href="equipe.php" class="btn btn-primary btn-block mb-3" style="padding: 1rem;">
                        <i class="bi bi-people-fill" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <strong>Gerenciar Equipe</strong>
                    </a>

                    <?php if ($categoria === 'Segurança' || $categoria === 'seguranca'): ?>
                    <a href="checkin.php" class="btn btn-success btn-block mb-3" style="padding: 1rem;">
                        <i class="bi bi-qr-code-scan" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <strong>Check-in Convidados</strong>
                    </a>
                    <?php endif; ?>

                    <a href="equipe.php?action=add" class="btn btn-outline btn-block" style="padding: 1rem;">
                        <i class="bi bi-person-plus" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                        <strong>Adicionar Membro</strong>
                    </a>
                </div>
            </div>

            <!-- Contagem Regressiva -->
            <?php if (!$eventoPassou): ?>
            <div class="card mt-3">
                <div class="card-header" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        <i class="bi bi-clock-history"></i> Contagem Regressiva
                    </h3>
                </div>
                <div class="card-body text-center">
                    <?php if ($isEventoHoje): ?>
                        <div style="font-size: 3rem; font-weight: 700; color: var(--success-color);">
                            HOJE!
                        </div>
                        <p style="margin: 0; color: var(--gray-medium);">O evento é hoje</p>
                    <?php else: ?>
                        <div style="font-size: 3rem; font-weight: 700; color: var(--primary-color);">
                            <?php echo $diasParaEvento; ?>
                        </div>
                        <p style="margin: 0; color: var(--gray-medium);">
                            <?php echo $diasParaEvento == 1 ? 'dia' : 'dias'; ?> restantes
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Informações de Contato -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">📞 Suporte</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">
                        Precisa de ajuda? Entre em contato:
                    </p>
                    <div style="font-size: 0.875rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <i class="bi bi-envelope"></i> 
                            <a href="mailto:<?php echo ADMIN_EMAIL; ?>"><?php echo ADMIN_EMAIL; ?></a>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <i class="bi bi-phone"></i> 
                            <a href="tel:+244948005566">+244 948 005 566</a>
                        </div>
                        <div>
                            <i class="bi bi-whatsapp"></i> 
                            <a href="https://wa.me/244948005566" target="_blank">WhatsApp</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dicas -->
            <div class="card mt-3">
                <div class="card-header" style="background: #FEF3C7;">
                    <h3 class="card-title" style="color: #92400E; margin: 0;">
                        💡 Dicas
                    </h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">Cadastre sua equipe com antecedência</li>
                        <li style="margin-bottom: 0.5rem;">Faça check-in ao chegar no evento</li>
                        <?php if ($categoria === 'Segurança' || $categoria === 'seguranca'): ?>
                        <li style="margin-bottom: 0.5rem;">Use o leitor de QR Code para controle de entrada</li>
                        <?php endif; ?>
                        <li>Mantenha contato com o organizador</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/fornecedor_footer.php'; ?>