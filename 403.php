<?php

/**
 * 403.php - ACESSO NEGADO
 */
define('SYSTEM_INIT', true);
require_once 'config.php';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Acesso Negado | <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6e8de3 0%, #1E3A8A 100%);
            padding: 2rem;
        }
        .error-box {
            background: white;
            border-radius: 20px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            background: linear-gradient(135deg, #6e8de3, #1E3A8A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 2rem;
            color: #1F2937;
            margin-bottom: 1rem;
        }
        .error-message {
            color: #6B7280;
            margin-bottom: 2rem;
            font-size: 1.125rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-box">
            <div class="error-code">403</div>
            <h1 class="error-title">Acesso Negado</h1>
            <p class="error-message">
                Você não tem permissão para acessar esta página.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">
                    <i class="bi bi-house"></i> Ir para Início
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </button>
            </div>
        </div>
    </div>
</body>
</html>
