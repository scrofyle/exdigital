<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Perfil do Cliente
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Buscar dados do cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

if (!$cliente) {
    Session::setFlash('error', 'Cliente não encontrado');
    redirect('/cliente/dashboard.php');
}

$errors = [];
$tab = get('tab', 'dados');

// Atualizar dados pessoais
if (isPost() && post('action') === 'update_dados') {
    $nome = post('nome_completo');
    $email = post('email');
    $telefone = post('telefone');
    $empresa = post('empresa');
    $endereco = post('endereco');
    $cidade = post('cidade');
    $provincia = post('provincia');
    
    // Validações
    if (empty($nome)) {
        $errors['nome'] = 'Nome completo é obrigatório';
    }
    
    if (empty($email) || !Security::validateEmail($email)) {
        $errors['email'] = 'Email válido é obrigatório';
    } else {
        // Verificar se email já existe (exceto o próprio)
        $stmt = $db->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
        $stmt->execute([$email, $clienteId]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Este email já está em uso';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE clientes SET
                    nome_completo = ?,
                    email = ?,
                    telefone = ?,
                    empresa = ?,
                    endereco = ?,
                    cidade = ?,
                    provincia = ?,
                    atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $nome,
                $email,
                $telefone,
                $empresa,
                $endereco,
                $cidade,
                $provincia,
                $clienteId
            ]);
            
            // Atualizar sessão
            Session::set('user_name', $nome);
            Session::set('user_email', $email);
            
            logAccess('cliente', $clienteId, 'update_perfil', 'Dados pessoais atualizados');
            
            Session::setFlash('success', 'Dados atualizados com sucesso!');
            redirect('/cliente/perfil.php');
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao atualizar dados. Tente novamente.';
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
        }
    }
}

// Alterar senha
if (isPost() && post('action') === 'change_password') {
    $senhaAtual = post('senha_atual');
    $novaSenha = post('nova_senha');
    $confirmarSenha = post('confirmar_senha');
    
    if (empty($senhaAtual)) {
        $errors['senha_atual'] = 'Senha atual é obrigatória';
    } elseif (!Security::verifyPassword($senhaAtual, $cliente['senha'])) {
        $errors['senha_atual'] = 'Senha atual incorreta';
    }
    
    if (empty($novaSenha)) {
        $errors['nova_senha'] = 'Nova senha é obrigatória';
    } elseif (!Security::isStrongPassword($novaSenha)) {
        $errors['nova_senha'] = 'Senha deve ter no mínimo 8 caracteres com letras maiúsculas, minúsculas e números';
    }
    
    if ($novaSenha !== $confirmarSenha) {
        $errors['confirmar_senha'] = 'As senhas não coincidem';
    }
    
    if (empty($errors)) {
        try {
            $senhaHash = Security::hashPassword($novaSenha);
            
            $stmt = $db->prepare("UPDATE clientes SET senha = ?, atualizado_em = NOW() WHERE id = ?");
            $stmt->execute([$senhaHash, $clienteId]);
            
            logAccess('cliente', $clienteId, 'change_password', 'Senha alterada');
            
            Session::setFlash('success', 'Senha alterada com sucesso!');
            redirect('/cliente/perfil.php?tab=seguranca');
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao alterar senha. Tente novamente.';
            error_log("Erro ao alterar senha: " . $e->getMessage());
        }
    } else {
        $tab = 'seguranca';
    }
}

// Upload de foto
if (isPost() && post('action') === 'upload_foto') {
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['foto_perfil'], 'perfis');
        
        if ($upload['success']) {
            // Deletar foto antiga
            if ($cliente['foto_perfil']) {
                deleteFile('perfis/' . $cliente['foto_perfil']);
            }
            
            $stmt = $db->prepare("UPDATE clientes SET foto_perfil = ? WHERE id = ?");
            $stmt->execute([$upload['filename'], $clienteId]);
            
            logAccess('cliente', $clienteId, 'upload_foto', 'Foto de perfil atualizada');
            
            Session::setFlash('success', 'Foto atualizada com sucesso!');
            redirect('/cliente/perfil.php');
        } else {
            $errors['foto'] = $upload['message'];
        }
    } else {
        $errors['foto'] = 'Selecione uma imagem';
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Meu Perfil</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Perfil</span>
            </div>
        </div>
    </div>

    <?php if (Session::getFlash('success')): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✓</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('success'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($errors['geral'])): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo $errors['geral']; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Sidebar do Perfil -->
        <div class="col-4">
            <div class="card">
                <div class="card-body text-center">
                    <div style="margin-bottom: 1.5rem;">
                        <?php if ($cliente['foto_perfil']): ?>
                            <img src="<?php echo asset('uploads/perfis/' . $cliente['foto_perfil']); ?>" 
                                 alt="Foto de Perfil" 
                                 style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary-color);">
                        <?php else: ?>
                            <div style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); display: flex; align-items: center; justify-content: center; margin: 0 auto; color: white; font-size: 3rem; font-weight: 700;">
                                <?php echo strtoupper(substr($cliente['nome_completo'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin-bottom: 0.5rem;"><?php echo Security::clean($cliente['nome_completo']); ?></h3>
                    <p style="color: var(--gray-medium); margin-bottom: 1.5rem;"><?php echo Security::clean($cliente['email']); ?></p>

                    <form method="POST" enctype="multipart/form-data" id="fotoForm">
                        <input type="hidden" name="action" value="upload_foto">
                        <input type="file" name="foto_perfil" id="foto_perfil" accept="image/*" style="display: none;" onchange="document.getElementById('fotoForm').submit();">
                        <button type="button" onclick="document.getElementById('foto_perfil').click()" class="btn btn-primary btn-block">
                            📷 Alterar Foto
                        </button>
                    </form>

                    <?php if (isset($errors['foto'])): ?>
                        <span class="form-error" style="display: block; margin-top: 0.5rem;"><?php echo $errors['foto']; ?></span>
                    <?php endif; ?>

                    <hr style="margin: 1.5rem 0;">

                    <div style="text-align: left;">
                        <div style="margin-bottom: 1rem;">
                            <small style="color: var(--gray-medium);">Membro desde</small>
                            <div><strong><?php echo formatDate($cliente['criado_em'], 'F Y'); ?></strong></div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <small style="color: var(--gray-medium);">Status da Conta</small>
                            <div>
                                <?php 
                                $statusLabels = [
                                    'ativo' => '<span class="badge badge-success">Ativo</span>',
                                    'inativo' => '<span class="badge badge-secondary">Inativo</span>',
                                    'suspenso' => '<span class="badge badge-danger">Suspenso</span>',
                                    'inadimplente' => '<span class="badge badge-warning">Inadimplente</span>'
                                ];
                                echo $statusLabels[$cliente['status']] ?? $cliente['status'];
                                ?>
                            </div>
                        </div>

                        <?php if ($cliente['ultimo_acesso']): ?>
                        <div>
                            <small style="color: var(--gray-medium);">Último acesso</small>
                            <div><strong><?php echo timeAgo($cliente['ultimo_acesso']); ?></strong></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conteúdo do Perfil -->
        <div class="col-8">
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab-item <?php echo $tab === 'dados' ? 'active' : ''; ?>" onclick="window.location='?tab=dados'">
                    Dados Pessoais
                </div>
                <div class="tab-item <?php echo $tab === 'seguranca' ? 'active' : ''; ?>" onclick="window.location='?tab=seguranca'">
                    Segurança
                </div>
            </div>

            <!-- Tab: Dados Pessoais -->
            <div class="tab-content <?php echo $tab === 'dados' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Informações Pessoais</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" data-validate>
                            <input type="hidden" name="action" value="update_dados">

                            <div class="form-group">
                                <label class="form-label form-label-required">Nome Completo</label>
                                <input type="text" name="nome_completo" 
                                       class="form-control <?php echo isset($errors['nome']) ? 'error' : ''; ?>" 
                                       value="<?php echo Security::clean($cliente['nome_completo']); ?>" required>
                                <?php if (isset($errors['nome'])): ?>
                                    <span class="form-error"><?php echo $errors['nome']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label form-label-required">Email</label>
                                        <input type="email" name="email" 
                                               class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                                               value="<?php echo Security::clean($cliente['email']); ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <span class="form-error"><?php echo $errors['email']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Telefone</label>
                                        <input type="tel" name="telefone" class="form-control" 
                                               value="<?php echo Security::clean($cliente['telefone']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Empresa</label>
                                <input type="text" name="empresa" class="form-control" 
                                       placeholder="Nome da sua empresa (opcional)"
                                       value="<?php echo Security::clean($cliente['empresa']); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Endereço</label>
                                <input type="text" name="endereco" class="form-control" 
                                       placeholder="Seu endereço completo"
                                       value="<?php echo Security::clean($cliente['endereco']); ?>">
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Cidade</label>
                                        <input type="text" name="cidade" class="form-control" 
                                               value="<?php echo Security::clean($cliente['cidade']); ?>">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Província</label>
                                        <input type="text" name="provincia" class="form-control" 
                                               value="<?php echo Security::clean($cliente['provincia']); ?>">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                ✓ Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Segurança -->
            <div class="tab-content <?php echo $tab === 'seguranca' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Alterar Senha</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" data-validate>
                            <input type="hidden" name="action" value="change_password">

                            <div class="form-group">
                                <label class="form-label form-label-required">Senha Atual</label>
                                <input type="password" name="senha_atual" 
                                       class="form-control <?php echo isset($errors['senha_atual']) ? 'error' : ''; ?>" 
                                       placeholder="••••••••" required>
                                <?php if (isset($errors['senha_atual'])): ?>
                                    <span class="form-error"><?php echo $errors['senha_atual']; ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Nova Senha</label>
                                <input type="password" name="nova_senha" 
                                       class="form-control <?php echo isset($errors['nova_senha']) ? 'error' : ''; ?>" 
                                       placeholder="••••••••" required>
                                <?php if (isset($errors['nova_senha'])): ?>
                                    <span class="form-error"><?php echo $errors['nova_senha']; ?></span>
                                <?php else: ?>
                                    <span class="form-help">Mínimo 8 caracteres com letras maiúsculas, minúsculas e números</span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label form-label-required">Confirmar Nova Senha</label>
                                <input type="password" name="confirmar_senha" 
                                       class="form-control <?php echo isset($errors['confirmar_senha']) ? 'error' : ''; ?>" 
                                       placeholder="••••••••" required>
                                <?php if (isset($errors['confirmar_senha'])): ?>
                                    <span class="form-error"><?php echo $errors['confirmar_senha']; ?></span>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                🔒 Alterar Senha
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Informações de Segurança -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">🛡️ Dicas de Segurança</h3>
                    </div>
                    <div class="card-body">
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                                ✓ Use uma senha forte e única
                            </li>
                            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                                ✓ Nunca compartilhe sua senha com ninguém
                            </li>
                            <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                                ✓ Altere sua senha regularmente
                            </li>
                            <li style="padding: 0.75rem 0;">
                                ✓ Não use a mesma senha em outros sites
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/cliente_footer.php'; ?>