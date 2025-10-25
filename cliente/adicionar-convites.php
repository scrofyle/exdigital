<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Adicionar Convite
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
    SELECT e.*, p.max_convites,
           (SELECT COUNT(*) FROM convites WHERE evento_id = e.id) as total_convites
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
    Session::setFlash('error', 'Efetue o pagamento do evento para adicionar convites');
    redirect('/cliente/processar-pagamento.php?evento=' . $eventoId);
}

// Verificar limite de convites
if ($evento['total_convites'] >= $evento['max_convites']) {
    Session::setFlash('error', 'Limite de convites atingido para este plano');
    redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
}

$errors = [];

if (isPost()) {
    $nomeConvidado1 = post('nome_convidado1');
    $telefone1 = post('telefone1');
    $email1 = post('email1');
    $nomeConvidado2 = post('nome_convidado2');
    $telefone2 = post('telefone2');
    $email2 = post('email2');
    $tipoConvidado = post('tipo_convidado', 'normal');
    $mesaNumero = post('mesa_numero');
    $observacoes = post('observacoes');
    
    // Validações
    if (empty($nomeConvidado1)) {
        $errors['nome1'] = 'Nome do primeiro convidado é obrigatório';
    }
    
    if ($email1 && !Security::validateEmail($email1)) {
        $errors['email1'] = 'Email inválido';
    }
    
    if ($email2 && !Security::validateEmail($email2)) {
        $errors['email2'] = 'Email inválido';
    }
    
    if (empty($errors)) {
        try {
            // Gerar código único
            $codigoConvite = 'CNV-' . strtoupper(substr(uniqid(), -7));
            
            // Inserir convite
            $stmt = $db->prepare("
                INSERT INTO convites (
                    evento_id, codigo_convite, nome_convidado1, telefone1, email1,
                    nome_convidado2, telefone2, email2, tipo_convidado, mesa_numero,
                    observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $eventoId,
                $codigoConvite,
                $nomeConvidado1,
                $telefone1,
                $email1,
                $nomeConvidado2,
                $telefone2,
                $email2,
                $tipoConvidado,
                $mesaNumero,
                $observacoes
            ]);
            
            // Registrar log
            logAccess('cliente', $clienteId, 'adicionar_convite', "Convite adicionado: $nomeConvidado1");
            
            Session::setFlash('success', 'Convite adicionado com sucesso!');
            redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
            
        } catch (PDOException $e) {
            $errors['geral'] = 'Erro ao adicionar convite. Tente novamente.';
            error_log("Erro ao adicionar convite: " . $e->getMessage());
        }
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Adicionar Convite</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">
                    <?php echo truncate($evento['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Adicionar Convite</span>
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
                    <h3 class="card-title">Dados dos Convidados</h3>
                </div>
                <div class="card-body">
                    <form method="POST" data-validate>
                        
                        <!-- Convidado 1 -->
                        <h4 style="margin-bottom: 1.5rem; color: var(--primary-color);">👤 Convidado Principal</h4>
                        
                        <div class="form-group">
                            <label class="form-label form-label-required">Nome Completo</label>
                            <input type="text" name="nome_convidado1" 
                                   class="form-control <?php echo isset($errors['nome1']) ? 'error' : ''; ?>" 
                                   placeholder="Ex: João Silva"
                                   value="<?php echo post('nome_convidado1', ''); ?>" required>
                            <?php if (isset($errors['nome1'])): ?>
                                <span class="form-error"><?php echo $errors['nome1']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone1" class="form-control" 
                                           placeholder="+244 900 000 000"
                                           value="<?php echo post('telefone1', ''); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email1" 
                                           class="form-control <?php echo isset($errors['email1']) ? 'error' : ''; ?>" 
                                           placeholder="joao@email.com"
                                           value="<?php echo post('email1', ''); ?>">
                                    <?php if (isset($errors['email1'])): ?>
                                        <span class="form-error"><?php echo $errors['email1']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 2rem 0;">

                        <!-- Convidado 2 (Opcional) -->
                        <h4 style="margin-bottom: 1rem; color: var(--gray-dark);">👥 Segundo Convidado <span style="font-size: 0.875rem; font-weight: normal; color: var(--gray-medium);">(Opcional - Ex: Acompanhante)</span></h4>
                        
                        <div class="form-group">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="nome_convidado2" class="form-control" 
                                   placeholder="Ex: Maria Silva"
                                   value="<?php echo post('nome_convidado2', ''); ?>">
                            <span class="form-help">Deixe em branco se for apenas 1 pessoa</span>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" name="telefone2" class="form-control" 
                                           placeholder="+244 900 000 000"
                                           value="<?php echo post('telefone2', ''); ?>">
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email2" 
                                           class="form-control <?php echo isset($errors['email2']) ? 'error' : ''; ?>" 
                                           placeholder="maria@email.com"
                                           value="<?php echo post('email2', ''); ?>">
                                    <?php if (isset($errors['email2'])): ?>
                                        <span class="form-error"><?php echo $errors['email2']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 2rem 0;">