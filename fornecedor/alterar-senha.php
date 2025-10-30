<?php
/**
 * FORNECEDOR - ALTERAR SENHA
 * Sistema para alterar senha (primeiro acesso ou recuperação)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'fornecedor') {
    redirect('/fornecedor/login.php');
}

$db = Database::getInstance()->getConnection();
$fornecedorId = Session::getUserId();

$success = '';
$error = '';

// Processar alteração de senha
if (isPost()) {
    $senhaAtual = post('senha_atual');
    $senhaNova = post('senha_nova');
    $senhaConfirm = post('senha_confirm');
    
    if (empty($senhaAtual) || empty($senhaNova) || empty($senhaConfirm)) {
        $error = 'Preencha todos os campos!';
    } elseif ($senhaNova !== $senhaConfirm) {
        $error = 'As senhas não coincidem!';
    } elseif (strlen($senhaNova) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres!';
    } else {
        // Buscar fornecedor
        $stmt = $db->prepare("SELECT senha FROM fornecedores_evento WHERE id = ?");
        $stmt->execute([$fornecedorId]);
        $fornecedor = $stmt->fetch();
        
        if ($fornecedor && Security::verifyPassword($senhaAtual, $fornecedor['senha'])) {
            // Atualizar senha
            $novaSenhaHash = Security::hashPassword($senhaNova);
            $stmt = $db->prepare("UPDATE fornecedores_evento SET senha = ? WHERE id = ?");
            $stmt->execute([$novaSenhaHash, $fornecedorId]);
            
            // Log
            logAccess('fornecedor', $fornecedorId, 'senha_alterada', 'Senha alterada com sucesso');
            
            $success = 'Senha alterada com sucesso! Você já pode usar a nova senha.';
            
            Session::setFlash('success', $success);
            redirect('/fornecedor/dashboard.php');
        } else {
            $error = 'Senha atual incorreta!';
        }
    }
}

include '../includes/fornecedor_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">🔒 Alterar Senha</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Alterar Senha</span>
        </div>
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

    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔐 Definir Nova Senha</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            Por segurança, recomendamos alterar a senha temporária.
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label form-label-required">Senha Atual</label>
                            <input type="password" name="senha_atual" class="form-control" 
                                   placeholder="Sua senha temporária" required autofocus>
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label-required">Nova Senha</label>
                            <input type="password" name="senha_nova" class="form-control" 
                                   placeholder="Mínimo 6 caracteres" required id="senha_nova">
                            <small class="form-help">Use letras e números para maior segurança</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label-required">Confirmar Nova Senha</label>
                            <input type="password" name="senha_confirm" class="form-control" 
                                   placeholder="Digite novamente" required id="senha_confirm">
                        </div>

                        <div id="senha-strength" style="margin-top: 1rem;"></div>

                        <div class="text-right mt-4">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Alterar Senha
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="card">
                <div class="card-header" style="background: #FEF3C7;">
                    <h3 class="card-title" style="color: #92400E; margin: 0;">
                        💡 Dicas de Segurança
                    </h3>
                </div>
                <div class="card-body">
                    <h5>Como criar uma senha forte:</h5>
                    <ul style="line-height: 1.8;">
                        <li>✅ Use pelo menos 8 caracteres</li>
                        <li>✅ Misture letras maiúsculas e minúsculas</li>
                        <li>✅ Inclua números</li>
                        <li>✅ Adicione símbolos especiais (@, #, $, etc)</li>
                        <li>❌ Não use datas de nascimento</li>
                        <li>❌ Evite sequências (123456, abcdef)</li>
                        <li>❌ Não use palavras comuns</li>
                    </ul>

                    <hr>

                    <h5>Exemplos de senhas fortes:</h5>
                    <div style="background: #F8F9FA; padding: 1rem; border-radius: var(--border-radius); font-family: monospace;">
                        Evento@2025<br>
                        Forn#2024!<br>
                        Seg123$Forte
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Verificar força da senha
document.getElementById('senha_nova').addEventListener('input', function(e) {
    const senha = e.target.value;
    const strengthDiv = document.getElementById('senha-strength');
    
    if (senha.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    // Verificações
    if (senha.length >= 8) {
        strength += 25;
        feedback.push('✅ Comprimento adequado');
    } else {
        feedback.push('❌ Use pelo menos 8 caracteres');
    }
    
    if (/[a-z]/.test(senha) && /[A-Z]/.test(senha)) {
        strength += 25;
        feedback.push('✅ Maiúsculas e minúsculas');
    } else {
        feedback.push('❌ Use maiúsculas e minúsculas');
    }
    
    if (/[0-9]/.test(senha)) {
        strength += 25;
        feedback.push('✅ Contém números');
    } else {
        feedback.push('❌ Adicione números');
    }
    
    if (/[^a-zA-Z0-9]/.test(senha)) {
        strength += 25;
        feedback.push('✅ Contém símbolos');
    } else {
        feedback.push('❌ Adicione símbolos especiais');
    }
    
    // Determinar cor e texto
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
    
    // Exibir feedback
    strengthDiv.innerHTML = `
        <div style="margin-bottom: 0.5rem;">
            <strong>Força da senha: <span style="color: ${color}">${text}</span></strong>
        </div>
        <div class="progress" style="height: 10px; margin-bottom: 1rem;">
            <div class="progress-bar" style="width: ${strength}%; background: ${color};"></div>
        </div>
        <div style="font-size: 0.875rem;">
            ${feedback.join('<br>')}
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
</script>

<?php include '../includes/fornecedor_footer.php'; ?>