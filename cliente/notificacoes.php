<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Central de Notificações do Cliente
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Filtro
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todas';

// Ação: Marcar como lida
if (isset($_GET['action']) && $_GET['action'] === 'marcar_lida' && isset($_GET['id'])) {
    $notifId = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ? AND usuario_tipo = 'cliente'");
        $stmt->execute([$notifId, $clienteId]);
        
        // Se tem link, redirecionar
        $stmt = $db->prepare("SELECT link FROM notificacoes WHERE id = ?");
        $stmt->execute([$notifId]);
        $notif = $stmt->fetch();
        if ($notif && $notif['link']) {
            redirect($notif['link']);
        }
    } catch (PDOException $e) {
        error_log("Erro ao marcar notificação: " . $e->getMessage());
    }
    redirect('/cliente/notificacoes.php');
}

// Ação: Marcar todas como lidas
if (isset($_GET['action']) && $_GET['action'] === 'marcar_todas') {
    try {
        $stmt = $db->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND usuario_tipo = 'cliente'");
        $stmt->execute([$clienteId]);
        Session::setFlash('success', 'Todas as notificações foram marcadas como lidas!');
    } catch (PDOException $e) {
        error_log("Erro ao marcar notificações: " . $e->getMessage());
    }
    redirect('/cliente/notificacoes.php');
}

// Ação: Deletar notificação
if (isset($_GET['action']) && $_GET['action'] === 'deletar' && isset($_GET['id'])) {
    $notifId = (int)$_GET['id'];
    try {
        $stmt = $db->prepare("DELETE FROM notificacoes WHERE id = ? AND usuario_id = ? AND usuario_tipo = 'cliente'");
        $stmt->execute([$notifId, $clienteId]);
        Session::setFlash('success', 'Notificação deletada!');
    } catch (PDOException $e) {
        error_log("Erro ao deletar notificação: " . $e->getMessage());
    }
    redirect('/cliente/notificacoes.php');
}

// Buscar notificações
$query = "
    SELECT * FROM notificacoes 
    WHERE usuario_tipo = 'cliente' AND usuario_id = ?
";

$params = [$clienteId];

if ($filtro === 'nao_lidas') {
    $query .= " AND lida = 0";
} elseif ($filtro === 'lidas') {
    $query .= " AND lida = 1";
}

$query .= " ORDER BY criado_em DESC LIMIT 50";

$stmt = $db->prepare($query);
$stmt->execute($params);
$notificacoes = $stmt->fetchAll();

// Estatísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas,
        SUM(CASE WHEN lida = 1 THEN 1 ELSE 0 END) as lidas
    FROM notificacoes 
    WHERE usuario_tipo = 'cliente' AND usuario_id = ?
");
$stmt->execute([$clienteId]);
$stats = $stmt->fetch();

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">🔔 Notificações</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Notificações</span>
            </div>
        </div>
        <?php if ($stats['nao_lidas'] > 0): ?>
        <a href="?action=marcar_todas" class="btn btn-primary" onclick="return confirm('Marcar todas as notificações como lidas?')">
            ✓ Marcar Todas como Lidas
        </a>
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
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Não Lidas</div>
                <div class="stat-value"><?php echo number_format($stats['nao_lidas']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Lidas</div>
                <div class="stat-value"><?php echo number_format($stats['lidas']); ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-body" style="padding: 1rem;">
            <div style="display: flex; gap: 1rem; align-items: center;">
                <strong style="color: var(--gray-dark);">Filtrar:</strong>
                <div class="btn-group" style="display: flex; gap: 0.5rem;">
                    <a href="?filtro=todas" class="btn btn-sm <?php echo $filtro === 'todas' ? 'btn-primary' : 'btn-outline'; ?>">
                        Todas (<?php echo $stats['total']; ?>)
                    </a>
                    <a href="?filtro=nao_lidas" class="btn btn-sm <?php echo $filtro === 'nao_lidas' ? 'btn-primary' : 'btn-outline'; ?>">
                        Não Lidas (<?php echo $stats['nao_lidas']; ?>)
                    </a>
                    <a href="?filtro=lidas" class="btn btn-sm <?php echo $filtro === 'lidas' ? 'btn-primary' : 'btn-outline'; ?>">
                        Lidas (<?php echo $stats['lidas']; ?>)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Notificações -->
    <div class="card" style="margin-top: 1rem;">
        <div class="card-body p-0">
            <?php if (empty($notificacoes)): ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <svg width="100" height="100" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--gray-light); margin-bottom: 1.5rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <h3 style="color: var(--gray-medium); margin-bottom: 1rem;">
                        <?php 
                        if ($filtro === 'nao_lidas') {
                            echo 'Nenhuma notificação não lida';
                        } elseif ($filtro === 'lidas') {
                            echo 'Nenhuma notificação lida';
                        } else {
                            echo 'Nenhuma notificação ainda';
                        }
                        ?>
                    </h3>
                    <p style="color: var(--gray-medium);">
                        As notificações sobre seus eventos aparecerão aqui
                    </p>
                </div>
            <?php else: ?>
                <div class="notificacoes-lista">
                    <?php foreach ($notificacoes as $notif): ?>
                    <div class="notificacao-item <?php echo !$notif['lida'] ? 'nao-lida' : ''; ?>" 
                         style="display: flex; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid var(--gray-lighter); <?php echo !$notif['lida'] ? 'background: rgba(59, 93, 188, 0.05);' : ''; ?>">
                        
                        <!-- Ícone -->
                        <div style="flex-shrink: 0;">
                            <?php
                            $iconClass = 'primary';
                            $icon = '🔔';
                            
                            switch($notif['tipo']) {
                                case 'sucesso':
                                    $iconClass = 'success';
                                    $icon = '✅';
                                    break;
                                case 'alerta':
                                    $iconClass = 'warning';
                                    $icon = '⚠️';
                                    break;
                                case 'erro':
                                    $iconClass = 'danger';
                                    $icon = '❌';
                                    break;
                                default:
                                    $iconClass = 'info';
                                    $icon = 'ℹ️';
                            }
                            ?>
                            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--<?php echo $iconClass; ?>-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                <?php echo $icon; ?>
                            </div>
                        </div>

                        <!-- Conteúdo -->
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--dark-color);">
                                    <?php echo Security::clean($notif['titulo']); ?>
                                    <?php if (!$notif['lida']): ?>
                                        <span style="display: inline-block; width: 8px; height: 8px; background: var(--primary-color); border-radius: 50%; margin-left: 0.5rem;"></span>
                                    <?php endif; ?>
                                </h4>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php if (!$notif['lida']): ?>
                                        <a href="?action=marcar_lida&id=<?php echo $notif['id']; ?>" 
                                           class="btn btn-sm btn-success" 
                                           title="Marcar como lida"
                                           style="padding: 0.25rem 0.5rem;">
                                            ✓
                                        </a>
                                    <?php endif; ?>
                                    <a href="?action=deletar&id=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Deseja deletar esta notificação?')"
                                       title="Deletar"
                                       style="padding: 0.25rem 0.5rem;">
                                        <i class="bi bi-trash"></i> 
                                    </a>
                                </div>
                            </div>
                            
                            <p style="margin: 0 0 0.75rem 0; color: var(--gray-dark); line-height: 1.5;">
                                <?php echo nl2br(Security::clean($notif['mensagem'])); ?>
                            </p>

                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <small style="color: var(--gray-medium);">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 0.25rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <?php echo timeAgo($notif['criado_em']); ?>
                                </small>

                                <?php if ($notif['link']): ?>
                                    <a href="?action=marcar_lida&id=<?php echo $notif['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        Ver Detalhes →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginação Info -->
                <div style="padding: 1rem; text-align: center; border-top: 1px solid var(--gray-lighter); background: var(--gray-lighter);">
                    <small style="color: var(--gray-medium);">
                        Exibindo <?php echo count($notificacoes); ?> notificação(ões) mais recente(s)
                        <?php if (count($notificacoes) >= 50): ?>
                            <br>As notificações mais antigas foram ocultadas
                        <?php endif; ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dicas -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">💡 Sobre as Notificações</h3>
        </div>
        <div class="card-body">
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                    <strong style="display: block; margin-bottom: 0.25rem;">✅ Aprovações</strong>
                    <small style="color: var(--gray-medium);">Você será notificado quando seus pagamentos forem aprovados</small>
                </li>
                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                    <strong style="display: block; margin-bottom: 0.25rem;">📅 Lembretes</strong>
                    <small style="color: var(--gray-medium);">Receba lembretes sobre eventos próximos</small>
                </li>
                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                    <strong style="display: block; margin-bottom: 0.25rem;">⚠️ Alertas</strong>
                    <small style="color: var(--gray-medium);">Seja avisado sobre problemas ou pendências</small>
                </li>
                <li style="padding: 0.75rem 0;">
                    <strong style="display: block; margin-bottom: 0.25rem;">📊 Atualizações</strong>
                    <small style="color: var(--gray-medium);">Fique por dentro de mudanças no sistema</small>
                </li>
            </ul>
        </div>
    </div>
</div>

<style>
.notificacao-item {
    transition: var(--transition-fast);
}

.notificacao-item:hover {
    background: var(--gray-lighter) !important;
}

.notificacao-item.nao-lida {
    border-left: 4px solid var(--primary-color);
}

.btn-group {
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    
    .notificacao-item {
        flex-direction: column;
    }
}
</style>

<?php include '../includes/cliente_footer.php'; ?>