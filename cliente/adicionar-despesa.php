<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Adicionar Despesa ao Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$eventoId = get('evento');

if (!$eventoId) {
    Session::setFlash('error', 'Evento não especificado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar evento
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado');
    redirect('/cliente/meus-eventos.php');
}

// Buscar categorias
$stmt = $db->query("SELECT * FROM categorias_despesas ORDER BY nome ASC");
$categorias = $stmt->fetchAll();

$errors = [];

if (isPost()) {
    $categoriaId = post('categoria_id');
    $descricao = post('descricao');
    $fornecedor = post('fornecedor');
    $valor = post('valor');
    $dataVencimento = post('data_vencimento');
    $dataPagamento = post('data_pagamento');
    $statusPagamento = post('status_pagamento', 'pendente');
    $metodoPagamento = post('metodo_pagamento');
    $observacoes = post('observacoes');
    
    // Validações
    if (empty($categoriaId)) {
        $errors['categoria'] = 'Categoria é obrigatória';
    }
    
    if (empty($descricao)) {
        $errors['descricao'] = 'Descrição é obrigatória';
    }
    
    if (empty($valor) || $valor <= 0) {
        $errors['valor'] = 'Valor deve ser maior que zero';
    }
    
    if (empty($errors)) {
        try {
            // Upload de comprovante (se houver)
            $comprovante = null;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['comprovante'], 'comprovantes');
                if ($upload['success']) {
                    $comprovante = $upload['path'];
                }
            }
            
            // Inserir despesa
            $stmt = $db->prepare("
                INSERT INTO despesas_evento (
                    evento_id, categoria_id, descricao, fornecedor, valor,
                    data_vencimento, data_pagamento, status_pagamento,
                    metodo_pagamento, comprovante, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $eventoId,
                $categoriaId,
                $descricao,
                $fornecedor,
                $valor,
                $dataVencimento ?: null,
                $dataPagamento ?: null,
                $statusPagamento,
                $metodoPagamento,
                $comprovante,
                $observacoes
            ]);
            
            // Registrar log
            logAccess('cliente', $clienteId, 'adicionar_despesa', "Despesa adicionada: $descricao - " . formatMoney($valor));
            
            Session::setFlash('success', 'Despesa adicionada com sucesso!');
            redirect('/cliente/despesas.php?evento=' . $eventoId);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao adicionar despesa. Tente novamente.';
            error_log("Erro ao adicionar despesa: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Adicionar Despesa</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <a href="despesas.php?evento=<?php echo $eventoId; ?>">Despesas</a>
                <span class="breadcrumb-separator">/</span>
                <span>Adicionar</span>
            </div>
        </div>
    </div>

    <?php if (isset($errors['geral'])): ?>
    <div class="alert alert-danger">
        <div class="alert-icon">⚠️</div>
        <div class="alert-content">
            <p class="alert-message"><?php echo $errors['geral']; ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dados da Despesa</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" data-validate>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Categoria</label>
                            <select name="categoria_id" class="form-control <?php echo isset($errors['categoria']) ? 'error' : ''; ?>" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo post('categoria_id') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo $cat['icone']; ?> <?php echo $cat['nome']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['categoria'])): ?>
                                <span class="form-error"><?php echo $errors['categoria']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label-required">Descrição</label>
                            <input type="text" name="descricao" 
                                   class="form-control <?php echo isset($errors['descricao']) ? 'error' : ''; ?>" 
                                   placeholder="Ex: Contratação de fotógrafo"
                                   value="<?php echo post('descricao', ''); ?>" required>
                            <?php if (isset($errors['descricao'])): ?>
                                <span class="form-error"><?php echo $errors['descricao']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Fornecedor</label>
                                    <input type="text" name="fornecedor" class="form-control" 
                                           placeholder="Nome do fornecedor"
                                           value="<?php echo post('fornecedor', ''); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Valor</label>
                                    <input type="number" name="valor" 
                                           class="form-control <?php echo isset($errors['valor']) ? 'error' : ''; ?>" 
                                           placeholder="0.00" step="0.01" min="0.01"
                                           value="<?php echo post('valor', ''); ?>" required>
                                    <?php if (isset($errors['valor'])): ?>
                                        <span class="form-error"><?php echo $errors['valor']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Data de Vencimento</label>
                                    <input type="date" name="data_vencimento" class="form-control" 
                                           value="<?php echo post('data_vencimento', ''); ?>">
                                    <span class="form-help">Quando deve ser pago</span>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Status do Pagamento</label>
                                    <select name="status_pagamento" class="form-control">
                                        <option value="pendente" <?php echo post('status_pagamento') === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                                        <option value="pago" <?php echo post('status_pagamento') === 'pago' ? 'selected' : ''; ?>>Pago</option>
                                        <option value="atrasado" <?php echo post('status_pagamento') === 'atrasado' ? 'selected' : ''; ?>>Atrasado</option>
                                        <option value="cancelado" <?php echo post('status_pagamento') === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div id="pagamentoFields" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Data do Pagamento</label>
                                        <input type="date" name="data_pagamento" class="form-control" 
                                               value="<?php echo post('data_pagamento', ''); ?>">
                                    </div>
                                </div>

                                <div class="col-6">
                                    <div class="form-group">
                                        <label class="form-label">Método de Pagamento</label>
                                        <select name="metodo_pagamento" class="form-control">
                                            <option value="">Selecione...</option>
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="transferencia">Transferência</option>
                                            <option value="cartao">Cartão</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="outro">Outro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Comprovante</label>
                                <input type="file" name="comprovante" class="form-control" accept="image/*,application/pdf">
                                <span class="form-help">Formatos aceitos: JPG, PNG, PDF (máx. 5MB)</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3" 
                                      placeholder="Observações adicionais..."><?php echo post('observacoes', ''); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                ✓ Adicionar Despesa
                            </button>
                            <a href="despesas.php?evento=<?php echo $eventoId; ?>" class="btn btn-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Informações -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Evento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Nome</small>
                        <div><strong><?php echo Security::clean($evento['nome_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Data</small>
                        <div><strong><?php echo formatDate($evento['data_evento']); ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">💡 Dicas</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Seja detalhado</strong>
                            <small style="color: var(--gray-medium);">Descreva bem cada despesa</small>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Guarde comprovantes</strong>
                            <small style="color: var(--gray-medium);">Faça upload dos documentos</small>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Defina prazos</strong>
                            <small style="color: var(--gray-medium);">Cadastre a data de vencimento</small>
                        </li>
                        <li style="padding: 0.75rem 0;">
                            <strong style="display: block; margin-bottom: 0.25rem;">Atualize o status</strong>
                            <small style="color: var(--gray-medium);">Marque como pago quando efetuar</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar campos de pagamento quando status for "pago"
document.querySelector('select[name="status_pagamento"]').addEventListener('change', function() {
    const pagamentoFields = document.getElementById('pagamentoFields');
    if (this.value === 'pago') {
        pagamentoFields.style.display = 'block';
    } else {
        pagamentoFields.style.display = 'none';
    }
});

// Verificar no load
if (document.querySelector('select[name="status_pagamento"]').value === 'pago') {
    document.getElementById('pagamentoFields').style.display = 'block';
}
</script>

<?php include '../includes/cliente_footer.php'; ?>