<?php
/**
 * API - VERIFICAR CONVITE
 * Endpoint para verificação de convites via QR Code
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = Database::getInstance()->getConnection();
    
    // Obter código do convite
    $codigo = get('codigo') ?: post('codigo');
    
    if (empty($codigo)) {
        throw new Exception('Código do convite não fornecido');
    }
    
    // Limpar código (remover hífens e espaços)
    $codigo = strtoupper(str_replace(['-', ' '], '', $codigo));
    
    // Buscar convite
    $stmt = $db->prepare("
        SELECT c.*, 
               e.nome_evento, e.data_evento, e.local_nome, e.local_endereco,
               e.hora_inicio, e.codigo_evento
        FROM convites c
        JOIN eventos e ON c.evento_id = e.id
        WHERE REPLACE(c.codigo_convite, '-', '') = ?
        OR c.codigo_convite = ?
    ");
    $stmt->execute([$codigo, $codigo]);
    $convite = $stmt->fetch();
    
    if (!$convite) {
        throw new Exception('Convite não encontrado');
    }
    
    // Preparar resposta
    $response = [
        'success' => true,
        'convite' => [
            'id' => $convite['id'],
            'codigo' => $convite['codigo_convite'],
            'tipo' => $convite['tipo_convidado'],
            'mesa' => $convite['mesa_numero'],
            'observacoes' => $convite['observacoes']
        ],
        'convidado1' => [
            'nome' => $convite['nome_convidado1'],
            'telefone' => $convite['telefone1'],
            'email' => $convite['email1'],
            'presente' => (bool)$convite['presente_convidado1'],
            'hora_checkin' => $convite['hora_checkin1']
        ],
        'evento' => [
            'nome' => $convite['nome_evento'],
            'codigo' => $convite['codigo_evento'],
            'data' => $convite['data_evento'],
            'data_formatada' => formatDate($convite['data_evento']),
            'hora_inicio' => $convite['hora_inicio'],
            'local' => $convite['local_nome'],
            'endereco' => $convite['local_endereco']
        ]
    ];
    
    // Adicionar segundo convidado se existir
    if ($convite['nome_convidado2']) {
        $response['convidado2'] = [
            'nome' => $convite['nome_convidado2'],
            'telefone' => $convite['telefone2'],
            'email' => $convite['email2'],
            'presente' => (bool)$convite['presente_convidado2'],
            'hora_checkin' => $convite['hora_checkin2']
        ];
    }
    
    // Registrar acesso via API
    logAccess('api', 0, 'verificar_convite', "Convite verificado: {$convite['codigo_convite']}");
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

exit;