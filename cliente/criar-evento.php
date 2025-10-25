<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Criar Novo Evento
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$errors = [];
$success = '';

// Buscar planos disponíveis
$stmt = $db->query("SELECT * FROM planos WHERE status = 'ativo' ORDER BY preco_aoa ASC");
$planos = $stmt->fetchAll();

if (isPost()) {
    $nome = post('nome_evento');
    $tipo = post('tipo_evento');
    $data = post('data_evento');
    $horaInicio = post('hora_inicio');
    $horaFim = post('hora_fim');
    $localNome = post('local_nome');
    $localEndereco = post('local_endereco');
    $localCidade = post('local_cidade', 'Luanda');
    $numeroConvidados = post('numero_convidados_esperado');
    $planoId = post('plano_id');
    $observacoes = post('observacoes');
    
    // Validações
    if (empty($nome)) {
        $errors['nome'] = 'Nome do evento é obrigatório';
    }
    
    if (empty($tipo)) {
        $errors['tipo'] = 'Tipo do evento é obrigatório';
    }
    
    if (empty($data)) {
        $errors['data'] = 'Data do evento é obrigatória';
    } elseif (strtotime($data) < strtotime('today')) {
        $errors['data'] = 'Data do evento não pode ser no passado';
    }
    
    if (empty($horaInicio)) {
        $errors['hora_inicio'] = 'Hora de início é obrigatória';
    }
    
    if (empty($planoId)) {
        $errors['plano'] = 'Selecione um plano';
    }
    
    if (empty($errors)) {
        try {
            // Gerar código único para o evento
            $codigoEvento = 'EVT-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Inserir evento
            $stmt = $db->prepare("
                INSERT INTO eventos (
                    cliente_id, plano_id, nome_evento, tipo_evento, descricao,
                    data_evento, hora_inicio, hora_fim, local_nome, local_endereco,
                    local_cidade, numero_convidados_esperado, codigo_evento,
                    status, pago, observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', 0, ?)
            ");
            
            $dataEvento = $data . ' ' . ($horaInicio ?? '00:00:00');
            
            $stmt->execute([
                $clienteId,
                $planoId,
                $nome,
                $tipo,
                '', // descrição
                $dataEvento,
                $horaInicio,
                $horaFim,
                $localNome,
                $localEndereco,
                $localCidade,
                $numeroConvidados,
                $codigoEvento,
                $observacoes
            ]);
            
            $eventoId = $db->lastInsertId();
            
            // Buscar informações do plano
            $stmt = $db->prepare("SELECT * FROM planos WHERE id = ?");
            $stmt->execute([$planoId]);
            $plano = $stmt->fetch();
            
            // Criar registro de pagamento pendente
            $referencia = 'PAG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            $stmt = $db->prepare("
                INSERT INTO pagamentos (
                    cliente_id, evento_id, plano_id, referencia, valor,
                    moeda, metodo_pagamento, status, data_vencimento, ip_address
                ) VALUES (?, ?, ?, ?, ?, 'AOA', 'referencia', 'pendente', 
                         DATE_ADD(NOW(), INTERVAL 3 DAY), ?)
            ");
            
            $stmt->execute([
                $clienteId,
                $eventoId,
                $planoId,
                $referencia,
                $plano['preco_aoa'],
                Security::getClientIP()
            ]);
            
            // Registrar log
            logAccess('cliente', $clienteId, 'criar_evento', "Evento criado: $nome");
            
            // Criar notificação
            createNotification(
                'cliente',
                $clienteId,
                'Evento criado!',
                "Seu evento '$nome' foi criado. Efetue o pagamento para ativá-lo.",
                'success',
                'processar-pagamento.php?evento=' . $eventoId
            );
            
            Session::setFlash('success', 'Evento criado com sucesso! Agora efetue o pagamento para ativá-lo.');
            redirect('/cliente/processar-pagamento.php?evento=' . $eventoId);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao criar evento. Tente novamente.';
            error_log("Erro ao criar evento: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Criar Novo Evento</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <span>Criar Evento</span>
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
        <!-- Formulário -->
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informações do Evento</h3>
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Nome do Evento</label>
                            <input type="text" name="nome_evento" class="form-control <?php echo isset($errors['nome']) ? 'error' : ''; ?>" 
                                   placeholder="Ex: Casamento de João e Maria" 
                                   value="<?php echo post('nome_evento', ''); ?>" required>
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
                                        <option value="casamento" <?php echo post('tipo_evento') === 'casamento' ? 'selected' : ''; ?>>Casamento</option>
                                        <option value="aniversario" <?php echo post('tipo_evento') === 'aniversario' ? 'selected' : ''; ?>>Aniversário</option>
                                        <option value="noivado" <?php echo post('tipo_evento') === 'noivado' ? 'selected' : ''; ?>>Noivado</option>
                                        <option value="corporativo" <?php echo post('tipo_evento') === 'corporativo' ? 'selected' : ''; ?>>Corporativo</option>
                                        <option value="batizado" <?php echo post('tipo_evento') === 'batizado' ? 'selected' : ''; ?>>Batizado</option>
                                        <option value="formatura" <?php echo post('tipo_evento') === 'formatura' ? 'selected' : ''; ?>>Formatura</option>
                                        <option value="outro" <?php echo post('tipo_evento') === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                    <?php if (isset($errors['tipo'])): ?>
                                        <span class="form-error"><?php echo $errors['tipo']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Convidados Esperados</label>
                                    <input type="number" name="numero_convidados_esperado" class="form-control" 
                                           placeholder="Ex: 150" min="1"
                                           value="<?php echo post('numero_convidados_esperado', ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Data do Evento</label>
                                    <input type="date" name="data_evento" class="form-control <?php echo isset($errors['data']) ? 'error' : ''; ?>" 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo post('data_evento', ''); ?>" required>
                                    <?php if (isset($errors['data'])): ?>
                                        <span class="form-error"><?php echo $errors['data']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Hora de Início</label>
                                    <input type="time" name="hora_inicio" class="form-control <?php echo isset($errors['hora_inicio']) ? 'error' : ''; ?>" 
                                           value="<?php echo post('hora_inicio', ''); ?>" required>
                                    <?php if (isset($errors['hora_inicio'])): ?>
                                        <span class="form-error"><?php echo $errors['hora_inicio']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-4">
                                <div class="form-group">
                                    <label class="form-label">Hora de Término</label>
                                    <input type="time" name="hora_fim" class="form-control" 
                                           value="<?php echo post('hora_fim', ''); ?>">
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 2rem 0;">

                        <h4 style="margin-bottom: 1.5rem;">Local do Evento</h4>

                        <div class="form-group">
                            <label class="form-label">Nome do Local</label>
                            <input type="text" name="local_nome" class="form-control" 
                                   placeholder="Ex: Salão de Festas Premium"
                                   value="<?php echo post('local_nome', ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Endereço</label>
                            <input type="text" name="local_endereco" class="form-control" 
                                   placeholder="Ex: Rua Principal, 123, Talatona"
                                   value="<?php echo post('local_endereco', ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="local_cidade" class="form-control" 
                                   placeholder="Ex: Luanda"
                                   value="<?php echo post('local_cidade', 'Luanda'); ?>">
                        </div>

                        <hr style="margin: 2rem 0;">

                        <h4 style="margin-bottom: 1.5rem;">Escolha seu Plano</h4>

                        <div class="row">
                            <?php foreach ($planos as $plano): ?>
                            <div class="col-6">
                                <label class="plan-card <?php echo post('plano_id') == $plano['id'] ? 'active' : ''; ?>" style="cursor: pointer; display: block; border: 2px solid var(--gray-light); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 1rem; transition: var(--transition-fast);">
                                    <input type="radio" name="plano_id" value="<?php echo $plano['id']; ?>" 
                                           <?php echo post('plano_id') == $plano['id'] ? 'checked' : ''; ?>
                                           style="margin-right: 1rem;" required>
                                    <div>
                                        <h5 style="margin-bottom: 0.5rem;"><?php echo $plano['nome']; ?></h5>
                                        <p style="color: var(--gray-medium); font-size: 0.875rem; margin-bottom: 1rem;">
                                            <?php echo $plano['descricao']; ?>
                                        </p>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong style="font-size: 1.5rem; color: var(--primary-color);">
                                                    <?php echo formatMoney($plano['preco_aoa']); ?>
                                                </strong>
                                            </div>
                                            <div style="font-size: 0.813rem; color: var(--gray-medium);">
                                                Até <?php echo $plano['max_convites']; ?> convites<br>
                                                <?php echo $plano['validade_dias']; ?> dias de validade
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (isset($errors['plano'])): ?>
                            <span class="form-error"><?php echo $errors['plano']; ?></span>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="4" 
                                      placeholder="Informações adicionais sobre o evento..."><?php echo post('observacoes', ''); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Criar Evento e Prosseguir para Pagamento
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary btn-lg">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Informações -->
        <div class="col-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">ℹ️ Como Funciona</h3>
                </div>
                <div class="card-body">
                    <ol style="padding-left: 1.5rem; color: var(--gray-dark);">
                        <li style="margin-bottom: 1rem;">
                            <strong>Crie seu evento</strong> preenchendo as informações
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong>Escolha um plano</strong> que atenda suas necessidades
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong>Efetue o pagamento</strong> para ativar o evento
                        </li>
                        <li style="margin-bottom: 1rem;">
                            <strong>Adicione convidados</strong> e gerencie tudo
                        </li>
                        <li>
                            <strong>Use QR Code</strong> para check-in no dia do evento
                        </li>
                    </ol>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Incluso em Todos os Planos</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Gestão de convidados
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            QR Code para convites
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Check-in automático
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Gestão de fornecedores
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Controle de despesas
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Relatórios detalhados
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Exportação de dados
                        </li>
                        <li style="padding: 0.5rem 0; display: flex; align-items: center;">
                            <span style="color: var(--success-color); margin-right: 0.5rem;">✓</span>
                            Suporte online
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.plan-card:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-md);
}

.plan-card.active {
    border-color: var(--primary-color);
    background: rgba(108, 99, 255, 0.05);
}

.plan-card input[type="radio"] {
    accent-color: var(--primary-color);
}
</style>

<?php include '../includes/cliente_footer.php'; ?>