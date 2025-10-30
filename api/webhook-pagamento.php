<?php
/**
 * API - WEBHOOK DE PAGAMENTO
 * Recebe notificações de pagamento de gateways
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

header('Content-Type: application/json');

// Log de requisição
$logFile = __DIR__ . '/../logs/webhook_payments.log';
$timestamp = date('Y-m-d H:i:s');

try {
    $db = Database::getInstance()->getConnection();
    
    // Obter dados da requisição
    $method = $_SERVER['REQUEST_METHOD'];
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // Log da requisição
    file_put_contents($logFile, "[$timestamp] Webhook recebido - Method: $method\n", FILE_APPEND);
    
    // Processar POST (PayPal, Express, etc)
    if ($method === 'POST') {
        
        // Obter corpo da requisição
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Dados inválidos');
        }
        
        file_put_contents($logFile, "[$timestamp] Dados recebidos: " . json_encode($data) . "\n", FILE_APPEND);
        
        // Identificar tipo de pagamento
        $paymentId = null;
        $status = null;
        $transactionId = null;
        $gateway = 'unknown';
        
        // PAYPAL
        if (isset($data['paypal_order_id'])) {
            $gateway = 'paypal';
            $paymentId = $data['payment_id'] ?? null;
            $transactionId = $data['paypal_order_id'];
            $status = 'aprovado';
            
            file_put_contents($logFile, "[$timestamp] PayPal detectado - Order: $transactionId\n", FILE_APPEND);
        }
        
        // EXPRESS
        elseif (isset($data['payment_reference'])) {
            $gateway = 'express';
            $transactionId = $data['payment_reference'];
            $status = $data['status'] === 'paid' ? 'aprovado' : 'pendente';
            
            // Buscar pagamento pela referência
            $stmt = $db->prepare("SELECT id FROM pagamentos WHERE referencia = ?");
            $stmt->execute([$data['reference']]);
            $payment = $stmt->fetch();
            $paymentId = $payment['id'] ?? null;
            
            file_put_contents($logFile, "[$timestamp] Express detectado - Ref: $transactionId\n", FILE_APPEND);
        }
        
        // MULTICAIXA
        elseif (isset($data['entity']) && isset($data['reference'])) {
            $gateway = 'multicaixa';
            $transactionId = $data['reference'];
            $status = 'aprovado';
            
            // Buscar pagamento pela referência
            $stmt = $db->prepare("SELECT id FROM pagamentos WHERE referencia = ?");
            $stmt->execute([$data['reference']]);
            $payment = $stmt->fetch();
            $paymentId = $payment['id'] ?? null;
            
            file_put_contents($logFile, "[$timestamp] Multicaixa detectado - Ref: $transactionId\n", FILE_APPEND);
        }
        
        // Atualizar pagamento se encontrado
        if ($paymentId && $status) {
            
            // Buscar pagamento completo
            $stmt = $db->prepare("
                SELECT p.*, c.nome_completo, c.email, e.id as evento_id
                FROM pagamentos p
                JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN eventos e ON p.evento_id = e.id
                WHERE p.id = ?
            ");
            $stmt->execute([$paymentId]);
            $pagamento = $stmt->fetch();
            
            if ($pagamento && $pagamento['status'] !== 'aprovado') {
                
                // Atualizar status do pagamento
                $stmt = $db->prepare("
                    UPDATE pagamentos 
                    SET status = ?, 
                        data_aprovacao = NOW(),
                        dados_pagamento = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $status,
                    json_encode($data),
                    $paymentId
                ]);
                
                // Se aprovado, ativar evento
                if ($status === 'aprovado' && $pagamento['evento_id']) {
                    $stmt = $db->prepare("
                        UPDATE eventos 
                        SET pago = 1, 
                            data_pagamento = NOW(),
                            status = 'ativo'
                        WHERE id = ?
                    ");
                    $stmt->execute([$pagamento['evento_id']]);
                }
                
                // Criar notificação
                createNotification(
                    'cliente',
                    $pagamento['cliente_id'],
                    'Pagamento Aprovado',
                    'Seu pagamento de ' . formatMoney($pagamento['valor'], $pagamento['moeda']) . ' foi aprovado!',
                    'success',
                    '/cliente/pagamentos.php'
                );
                
                // Enviar email
                $assunto = 'Pagamento Aprovado - ' . SITE_NAME;
                $mensagem = "
                    <h2>Pagamento Aprovado!</h2>
                    <p>Olá, <strong>{$pagamento['nome_completo']}</strong>!</p>
                    <p>Seu pagamento foi aprovado com sucesso!</p>
                    <ul>
                        <li><strong>Referência:</strong> {$pagamento['referencia']}</li>
                        <li><strong>Valor:</strong> " . formatMoney($pagamento['valor'], $pagamento['moeda']) . "</li>
                        <li><strong>Método:</strong> " . strtoupper($gateway) . "</li>
                        <li><strong>Data:</strong> " . date('d/m/Y H:i') . "</li>
                    </ul>
                    <p>Seu evento já está ativo e você pode gerenciá-lo no sistema.</p>
                    <p><a href='" . SITE_URL . "/cliente/dashboard.php' style='background: #10B981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Acessar Sistema</a></p>
                ";
                sendEmail($pagamento['email'], $assunto, $mensagem);
                
                // Log
                logAccess('sistema', 0, 'webhook_payment_approved', "Pagamento #$paymentId aprovado via $gateway");
                
                file_put_contents($logFile, "[$timestamp] Pagamento #$paymentId atualizado para: $status\n", FILE_APPEND);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Pagamento processado com sucesso',
                    'payment_id' => $paymentId,
                    'status' => $status
                ]);
                
            } else {
                throw new Exception('Pagamento já processado ou não encontrado');
            }
            
        } else {
            throw new Exception('Pagamento não identificado');
        }
        
    } else {
        throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    file_put_contents($logFile, "[$timestamp] ERRO: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;