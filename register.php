<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Página de Registro de Clientes
 */

define('SYSTEM_INIT', true);
require_once 'config.php';

// Se já estiver logado, redirecionar
if (Session::isLoggedIn()) {
    redirect('/cliente/dashboard.php');
}

$errors = [];
$success = '';

if (isPost()) {
    $nome = post('nome_completo');
    $email = post('email');
    $telefone = post('telefone');
    $password = post('password');
    $confirmPassword = post('confirm_password');
    $termos = post('termos');
    
    // Validações
    if (empty($nome)) {
        $errors['nome'] = 'Nome completo é obrigatório';
    }
    
    if (empty($email) || !Security::validateEmail($email)) {
        $errors['email'] = 'Email válido é obrigatório';
    }
    
    if (empty($telefone)) {
        $errors['telefone'] = 'Telefone é obrigatório';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Senha é obrigatória';
    } elseif (!Security::isStrongPassword($password)) {
        $errors['password'] = 'Senha deve ter no mínimo 8 caracteres, com letras maiúsculas, minúsculas e números';
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'As senhas não coincidem';
    }
    
    if (!$termos) {
        $errors['termos'] = 'Você deve aceitar os termos de uso';
    }
    
    // Se não houver erros, verificar se email já existe
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar email duplicado
            $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Este email já está cadastrado';
            } else {
                // Inserir novo cliente
                $hashedPassword = Security::hashPassword($password);
                $stmt = $db->prepare("
                    INSERT INTO clientes (nome_completo, email, telefone, senha, status)
                    VALUES (?, ?, ?, ?, 'ativo')
                ");
                
                if ($stmt->execute([$nome, $email, $telefone, $hashedPassword])) {
                    $clienteId = $db->lastInsertId();
                    
                    // Registrar log
                    logAccess('cliente', $clienteId, 'registro', 'Nova conta criada');
                    
                    // Criar notificação de boas-vindas
                    createNotification(
                        'cliente',
                        $clienteId,
                        'Bem-vindo!',
                        'Sua conta foi criada com sucesso. Comece criando seu primeiro evento!',
                        'success',
                        '/cliente/criar-evento.php'
                    );
                    
                    // Enviar email de boas-vindas (opcional)
                    // sendEmail($email, 'Bem-vindo ao Sistema', 'Mensagem...');
                    
                    Session::setFlash('success', 'Conta criada com sucesso! Faça login para continuar.');
                    redirect('/login.php');
                }
            }
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao criar conta. Tente novamente.';
            error_log("Erro no registro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">
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
                <h1 class="auth-title">Criar Conta</h1>
                <p class="auth-subtitle">Comece a gerenciar seus eventos agora</p>
            </div>

            <?php if (isset($errors['geral'])): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <p class="alert-message"><?php echo $errors['geral']; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label form-label-required">Nome Completo</label>
                    <input type="text" name="nome_completo" class="form-control <?php echo isset($errors['nome']) ? 'error' : ''; ?>" 
                           placeholder="Seu nome completo" value="<?php echo post('nome_completo', ''); ?>" required>
                    <?php if (isset($errors['nome'])): ?>
                        <span class="form-error"><?php echo $errors['nome']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Email</label>
                    <input type="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                           placeholder="seu@email.com" value="<?php echo post('email', ''); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <span class="form-error"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Telefone</label>
                    <input type="tel" name="telefone" class="form-control <?php echo isset($errors['telefone']) ? 'error' : ''; ?>" 
                           placeholder="+244 900 000 000" value="<?php echo post('telefone', ''); ?>" required>
                    <?php if (isset($errors['telefone'])): ?>
                        <span class="form-error"><?php echo $errors['telefone']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Senha</label>
                    <input type="password" name="password" class="form-control <?php echo isset($errors['password']) ? 'error' : ''; ?>" 
                           placeholder="••••••••" required>
                    <?php if (isset($errors['password'])): ?>
                        <span class="form-error"><?php echo $errors['password']; ?></span>
                    <?php else: ?>
                        <span class="form-help">Mínimo 8 caracteres com letras e números</span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Confirmar Senha</label>
                    <input type="password" name="confirm_password" class="form-control <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>" 
                           placeholder="••••••••" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="form-error"><?php echo $errors['confirm_password']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="termos" name="termos" required>
                        <label for="termos">
                            Aceito os <a href="termos.php" target="_blank">Termos de Uso</a> e 
                            <a href="privacidade.php" target="_blank">Política de Privacidade</a>
                        </label>
                    </div>
                    <?php if (isset($errors['termos'])): ?>
                        <span class="form-error"><?php echo $errors['termos']; ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Criar Conta Gratuita
                </button>

               <!-- <div class="auth-divider">
                    <span>ou</span>
                </div> -->

                <div class="auth-register">
                    <p>Já tem uma conta?</p>
                    <a href="login.php" class="btn btn-outline btn-block">
                        Fazer Login
                    </a>
                </div>
            </form>
        </div>

        <div class="auth-side">
            <div class="auth-side-content">
                <h2>Por que escolher nosso sistema?</h2>
                <ul class="auth-features">
                    <li>
                        <span class="feature-icon">🎉</span>
                        <span>Organize eventos de forma profissional</span>
                    </li>
                    <li>
                        <span class="feature-icon">💰</span>
                        <span>Controle total das finanças do evento</span>
                    </li>
                    <li>
                        <span class="feature-icon">📱</span>
                        <span>QR Code para check-in automático</span>
                    </li>
                    <li>
                        <span class="feature-icon">👥</span>
                        <span>Gerencie equipes e fornecedores</span>
                    </li>
                    <li>
                        <span class="feature-icon">📊</span>
                        <span>Relatórios detalhados em tempo real</span>
                    </li>
                    <li>
                        <span class="feature-icon">🔒</span>
                        <span>Segurança e privacidade garantidas</span>
                    </li>
                </ul>
                <div class="auth-testimonial">
                    <p>"Simplesmente incrível! Consegui organizar meu casamento com 300 convidados sem nenhum stress. Recomendo!"</p>
                    <div class="testimonial-author">
                        <strong>João Baptista</strong>
                        <span>Luanda, Angola</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>