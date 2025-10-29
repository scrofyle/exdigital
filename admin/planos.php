<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Gestão de Planos (Admin)
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como admin
if (!Session::isLoggedIn() || Session::getUserType() !== 'admin') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$userId = Session::getUserId();

// Buscar todos os planos
$stmt = $db->query("
    SELECT p.*,
           (SELECT COUNT(*) FROM eventos WHERE plano_id = p.id) as total_eventos
    FROM planos p
    ORDER BY p.preco_aoa ASC
");
$planos = $stmt->fetchAll();

// Estatísticas
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_planos,
        SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as planos_ativos,
        (SELECT COUNT(*) FROM eventos) as total_eventos_vendidos,
        (SELECT COUNT(DISTINCT cliente_id) FROM eventos) as clientes_ativos
    FROM planos
");
$stats = $stmt->fetch();

// Ações
$action = isset($_GET['action']) ? $_GET['action'] : '';
$planoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ativar/Desativar plano
if ($action === 'toggle_status' && $planoId) {
    try {
        $stmt = $db->prepare("SELECT status FROM planos WHERE id = ?");
        $stmt->execute([$planoId]);
        $plano = $stmt->fetch();
        
        $novoStatus = $plano['status'] === 'ativo' ? 'inativo' : 'ativo';
        
        $stmt = $db->prepare("UPDATE planos SET status = ? WHERE id = ?");
        $stmt->execute([$novoStatus, $planoId]);
        
        logAccess('admin', $userId, 'toggle_plano', "Plano ID: $planoId alterado para $novoStatus");
        
        Session::setFlash('success', 'Status do plano atualizado!');
    } catch (PDOException $e) {
        Session::setFlash('error', 'Erro ao atualizar status do plano');
        error_log("Erro ao alterar status do plano: " . $e->getMessage());
    }
    redirect('/admin/planos.php');
}

// Processar formulário de edição
if (isPost() && post('action') === 'editar_plano') {
    $planoId = post('plano_id');
    $nome = post('nome');
    $descricao = post('descricao');
    $precoAoa = post('preco_aoa');
    $precoUsd = post('preco_usd');
    $maxConvites = post('max_convites');
    $maxFornecedores = post('max_fornecedores');
    $validadeDias = post('validade_dias');
    
    $errors = [];
    
    if (empty($nome)) {
        $errors[] = 'Nome é obrigatório';
    }
    
    if (empty($precoAoa) || $precoAoa <= 0) {
        $errors[] = 'Preço em AOA deve ser maior que zero';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE planos SET
                    nome = ?,
                    descricao = ?,
                    preco_aoa = ?,
                    preco_usd = ?,
                    max_convites = ?,
                    max_fornecedores = ?,
                    validade_dias = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $nome,
                $descricao,
                $precoAoa,
                $precoUsd,
                $maxConvites,
                $maxFornecedores,
                $validadeDias,
                $planoId
            ]);
            
            logAccess('admin', $userId, 'editar_plano', "Plano editado: $nome (ID: $planoId)");
            
            Session::setFlash('success', 'Plano atualizado com sucesso!');
            redirect('/admin/planos.php');
            
        } catch (PDOException $e) {
            Session::setFlash('error', 'Erro ao atualizar plano');
            error_log("Erro ao editar plano: " . $e->getMessage());
        }
    } else {
        Session::setFlash('error', implode('<br>', $errors));
    }
}

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Gestão de Planos</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Planos</span>
            </div>
        </div>
    </div>

    <?php if (Session::getFlash('success')): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('success'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (Session::getFlash('error')): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo Session::getFlash('error'); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Planos</div>
                <div class="stat-value"><?php echo number_format($stats['total_planos']); ?></div>
                <div class="stat-change"><?php echo $stats['planos_ativos']; ?> ativos</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eventos Vendidos</div>
                <div class="stat-value"><?php echo number_format($stats['total_eventos_vendidos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <div class="stat-content">
                <div class="stat-label">Clientes Ativos</div>
                <div class="stat-value"><?php echo number_format($stats['clientes_ativos']); ?></div>
            </div>
        </div>
    </div>

    <!-- Lista de Planos -->
    <div class="row" style="margin-top: 2rem;">
        <?php foreach ($planos as $plano): ?>
        <div class="col-3">
            <div class="card" style="<?php echo $plano['status'] === 'ativo' ? 'border: 2px solid var(--primary-color);' : 'opacity: 0.7;'; ?>">
                <div class="card-body">
                    <!-- Status Badge -->
                    <div style="text-align: right; margin-bottom: 1rem;">
                        <?php if ($plano['status'] === 'ativo'): ?>
                            <span class="badge badge-success">Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Inativo</span>
                        <?php endif; ?>
                    </div>

                    <!-- Nome do Plano -->
                    <h3 style="text-align: center; color: var(--primary-color); margin-bottom: 1rem;">
                        <?php echo Security::clean($plano['nome']); ?>
                    </h3>

                    <!-- Preço -->
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 2.5rem; font-weight: 700; color: var(--dark-color); margin-bottom: 0.25rem;">
                            <?php echo formatMoney($plano['preco_aoa']); ?>
                        </div>
                        <?php if ($plano['preco_usd']): ?>
                            <div style="color: var(--gray-medium); font-size: 0.875rem;">
                                ou <?php echo formatMoney($plano['preco_usd'], 'USD'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Descrição -->
                    <?php if ($plano['descricao']): ?>
                    <p style="text-align: center; color: var(--gray-medium); font-size: 0.875rem; margin-bottom: 1.5rem;">
                        <?php echo Security::clean($plano['descricao']); ?>
                    </p>
                    <?php endif; ?>

                    <!-- Características -->
                    <ul style="list-style: none; padding: 0; margin-bottom: 1.5rem;">
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter); display: flex; justify-content: space-between;">
                            <span>Convites</span>
                            <strong><?php echo number_format($plano['max_convites']); ?></strong>
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter); display: flex; justify-content: space-between;">
                            <span>Fornecedores</span>
                            <strong><?php echo number_format($plano['max_fornecedores']); ?></strong>
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter); display: flex; justify-content: space-between;">
                            <span>Validade</span>
                            <strong><?php echo $plano['validade_dias']; ?> dias</strong>
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; justify-content: space-between;">
                            <span>Eventos Vendidos</span>
                            <strong style="color: var(--success-color);"><?php echo $plano['total_eventos']; ?></strong>
                        </li>
                    </ul>

                    <!-- Ações -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <button onclick="editarPlano(<?php echo htmlspecialchars(json_encode($plano)); ?>)" 
                                class="btn btn-primary btn-block btn-sm">
                            ✏️ Editar
                        </button>
                        <a href="?action=toggle_status&id=<?php echo $plano['id']; ?>" 
                           class="btn <?php echo $plano['status'] === 'ativo' ? 'btn-secondary' : 'btn-success'; ?> btn-block btn-sm"
                           onclick="return confirm('Deseja <?php echo $plano['status'] === 'ativo' ? 'desativar' : 'ativar'; ?> este plano?')">
                            <?php echo $plano['status'] === 'ativo' ? '❌ Desativar' : '✅ Ativar'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Aviso -->
    <div class="card" style="margin-top: 2rem;">
        <div class="card-header">
            <h3 class="card-title">⚠️ Importante</h3>
        </div>
        <div class="card-body">
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                    <strong>Planos Inativos:</strong> Não aparecem para novos clientes, mas eventos já criados continuam funcionando
                </li>
                <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                    <strong>Alteração de Preços:</strong> Não afeta eventos já pagos
                </li>
                <li style="padding: 0.75rem 0;">
                    <strong>Exclusão:</strong> Não é possível excluir planos que já têm eventos associados
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div class="modal-overlay" id="editarPlanoModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Editar Plano</h3>
            <button class="modal-close" onclick="closeModal('editarPlanoModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="editar_plano">
            <input type="hidden" name="plano_id" id="edit_plano_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label form-label-required">Nome do Plano</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="descricao" id="edit_descricao" class="form-control" rows="2"></textarea>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label form-label-required">Preço (AOA)</label>
                            <input type="number" name="preco_aoa" id="edit_preco_aoa" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="form-label">Preço (USD)</label>
                            <input type="number" name="preco_usd" id="edit_preco_usd" class="form-control" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label form-label-required">Máx. Convites</label>
                            <input type="number" name="max_convites" id="edit_max_convites" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label form-label-required">Máx. Fornecedores</label>
                            <input type="number" name="max_fornecedores" id="edit_max_fornecedores" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="form-group">
                            <label class="form-label form-label-required">Validade (dias)</label>
                            <input type="number" name="validade_dias" id="edit_validade_dias" class="form-control" min="1" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal('editarPlanoModal')" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarPlano(plano) {
    document.getElementById('edit_plano_id').value = plano.id;
    document.getElementById('edit_nome').value = plano.nome;
    document.getElementById('edit_descricao').value = plano.descricao || '';
    document.getElementById('edit_preco_aoa').value = plano.preco_aoa;
    document.getElementById('edit_preco_usd').value = plano.preco_usd || '';
    document.getElementById('edit_max_convites').value = plano.max_convites;
    document.getElementById('edit_max_fornecedores').value = plano.max_fornecedores;
    document.getElementById('edit_validade_dias').value = plano.validade_dias;
    
    openModal('editarPlanoModal');
}
</script>

<?php include '../includes/admin_footer.php'; ?>