<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Editar Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$eventoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Buscar planos disponíveis
$stmt = $db->query("SELECT * FROM planos WHERE status = 'ativo' ORDER BY preco_aoa ASC");
$planos = $stmt->fetchAll();

$errors = [];

if (isPost()) {
    $nomeEvento = post('nome_evento');
    $tipoEvento = post('tipo_evento');
    $descricao = post('descricao');
    $dataEvento = post('data_evento');
    $horaInicio = post('hora_inicio');
    $horaFim = post('hora_fim');
    $localNome = post('local_nome');
    $localEndereco = post('local_endereco');
    $localCidade = post('local_cidade');
    $numeroConvidados = post('numero_convidados_esperado');
    $observacoes = post('observacoes');
    
    // Validações
    if (empty($nomeEvento)) {
        $errors['nome'] = 'Nome do evento é obrigatório';
    }
    
    if (empty($tipoEvento)) {
        $errors['tipo'] = 'Tipo de evento é obrigatório';
    }
    
    if (empty($dataEvento)) {
        $errors['data'] = 'Data do evento é obrigatória';
    } else {
        // Verificar se a data não é no passado
        $dataEventoTimestamp = strtotime($dataEvento);
        if ($dataEventoTimestamp < strtotime('today')) {
            $errors['data'] = 'Data do evento não pode ser no passado';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                UPDATE eventos SET
                    nome_evento = ?,
                    tipo_evento = ?,
                    descricao = ?,
                    data_evento = ?,
                    hora_inicio = ?,
                    hora_fim = ?,
                    local_nome = ?,
                    local_endereco = ?,
                    local_cidade = ?,
                    numero_convidados_esperado = ?,
                    observacoes = ?,
                    atualizado_em = NOW()
                WHERE id = ? AND cliente_id = ?
            ");
            
            $stmt->execute([
                $nomeEvento,
                $tipoEvento,
                $descricao,
                $dataEvento,
                $horaInicio,
                $horaFim,
                $localNome,
                $localEndereco,
                $localCidade,
                $numeroConvidados ?: null,
                $observacoes,
                $eventoId,
                $clienteId
            ]);
            
            // Registrar log
            logAccess('cliente', $clienteId, 'editar_evento', "Evento editado: $nomeEvento (ID: $eventoId)");
            
            Session::setFlash('success', 'Evento atualizado com sucesso!');
            redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao atualizar evento. Tente novamente.';
            error_log("Erro ao editar evento: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Editar Evento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="meus-eventos.php">Meus Eventos</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Editar</span>
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
                    <h3 class="card-title">Dados do Evento</h3>
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        
                        <!-- Informações Básicas -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">📋 Informações Básicas</h4>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Nome do Evento</label>
                            <input type="text" name="nome_evento" 
                                   class="form-control <?php echo isset($errors['nome']) ? 'error' : ''; ?>" 
                                   placeholder="Ex: Casamento de João e Maria"
                                   value="<?php echo Security::clean($evento['nome_evento']); ?>" required>
                            <?php if (isset($errors['nome'])): ?>
                                <span class="form-error"><?php echo $errors['nome']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Tipo de Evento</label>
                                    <select name="tipo_evento" class="form-control <?php echo isset($errors['tipo']) ? 'error' : ''; ?>" required>
                                        <option value="">Selecione...</option>
                                        <option value="casamento" <?php echo $evento['tipo_evento'] === 'casamento' ? 'selected' : ''; ?>>💍 Casamento</option>
                                        <option value="aniversario" <?php echo $evento['tipo_evento'] === 'aniversario' ? 'selected' : ''; ?>>🎂 Aniversário</option>
                                        <option value="noivado" <?php echo $evento['tipo_evento'] === 'noivado' ? 'selected' : ''; ?>>💑 Noivado</option>
                                        <option value="corporativo" <?php echo $evento['tipo_evento'] === 'corporativo' ? 'selected' : ''; ?>>💼 Corporativo</option>
                                        <option value="batizado" <?php echo $evento['tipo_evento'] === 'batizado' ? 'selected' : ''; ?>>👶 Batizado</option>
                                        <option value="formatura" <?php echo $evento['tipo_evento'] === 'formatura' ? 'selected' : ''; ?>>🎓 Formatura</option>
                                        <option value="outro" <?php echo $evento['tipo_evento'] === 'outro' ? 'selected' : ''; ?>>📦 Outro</option>
                                    </select>
                                    <?php if (isset($errors['tipo'])): ?>
                                        <span class="form-error"><?php echo $errors['tipo']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Número de Convidados Esperado</label>
                                    <input type="number" name="numero_convidados_esperado" class="form-control" 
                                           placeholder="Ex: 150" min="1"
                                           value="<?php echo $evento['numero_convidados_esperado']; ?>">
                                    <span class="form-help">Estimativa de pessoas</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="3" 
                                      placeholder="Descreva seu evento..."><?php echo Security::clean($evento['descricao']); ?></textarea>
                        </div>

                        <hr style="margin: 2rem 0;">

                        <!-- Data e Horário -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">📅 Data e Horário</h4>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Data do Evento</label>
                                    <input type="date" name="data_evento" 
                                           class="form-control <?php echo isset($errors['data']) ? 'error' : ''; ?>" 
                                           value="<?php echo date('Y-m-d', strtotime($evento['data_evento'])); ?>" required>
                                    <?php if (isset($errors['data'])): ?>
                                        <span class="form-error"><?php echo $errors['data']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Hora de Início</label>
                                    <input type="time" name="hora_inicio" class="form-control" 
                                           value="<?php echo $evento['hora_inicio']; ?>">
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Hora de Término</label>
                                    <input type="time" name="hora_fim" class="form-control" 
                                           value="<?php echo $evento['hora_fim']; ?>">
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 2rem 0;">

                        <!-- Local -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">📍 Local do Evento</h4>

                        <div class="form-group">
                            <label class="form-label">Nome do Local</label>
                            <input type="text" name="local_nome" class="form-control" 
                                   placeholder="Ex: Salão de Festas Imperial"
                                   value="<?php echo Security::clean($evento['local_nome']); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Endereço</label>
                            <input type="text" name="local_endereco" class="form-control" 
                                   placeholder="Rua, número, bairro"
                                   value="<?php echo Security::clean($evento['local_endereco']); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="local_cidade" class="form-control" 
                                   placeholder="Ex: Luanda"
                                   value="<?php echo Security::clean($evento['local_cidade']); ?>">
                        </div>

                        <hr style="margin: 2rem 0;">

                        <!-- Observações -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">📝 Observações</h4>

                        <div class="form-group">
                            <label class="form-label">Observações Adicionais</label>
                            <textarea name="observacoes" class="form-control" rows="4" 
                                      placeholder="Informações importantes sobre o evento..."><?php echo Security::clean($evento['observacoes']); ?></textarea>
                        </div>

                        <!-- Botões -->
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                ✅ Salvar Alterações
                            </button>
                            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary btn-lg">
                                ❌ Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-4">
            <!-- Info do Evento -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📊 Informações do Evento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Código do Evento</small>
                        <strong style="font-size: 1.25rem; color: var(--primary-color);">
                            <?php echo $evento['codigo_evento']; ?>
                        </strong>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Plano Atual</small>
                        <div><strong><?php echo $evento['plano_nome']; ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Status</small>
                        <div><?php echo getStatusLabel($evento['status'], 'evento'); ?></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Pagamento</small>
                        <div>
                            <?php if ($evento['pago']): ?>
                                <span class="badge badge-success">✔ Pago</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pendente</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <small style="color: var(--gray-medium); display: block; margin-bottom: 0.25rem;">Criado em</small>
                        <div><?php echo formatDate($evento['criado_em']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Aviso -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">⚠️ Atenção</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0; font-size: 0.875rem;">
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Plano não pode ser alterado</strong>
                            <span style="color: var(--gray-medium);">O plano foi definido na criação</span>
                        </li>
                        <li style="padding: 0.75rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong style="display: block; margin-bottom: 0.25rem;">Data no passado</strong>
                            <span style="color: var(--gray-medium);">Não é permitido definir data anterior a hoje</span>
                        </li>
                        <li style="padding: 0.75rem 0;">
                            <strong style="display: block; margin-bottom: 0.25rem;">Convites não afetados</strong>
                            <span style="color: var(--gray-medium);">Os convites já criados não serão alterados</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/cliente_footer.php'; ?>