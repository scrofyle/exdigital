<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Detalhes e Gestão do Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$eventoId = get('id');

if (!$eventoId) {
    Session::setFlash('error', 'Evento não especificado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar evento completo
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome, p.max_convites, p.max_fornecedores,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites,
           (SELECT SUM(CASE WHEN nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) 
            FROM convites WHERE evento_id = e.id) as total_convidados,
           (SELECT SUM(CASE WHEN presente_convidado1 = 1 THEN 1 ELSE 0 END + 
                          CASE WHEN presente_convidado2 = 1 THEN 1 ELSE 0 END)
            FROM convites WHERE evento_id = e.id) as total_presentes,
           (SELECT COUNT(*) FROM fornecedores_evento WHERE evento_id = e.id) as total_fornecedores,
           (SELECT COALESCE(SUM(valor), 0) FROM despesas_evento WHERE evento_id = e.id) as total_despesas
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar convites
$searchTerm = get('busca', '');
$stmt = $db->prepare("
    SELECT * FROM convites 
    WHERE evento_id = ?
    " . ($searchTerm ? " AND (nome_convidado1 LIKE ? OR nome_convidado2 LIKE ? OR codigo_convite LIKE ?)" : "") . "
    ORDER BY criado_em DESC
");

if ($searchTerm) {
    $searchParam = "%$searchTerm%";
    $stmt->execute([$eventoId, $searchParam, $searchParam, $searchParam]);
} else {
    $stmt->execute([$eventoId]);
}
$convites = $stmt->fetchAll();

// Buscar fornecedores
$stmt = $db->prepare("SELECT * FROM fornecedores_evento WHERE evento_id = ? ORDER BY criado_em DESC");
$stmt->execute([$eventoId]);
$fornecedores = $stmt->fetchAll();

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title"><?php echo Security::clean($evento['nome_evento']); ?></h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="meus-eventos.php">Meus Eventos</a>
                <span class="breadcrumb-separator">/</span>
                <span>Detalhes</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php if (!$evento['pago']): ?>
                <a href="processar-pagamento.php?evento=<?php echo $evento['id']; ?>" class="btn btn-warning">
                    💳 Pagar Agora
                </a>
            <?php endif; ?>
            <a href="relatorio-evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-secondary">
                📊 Relatório
            </a>
        </div>
    </div>

    <?php if (!$evento['pago']): ?>
    <div class="alert alert-warning">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <div class="alert-title">Pagamento Pendente</div>
            <p class="alert-message">
                Este evento está aguardando pagamento. 
                <a href="processar-pagamento.php?evento=<?php echo $evento['id']; ?>" style="color: inherit; font-weight: 600; text-decoration: underline;">Efetue o pagamento</a> 
                para desbloquear todas as funcionalidades.
            </p>
        </div>
    </div>
    <?php endif; ?>

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
                <div class="stat-change">
                    <a href="despesas.php?evento=<?php echo $evento['id']; ?>" style="color: var(--primary-color);">Ver detalhes</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab-item active" data-target="convites-tab">Convites</div>
        <div class="tab-item" data-target="fornecedores-tab">Fornecedores</div>
        <div class="tab-item" data-target="despesas-tab">Despesas</div>
        <div class="tab-item" data-target="info-tab">Informações</div>
    </div>

    <!-- Tab: Convites -->
    <div class="tab-content active" id="convites-tab">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Lista de Convites</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="exportTableToCSV('convitesTable', 'convites_<?php echo $evento['codigo_evento']; ?>.csv')" class="btn btn-sm btn-secondary">
                        <i class="bi bi-download"></i> Exportar CSV
                    </button>
                    <?php if ($evento['pago']): ?>
                        <a href="adicionar-convite.php?evento=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-square"></i> Adicionar Convite
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Busca -->
                <div style="margin-bottom: 1.5rem;">
                    <form method="GET" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="id" value="<?php echo $eventoId; ?>">
                        <input type="text" name="busca" class="form-control" placeholder="Buscar por nome ou código..." 
                               value="<?php echo Security::clean($searchTerm); ?>" style="max-width: 400px;">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        <?php if ($searchTerm): ?>
                            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary">Limpar</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table" id="convitesTable">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Convidado(s)</th>
                                <th>Contato</th>
                                <th>Tipo</th>
                                <th>Presença</th>
                                <th>QR Code</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($convites)): ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div style="padding: 2rem;">
                                        <?php if (!$evento['pago']): ?>
                                            <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                                                Efetue o pagamento para começar a adicionar convites
                                            </p>
                                            <a href="processar-pagamento.php?evento=<?php echo $evento['id']; ?>" class="btn btn-warning">
                                                💳 Pagar Agora
                                            </a>
                                        <?php else: ?>
                                            <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                                                Nenhum convite adicionado ainda
                                            </p>
                                            <a href="adicionar-convite.php?evento=<?php echo $evento['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-plus-square"></i> Adicionar Primeiro Convite
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($convites as $convite): ?>
                                <tr>
                                    <td><strong><?php echo $convite['codigo_convite']; ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?php echo Security::clean($convite['nome_convidado1']); ?></strong>
                                            <?php if ($convite['presente_convidado1']): ?>
                                                <span class="badge badge-success" style="font-size: 0.75rem;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($convite['nome_convidado2']): ?>
                                        <div style="margin-top: 0.25rem;">
                                            <strong><?php echo Security::clean($convite['nome_convidado2']); ?></strong>
                                            <?php if ($convite['presente_convidado2']): ?>
                                                <span class="badge badge-success" style="font-size: 0.75rem;">✓</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($convite['telefone1']): ?>
                                            <div><?php echo Security::clean($convite['telefone1']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($convite['email1']): ?>
                                            <div><small><?php echo Security::clean($convite['email1']); ?></small></div>
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
                                        ?>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div class="progress" style="flex: 1; max-width: 100px;">
                                                <div class="progress-bar success" style="width: <?php echo ($presentes / $total) * 100; ?>%"></div>
                                            </div>
                                            <span style="font-size: 0.875rem;"><?php echo $presentes; ?>/<?php echo $total; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <button onclick="showQRCode('<?php echo $convite['codigo_convite']; ?>')" class="btn btn-sm btn-info">
                                            QR Code
                                        </button>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <a href="editar-convite.php?id=<?php echo $convite['id']; ?>" class="btn btn-sm btn-primary" title="Editar"><i class="bi bi-brush"></i></a>
                                            <a href="deletar-convite.php?id=<?php echo $convite['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Tem certeza que deseja excluir este convite?')" 
                                               title="Deletar"><i class="bi bi-trash"></i></a>
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

    <!-- Tab: Fornecedores -->
    <div class="tab-content" id="fornecedores-tab">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Fornecedores do Evento</h3>
                <?php if ($evento['pago']): ?>
                    <a href="adicionar-fornecedor.php?evento=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-square"></i> Adicionar Fornecedor
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($fornecedores)): ?>
                    <div class="text-center" style="padding: 2rem;">
                        <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                            Nenhum fornecedor adicionado ainda
                        </p>
                        <?php if ($evento['pago']): ?>
                            <a href="adicionar-fornecedor.php?evento=<?php echo $evento['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-plus-square"></i> Adicionar Fornecedor
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($fornecedores as $fornecedor): ?>
                        <div class="col-6">
                            <div style="border: 1px solid var(--gray-light); border-radius: var(--border-radius-sm); padding: 1.5rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h5 style="margin-bottom: 0.5rem;"><?php echo Security::clean($fornecedor['nome_responsavel']); ?></h5>
                                        <span class="badge badge-primary"><?php echo ucfirst($fornecedor['categoria']); ?></span>
                                    </div>
                                    <span class="badge <?php echo $fornecedor['status'] === 'ativo' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo ucfirst($fornecedor['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if ($fornecedor['empresa']): ?>
                                    <p style="margin-bottom: 0.5rem;"><strong>Empresa:</strong> <?php echo Security::clean($fornecedor['empresa']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($fornecedor['telefone']): ?>
                                    <p style="margin-bottom: 0.5rem;"><strong>Telefone:</strong> <?php echo Security::clean($fornecedor['telefone']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($fornecedor['email']): ?>
                                    <p style="margin-bottom: 0.5rem;"><strong>Email:</strong> <?php echo Security::clean($fornecedor['email']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($fornecedor['codigo_acesso']): ?>
                                    <p style="margin-bottom: 0.5rem;">
                                        <strong>Código de Acesso:</strong> 
                                        <code style="background: var(--gray-lighter); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                            <?php echo $fornecedor['codigo_acesso']; ?>
                                        </code>
                                        <button onclick="copyToClipboard('<?php echo $fornecedor['codigo_acesso']; ?>')" 
                                                class="btn btn-sm btn-secondary" style="padding: 0.25rem 0.5rem;">
                                            📋
                                        </button>
                                    </p>
                                <?php endif; ?>
                                
                                <?php if ($fornecedor['valor_contratado']): ?>
                                    <p style="margin-top: 1rem;">
                                        <strong>Valor Contratado:</strong> 
                                        <span style="color: var(--success-color); font-size: 1.125rem; font-weight: 600;">
                                            <?php echo formatMoney($fornecedor['valor_contratado']); ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Despesas -->
    <div class="tab-content" id="despesas-tab">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Resumo de Despesas</h3>
                <a href="despesas.php?evento=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary">
                    Ver Detalhes Completos
                </a>
            </div>
            <div class="card-body">
                <div class="text-center" style="padding: 2rem;">
                    <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">
                        <?php echo formatMoney($evento['total_despesas']); ?>
                    </div>
                    <p style="color: var(--gray-medium); margin-bottom: 1.5rem;">
                        Total de despesas registradas
                    </p>
                    <?php if ($evento['pago']): ?>
                        <a href="adicionar-despesa.php?evento=<?php echo $evento['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-plus-square"></i> Adicionar Despesa
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Informações -->
    <div class="tab-content" id="info-tab">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informações do Evento</h3>
                <a href="editar-evento.php?id=<?php echo $evento['id']; ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-brush"></i>Editar
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Nome do Evento</small>
                            <div><strong><?php echo Security::clean($evento['nome_evento']); ?></strong></div>
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

                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Status</small>
                            <div><?php echo getStatusLabel($evento['status'], 'evento'); ?></div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Código do Evento</small>
                            <div>
                                <code style="background: var(--gray-lighter); padding: 0.5rem; border-radius: 4px; font-size: 1rem;">
                                    <?php echo $evento['codigo_evento']; ?>
                                </code>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
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

                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Plano</small>
                            <div><strong><?php echo $evento['plano_nome']; ?></strong></div>
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

                        <?php if ($evento['observacoes']): ?>
                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Observações</small>
                            <div><?php echo nl2br(Security::clean($evento['observacoes'])); ?></div>
                        </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 1.5rem;">
                            <small style="color: var(--gray-medium);">Criado em</small>
                            <div><?php echo formatDateTime($evento['criado_em']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal QR Code -->
<div class="modal-overlay" id="qrCodeModal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">QR Code do Convite</h3>
            <button class="modal-close" onclick="closeModal('qrCodeModal')">×</button>
        </div>
        <div class="modal-body text-center">
            <div id="qrCodeContainer" style="padding: 2rem;">
                <!-- QR Code será inserido aqui -->
            </div>
            <p style="margin-top: 1rem; color: var(--gray-medium);">
                Use este QR Code para check-in no evento
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="closeModal('qrCodeModal')" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
function showQRCode(codigo) {
    const container = document.getElementById('qrCodeContainer');
    const url = '<?php echo SITE_URL; ?>/api/verificar-convite.php?codigo=' + codigo;
    
    // Gerar QR Code usando API
    container.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}" alt="QR Code" style="max-width: 100%;">`;
    
    openModal('qrCodeModal');
}
</script>

<?php include '../includes/cliente_footer.php'; ?>