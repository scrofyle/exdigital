<?php
/**
 * FORNECEDOR - GESTÃO DE EQUIPE
 * Cadastro e controle de membros da equipe
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'fornecedor') {
    redirect('/fornecedor/login.php');
}

$db = Database::getInstance()->getConnection();
$fornecedorId = Session::getUserId();
$eventoId = Session::get('evento_id');

$success = '';
$error = '';

// Processar adição de membro
if (isPost() && isset($_POST['adicionar_membro'])) {
    $validator = new Validator();
    
    $rules = [
        'nome_completo' => 'required|min:3',
        'funcao' => 'required',
        'telefone' => 'required'
    ];
    
    if ($validator->validate($_POST, $rules)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO equipe_fornecedor 
                (fornecedor_id, nome_completo, funcao, telefone, documento, observacoes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $fornecedorId,
                post('nome_completo'),
                post('funcao'),
                post('telefone'),
                post('documento'),
                post('observacoes')
            ]);
            
            $success = 'Membro adicionado com sucesso!';
            
            // Log
            logAccess('fornecedor', $fornecedorId, 'equipe_add', 'Adicionou membro: ' . post('nome_completo'));
            
        } catch (PDOException $e) {
            $error = 'Erro ao adicionar membro: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, preencha todos os campos obrigatórios!';
    }
}

// Processar toggle de presença
if (isPost() && isset($_POST['toggle_presenca_equipe'])) {
    $membroId = post('membro_id');
    
    $stmt = $db->prepare("
        SELECT presente FROM equipe_fornecedor 
        WHERE id = ? AND fornecedor_id = ?
    ");
    $stmt->execute([$membroId, $fornecedorId]);
    $membro = $stmt->fetch();
    
    if ($membro) {
        $novoStatus = !$membro['presente'];
        $horaCheckin = $novoStatus ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("
            UPDATE equipe_fornecedor 
            SET presente = ?, hora_checkin = ? 
            WHERE id = ?
        ");
        $stmt->execute([$novoStatus, $horaCheckin, $membroId]);
        
        $success = $novoStatus ? 'Check-in realizado!' : 'Check-out realizado!';
    }
}

// Processar exclusão de membro
if (isPost() && isset($_POST['deletar_membro'])) {
    $membroId = post('membro_id');
    
    $stmt = $db->prepare("
        DELETE FROM equipe_fornecedor 
        WHERE id = ? AND fornecedor_id = ?
    ");
    $stmt->execute([$membroId, $fornecedorId]);
    
    $success = 'Membro removido com sucesso!';
}

// Buscar membros da equipe
$stmt = $db->prepare("
    SELECT * FROM equipe_fornecedor 
    WHERE fornecedor_id = ? 
    ORDER BY presente DESC, nome_completo ASC
");
$stmt->execute([$fornecedorId]);
$equipeMembers = $stmt->fetchAll();

// Estatísticas
$totalEquipe = count($equipeMembers);
$equipePresenteTotal = count(array_filter($equipeMembers, function($m) { return $m['presente']; }));
$taxaPresenca = $totalEquipe > 0 ? ($equipePresenteTotal / $totalEquipe) * 100 : 0;

include '../includes/fornecedor_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">👥 Gestão de Equipe</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Equipe</span>
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
                <div class="stat-label">Total da Equipe</div>
                <div class="stat-value"><?php echo $totalEquipe; ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-person-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Presentes</div>
                <div class="stat-value"><?php echo $equipePresenteTotal; ?></div>
                <div class="stat-change"><?php echo number_format($taxaPresenca, 1); ?>%</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Aguardando</div>
                <div class="stat-value"><?php echo $totalEquipe - $equipePresenteTotal; ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Lista de Equipe -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">👥 Membros da Equipe</h3>
                    <button onclick="openModal('modalAddMembro')" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Adicionar Membro
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($equipeMembers)): ?>
                    <div class="text-center" style="padding: 3rem;">
                        <i class="bi bi-people" style="font-size: 4rem; color: var(--gray-light);"></i>
                        <h5 class="mt-3">Nenhum membro cadastrado</h5>
                        <p class="text-muted">Comece adicionando membros da sua equipe</p>
                        <button onclick="openModal('modalAddMembro')" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-circle"></i> Adicionar Primeiro Membro
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Função</th>
                                    <th>Contato</th>
                                    <th>Status</th>
                                    <th>Check-in</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipeMembers as $membro): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo Security::clean($membro['nome_completo']); ?></strong>
                                        <?php if ($membro['documento']): ?>
                                        <br><small class="text-muted">Doc: <?php echo Security::clean($membro['documento']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo Security::clean($membro['funcao']); ?></td>
                                    <td><?php echo Security::clean($membro['telefone']); ?></td>
                                    <td>
                                        <?php if ($membro['presente']): ?>
                                            <span class="badge badge-success">
                                                <i class="bi bi-check-circle"></i> Presente
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="bi bi-clock"></i> Aguardando
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($membro['hora_checkin']): ?>
                                            <small><?php echo formatDateTime($membro['hora_checkin']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="toggle_presenca_equipe" value="1">
                                            <input type="hidden" name="membro_id" value="<?php echo $membro['id']; ?>">
                                            <button type="submit" 
                                                    class="btn btn-sm <?php echo $membro['presente'] ? 'btn-secondary' : 'btn-success'; ?>"
                                                    title="<?php echo $membro['presente'] ? 'Remover presença' : 'Marcar presença'; ?>">
                                                <i class="bi bi-<?php echo $membro['presente'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="deletar_membro" value="1">
                                            <input type="hidden" name="membro_id" value="<?php echo $membro['id']; ?>">
                                            <button type="submit" 
                                                    class="btn btn-sm btn-danger"
                                                    onclick="return confirm('Deseja realmente remover este membro?')"
                                                    title="Remover">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-4">
            <!-- Progresso -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📊 Progresso da Equipe</h3>
                </div>
                <div class="card-body">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 3rem; font-weight: 700; color: var(--success-color);">
                            <?php echo number_format($taxaPresenca, 1); ?>%
                        </div>
                        <p style="margin: 0; color: var(--gray-medium);">Da equipe presente</p>
                    </div>

                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar success" style="width: <?php echo $taxaPresenca; ?>%;"></div>
                    </div>

                    <div style="margin-top: 1rem; text-align: center;">
                        <strong><?php echo $equipePresenteTotal; ?></strong> de 
                        <strong><?php echo $totalEquipe; ?></strong> membros
                    </div>
                </div>
            </div>

            <!-- Dicas -->
            <div class="card mt-3">
                <div class="card-header" style="background: #EFF6FF;">
                    <h3 class="card-title" style="color: var(--info-color); margin: 0;">
                        💡 Dicas
                    </h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">Cadastre todos os membros antes do evento</li>
                        <li style="margin-bottom: 0.5rem;">Faça check-in ao chegar no local</li>
                        <li style="margin-bottom: 0.5rem;">Mantenha os dados de contato atualizados</li>
                        <li>Use observações para instruções especiais</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Membro -->
<div class="modal-overlay" id="modalAddMembro">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">➕ Adicionar Membro da Equipe</h3>
            <button class="modal-close" onclick="closeModal('modalAddMembro')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="adicionar_membro" value="1">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label form-label-required">Nome Completo</label>
                    <input type="text" name="nome_completo" class="form-control" required>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label form-label-required">Função</label>
                            <input type="text" name="funcao" class="form-control" 
                                   placeholder="Ex: Segurança, Garçom, etc" required>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label form-label-required">Telefone</label>
                            <input type="tel" name="telefone" class="form-control" 
                                   placeholder="+244 900 000 000" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Documento (BI/Passaporte)</label>
                    <input type="text" name="documento" class="form-control" 
                           placeholder="Ex: 000000000LA000">
                </div>

                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea name="observacoes" class="form-control" rows="3" 
                              placeholder="Instruções especiais, horários, etc"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddMembro')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Adicionar Membro
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/fornecedor_footer.php'; ?>