
<?php
/*-- ========================================
-- create_admin.php - Execute este arquivo UMA VEZ para criar o usuário admin
-- Depois delete este arquivo por segurança!
-- ======================================== */
// Configurações do banco de dados
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'gestao_eventos_pro';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Dados do novo admin
    $username = 'admin';
    $email = 'scrofyle@exdigital.com';
    $password = '4ng0l42025'; // MUDE ESTA SENHA!
    $role = 'super_admin';
    
    // Gerar hash da senha
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Verificar se usuário já existe
    $check = $pdo->prepare("SELECT id FROM administradores WHERE email = ?");
    $check->execute([$email]);
    
    if($check->rowCount() > 0) {
        // Atualizar usuário existente
        $stmt = $pdo->prepare("UPDATE administradores SET senha = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        echo "✅ Usuário admin ATUALIZADO com sucesso!<br>";
    } else {
        // Criar novo usuário
        $stmt = $pdo->prepare("INSERT INTO administradores (email, senha) VALUES (?, ?)");
        $stmt->execute([$email, $hashedPassword]);
        echo "✅ Usuário admin CRIADO com sucesso!<br>";
    }
    
    echo "<br><strong>Credenciais de acesso:</strong><br>";
    echo "Senha: <strong>$password</strong><br>";
    echo "<br>Hash gerado: $hashedPassword<br>";
    echo "<br>⚠️ <strong>IMPORTANTE: Delete este arquivo (create_admin.php) após executar!</strong>";
    
} catch(PDOException $e) {
    die("❌ Erro: " . $e->getMessage() . "<br><br>Verifique se:<br>1. O banco de dados 'eventos_gestao' existe<br>2. As credenciais no arquivo estão corretas<br>3. A tabela 'users' foi criada");
}
?>
