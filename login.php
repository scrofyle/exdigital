<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Página de Login
 */

define('SYSTEM_INIT', true);
require_once 'config.php';

// Se já estiver logado, redirecionar
if (Session::isLoggedIn()) {
    $userType = Session::getUserType();
    if ($userType === 'admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/cliente/dashboard.php');
    }
}

$error = '';
$success = Session::getFlash('success');

if (isPost()) {
    $email = post('email');
    $password = post('password');
    $userType = post('user_type', 'cliente');
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Determinar tabela baseado no tipo de usuário
            if ($userType === 'admin') {
                $table = 'administradores';
                $query = "SELECT a.*, n.nome as nivel_nome 
                         FROM administradores a 
                         JOIN niveis_acesso n ON a.nivel_acesso_id = n.id 
                         WHERE a.email = ? AND a.status = 'ativo'";
            } else {
                $table = 'clientes';
                $query = "SELECT * FROM clientes WHERE email = ? AND status = 'ativo'";
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && Security::verifyPassword($password, $user['senha'])) {
                // Login bem-sucedido
                Session::set('user_id', $user['id']);
                Session::set('user_type', $userType);
                Session::set('user_name', $user['nome_completo']);
                Session::set('user_email', $user['email']);
                
                if ($userType === 'admin') {
                    Session::set('nivel_acesso', $user['nivel_nome']);
                    Session::set('is_admin', true);
                }
                
                // Atualizar último acesso
                $updateStmt = $db->prepare("UPDATE $table SET ultimo_acesso = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Registrar log
                logAccess($userType, $user['id'], 'login', 'Login realizado com sucesso');
                
                // Redirecionar
                if ($userType === 'admin') {
                    redirect('/admin/dashboard.php');
                } else {
                    redirect('/cliente/dashboard.php');
                }
            } else {
                $error = 'Email ou senha incorretos';
                logAccess($userType, 0, 'login_failed', 'Tentativa de login com email: ' . $email);
            }
            
        } catch (PDOException $e) {
            $error = 'Erro ao processar login. Tente novamente.';
            error_log("Erro de login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <div class="auth-logo">
                    <svg width="50" height="50" viewBox="0 0 50 50" fill="none">
                        <rect width="50" height="50" rx="12" fill="url(#gradient)"/>
                        <path d="M25 15L35 25L25 35L15 25L25 15Z" fill="white"/>
                        <defs>
                            <linearGradient id="gradient" x1="0" y1="0" x2="50" y2="50">
                                <stop offset="0%" stop-color="#385298ff"/>
                                <stop offset="100%" stop-color="#1E3A8A"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <h1 class="auth-title">Bem-vindo de volta!</h1>
                <p class="auth-subtitle">Entre na sua conta para continuar</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <p class="alert-message"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <div class="alert-icon">✓</div>
                <div class="alert-content">
                    <p class="alert-message"><?php echo $success; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="auth-tabs">
                    <button type="button" class="auth-tab active" data-type="cliente">
                        Cliente
                    </button>
                    <button type="button" class="auth-tab" data-type="admin">
                        Administrador
                    </button>
                </div>

                <input type="hidden" name="user_type" id="user_type" value="cliente">

                <div class="form-group">
                    <label class="form-label form-label-required">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Senha</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Lembrar-me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Entrar
                </button>

                <div class="auth-links">
                    <a href="recuperar-senha.php" class="auth-link">Esqueceu a senha?</a> ou ainda não tem conta?
                </div>

                <div class="auth-register">
                    <a href="register.php" class="btn btn-outline btn-block">
                        Criar Conta Gratuita
                    </a>
                </div>
            </form> 
        </div>

        <div class="auth-side">
            <div class="auth-side-content">
                <h2>Sistema Profissional de Gestão de Eventos</h2>
                <ul class="auth-features">
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>Gestão completa de convidados</span>
                    </li>
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>Controle financeiro integrado</span>
                    </li>
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>QR Code para check-in</span>
                    </li>
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>Relatórios em tempo real</span>
                    </li>
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>Múltiplos fornecedores</span>
                    </li>
                    <li>
                        <span class="feature-icon">✓</span>
                        <span>100% Responsivo</span>
                    </li>
                </ul>
                <div class="auth-testimonial">
                    <p>"O melhor sistema de gestão de eventos que já utilizei. Profissional e completo!"</p>
                    <div class="testimonial-author">
                        <strong>Maria Silva</strong>
                        <span>Fotógrafa de Eventos</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo asset('js/main.js'); ?>"></script>
    <script>
        // Alternar entre Cliente e Admin
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remover active de todos
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                // Adicionar active ao clicado
                this.classList.add('active');
                // Atualizar campo hidden
                document.getElementById('user_type').value = this.dataset.type;
            });
        });
    </script>
</body>
</html>

