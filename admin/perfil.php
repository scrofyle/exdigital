<?php
/**
 * ADMIN - PERFIL DO ADMINISTRADOR
 * Editar dados pessoais e alterar senha
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

$success = '';
$error = '';

// Processar atualização de dados
if (isPost() && isset($_POST['atualizar_dados'])) {
    $validator = new Validator();
    
    $rules = [
        'nome_completo' => 'required|min:3',
        'email' => 'required|email',
        'telefone' => 'required'
    ];
    
    if ($validator->validate($_POST, $rules)) {
        // Verificar se email já está em uso por outro admin
        $stmt = $db->prepare("SELECT id FROM administradores WHERE email = ? AND id != ?");
        $stmt->execute([post('email'), $userId]);
        
        if ($stmt->fetch()) {
            $error = 'Este email já está em uso por outro administrador!';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE administradores 
                    SET nome_completo = ?, email = ?, telefone = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    post('nome_completo'),
                    post('email'),
                    post('telefone'),
                    $userId
                ]);
                
                // Atualizar sessão
                Session::set('user_name', post('nome_completo'));
                
                $success = 'Dados atualizados com sucesso!';
                
                logAccess('admin', $userId, 'perfil_atualizado', 'Atualizou dados do perfil');
                
            } catch (PDOException $e) {
                $error = 'Erro ao atualizar dados: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Por favor, preencha todos os campos obrigatórios!';
    }
}

// Processar alteração de senha
if (isPost() && isset($_POST['alterar_senha'])) {
    $senhaAtual = post('senha_atual');
    $senhaNova = post('senha_nova');
    $senhaConfirm = post('senha_confirm');
    
    if (empty($senhaAtual) || empty($senhaNova) || empty($senhaConfirm)) {
        $error = 'Preencha todos os campos de senha!';
    } elseif ($senhaNova !== $senhaConfirm) {
        $error = 'As senhas não coincidem!';
    } elseif (strlen($senhaNova) < 8) {
        $error = 'A nova senha deve ter no mínimo 8 caracteres!';
    } else {
        // Verificar senha atual
        $stmt = $db->prepare("SELECT senha FROM administradores WHERE id = ?");
        $stmt->execute([$userId]);
        $admin = $stmt->fetch();
        
        if (Security::verifyPassword($senhaAtual, $admin['senha'])) {
            $novaSenhaHash = Security::hashPassword($senhaNova);
            
            $stmt = $db->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
            $stmt->execute([$novaSenhaHash, $userId]);
            
            $success = 'Senha alterada com sucesso!';
            
            logAccess('admin', $userId, 'senha_alterada', 'Alterou a senha');
            
        } else {
            $error = 'Senha atual incorreta!';
        }
    }
}

// Processar upload de foto
if (isPost() && isset($_POST['atualizar_foto'])) {
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === 0) {
        $resultado = uploadFile($_FILES['foto_perfil'], 'perfis');
        
        if ($resultado['success']) {
            // Deletar foto antiga se existir
            $stmt = $db->prepare("SELECT foto_perfil FROM administradores WHERE id = ?");
            $stmt->execute([$userId]);
            $admin = $stmt->fetch();
            
            if ($admin['foto_perfil']) {
                deleteFile('perfis/' . $admin['foto_perfil']);
            }
            
            // Atualizar foto no banco
            $stmt = $db->prepare("UPDATE administradores SET foto_perfil = ? WHERE id = ?");
            $stmt->execute([$resultado['filename'], $userId]);
            
            $success = 'Foto atualizada com sucesso!';
            
            logAccess('admin', $userId, 'foto_atualizada', 'Atualizou foto de perfil');
            
        } else {
            $error = $resultado['message'];
        }
    } else {
        $error = 'Selecione uma foto para upload!';
    }
}

// Buscar dados do admin
$stmt = $db->prepare("
    SELECT a.*, n.nome as nivel_nome, n.descricao as nivel_descricao
    FROM administradores a
    JOIN niveis_acesso n ON a.nivel_acesso_id = n.id
    WHERE a.id = ?
");
$stmt->execute([$userId]);
$admin = $stmt->fetch();

// Estatísticas do admin
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM logs_acesso 
    WHERE usuario_tipo = 'admin' 
    AND usuario_id = ?
");
$stmt->execute([$userId]);
$totalAcessos = $stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT acao, COUNT(*) as total 
    FROM logs_acesso 
    WHERE usuario_tipo = 'admin' 
    AND usuario_id = ? 
    GROUP BY acao 
    ORDER BY total DESC 
    LIMIT 5
");
$stmt->execute([$userId]);
$acoesFrequentes = $stmt->fetchAll();

include '../includes/admin_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">👤 Meu Perfil</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Perfil</span>
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
        <!-- Formulários -->
        <div class="col-8">
            <!-- Tabs -->
            <div class="card">
                <div class="card-body">
                    <div class="tabs">
                        <div class="tab-item active" data-target="tab-dados">
                            <i class="bi bi-person"></i> Dados Pessoais
                        </div>
                        <div class="tab-item" data-target="tab-senha">
                            <i class="bi bi-lock"></i> Segurança
                        </div>
                        <div class="tab-item" data-target="tab-atividades">
                            <i class="bi bi-clock-history"></i> Atividades
                        </div>
                    </div>

                    <!-- Tab: Dados Pessoais -->
                    <div id="tab-dados" class="tab-content active">
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="atualizar_dados" value="1">
                            
                            <div class="form-group">
                                <label class="form-label form-label-required">Nome Completo</label>
                                <input type="text" name="nome_completo" class="form-control" 
                                       value="<?php echo Security::clean($admin['nome_completo']); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label form-label-required">Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo Security::clean($admin['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label form-label-required">Telefone</label>
                                        <input type="tel" name="telefone" class="form-control" 
                                               value="<?php echo Security::clean($admin['telefone']); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Segurança -->
                    <div id="tab-senha" class="tab-content">
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="alterar_senha" value="1">
                            
                            <div class="alert alert-info">
                                <div class="alert-icon">ℹ️</div>
                                <div class="alert-content">
                                    Por segurança, recomendamos alterar sua senha periodicamente.
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Senha Atual</label>
                                <input type="password" name="senha_atual" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Nova Senha</label>
                                <input type="password" name="senha_nova" class="form-control" 
                                       placeholder="Mínimo 8 caracteres" minlength="8" required id="senha_nova">
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Confirmar Nova Senha</label>
                                <input type="password" name="senha_confirm" class="form-control" 
                                       placeholder="Digite novamente" required id="senha_confirm">
                            </div>

                            <div id="senha-strength" style="margin-bottom: 1rem;"></div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-shield-check"></i> Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Tab: Atividades -->
                    <div id="tab-atividades" class="tab-content">
                        <div class="mt-4">
                            <h5 class="mb-3">📊 Estatísticas de Uso</h5>
                            
                            <div class="row mb-4">
                                <div class="col-4">
                                    <div style="text-align: center; padding: 1.5rem; background: #F8F9FA; border-radius: var(--border-radius);">
                                        <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                                            <?php echo number_format($totalAcessos); ?>
                                        </div>
                                        <small class="text-muted">Total de Acessos</small>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div style="text-align: center; padding: 1.5rem; background: #F8F9FA; border-radius: var(--border-radius);">
                                        <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                            <?php echo $admin['ultimo_acesso'] ? timeAgo($admin['ultimo_acesso']) : 'Agora'; ?>
                                        </div>
                                        <small class="text-muted">Último Acesso</small>
                                    </div>
                                </div>

                                <div class="col-4">
                                    <div style="text-align: center; padding: 1.5rem; background: #F8F9FA; border-radius: var(--border-radius);">
                                        <div style="font-size: 2rem; font-weight: 700; color: var(--info-color);">
                                            <?php echo formatDate($admin['criado_em'], 'd/m/Y'); ?>
                                        </div>
                                        <small class="text-muted">Membro Desde</small>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-3">🔥 Ações Mais Frequentes</h5>
                            <?php if (!empty($acoesFrequentes)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Ação</th>
                                            <th>Quantidade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($acoesFrequentes as $acao): ?>
                                        <tr>
                                            <td><strong><?php echo Security::clean($acao['acao']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo number_format($acao['total']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">Nenhuma atividade registrada ainda.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-4">
            <!-- Card de Perfil -->
            <div class="card">
                <div class="card-body text-center">
                    <img src="<?php echo $admin['foto_perfil'] ? asset('uploads/perfis/' . $admin['foto_perfil']) : asset('images/default-avatar.png'); ?>" 
                         alt="Foto" 
                         class="img-fluid"
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color); margin-bottom: 1rem;">
                    
                    <h4 style="margin-bottom: 0.5rem;"><?php echo Security::clean($admin['nome_completo']); ?></h4>
                    <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                        <span class="badge badge-primary" style="font-size: 0.875rem;">
                            <?php echo Security::clean($admin['nivel_nome']); ?>
                        </span>
                    </p>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="atualizar_foto" value="1">
                        <input type="file" name="foto_perfil" id="foto_perfil" 
                               accept="image/*" style="display: none;" 
                               onchange="this.form.submit()">
                        <button type="button" class="btn btn-outline btn-sm" 
                                onclick="document.getElementById('foto_perfil').click()">
                            <i class="bi bi-camera"></i> Alterar Foto
                        </button>
                    </form>
                </div>
            </div>

            <!-- Informações do Nível -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">🔐 Seu Nível de Acesso</h3>
                </div>
                <div class="card-body">
                    <h5 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo Security::clean($admin['nivel_nome']); ?>
                    </h5>
                    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-medium);">
                        <?php echo Security::clean($admin['nivel_descricao']); ?>
                    </p>
                </div>
            </div>

            <!-- Informações da Conta -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">ℹ️ Informações da Conta</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small class="text-muted">Email:</small>
                        <div><strong><?php echo Security::clean($admin['email']); ?></strong></div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <small class="text-muted">Telefone:</small>
                        <div><strong><?php echo Security::clean($admin['telefone']); ?></strong></div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <small class="text-muted">Status:</small>
                        <div>
                            <?php if ($admin['status'] === 'ativo'): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?php echo ucfirst($admin['status']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div>
                        <small class="text-muted">Membro desde:</small>
                        <div><strong><?php echo formatDate($admin['criado_em']); ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- Dicas de Segurança -->
            <div class="card mt-3">
                <div class="card-header" style="background: #FEF3C7;">
                    <h3 class="card-title" style="color: #92400E; margin: 0;">
                        💡 Dicas de Segurança
                    </h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">Altere sua senha regularmente</li>
                        <li style="margin-bottom: 0.5rem;">Use senhas fortes e únicas</li>
                        <li style="margin-bottom: 0.5rem;">Nunca compartilhe sua senha</li>
                        <li>Faça logout ao sair do sistema</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Verificador de força da senha
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
        let feedback = [];
        
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
}
</script>

<?php include '../includes/admin_footer.php'; ?>