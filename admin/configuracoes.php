<?php
/**
 * ADMIN - CONFIGURAÇÕES DO SISTEMA
 * Gerenciamento completo de configurações
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
    Session::setFlash('error', 'Acesso negado. Apenas Super Administradores podem acessar esta área.');
    redirect('/admin/dashboard.php');
}

$success = '';
$error = '';

// Processar atualização de configurações
if (isPost()) {
    try {
        $db->beginTransaction();
        
        // Configurações Gerais
        if (isset($_POST['config_geral'])) {
            $configs = [
                'site_nome' => post('site_nome'),
                'site_email' => post('site_email'),
                'site_telefone' => post('site_telefone'),
                'moeda_padrao' => post('moeda_padrao'),
                'dias_expiracao_pagamento' => post('dias_expiracao_pagamento')
            ];
            
            foreach ($configs as $chave => $valor) {
                $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            }
            
            $success = 'Configurações gerais atualizadas com sucesso!';
        }
        
        // Configurações de Pagamento
        if (isset($_POST['config_pagamento'])) {
            $configs = [
                'taxa_express' => post('taxa_express'),
                'taxa_referencia' => post('taxa_referencia'),
                'multicaixa_entity' => post('multicaixa_entity'),
                'express_api_key' => post('express_api_key'),
                'paypal_client_id' => post('paypal_client_id'),
                'paypal_secret' => post('paypal_secret')
            ];
            
            foreach ($configs as $chave => $valor) {
                $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            }
            
            $success = 'Configurações de pagamento atualizadas com sucesso!';
        }
        
        // Configurações de Email
        if (isset($_POST['config_email'])) {
            $configs = [
                'email_smtp_host' => post('email_smtp_host'),
                'email_smtp_port' => post('email_smtp_port'),
                'email_smtp_user' => post('email_smtp_user'),
                'email_smtp_pass' => post('email_smtp_pass')
            ];
            
            foreach ($configs as $chave => $valor) {
                $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = ?");
                $stmt->execute([$valor, $chave]);
            }
            
            $success = 'Configurações de email atualizadas com sucesso!';
        }
        
        // Modo Manutenção
        if (isset($_POST['toggle_manutencao'])) {
            $manutencao = post('manutencao_ativo') === '1' ? '1' : '0';
            $stmt = $db->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'manutencao_ativo'");
            $stmt->execute([$manutencao]);
            
            $success = $manutencao == '1' ? 'Modo manutenção ATIVADO!' : 'Modo manutenção DESATIVADO!';
        }
        
        // Testar Email
        if (isset($_POST['test_email'])) {
            $emailTeste = post('email_teste');
            if (Security::validateEmail($emailTeste)) {
                $subject = 'Teste de Email - ' . SITE_NAME;
                $body = '<h2>Teste de Configuração SMTP</h2>';
                $body .= '<p>Se você recebeu este email, suas configurações SMTP estão funcionando corretamente!</p>';
                $body .= '<p><strong>Data/Hora:</strong> ' . date('d/m/Y H:i:s') . '</p>';
                
                if (sendEmail($emailTeste, $subject, $body)) {
                    $success = 'Email de teste enviado com sucesso para ' . $emailTeste;
                } else {
                    $error = 'Erro ao enviar email. Verifique as configurações SMTP.';
                }
            } else {
                $error = 'Email inválido!';
            }
        }
        
        // Limpar Cache
        if (isset($_POST['clear_cache'])) {
            // Limpar logs antigos
            $stmt = $db->prepare("DELETE FROM logs_acesso WHERE criado_em < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            
            $success = 'Cache limpo com sucesso!';
        }
        
        // Backup Database
        if (isset($_POST['backup_db'])) {
            $backupFile = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backupPath = __DIR__ . '/../exports/' . $backupFile;
            
            // Comando mysqldump (ajustar conforme servidor)
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                DB_USER,
                DB_PASS,
                DB_HOST,
                DB_NAME,
                $backupPath
            );
            
            system($command, $output);
            
            if ($output === 0) {
                $success = 'Backup criado: ' . $backupFile;
            } else {
                $error = 'Erro ao criar backup. Verifique permissões.';
            }
        }
        
        $db->commit();
        
        if ($success) {
            Session::setFlash('success', $success);
            logAccess('admin', $userId, 'configuracoes_atualizadas', $success);
        }
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'Erro ao atualizar configurações: ' . $e->getMessage();
        Session::setFlash('error', $error);
    }
}

// Buscar configurações atuais
$stmt = $db->query("SELECT * FROM configuracoes ORDER BY chave");
$configuracoes = $stmt->fetchAll();

$config = [];
foreach ($configuracoes as $c) {
    $config[$c['chave']] = $c['valor'];
}

// Estatísticas do Sistema
$stmt = $db->query("SELECT 
    (SELECT COUNT(*) FROM clientes) as total_clientes,
    (SELECT COUNT(*) FROM eventos) as total_eventos,
    (SELECT COUNT(*) FROM pagamentos WHERE status = 'aprovado') as pagamentos_aprovados,
    (SELECT SUM(valor) FROM pagamentos WHERE status = 'aprovado') as receita_total,
    (SELECT COUNT(*) FROM logs_acesso WHERE criado_em >= CURDATE()) as acessos_hoje
");
$stats = $stmt->fetch();

include '../includes/admin_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            <h1>⚙️ Configurações do Sistema</h1>
        </div>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Configurações</span>
        </div>
    </div>

    <?php if (Session::getFlash('success')): ?>
        <div class="alert alert-success">
            <div class="alert-icon">✅</div>
            <div class="alert-content">
                <div class="alert-title">Sucesso!</div>
                <p class="alert-message"><?php echo Session::getFlash('success'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (Session::getFlash('error')): ?>
        <div class="alert alert-danger">
            <div class="alert-icon">❌</div>
            <div class="alert-content">
                <div class="alert-title">Erro!</div>
                <p class="alert-message"><?php echo Session::getFlash('error'); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cards de Estatísticas -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Clientes</div>
                <div class="stat-value"><?php echo number_format($stats['total_clientes']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total de Eventos</div>
                <div class="stat-value"><?php echo number_format($stats['total_eventos']); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Receita Total</div>
                <div class="stat-value"><?php echo formatMoney($stats['receita_total'] ?? 0); ?></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Acessos Hoje</div>
                <div class="stat-value"><?php echo number_format($stats['acessos_hoje']); ?></div>
            </div>
        </div>
    </div>

    <!-- Tabs de Configurações -->
    <div class="card">
        <div class="card-body">
            <div class="tabs">
                <div class="tab-item active" data-target="tab-geral">
                    <i class="bi bi-gear"></i> Gerais
                </div>
                <div class="tab-item" data-target="tab-pagamento">
                    <i class="bi bi-credit-card"></i> Pagamentos
                </div>
                <div class="tab-item" data-target="tab-email">
                    <i class="bi bi-envelope"></i> Email/SMTP
                </div>
                <div class="tab-item" data-target="tab-sistema">
                    <i class="bi bi-shield-check"></i> Sistema
                </div>
                <div class="tab-item" data-target="tab-backup">
                    <i class="bi bi-database"></i> Backup
                </div>
            </div>

            <!-- Tab: Configurações Gerais -->
            <div id="tab-geral" class="tab-content active">
                <form method="POST" class="mt-4">
                    <input type="hidden" name="config_geral" value="1">
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label form-label-required">Nome do Site</label>
                                <input type="text" name="site_nome" class="form-control" 
                                       value="<?php echo Security::clean($config['site_nome'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label form-label-required">Email do Site</label>
                                <input type="email" name="site_email" class="form-control" 
                                       value="<?php echo Security::clean($config['site_email'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Telefone de Contato</label>
                                <input type="text" name="site_telefone" class="form-control" 
                                       value="<?php echo Security::clean($config['site_telefone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label form-label-required">Moeda Padrão</label>
                                <select name="moeda_padrao" class="form-control" required>
                                    <option value="AOA" <?php echo ($config['moeda_padrao'] ?? '') === 'AOA' ? 'selected' : ''; ?>>AOA - Kwanza Angolano</option>
                                    <option value="USD" <?php echo ($config['moeda_padrao'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - Dólar Americano</option>
                                    <option value="EUR" <?php echo ($config['moeda_padrao'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label-required">Dias para Expiração de Pagamento</label>
                        <input type="number" name="dias_expiracao_pagamento" class="form-control" 
                               value="<?php echo Security::clean($config['dias_expiracao_pagamento'] ?? '3'); ?>" 
                               min="1" max="30" required>
                        <small class="form-help">Número de dias para pagamentos pendentes expirarem</small>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Salvar Configurações Gerais
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab: Pagamentos -->
            <div id="tab-pagamento" class="tab-content">
                <form method="POST" class="mt-4">
                    <input type="hidden" name="config_pagamento" value="1">
                    
                    <h5 class="mb-3">💳 Taxas de Pagamento</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Taxa Express (%)</label>
                                <input type="number" step="0.01" name="taxa_express" class="form-control" 
                                       value="<?php echo Security::clean($config['taxa_express'] ?? '2.5'); ?>">
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Taxa Referência (%)</label>
                                <input type="number" step="0.01" name="taxa_referencia" class="form-control" 
                                       value="<?php echo Security::clean($config['taxa_referencia'] ?? '1.5'); ?>">
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">🏦 Multicaixa Express</h5>
                    <div class="form-group">
                        <label class="form-label">Entidade Multicaixa</label>
                        <input type="text" name="multicaixa_entity" class="form-control" 
                               value="<?php echo Security::clean($config['multicaixa_entity'] ?? ''); ?>" 
                               placeholder="Ex: 11223">
                    </div>

                    <div class="form-group">
                        <label class="form-label">API Key Express</label>
                        <input type="password" name="express_api_key" class="form-control" 
                               value="<?php echo Security::clean($config['express_api_key'] ?? ''); ?>" 
                               placeholder="Sua chave API Express">
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">💰 PayPal</h5>
                    <div class="form-group">
                        <label class="form-label">PayPal Client ID</label>
                        <input type="text" name="paypal_client_id" class="form-control" 
                               value="<?php echo Security::clean($config['paypal_client_id'] ?? ''); ?>" 
                               placeholder="Client ID do PayPal">
                    </div>

                    <div class="form-group">
                        <label class="form-label">PayPal Secret</label>
                        <input type="password" name="paypal_secret" class="form-control" 
                               value="<?php echo Security::clean($config['paypal_secret'] ?? ''); ?>" 
                               placeholder="Secret do PayPal">
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Salvar Configurações de Pagamento
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab: Email/SMTP -->
            <div id="tab-email" class="tab-content">
                <form method="POST" class="mt-4">
                    <input type="hidden" name="config_email" value="1">
                    
                    <div class="alert alert-info">
                        <div class="alert-icon">ℹ️</div>
                        <div class="alert-content">
                            <strong>Importante:</strong> Configure seu servidor SMTP para envio de emails automáticos.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="form-group">
                                <label class="form-label">Servidor SMTP</label>
                                <input type="text" name="email_smtp_host" class="form-control" 
                                       value="<?php echo Security::clean($config['email_smtp_host'] ?? ''); ?>" 
                                       placeholder="smtp.gmail.com">
                            </div>
                        </div>

                        <div class="col-4">
                            <div class="form-group">
                                <label class="form-label">Porta SMTP</label>
                                <input type="number" name="email_smtp_port" class="form-control" 
                                       value="<?php echo Security::clean($config['email_smtp_port'] ?? '587'); ?>" 
                                       placeholder="587">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Usuário SMTP</label>
                        <input type="email" name="email_smtp_user" class="form-control" 
                               value="<?php echo Security::clean($config['email_smtp_user'] ?? ''); ?>" 
                               placeholder="seu-email@gmail.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Senha SMTP</label>
                        <input type="password" name="email_smtp_pass" class="form-control" 
                               value="<?php echo Security::clean($config['email_smtp_pass'] ?? ''); ?>" 
                               placeholder="Sua senha ou App Password">
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Salvar Configurações de Email
                        </button>
                    </div>
                </form>

                <hr class="my-4">

                <h5 class="mb-3">📧 Testar Email</h5>
                <form method="POST">
                    <input type="hidden" name="test_email" value="1">
                    <div class="row">
                        <div class="col-9">
                            <input type="email" name="email_teste" class="form-control" 
                                   placeholder="Digite um email para teste" required>
                        </div>
                        <div class="col-3">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="bi bi-send"></i> Enviar Teste
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab: Sistema -->
            <div id="tab-sistema" class="tab-content">
                <div class="mt-4">
                    <h5 class="mb-3">🔧 Modo Manutenção</h5>
                    <div class="card" style="background: #FFF3CD; border: 1px solid #FFE69C;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Status Atual:</strong> 
                                    <?php if (($config['manutencao_ativo'] ?? '0') == '1'): ?>
                                        <span class="badge badge-danger">ATIVO</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">DESATIVADO</span>
                                    <?php endif; ?>
                                    <p class="mt-2 mb-0">
                                        Quando ativado, apenas administradores poderão acessar o sistema.
                                    </p>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="toggle_manutencao" value="1">
                                    <input type="hidden" name="manutencao_ativo" 
                                           value="<?php echo ($config['manutencao_ativo'] ?? '0') == '1' ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo ($config['manutencao_ativo'] ?? '0') == '1' ? 'btn-success' : 'btn-warning'; ?>">
                                        <?php echo ($config['manutencao_ativo'] ?? '0') == '1' ? '✅ Desativar' : '⚠️ Ativar'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">🧹 Limpar Cache</h5>
                    <div class="card">
                        <div class="card-body">
                            <p>Remove logs antigos (mais de 90 dias) e limpa cache do sistema.</p>
                            <form method="POST">
                                <input type="hidden" name="clear_cache" value="1">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Deseja limpar o cache?')">
                                    <i class="bi bi-trash"></i> Limpar Cache
                                </button>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">ℹ️ Informações do Sistema</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th width="30%">Versão do Sistema</th>
                                <td><?php echo SITE_VERSION; ?></td>
                            </tr>
                            <tr>
                                <th>Versão do PHP</th>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <th>Servidor Web</th>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>MySQL</th>
                                <td><?php 
                                    $stmt = $db->query("SELECT VERSION()");
                                    echo $stmt->fetchColumn();
                                ?></td>
                            </tr>
                            <tr>
                                <th>Timezone</th>
                                <td><?php echo date_default_timezone_get(); ?></td>
                            </tr>
                            <tr>
                                <th>URL do Sistema</th>
                                <td><?php echo SITE_URL; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Backup -->
            <div id="tab-backup" class="tab-content">
                <div class="mt-4">
                    <h5 class="mb-3">💾 Backup da Base de Dados</h5>
                    
                    <div class="alert alert-warning">
                        <div class="alert-icon">⚠️</div>
                        <div class="alert-content">
                            <strong>Importante:</strong> Faça backups regulares da base de dados. 
                            Recomendamos fazer backup diário em produção.
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <h6>Criar Novo Backup</h6>
                            <p>Gera um arquivo SQL com toda a base de dados atual.</p>
                            <form method="POST">
                                <input type="hidden" name="backup_db" value="1">
                                <button type="submit" class="btn btn-primary" onclick="return confirm('Criar backup agora?')">
                                    <i class="bi bi-download"></i> Criar Backup Agora
                                </button>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">📁 Backups Disponíveis</h5>
                    <div class="card">
                        <div class="card-body">
                            <?php
                            $exportDir = __DIR__ . '/../exports/';
                            if (is_dir($exportDir)) {
                                $files = glob($exportDir . 'backup_*.sql');
                                if (!empty($files)):
                                    rsort($files);
                            ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Nome do Arquivo</th>
                                                <th>Tamanho</th>
                                                <th>Data</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($files, 0, 10) as $file): 
                                                $filename = basename($file);
                                                $filesize = filesize($file);
                                                $filedate = filemtime($file);
                                            ?>
                                            <tr>
                                                <td><?php echo $filename; ?></td>
                                                <td><?php echo number_format($filesize / 1024, 2); ?> KB</td>
                                                <td><?php echo date('d/m/Y H:i', $filedate); ?></td>
                                                <td>
                                                    <a href="../exports/<?php echo $filename; ?>" 
                                                       class="btn btn-sm btn-success" download>
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Nenhum backup encontrado.</p>
                            <?php 
                                endif;
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>