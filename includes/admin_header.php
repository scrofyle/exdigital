<?php
if (!defined('SYSTEM_INIT')) {
    die('Acesso negado');
}

// Buscar notificações não lidas
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM notificacoes 
    WHERE usuario_tipo = 'admin' 
    AND usuario_id = ? 
    AND lida = 0
");
$stmt->execute([Session::getUserId()]);
$notificacoesCount = $stmt->fetchColumn();

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Admin</title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><?php echo SITE_NAME; ?></div>
                <div class="sidebar-subtitle">Painel Administrativo</div>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="dashboard.php" class="sidebar-menu-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-speedometer2"></i></span>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu-item">
                    <a href="clientes.php" class="sidebar-menu-link <?php echo $currentPage === 'clientes' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="sidebar-menu-text">Clientes</span>
                    </a>
                </li>

                <li class="sidebar-menu-item">
                    <a href="eventos.php" class="sidebar-menu-link <?php echo $currentPage === 'eventos' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-calendar-event"></i></span>
                        <span class="sidebar-menu-text">Eventos</span>
                    </a>
                </li>

                <?php if (Session::get('nivel_acesso') === 'super_admin' || Session::get('nivel_acesso') === 'admin_financeiro'): ?>
                <li class="sidebar-menu-item">
                    <a href="pagamentos.php" class="sidebar-menu-link <?php echo $currentPage === 'pagamentos' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-cash-coin"></i></span>
                        <span class="sidebar-menu-text">Pagamentos</span>
                        <?php if ($notificacoesCount > 0): ?>
                            <span class="sidebar-menu-badge"><?php echo $notificacoesCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <li class="sidebar-menu-item">
                    <a href="planos.php" class="sidebar-menu-link <?php echo $currentPage === 'planos' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-star-fill"></i></span>
                        <span class="sidebar-menu-text">Planos</span>
                    </a>
                </li>

                <li class="sidebar-menu-item">
                    <a href="relatorios.php" class="sidebar-menu-link <?php echo $currentPage === 'relatorios' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-journal-text"></i></span>
                        <span class="sidebar-menu-text">Relatórios</span>
                    </a>
                </li>

                <?php if (Session::get('nivel_acesso') === 'super_admin'): ?>
                <li class="sidebar-menu-item">
                    <a href="administradores.php" class="sidebar-menu-link <?php echo $currentPage === 'administradores' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-person-fill-gear"></i></span>
                        <span class="sidebar-menu-text">Administradores</span>
                    </a>
                </li>
                <?php endif; ?>

                <li class="sidebar-menu-item">
                    <a href="logs.php" class="sidebar-menu-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-clock-history"></i></span>
                        <span class="sidebar-menu-text">Logs</span>
                    </a>
                </li>

                <?php if (Session::get('nivel_acesso') === 'super_admin'): ?>
                <li class="sidebar-menu-item">
                    <a href="configuracoes.php" class="sidebar-menu-link <?php echo $currentPage === 'configuracoes' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-gear"></i></span>
                        <span class="sidebar-menu-text">Configurações</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer" style="padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto;">
                <a href="../logout.php" class="btn btn-danger btn-block" style="background: #3b5dbc; color: white; border: 1px solid rgba(255,255,255,0.3);">
                    <span style="margin-right: 0.5rem;"><i class="bi bi-box-arrow-left"></i></span>
                    Sair
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>

                    <div class="header-search">
                        <svg class="header-search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" class="header-search-input" placeholder="Buscar...">
                    </div>
                </div>

                <div class="header-right">
                    <!-- Notificações -->
                    <div class="dropdown header-notification" id="notificationDropdown">
                        <div class="dropdown-toggle header-notification-icon">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <?php if ($notificacoesCount > 0): ?>
                                <span class="header-notification-badge"><?php echo $notificacoesCount; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-menu">
                            <div style="padding: 1rem; border-bottom: 1px solid var(--gray-lighter);">
                                <strong>Notificações</strong>
                            </div>
                            <?php
                            $stmt = $db->prepare("
                                SELECT * FROM notificacoes 
                                WHERE usuario_tipo = 'admin' 
                                AND usuario_id = ? 
                                ORDER BY criado_em DESC 
                                LIMIT 5
                            ");
                            $stmt->execute([Session::getUserId()]);
                            $notificacoes = $stmt->fetchAll();
                            
                            if (empty($notificacoes)):
                            ?>
                                <div class="dropdown-item">Nenhuma notificação</div>
                            <?php else: ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                <a href="<?php echo $notif['link'] ?? '#'; ?>" class="dropdown-item" style="<?php echo $notif['lida'] ? '' : 'background: rgba(108, 99, 255, 0.05);'; ?>">
                                    <strong style="display: block; margin-bottom: 0.25rem;"><?php echo Security::clean($notif['titulo']); ?></strong>
                                    <small style="color: var(--gray-medium);"><?php echo truncate($notif['mensagem'], 60); ?></small>
                                    <small style="display: block; margin-top: 0.25rem; color: var(--gray-medium);"><?php echo timeAgo($notif['criado_em']); ?></small>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="notificacoes.php" class="dropdown-item text-center" style="color: var(--primary-color); font-weight: 600;">
                                Ver todas
                            </a>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="dropdown header-user" id="userDropdown">
                        <div class="dropdown-toggle">
                            <img src="<?php echo asset('images/default-avatar.png'); ?>" alt="Avatar" class="header-user-avatar">
                            <div class="header-user-info">
                                <div class="header-user-name"><?php echo Security::clean(Session::get('user_name')); ?></div>
                                <div class="header-user-role"><?php echo ucfirst(Session::get('nivel_acesso')); ?></div>
                            </div>
                        </div>
                        <div class="dropdown-menu">
                            <a href="perfil.php" class="dropdown-item">
                                <span>👤</span> Meu Perfil
                            </a>
                            <a href="configuracoes.php" class="dropdown-item">
                                <span>⚙️</span> Configurações
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item" style="color: var(--danger-color);">
                                <span>🚪</span> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </header>