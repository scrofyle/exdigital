<?php
/**
 * CLIENTE - ADICIONAR FORNECEDOR AO EVENTO
 * Sistema com senha temporária visível
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Obter ID do evento
$eventoId = get('evento');
if (!$eventoId) {
    Session::setFlash('error', 'Evento não encontrado!');
    redirect('/cliente/meus-eventos.php');
}

// Buscar detalhes do evento
$stmt = $db->prepare("
    SELECT e.*, p.nome as plano_nome, p.max_fornecedores
    FROM eventos e
    JOIN planos p ON e.plano_id = p.id
    WHERE e.id = ? AND e.cliente_id = ?
");
$stmt->execute([$eventoId, $clienteId]);
$evento = $stmt->fetch();

if (!$evento) {
    Session::setFlash('error', 'Evento não encontrado!');
    redirect('/cliente/meus-eventos.php');
}

// Verificar limite de fornecedores
$stmt = $db->prepare("SELECT COUNT(*) FROM fornecedores_evento WHERE evento_id = ?");
$stmt->execute([$eventoId]);
$totalFornecedores = $stmt->fetchColumn();

if ($totalFornecedores >= $evento['max_fornecedores']) {
    Session::setFlash('error', 'Limite de fornecedores atingido para este plano!');
    redirect('/cliente/evento-detalhes.php?id=' . $eventoId);
}

$success = '';
$error = '';
$fornecedorCriado = null;

// Processar cadastro
if (isPost()) {
    $validator = new Validator();
    
    $rules = [
        'categoria' => 'required',
        'nome_responsavel' => 'required|min:3',
        'telefone' => 'required'
    ];
    
    if ($validator->validate($_POST, $rules)) {
        try {
            // Gerar código único
            $codigoAcesso = 'FOR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            
            // Gerar senha temporária FORTE e ÚNICA
            $senhaTemp = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 3) . 
                         substr(str_shuffle('23456789'), 0, 3) . 
                         substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 2);
            
            $senhaHash = Security::hashPassword($senhaTemp);
            
            // Inserir fornecedor
            $stmt = $db->prepare("
                INSERT INTO fornecedores_evento 
                (evento_id, categoria, nome_responsavel, email, telefone, senha, 
                 empresa, descricao_servico, valor_contratado, codigo_acesso, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            
            $stmt->execute([
                $eventoId,
                post('categoria'),
                post('nome_responsavel'),
                post('email'),
                post('telefone'),
                $senhaHash,
                post('empresa'),
                post('descricao_servico'),
                post('valor_contratado') ?: 0,
                $codigoAcesso
            ]);
            
            $fornecedorId = $db->lastInsertId();
            
            // Buscar fornecedor criado
            $stmt = $db->prepare("SELECT * FROM fornecedores_evento WHERE id = ?");
            $stmt->execute([$fornecedorId]);
            $fornecedorCriado = $stmt->fetch();
            $fornecedorCriado['senha_temporaria'] = $senhaTemp; // Guardar senha para exibir
            
            // Criar notificação
            createNotification(
                'cliente',
                $clienteId,
                'Fornecedor Adicionado',
                'Fornecedor ' . post('nome_responsavel') . ' foi adicionado ao evento.',
                'success',
                '/cliente/evento-detalhes.php?id=' . $eventoId
            );
            
            // Log
            logAccess('cliente', $clienteId, 'fornecedor_add', 
                     'Adicionou fornecedor: ' . post('nome_responsavel'));
            
            $success = 'Fornecedor adicionado com sucesso!';
            
        } catch (PDOException $e) {
            $error = 'Erro ao adicionar fornecedor: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor, preencha todos os campos obrigatórios!';
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">➕ Adicionar Fornecedor</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>">Evento</a>
            <span class="breadcrumb-separator">/</span>
            <span>Adicionar Fornecedor</span>
        </div>
    </div>

    <?php if ($success && $fornecedorCriado): ?>
    <div class="alert alert-success">
        <div class="alert-icon">✅</div>
        <div class="alert-content">
            <strong>Fornecedor cadastrado com sucesso!</strong>
            <p class="alert-message">Compartilhe as informações de acesso abaixo com o fornecedor.</p>
        </div>
    </div>

    <!-- Card com Dados de Acesso -->
    <div class="card mb-4" style="border: 3px solid var(--success-color);">
        <div class="card-header" style="background: var(--success-color); color: white;">
            <h3 class="card-title" style="color: white; margin: 0;">
                🔑 Dados de Acesso do Fornecedor
            </h3>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <strong>IMPORTANTE:</strong> Anote ou compartilhe estas informações AGORA! 
                    Elas não serão exibidas novamente por segurança.
                </div>
            </div>

            <div class="row">
                <div class="col-6">
                    <div style="background: #F8F9FA; padding: 2rem; border-radius: var(--border-radius); text-align: center;">
                        <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                            <i class="bi bi-key"></i> CÓDIGO DE ACESSO
                        </label>
                        <div id="codigo-acesso" style="font-size: 2rem; font-weight: 700; color: var(--primary-color); font-family: monospace; letter-spacing: 2px;">
                            <?php echo Security::clean($fornecedorCriado['codigo_acesso']); ?>
                        </div>
                        <button onclick="copiarTexto('codigo-acesso')" class="btn btn-sm btn-outline mt-2">
                            <i class="bi bi-clipboard"></i> Copiar Código
                        </button>
                    </div>
                </div>

                <div class="col-6">
                    <div style="background: #F8F9FA; padding: 2rem; border-radius: var(--border-radius); text-align: center;">
                        <label style="font-size: 0.875rem; color: var(--gray-medium); display: block; margin-bottom: 0.5rem;">
                            <i class="bi bi-lock"></i> SENHA TEMPORÁRIA
                        </label>
                        <div id="senha-temp" style="font-size: 2rem; font-weight: 700; color: var(--danger-color); font-family: monospace; letter-spacing: 2px;">
                            <?php echo Security::clean($fornecedorCriado['senha_temporaria']); ?>
                        </div>
                        <button onclick="copiarTexto('senha-temp')" class="btn btn-sm btn-outline mt-2">
                            <i class="bi bi-clipboard"></i> Copiar Senha
                        </button>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Informações do Fornecedor -->
            <div class="row">
                <div class="col-6">
                    <h5 class="mb-3">👤 Dados do Fornecedor</h5>
                    <table style="width: 100%; font-size: 0.938rem;">
                        <tr style="border-bottom: 1px solid var(--gray-lighter);">
                            <td style="padding: 0.75rem 0; color: var(--gray-medium);">Nome:</td>
                            <td style="padding: 0.75rem 0;"><strong><?php echo Security::clean($fornecedorCriado['nome_responsavel']); ?></strong></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--gray-lighter);">
                            <td style="padding: 0.75rem 0; color: var(--gray-medium);">Categoria:</td>
                            <td style="padding: 0.75rem 0;"><strong><?php echo Security::clean($fornecedorCriado['categoria']); ?></strong></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--gray-lighter);">
                            <td style="padding: 0.75rem 0; color: var(--gray-medium);">Telefone:</td>
                            <td style="padding: 0.75rem 0;"><strong><?php echo Security::clean($fornecedorCriado['telefone']); ?></strong></td>
                        </tr>
                        <?php if ($fornecedorCriado['email']): ?>
                        <tr>
                            <td style="padding: 0.75rem 0; color: var(--gray-medium);">Email:</td>
                            <td style="padding: 0.75rem 0;"><strong><?php echo Security::clean($fornecedorCriado['email']); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="col-6">
                    <h5 class="mb-3">📝 Instruções para o Fornecedor</h5>
                    <ol style="font-size: 0.938rem; line-height: 1.8;">
                        <li>Acesse: <strong><?php echo SITE_URL; ?>/fornecedor/login.php</strong></li>
                        <li>Use o <strong>Código de Acesso</strong> e <strong>Senha Temporária</strong></li>
                        <li>Após o primeiro login, <strong>altere a senha</strong></li>
                        <li>Cadastre sua equipe antes do evento</li>
                    </ol>
                </div>
            </div>

            <hr class="my-4">

            <!-- Botões de Ação -->
            <div class="text-center">
                <button onclick="imprimirCredenciais()" class="btn btn-primary">
                    <i class="bi bi-printer"></i> Imprimir Credenciais
                </button>
                
                <button onclick="enviarPorWhatsApp()" class="btn btn-success">
                    <i class="bi bi-whatsapp"></i> Enviar por WhatsApp
                </button>
                
                <button onclick="copiarTudo()" class="btn btn-info">
                    <i class="bi bi-clipboard"></i> Copiar Tudo
                </button>
                
                <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar ao Evento
                </a>
            </div>
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

    <?php if (!$fornecedorCriado): ?>
    <!-- Formulário de Cadastro -->
    <div class="row">
        <div class="col-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Dados do Fornecedor</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="alert alert-info">
                            <div class="alert-icon">ℹ️</div>
                            <div class="alert-content">
                                <strong>Limite do Plano:</strong> Você pode adicionar até 
                                <strong><?php echo $evento['max_fornecedores']; ?></strong> fornecedores. 
                                Já foram cadastrados <strong><?php echo $totalFornecedores; ?></strong>.
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label-required">Categoria do Serviço</label>
                            <select name="categoria" class="form-control" required>
                                <option value="">Selecione...</option>
                                <option value="Segurança">🛡️ Segurança</option>
                                <option value="Fotografia">📷 Fotografia</option>
                                <option value="DJ/Música">🎵 DJ/Música</option>
                                <option value="Decoração">🎨 Decoração</option>
                                <option value="Catering/Buffet">🍽️ Catering/Buffet</option>
                                <option value="Bolos e Doces">🎂 Bolos e Doces</option>
                                <option value="Transporte">🚗 Transporte</option>
                                <option value="Animação">🎭 Animação</option>
                                <option value="Limpeza">🧹 Limpeza</option>
                                <option value="Outro">📦 Outro</option>
                            </select>
                            <small class="form-help">
                                <strong>Nota:</strong> Segurança terá acesso ao sistema de check-in de convidados
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Nome do Responsável</label>
                                    <input type="text" name="nome_responsavel" class="form-control" 
                                           value="<?php echo post('nome_responsavel'); ?>" required>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Empresa</label>
                                    <input type="text" name="empresa" class="form-control" 
                                           value="<?php echo post('empresa'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label form-label-required">Telefone</label>
                                    <input type="tel" name="telefone" class="form-control" 
                                           value="<?php echo post('telefone'); ?>" 
                                           placeholder="+244 900 000 000" required>
                                </div>
                            </div>

                            <div class="col-6">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo post('email'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Descrição do Serviço</label>
                            <textarea name="descricao_servico" class="form-control" rows="3"><?php echo post('descricao_servico'); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Valor Contratado (opcional)</label>
                            <input type="number" name="valor_contratado" class="form-control" 
                                   value="<?php echo post('valor_contratado'); ?>" 
                                   step="0.01" min="0" placeholder="0.00">
                            <small class="form-help">Valor acordado para o serviço</small>
                        </div>

                        <div class="text-right">
                            <a href="evento-detalhes.php?id=<?php echo $eventoId; ?>" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Cadastrar Fornecedor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Informativa -->
        <div class="col-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📋 Sobre o Evento</h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 1rem;">
                        <small class="text-muted">Nome do Evento:</small>
                        <div><strong><?php echo Security::clean($evento['nome_evento']); ?></strong></div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <small class="text-muted">Data:</small>
                        <div><strong><?php echo formatDate($evento['data_evento']); ?></strong></div>
                    </div>
                    <div>
                        <small class="text-muted">Plano:</small>
                        <div><strong><?php echo Security::clean($evento['plano_nome']); ?></strong></div>
                    </div>
                    
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header" style="background: #FEF3C7;">
                    <h3 class="card-title" style="color: #92400E; margin: 0;">💡 Dicas</h3>
                </div>
                <div class="card-body">
                    <ul style="margin: 0; padding-left: 1.5rem; font-size: 0.875rem;">
                        <li style="margin-bottom: 0.5rem;">O fornecedor receberá código de acesso único</li>
                        <li style="margin-bottom: 0.5rem;">Senha temporária será gerada automaticamente</li>
                        <li style="margin-bottom: 0.5rem;">Fornecedor deve alterar senha no primeiro acesso</li>
                        <li>Segurança terá acesso especial ao check-in</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function copiarTexto(elementId) {
    const texto = document.getElementById(elementId).textContent;
    const textarea = document.createElement('textarea');
    textarea.value = texto;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Copiado: ' + texto, 'success');
    } catch (err) {
        showNotification('Erro ao copiar', 'error');
    }
    
    document.body.removeChild(textarea);
}

function copiarTudo() {
    const codigo = document.getElementById('codigo-acesso').textContent;
    const senha = document.getElementById('senha-temp').textContent;
    
    const texto = `🔑 DADOS DE ACESSO - <?php echo SITE_NAME; ?>

Código de Acesso: ${codigo}
Senha Temporária: ${senha}

Link: <?php echo SITE_URL; ?>/fornecedor/login.php

⚠️ IMPORTANTE: Altere sua senha após o primeiro acesso!`;
    
    const textarea = document.createElement('textarea');
    textarea.value = texto;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Todas as informações copiadas!', 'success');
    } catch (err) {
        showNotification('Erro ao copiar', 'error');
    }
    
    document.body.removeChild(textarea);
}

function enviarPorWhatsApp() {
    const codigo = document.getElementById('codigo-acesso').textContent;
    const senha = document.getElementById('senha-temp').textContent;
    const telefone = '<?php echo $fornecedorCriado['telefone'] ?? ''; ?>';
    
    const mensagem = encodeURIComponent(
        `🔑 *DADOS DE ACESSO - <?php echo SITE_NAME; ?>*\n\n` +
        `Código de Acesso: *${codigo}*\n` +
        `Senha Temporária: *${senha}*\n\n` +
        `Link: <?php echo SITE_URL; ?>/fornecedor/login.php\n\n` +
        `⚠️ IMPORTANTE: Altere sua senha após o primeiro acesso!`
    );
    
    // Limpar telefone (remover caracteres não numéricos)
    const tel = telefone.replace(/\D/g, '');
    
    window.open(`https://wa.me/${tel}?text=${mensagem}`, '_blank');
}

function imprimirCredenciais() {
    window.print();
}
</script>

<style>
@media print {
    .sidebar, .header, .page-breadcrumb, .btn, .alert-warning {
        display: none !important;
    }
    
    .card {
        border: 2px solid #000 !important;
        page-break-inside: avoid;
    }
}
</style>

<?php include '../includes/cliente_footer.php'; ?>