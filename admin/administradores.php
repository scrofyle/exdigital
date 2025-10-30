<?php
/**
 * ADMIN - GESTÃO DE ADMINISTRADORES
 * CRUD completo de administradores do sistema
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação e permissão
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

// Verificar se é super admin
$stmt = $db->prepare("SELECT nivel_acesso_id FROM administradores WHERE id = ?");
$stmt->execute([$userId]);
$admin = $stmt->fetch();

if (!$admin || $admin['nivel_acesso_id'] != 1) {
    Session::setFlash('error', 'Acesso negado. Apenas Super Administradores podem gerenciar administradores.');
    redirect('/admin/dashboard.php');
}

$success = '';
$error = '';

// Processar adição de administrador
if (isPost() && isset($_POST['adicionar_admin'])) {
    $validator = new Validator();
    
    $rules = [
        'nome_completo' => 'required|min:3',
        'email' => 'required|email',
        'senha' => 'required|min:8',
        'nivel_acesso_id' => 'required'
    ];
    
    if ($validator->validate($_POST, $rules)) {
        // Verificar se email já existe
        $stmt = $db->prepare("SELECT id FROM administradores WHERE email = ?");
        $stmt->execute([post('email')]);
        
        if ($stmt->fetch()) {
            $error = 'Este email já está cadastrado!';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO administradores 
                    (nome_completo, email, senha, telefone, nivel_acesso_id, status)
                    VALUES (?, ?, ?, ?, ?, 'ativo')
                ");
                
                $senhaHash = Security::hashPassword(post('senha'));
                
                $stmt->execute([
                    post('nome_completo'),
                    post('email'),
                    $senhaHash,
                    post('telefone'),
                    post('nivel_acesso_id')
                ]);
                
                $success = 'Administrador adicionado com sucesso!';
                
                // Log
                logAccess('admin', $userId, 'admin_add', 'Adicionou administrador: ' . post('email'));
                
            } catch (PDOException $e) {
                $error = 'Erro ao adicionar administrador: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Por favor, preencha todos os campos obrigatórios!';
    }
}

// Processar edição
if (isPost() && isset($_POST['editar_admin'])) {
    $adminId = post('admin_id');
    
    if ($adminId == $userId) {
        $error = 'Use a página de Perfil para editar seus próprios dados!';
    } else {
        try {
            $sql = "UPDATE administradores SET nome_completo = ?, telefone = ?, nivel_acesso_id = ?";
            $params = [post('nome_completo'), post('telefone'), post('nivel_acesso_id')];
            
            // Atualizar senha se fornecida
            if (!empty(post('senha'))) {
                $sql .= ", senha = ?";
                $params[] = Security::hashPassword(post('senha'));
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $adminId;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            $success = 'Administrador atualizado com sucesso!';
            
            logAccess('admin', $userId, 'admin_edit', 'Editou administrador #' . $adminId);
            
        } catch (PDOException $e) {
            $error = 'Erro ao atualizar administrador: ' . $e->getMessage();
        }
    }
}

// Processar alteração de status
if (isPost() && isset($_POST['toggle_status'])) {
    $adminId = post('admin_id');
    
    if ($adminId == $userId) {
        $error = 'Você não pode desativar sua própria conta!';
    } else {
        $stmt = $db->prepare("SELECT status FROM administradores WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminData = $stmt->fetch();
        
        $novoStatus = $adminData['status'] === 'ativo' ? 'inativo' : 'ativo';
        
        $stmt = $db->prepare("UPDATE administradores SET status = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $adminId]);
        
        $success = 'Status atualizado com sucesso!';
        
        logAccess('admin', $userId, 'admin_status', "Status do admin #$adminId alterado para $novoStatus");
    }
}

// Processar exclusão
if (isPost() && isset($_POST['deletar_admin'])) {
    $adminId = post('admin_id');
    
    if ($adminId == $userId) {
        $error = 'Você não pode deletar sua própria conta!';
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM administradores WHERE id = ?");
            $stmt->execute([$adminId]);
            
            $success = 'Administrador removido com sucesso!';
            
            logAccess('admin', $userId, 'admin_delete', 'Deletou administrador #' . $adminId);
            
        } catch (PDOException $e) {
            $error = 'Erro ao deletar administrador: ' . $e->getMessage();
        }
    }
}

// Buscar todos os administradores
$stmt = $db->query("
    SELECT a.*, n.nome as nivel_nome
    FROM administradores a
    JOIN niveis_acesso n ON a.nivel_acesso_id = n.id
    ORDER BY a.id ASC
");
$administradores = $stmt->fetchAll();

// Buscar níveis de acesso
$stmt = $db->query("SELECT * FROM niveis_acesso ORDER BY id ASC");
$niveisAcesso = $stmt->fetchAll();

// Estatísticas
$totalAdmins = count($administradores);
$adminsAtivos = count(array_filter($administradores, function($a) { return $a['status'] === 'ativo'; }));
$superAdmins = count(array_filter($administradores, function($a) { return $a['nivel_acesso_id'] == 1; }));

include '../includes/admin_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">👥 Gestão de Administradores</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Administradores</span>
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

    <!-- Estatísticas -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Admins</div>
                <div class="stat-value"><?php echo $totalAdmins; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ativos</div>
                <div class="stat-value"><?php echo $adminsAtivos; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Super Admins</div>
                <div class="stat-value"><?php echo $superAdmins; ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Lista de Administradores -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Lista de Administradores</h3>
                    <button onclick="openModal('modalAddAdmin')" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Adicionar Administrador
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Nível de Acesso</th>
                                    <th>Status</th>
                                    <th>Último Acesso</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($administradores as $adm): ?>
                                <tr>
                                    <td><strong>#<?php echo $adm['id']; ?></strong></td>
                                    <td>
                                        <?php echo Security::clean($adm['nome_completo']); ?>
                                        <?php if ($adm['id'] == $userId): ?>
                                            <span class="badge badge-info" style="font-size: 0.75rem;">Você</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo Security::clean($adm['email']); ?></td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo Security::clean($adm['nivel_nome']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($adm['status'] === 'ativo'): ?>
                                            <span class="badge badge-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($adm['ultimo_acesso']): ?>
                                            <small><?php echo timeAgo($adm['ultimo_acesso']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Nunca</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($adm['id'] != $userId): ?>
                                            <button onclick="editarAdmin(<?php echo htmlspecialchars(json_encode($adm)); ?>)" 
                                                    class="btn btn-sm btn-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <input type="hidden" name="admin_id" value="<?php echo $adm['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm <?php echo $adm['status'] === 'ativo' ? 'btn-warning' : 'btn-success'; ?>"
                                                        title="<?php echo $adm['status'] === 'ativo' ? 'Desativar' : 'Ativar'; ?>">
                                                    <i class="bi bi-<?php echo $adm['status'] === 'ativo' ? 'x-circle' : 'check-circle'; ?>"></i>
                                                </button>
                                            </form>

                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="deletar_admin" value="1">
                                                <input type="hidden" name="admin_id" value="<?php echo $adm['id']; ?>">
                                                <button type="submit" 
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Deseja realmente deletar este administrador?')"
                                                        title="Deletar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a href="perfil.php" class="btn btn-sm btn-outline">
                                                <i class="bi bi-person"></i> Meu Perfil
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Informativa -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🔐 Níveis de Acesso</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($niveisAcesso as $nivel): ?>
                    <div style="padding: 1rem; background: #F8F9FA; border-radius: var(--border-radius); margin-bottom: 1rem;">
                        <strong style="color: var(--primary-color);">
                            <?php echo Security::clean($nivel['nome']); ?>
                        </strong>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--gray-medium);">
                            <?php echo Security::clean($nivel['descricao']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header" style="background: #FEF3C7;">
                    <h3 class="card-title" style="color: #92400E; margin: 0;">⚠️ Avisos</h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">Apenas Super Admins podem gerenciar administradores</li>
                        <li style="margin-bottom: 0.5rem;">Você não pode deletar ou desativar sua própria conta</li>
                        <li style="margin-bottom: 0.5rem;">Use senhas fortes para todos os administradores</li>
                        <li>Monitore os acessos regularmente</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Administrador -->
<div class="modal-overlay" id="modalAddAdmin">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">➕ Adicionar Administrador</h3>
            <button class="modal-close" onclick="closeModal('modalAddAdmin')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="adicionar_admin" value="1">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label form-label-required">Nome Completo</label>
                    <input type="text" name="nome_completo" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label form-label-required">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="tel" name="telefone" class="form-control" placeholder="+244 900 000 000">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Nível de Acesso</label>
                    <select name="nivel_acesso_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($niveisAcesso as $nivel): ?>
                        <option value="<?php echo $nivel['id']; ?>">
                            <?php echo Security::clean($nivel['nome']) . ' - ' . Security::clean($nivel['descricao']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label form-label-required">Senha Inicial</label>
                    <input type="password" name="senha" class="form-control" 
                           placeholder="Mínimo 8 caracteres" minlength="8" required>
                    <small class="form-help">O administrador deve alterar esta senha no primeiro acesso</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddAdmin')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Adicionar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Editar Administrador -->
<div class="modal-overlay" id="modalEditAdmin">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Editar Administrador</h3>
            <button class="modal-close" onclick="closeModal('modalEditAdmin')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="editar_admin" value="1">
            <input type="hidden" name="admin_id" id="edit_admin_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label form-label-required">Nome Completo</label>
                    <input type="text" name="nome_completo" id="edit_nome" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="tel" name="telefone" id="edit_telefone" class="form-control">
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label form-label-required">Nível de Acesso</label>
                            <select name="nivel_acesso_id" id="edit_nivel" class="form-control" required>
                                <?php foreach ($niveisAcesso as $nivel): ?>
                                <option value="<?php echo $nivel['id']; ?>">
                                    <?php echo Security::clean($nivel['nome']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Nova Senha (deixe vazio para não alterar)</label>
                    <input type="password" name="senha" class="form-control" 
                           placeholder="Mínimo 8 caracteres" minlength="8">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditAdmin')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editarAdmin(admin) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('edit_nome').value = admin.nome_completo;
    document.getElementById('edit_telefone').value = admin.telefone || '';
    document.getElementById('edit_nivel').value = admin.nivel_acesso_id;
    openModal('modalEditAdmin');
}
</script>

<?php include '../includes/admin_footer.php'; ?>