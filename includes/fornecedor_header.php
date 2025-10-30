<?php
/**
 * FORNECEDOR HEADER
 * includes/fornecedor_header.php
 */

if (!defined('SYSTEM_INIT')) {
    die('Acesso negado');
}

$db = Database::getInstance()->getConnection();
$fornecedorId = Session::getUserId();
$eventoNome = Session::get('evento_nome');
$categoria = Session::get('fornecedor_categoria');

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Fornecedor</title>
<link rel="apple-touch-icon" href="/assets/images/icon-192.png">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar" style="background: linear-gradient(180deg, #667eea 0%, #1E3A8A 100%);">
            <div class="sidebar-header">
                <div class="sidebar-logo"><?php echo SITE_NAME; ?></div>
                <div class="sidebar-subtitle">Área do Fornecedor</div>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="dashboard.php" class="sidebar-menu-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-speedometer2"></i></span>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-menu-item">
                    <a href="equipe.php" class="sidebar-menu-link <?php echo $currentPage === 'equipe' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-people-fill"></i></span>
                        <span class="sidebar-menu-text">Minha Equipe</span>
                    </a>
                </li>

                <?php if ($categoria === 'Segurança' || $categoria === 'seguranca'): ?>
                <li class="sidebar-menu-item">
                    <a href="checkin.php" class="sidebar-menu-link <?php echo $currentPage === 'checkin' ? 'active' : ''; ?>">
                        <span class="sidebar-menu-icon"><i class="bi bi-qr-code-scan"></i></span>
                        <span class="sidebar-menu-text">Check-in</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer" style="padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto;">
                <div style="background: rgba(255,255,255,0.1); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1rem;">
                    <small style="color: rgba(255,255,255,0.8); display: block; margin-bottom: 0.25rem;">Evento:</small>
                    <strong style="color: white; font-size: 0.875rem; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo Security::clean($eventoNome); ?>
                    </strong>
                </div>
                
                <a href="../logout.php" class="btn btn-danger btn-block" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3);">
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

                    <div style="margin-left: 1rem;">
                        <span style="color: var(--gray-medium); font-size: 0.875rem;">Fornecedor:</span>
                        <strong style="color: var(--dark-color); margin-left: 0.5rem;"><?php echo Security::clean($categoria); ?></strong>
                    </div>
                </div>

                <div class="header-right">
                    <!-- User Menu -->
                    <div class="dropdown header-user" id="userDropdown">
                        <div class="dropdown-toggle">
                            <img src="<?php echo asset('images/default-avatar.png'); ?>" alt="Avatar" class="header-user-avatar">
                            <div class="header-user-info">
                                <div class="header-user-name"><?php echo Security::clean(Session::get('user_name')); ?></div>
                                <div class="header-user-role">Fornecedor</div>
                            </div>
                        </div>
                        <div class="dropdown-menu">
                            <a href="dashboard.php" class="dropdown-item">
                                <span>🏠</span> Dashboard
                            </a>
                            <a href="equipe.php" class="dropdown-item">
                                <span>👥</span> Minha Equipe
                            </a>
                             <a href="alterar-senha.php" class="dropdown-item">
                                <span>👥</span> Alterar Senha
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item" style="color: var(--danger-color);">
                                <span>🚪</span> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </header>

<!-- CONTENT STARTS HERE -->