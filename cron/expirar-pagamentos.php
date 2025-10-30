<?php
/**
 * CRON - EXPIRAR PAGAMENTOS PENDENTES
 * Marca pagamentos pendentes como expirados após X dias
 * Executar a cada 6 horas: 0 */6 * * * php /caminho/cron/expirar-pagamentos.php
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

$db = Database::getInstance()->getConnection();
$timestamp = date('Y-m-d H:i:s');
$logFile = __DIR__ . '/../logs/cron_payments.log';

file_put_contents($logFile, "[$timestamp] Iniciando verificação de pagamentos...\n", FILE_APPEND);

try {
    $db->beginTransaction();
    
    // Buscar configuração de dias para expiração
    $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'dias_expiracao_pagamento'");
    $stmt->execute();
    $diasExpiracao = (int)$stmt->fetchColumn() ?: 3;
    
    file_put_contents($logFile, "[$timestamp] Configuração: Expirar após $diasExpiracao dias\n", FILE_APPEND);
    
    // Buscar pagamentos pendentes expirados
    $stmt = $db->prepare("
        SELECT p.*, c.nome_completo, c.email
        FROM pagamentos p
        JOIN clientes c ON p.cliente_id = c.id
        WHERE p.status = 'pendente'
        AND p.criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$diasExpiracao]);
    $pagamentosExpirados = $stmt->fetchAll();
    
    $total = count($pagamentosExpirados);
    
    if ($total > 0) {
        file_put_contents($logFile, "[$timestamp] Encontrados $total pagamento(s) para expirar\n", FILE_APPEND);
        
        foreach ($pagamentosExpirados as $pag) {
            // Marcar como expirado
            $stmt = $db->prepare("
                UPDATE pagamentos 
                SET status = 'expirado'
                WHERE id = ?
            ");
            $stmt->execute([$pag['id']]);
            
            // Notificar cliente
            createNotification(
                'cliente',
                $pag['cliente_id'],
                'Pagamento Expirado',
                'O pagamento ref. ' . $pag['referencia'] . ' expirou. Crie um novo pagamento se desejar continuar.',
                'warning',
                '/cliente/pagamentos.php'
            );
            
            // Enviar email
            $assunto = 'Pagamento Expirado - ' . SITE_NAME;
            $mensagem = "
                <h2>Pagamento Expirado</h2>
                <p>Olá, <strong>{$pag['nome_completo']}</strong>!</p>
                <p>Infelizmente, o pagamento abaixo expirou:</p>
                <ul>
                    <li><strong>Referência:</strong> {$pag['referencia']}</li>
                    <li><strong>Valor:</strong> " . formatMoney($pag['valor'], $pag['moeda']) . "</li>
                    <li><strong>Criado em:</strong> " . formatDateTime($pag['criado_em']) . "</li>
                </ul>
                <p>Se ainda deseja processar este pagamento, crie uma nova solicitação no sistema.</p>
                <p><a href='" . SITE_URL . "/cliente/processar-pagamento.php' style='background: #6C63FF; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Criar Novo Pagamento</a></p>
            ";
            sendEmail($pag['email'], $assunto, $mensagem);
            
            file_put_contents($logFile, "[$timestamp] Pagamento #{$pag['id']} expirado e notificado\n", FILE_APPEND);
        }
    } else {
        file_put_contents($logFile, "[$timestamp] Nenhum pagamento para expirar\n", FILE_APPEND);
    }
    
    // Limpar pagamentos muito antigos (mais de 90 dias)
    $stmt = $db->prepare("
        DELETE FROM pagamentos
        WHERE status IN ('expirado', 'cancelado')
        AND criado_em < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $deletados = $stmt->rowCount();
    
    if ($deletados > 0) {
        file_put_contents($logFile, "[$timestamp] $deletados pagamento(s) antigo(s) deletado(s)\n", FILE_APPEND);
    }
    
    $db->commit();
    
    file_put_contents($logFile, "[$timestamp] Concluído! Expirados: $total | Deletados: $deletados\n\n", FILE_APPEND);
    
    echo "SUCCESS: $total expirados, $deletados deletados\n";
    
} catch (Exception $e) {
    $db->rollBack();
    $erro = $e->getMessage();
    file_put_contents($logFile, "[$timestamp] ERRO: $erro\n\n", FILE_APPEND);
    echo "ERROR: $erro\n";
}

exit;