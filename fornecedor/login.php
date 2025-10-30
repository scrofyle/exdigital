<?php
/**
 * FORNECEDOR - LOGIN
 * Sistema de autenticação para fornecedores
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Se já estiver logado, redirecionar
if (Session::isLoggedIn() && Session::getUserType() === 'fornecedor') {
    redirect('/fornecedor/dashboard.php');
}

$db = Database::getInstance()->getConnection();
$error = '';

// Processar login
if (isPost()) {
    $codigoAcesso = post('codigo_acesso');
    $senha = post('senha');
    
    if (empty($codigoAcesso) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        // Buscar fornecedor
        $stmt = $db->prepare("
            SELECT f.*, e.nome_evento, e.data_evento, e.codigo_evento, e.id as evento_id
            FROM fornecedores_evento f
            JOIN eventos e ON f.evento_id = e.id
            WHERE f.codigo_acesso = ? AND f.status = 'ativo'
        ");
        $stmt->execute([$codigoAcesso]);
        $fornecedor = $stmt->fetch();
        
        if ($fornecedor && Security::verifyPassword($senha, $fornecedor['senha'])) {
            // Login bem-sucedido
            Session::set('user_id', $fornecedor['id']);
            Session::set('user_type', 'fornecedor');
            Session::set('user_name', $fornecedor['nome_responsavel']);
            Session::set('fornecedor_categoria', $fornecedor['categoria']);
            Session::set('evento_id', $fornecedor['evento_id']);
            Session::set('evento_nome', $fornecedor['nome_evento']);
            Session::set('evento_codigo', $fornecedor['codigo_evento']);
            
            // Registrar acesso
            logAccess('fornecedor', $fornecedor['id'], 'login', 'Login realizado');
            
            // Criar notificação para o cliente
            $stmt = $db->prepare("SELECT cliente_id FROM eventos WHERE id = ?");
            $stmt->execute([$fornecedor['evento_id']]);
            $clienteId = $stmt->fetchColumn();
            
            createNotification(
                'cliente',
                $clienteId,
                'Fornecedor Conectado',
                $fornecedor['nome_responsavel'] . ' (' . $fornecedor['categoria'] . ') acabou de fazer login no sistema.',
                'info'
            );
            
            redirect('/fornecedor/dashboard.php');
        } else {
            $error = 'Código de acesso ou senha inválidos!';
            
            // Log de tentativa falhada
            if ($fornecedor) {
                logAccess('fornecedor', $fornecedor['id'], 'login_failed', 'Tentativa de login com senha incorreta');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Fornecedor - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #1E3A8A 100%);
            padding: 2rem;
        }
        
        .auth-box {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-logo h1 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .auth-logo p {
            color: var(--gray-medium);
            font-size: 0.938rem;
        }
        
        .auth-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #6e8de3 0%, #1E3A8A 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
        }
        
        .info-box {
            background: #F0F9FF;
            border-left: 4px solid var(--info-color);
            padding: 1rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }
        
        .info-box strong {
            display: block;
            color: var(--info-color);
            margin-bottom: 0.5rem;
        }
        
        .info-box ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .info-box li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <div class="auth-icon">
                    <i class="bi bi-shop"></i>
                </div>
                <h1><?php echo SITE_NAME; ?></h1>
                <p>Área do Fornecedor</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">❌</div>
                <div class="alert-content">
                    <strong>Erro!</strong>
                    <p class="alert-message"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <strong>📋 Informações Importantes:</strong>
                <ul>
                    <li>Use o código de acesso fornecido pelo cliente</li>
                    <li>A senha é gerada no cadastro inicial</li>
                    <li>Entre em contato com o cliente se tiver problemas</li>
                </ul>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label form-label-required">
                        <i class="bi bi-key"></i> Código de Acesso
                    </label>
                    <input type="text" 
                           name="codigo_acesso" 
                           class="form-control" 
                           placeholder="Ex: FOR-ABC123"
                           value="<?php echo post('codigo_acesso'); ?>"
                           required 
                           autofocus>
                    <small class="form-help">Código fornecido pelo organizador do evento</small>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">
                        <i class="bi bi-lock"></i> Senha
                    </label>
                    <input type="password" 
                           name="senha" 
                           class="form-control" 
                           placeholder="Sua senha"
                           required>
                    <small class="form-help">Senha definida no primeiro acesso</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Entrar no Sistema
                </button>
            </form>

            <hr style="margin: 2rem 0;">

            <div style="text-align: center;">
                <p style="color: var(--gray-medium); font-size: 0.875rem; margin-bottom: 1rem;">
                    Problemas para acessar?
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="mailto:<?php echo ADMIN_EMAIL; ?>" 
                       style="color: var(--primary-color); font-size: 0.875rem;">
                        <i class="bi bi-envelope"></i> Email Suporte
                    </a>
                    <a href="tel:+244948005566" 
                       style="color: var(--primary-color); font-size: 0.875rem;">
                        <i class="bi bi-phone"></i> +244 948 005 566
                    </a>
                </div>
            </div>

            <div style="margin-top: 2rem; text-align: center;">
                <a href="<?php echo SITE_URL; ?>/login.php" 
                   class="btn btn-outline"
                   style="font-size: 0.875rem;">
                    <i class="bi bi-arrow-left"></i> Voltar ao Login Principal
                </a>
            </div>
        </div>
    </div>

    <script>
    // Formatar código de acesso em uppercase
    document.querySelector('input[name="codigo_acesso"]').addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
    </script>
</body>
</html>