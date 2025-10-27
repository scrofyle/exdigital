<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Visualizar Detalhes do Evento (Admin)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();
$eventoId = get('id');

if (!$eventoId) {
    Session::setFlash('error', 'Evento não especificado');
    redirect('/admin/eventos.php');
}

// Buscar evento completo
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
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id) as total_despesas
    FROM eventos e
    JOIN clientes c ON e.cliente_id = c.id
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ?
");
$stmt->execute([$eventoId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/admin/eventos.php');
}

// Buscar convites
$stmt = $db->prepare("SELECT * FROM convites WHERE evento_id = ? ORDER BY criado_em DESC LIMIT 10");
$stmt->execute([$eventoId]);
$convites = $stmt->fetchAll();

// Buscar pagamento
$stmt = $db->prepare("SELECT * FROM pagamentos WHERE evento_id = ? ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$eventoId]);
$pagamento = $stmt->fetch();

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo Security::clean($evento['nome_evento']); ?></h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="eventos.php">Eventos</a>
                <span class="breadcrumb-separator">/</span>
                <span>Detalhes</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php echo getStatusLabel($evento['status'], 'evento'); ?>
        </div>
    </div>

    <!-- Stats do Evento -->
    <div class="stats-grid">
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
                <div class="stat-change"><?php echo $evento['numero_convidados_esperado'] ? number_format($evento['numero_convidados_esperado']) . ' esperados' : 'Ilimitado'; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Confirmados Presentes</div>
                <div class="stat-value"><?php echo number_format($evento['total_presentes'] ?? 0); ?></div>
                <div class="stat-change">
                    <?php 
                    $taxa = $evento['total_convidados'] > 0 ? round(($evento['total_presentes'] / $evento['total_convidados']) * 100) : 0;
                    echo $taxa . '% confirmação';
                    ?>
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
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Informações do Evento -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informações do Evento</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Código do Evento</small>
                                <div><strong style="font-size: 1.25rem; color: var(--primary-color);"><?php echo $evento['codigo_evento']; ?></strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Tipo</small>
                                <div><strong><?php echo getEventType($evento['tipo_evento']); ?></strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Data e Hora</small>
                                <div><strong><?php echo formatDateTime($evento['data_evento'], 'd/m/Y'); ?></strong></div>
                                <div>
                                    <?php if ($evento['hora_inicio']): ?>
                                        <small>Das <?php echo date('H:i', strtotime($evento['hora_inicio'])); ?></small>
                                    <?php endif; ?>
                                    <?php if ($evento['hora_fim']): ?>
                                        <small>às <?php echo date('H:i', strtotime($evento['hora_fim'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($evento['local_nome']): ?>
                            <div style="margin-bottom: 1.5rem;">
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
                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Plano</small>
                                <div><strong><?php echo $evento['plano_nome']; ?></strong></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Status</small>
                                <div><?php echo getStatusLabel($evento['status'], 'evento'); ?></div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Pagamento</small>
                                <div>
                                    <?php if ($evento['pago']): ?>
                                        <span class="badge badge-success">✓ Pago</span>
                                        <?php if ($evento['data_pagamento']): ?>
                                            <div><small>em <?php echo formatDate($evento['data_pagamento']); ?></small></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <small style="color: var(--gray-medium);">Criado em</small>
                                <div><?php echo formatDateTime($evento['criado_em']); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($evento['observacoes']): ?>
                    <hr style="margin: 1.5rem 0;">
                    <div>
                        <strong style="display: block; margin-bottom: 0.5rem;">Observações:</strong>
                        <p style="background: var(--gray-lighter); padding: 1rem; border-radius: var(--border-radius-sm);">
                            <?php echo nl2br(Security::clean($evento['observacoes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Últimos Convites -->
            <?php if (!empty($convites)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Últimos Convites Adicionados</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Convidado(s)</th>
                                    <th>Tipo</th>
                                    <th>Presença</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($convites as $convite): ?>
                                <tr>
                                    <td><strong><?php echo $convite['codigo_convite']; ?></strong></td>
                                    <td>
                                        <div><?php echo Security::clean($convite['nome_convidado1']); ?></div>
                                        <?php if ($convite['nome_convidado2']): ?>
                                            <div><small><?php echo Security::clean($convite['nome_convidado2']); ?></small></div>
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
                                        echo $tipos[$convite['tipo_convidado']] ?? '';
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $presentes = 0;
                                        if ($convite['presente_convidado1']) $presentes++;
                                        if ($convite['presente_convidado2']) $presentes++;
                                        $total = $convite['nome_convidado2'] ? 2 : 1;
                                        echo "$presentes/$total";
                                        ?>
                                    </td>
                                    <td><?php echo formatDate($convite['criado_em']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Cliente e Pagamento -->
        <div class="col-4">
            <!-- Informações do Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Cliente</h3>
                </div>
                <div class="card-body">
                    <h5 style="margin-bottom: 0.5rem;"><?php echo Security::clean($evento['cliente_nome']); ?></h5>
                    
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Email</small>
                        <div><?php echo Security::clean($evento['cliente_email']); ?></div>
                    </div>

                    <?php if ($evento['cliente_telefone']): ?>
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Telefone</small>
                        <div><?php echo Security::clean($evento['cliente_telefone']); ?></div>
                    </div>
                    <?php endif; ?>

                    <a href="clientes.php" class="btn btn-primary btn-block btn-sm">
                        Ver Todos os Eventos do Cliente
                    </a>
                </div>
            </div>

            <!-- Informações de Pagamento -->
            <?php if ($pagamento): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Pagamento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Referência</small>
                        <div><strong><?php echo $pagamento['referencia']; ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Valor</small>
                        <div><strong style="font-size: 1.5rem; color: var(--success-color);">
                            <?php echo formatMoney($pagamento['valor'], $pagamento['moeda']); ?>
                        </strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Método</small>
                        <div><?php echo ucfirst($pagamento['metodo_pagamento']); ?></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Status</small>
                        <div><?php echo getStatusLabel($pagamento['status'], 'pagamento'); ?></div>
                    </div>

                    <?php if ($pagamento['status'] === 'pendente' || $pagamento['status'] === 'processando'): ?>
                        <a href="aprovar-pagamento.php?id=<?php echo $pagamento['id']; ?>" class="btn btn-success btn-block">
                            ✓ Aprovar/Rejeitar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>