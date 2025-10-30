<?php
/**
 * RECUPERAÇÃO DE SENHA
 * Sistema completo para clientes e administradores
 */

define('SYSTEM_INIT', true);
require_once 'config.php';

$db = Database::getInstance()->getConnection();
$success = '';
$error = '';
$step = 1; // 1: Email, 2: Token, 3: Nova Senha

// Processar envio de email
if (isPost() && isset($_POST['enviar_email'])) {
    $email = post('email');
    $tipo = post('tipo', 'cliente');
    
    if (!Security::validateEmail($email)) {
        $error = 'Email inválido!';
    } else {
        // Buscar usuário
        $tabela = $tipo === 'admin' ? 'administradores' : 'clientes';
        $stmt = $db->prepare("SELECT id, nome_completo FROM $tabela WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario) {
            // Gerar token único
            $token = Security::generateToken(32);
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Salvar token no banco
            $stmt = $db->prepare("
                INSERT INTO tokens_recuperacao (usuario_tipo, usuario_id, email, token, expira_em)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tipo, $usuario['id'], $email, $token, $expira]);
            
            // Link de recuperação
            $link = SITE_URL . '/recuperar-senha.php?token=' . $token;
            
            // Enviar email
            $assunto = 'Recuperação de Senha - ' . SITE_NAME;
            $mensagem = "
                <h2>Recuperação de Senha</h2>
                <p>Olá, <strong>{$usuario['nome_completo']}</strong>!</p>
                <p>Recebemos uma solicitação para recuperar sua senha.</p>
                <p>Clique no link abaixo para criar uma nova senha:</p>
                <p><a href='$link' style='background: #6C63FF; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Recuperar Senha</a></p>
                <p><small>Ou copie e cole este link: $link</small></p>
                <p><strong>Este link expira em 1 hora.</strong></p>
                <hr>
                <p><small>Se você não solicitou esta recuperação, ignore este email.</small></p>
                <p><small>ExDigital Pro 2025.</small></p>
            ";
            
            sendEmail($email, $assunto, $mensagem);
            
            $success = 'Instruções enviadas para seu email! Verifique sua caixa de entrada.';
            $step = 2;
            
        } else {
            $error = 'Email não encontrado em nosso sistema.';
        }
    }
}

// Validar token da URL
$tokenUrl = get('token');
$tokenValido = null;

if ($tokenUrl) {
    $stmt = $db->prepare("
        SELECT * FROM tokens_recuperacao 
        WHERE token = ? AND usado = 0 AND expira_em > NOW()
    ");
    $stmt->execute([$tokenUrl]);
    $tokenValido = $stmt->fetch();
    
    if ($tokenValido) {
        $step = 3;
    } else {
        $error = 'Link inválido ou expirado! Solicite nova recuperação.';
    }
}

// Processar nova senha
if (isPost() && isset($_POST['nova_senha'])) {
    $token = post('token');
    $senhaNova = post('senha_nova');
    $senhaConfirm = post('senha_confirm');
    
    if (empty($senhaNova) || empty($senhaConfirm)) {
        $error = 'Preencha todos os campos!';
    } elseif ($senhaNova !== $senhaConfirm) {
        $error = 'As senhas não coincidem!';
    } elseif (strlen($senhaNova) < 8) {
        $error = 'A senha deve ter no mínimo 8 caracteres!';
    } else {
        // Buscar token
        $stmt = $db->prepare("
            SELECT * FROM tokens_recuperacao 
            WHERE token = ? AND usado = 0 AND expira_em > NOW()
        ");
        $stmt->execute([$token]);
        $tokenDados = $stmt->fetch();
        
        if ($tokenDados) {
            // Atualizar senha
            $tabela = $tokenDados['usuario_tipo'] === 'admin' ? 'administradores' : 'clientes';
            $novaSenhaHash = Security::hashPassword($senhaNova);
            
            $stmt = $db->prepare("UPDATE $tabela SET senha = ? WHERE id = ?");
            $stmt->execute([$novaSenhaHash, $tokenDados['usuario_id']]);
            
            // Marcar token como usado
            $stmt = $db->prepare("UPDATE tokens_recuperacao SET usado = 1 WHERE id = ?");
            $stmt->execute([$tokenDados['id']]);
            
            // Log
            logAccess($tokenDados['usuario_tipo'], $tokenDados['usuario_id'], 'senha_recuperada', 'Senha alterada via recuperação');
            
            Session::setFlash('success', 'Senha alterada com sucesso! Faça login com sua nova senha.');
            redirect('/login.php');
            
        } else {
            $error = 'Token inválido ou expirado!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/auth.css'); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6e8de3 0%, #1E3A8A 100%);
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
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-lighter);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--gray-medium);
        }
        
        .step.active {
            background: var(--primary-color);
            color: white;
        }
        
        .step.completed {
            background: var(--success-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <div class="auth-icon">
                    <i class="bi bi-key"></i>
                </div>
                <h1 style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;">
                    Recuperar Senha
                </h1>
                <p style="color: var(--gray-medium);"><?php echo SITE_NAME; ?></p>
            </div>

            <!-- Indicador de Passos -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3</div>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <div class="alert-icon">✅</div>
                <div class="alert-content">
                    <strong>Sucesso!</strong>
                    <p class="alert-message"><?php echo $success; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">❌</div>
                <div class="alert-content">
                    <strong>Erro!</strong>
                    <p class="alert-message"><?php echo $error; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($step === 1 || ($step === 2 && !$success)): ?>
            <!-- Passo 1: Informar Email -->
            <form method="POST">
                <input type="hidden" name="enviar_email" value="1">
                
                <div class="form-group">
                    <label class="form-label">Tipo de Conta</label>
                    <select name="tipo" class="form-control" required>
                        <option value="cliente">Cliente</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Email</label>
                    <input type="email" name="email" class="form-control" 
                           placeholder="seu-email@exemplo.com" required autofocus>
                    <small class="form-help">Digite o email cadastrado na sua conta</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="bi bi-envelope"></i> Enviar Link de Recuperação
                </button>
            </form>
            <?php endif; ?>

            <?php if ($step === 2 && $success): ?>
            <!-- Passo 2: Email Enviado -->
            <div style="text-align: center; padding: 2rem 0;">
                <i class="bi bi-envelope-check" style="font-size: 4rem; color: var(--success-color);"></i>
                <h3 style="margin: 1rem 0;">Email Enviado!</h3>
                <p style="color: var(--gray-medium);">
                    Verifique sua caixa de entrada e clique no link para continuar.
                </p>
                <p style="margin-top: 1rem;">
                    <small>Não recebeu? <a href="recuperar-senha.php">Tentar novamente</a></small>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($step === 3): ?>
            <!-- Passo 3: Definir Nova Senha -->
            <form method="POST">
                <input type="hidden" name="nova_senha" value="1">
                <input type="hidden" name="token" value="<?php echo Security::clean($tokenUrl); ?>">
                
                <div class="alert alert-info">
                    <div class="alert-icon">ℹ️</div>
                    <div class="alert-content">
                        Defina uma senha forte com no mínimo 8 caracteres.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Nova Senha</label>
                    <input type="password" name="senha_nova" class="form-control" 
                           placeholder="Mínimo 8 caracteres" required id="senha_nova">
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Confirmar Senha</label>
                    <input type="password" name="senha_confirm" class="form-control" 
                           placeholder="Digite novamente" required id="senha_confirm">
                </div>

                <div id="senha-strength" style="margin-bottom: 1rem;"></div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="bi bi-check-circle"></i> Alterar Senha
                </button>
            </form>
            <?php endif; ?>

            <hr style="margin: 2rem 0;">

            <div style="text-align: center;">
                <a href="login.php" style="color: var(--primary-color);">
                    <i class="bi bi-arrow-left"></i> Voltar ao Login
                </a>
            </div>
        </div>
    </div>

    <script>
    // Verificar força da senha
    const senhaInput = document.getElementById('senha_nova');
    if (senhaInput) {
        senhaInput.addEventListener('input', function(e) {
            const senha = e.target.value;
            const strengthDiv = document.getElementById('senha-strength');
            
            if (senha.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            
            if (senha.length >= 8) strength += 25;
            if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) strength += 25;
            if (/[0-9]/.test(senha)) strength += 25;
            if (/[^a-zA-Z0-9]/.test(senha)) strength += 25;
            
            let color, text;
            if (strength < 50) {
                color = '#EF4444';
                text = 'Fraca';
            } else if (strength < 75) {
                color = '#F59E0B';
                text = 'Média';
            } else {
                color = '#10B981';
                text = 'Forte';
            }
            
            strengthDiv.innerHTML = `
                <div style="margin-bottom: 0.5rem;">
                    <strong>Força: <span style="color: ${color}">${text}</span></strong>
                </div>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar" style="width: ${strength}%; background: ${color};"></div>
                </div>
            `;
        });
        
        // Verificar se senhas coincidem
        document.getElementById('senha_confirm').addEventListener('input', function(e) {
            const senhaNova = document.getElementById('senha_nova').value;
            const senhaConfirm = e.target.value;
            
            if (senhaConfirm.length > 0) {
                if (senhaNova === senhaConfirm) {
                    e.target.style.borderColor = '#10B981';
                } else {
                    e.target.style.borderColor = '#EF4444';
                }
            } else {
                e.target.style.borderColor = '';
            }
        });
    }
    </script>
</body>
</html>