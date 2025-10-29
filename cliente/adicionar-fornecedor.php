<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Adicionar Fornecedor ao Evento
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

// Buscar evento e verificar limites
$stmt = $db->prepare("
    SELECT e.*, p.max_fornecedores,
           (SELECT COUNT(*) FROM fornecedores_evento WHERE evento_id = e.id) as total_fornecedores
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

if (!$evento['pago']) {
    Session::setFlash('error', 'Efetue o pagamento do evento para adicionar fornecedores');
    redirect('/cliente/processar-pagamento.php?evento=' . $eventoId);
}

// Verificar limite de fornecedores
if ($evento['total_fornecedores'] >= $evento['max_fornecedores']) {
    Session::setFlash('error', 'Limite de fornecedores atingido para este plano');
    redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
}

$errors = [];

if (isPost()) {
    $categoria = post('categoria');
    $nomeResponsavel = post('nome_responsavel');
    $email = post('email');
    $telefone = post('telefone');
    $empresa = post('empresa');
    $descricaoServico = post('descricao_servico');
    $valorContratado = post('valor_contratado');
    
    // Validações
    if (empty($categoria)) {
        $errors['categoria'] = 'Categoria é obrigatória';
    }
    
    if (empty($nomeResponsavel)) {
        $errors['nome'] = 'Nome do responsável é obrigatório';
    }
    
    if ($email && !Security::validateEmail($email)) {
        $errors['email'] = 'Email inválido';
    }
    
    if (empty($errors)) {
        try {
            // Gerar código de acesso único
            $codigoAcesso = 'FORN-' . strtoupper(substr(uniqid(), -6));
            
            // Gerar senha temporária
            $senhaTemp = substr(md5(uniqid()), 0, 8);
            $senhaHash = Security::hashPassword($senhaTemp);
            
            // Inserir fornecedor
            $stmt = $db->prepare("
                INSERT INTO fornecedores_evento (
                    evento_id, categoria, nome_responsavel, email, telefone,
                    senha, empresa, descricao_servico, valor_contratado, codigo_acesso, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            
            $stmt->execute([
                $eventoId,
                $categoria,
                $nomeResponsavel,
                $email,
                $telefone,
                $senhaHash,
                $empresa,
                $descricaoServico,
                $valorContratado ?: null,
                $codigoAcesso
            ]);
            
            $fornecedorId = $db->lastInsertId();
            
            // Registrar log
            logAccess('cliente', $clienteId, 'adicionar_fornecedor', "Fornecedor adicionado: $nomeResponsavel");
            
            // Se tiver email, enviar credenciais (implementar depois)
            if ($email) {
                // sendEmail($email, 'Acesso ao Evento', "Seu código: $codigoAcesso, Senha: $senhaTemp");
            }
            
            Session::setFlash('success', 'Fornecedor adicionado com sucesso!');
            redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao adicionar fornecedor. Tente novamente.';
            error_log("Erro ao adicionar fornecedor: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Adicionar Fornecedor</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Adicionar Fornecedor</span>
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
                    <h3 class="card-title">Dados do Fornecedor</h3>
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Categoria do Serviço</label>
                            <select name="categoria" class="form-control <?php echo isset($errors['categoria']) ? 'error' : ''; ?>" required>
                                <option value="">Selecione...</option>
                                <option value="DJ" <?php echo post('categoria') === 'DJ' ? 'selected' : ''; ?>>🎵 DJ / Música</option>
                                <option value="Fotografia" <?php echo post('categoria') === 'Fotografia' ? 'selected' : ''; ?>>📷 Fotografia</option>
                                <option value="Decoracao" <?php echo post('categoria') === 'Decoracao' ? 'selected' : ''; ?>>🎨 Decoração</option>
                                <option value="Catering" <?php echo post('categoria') === 'Catering' ? 'selected' : ''; ?>>🍽️ Catering/Buffet</option>
                                <option value="Seguranca" <?php echo post('categoria') === 'Seguranca' ? 'selected' : ''; ?>>🛡️ Segurança</option>
                                <option value="Transporte" <?php echo post('categoria') === 'Transporte' ? 'selected' : ''; ?>>🚗 Transporte</option>
                                <option value="Flores" <?php echo post('categoria') === 'Flores' ? 'selected' : ''; ?>>🌸 Flores</option>
                                <option value="Outro" <?php echo post('categoria') === 'Outro' ? 'selected' : ''; ?>>📦 Outro</option>
                            </select>
                            <?php if (isset($errors['categoria'])): ?>
                                <span class="form-error"><?php echo $errors['categoria']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label-required">Nome do Responsável</label>
                            <input type="text" name="nome_responsavel" 
                                   class="form-control <?php echo isset($errors['nome']) ? 'error' : ''; ?>" 
                                   placeholder="Nome completo do responsável"
                                   value="<?php echo post('nome_responsavel', ''); ?>" required>
                            <?php if (isset($errors['nome'])): ?>
                                <span class="form-error"><?php echo $errors['nome']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" 
                                           class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                                           placeholder="email@exemplo.com"
                                           value="<?php echo post('email', ''); ?>">
                                    <?php if (isset($errors['email'])): ?>
                                        <span class="form-error"><?php echo $errors['email']; ?></span>
                                    <?php else: ?>
                                        <span class="form-help">Credenciais de acesso serão enviadas por email</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone" class="form-control" 
                                           placeholder="+244 900 000 000"
                                           value="<?php echo post('telefone', ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Empresa/Nome Comercial</label>
                            <input type="text" name="empresa" class="form-control" 
                                   placeholder="Nome da empresa"
                                   value="<?php echo post('empresa', ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição do Serviço</label>
                            <textarea name="descricao_servico" class="form-control" rows="3" 
                                      placeholder="Descreva o serviço que será prestado..."><?php echo post('descricao_servico', ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Valor Contratado</label>
                            <input type="number" name="valor_contratado" class="form-control" 
                                   placeholder="0.00" step="0.01" min="0"
                                   value="<?php echo post('valor_contratado', ''); ?>">
                            <span class="form-help">Valor acordado para o serviço (opcional)</span>
                        </div>

                        <div class="alert alert-info">
                            <div class="alert-icon">ℹ️</div>
                            <div class="alert-content">
                                <p class="alert-message">
                                    <strong>Importante:</strong> O fornecedor receberá um código de acesso único para gerenciar sua equipe no evento.
                                </p>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary btn-lg">
                                ✓ Adicionar Fornecedor
                            </button>
                            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary btn-lg">
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
                    <h3 class="card-title">📊 Status do Evento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Evento</small>
                        <div><strong><?php echo Security::clean($evento['nome_evento']); ?></strong></div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Data</small>
                        <div><strong><?php echo formatDate($evento['data_evento']); ?></strong></div>
                    </div>

                    <hr style="margin: 1rem 0;">

                    <div style="margin-bottom: 1rem;">
                        <small style="color: var(--gray-medium);">Fornecedores Cadastrados</small>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong style="font-size: 1.5rem; color: var(--primary-color);">
                                <?php echo $evento['total_fornecedores']; ?>
                            </strong>
                            <span style="color: var(--gray-medium);">
                                de <?php echo $evento['max_fornecedores']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="progress">
                        <div class="progress-bar primary" 
                             style="width: <?php echo ($evento['total_fornecedores'] / $evento['max_fornecedores']) * 100; ?>%"></div>
                    </div>

                    <div style="margin-top: 1rem; text-align: center;">
                        <small style="color: var(--gray-medium);">
                            Restam <?php echo $evento['max_fornecedores'] - $evento['total_fornecedores']; ?> vagas
                        </small>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">💡 Tipos de Fornecedores</h3>
                </div>
                <div class="card-body">
                    <ul style="list-style: none; padding: 0;">
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>DJ/Música:</strong> Entretenimento musical
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Fotografia:</strong> Registro profissional
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Decoração:</strong> Ambientação do evento
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Catering:</strong> Serviço de comida e bebida
                        </li>
                        <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--gray-lighter);">
                            <strong>Segurança:</strong> Controle e proteção
                        </li>
                        <li style="padding: 0.5rem 0;">
                            <strong>Outros:</strong> Serviços complementares
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/cliente_footer.php'; ?>