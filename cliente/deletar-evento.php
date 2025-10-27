<?php
/**
 * SISTEMA DE GESTÃO DE EVENTOS
 * Deletar Convite
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar se está logado como cliente
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();
$conviteId = get('id');

if (!$conviteId) {
    Session::setFlash('error', 'Convite não especificado');
    redirect('/cliente/dashboard.php');
}

// Buscar convite e verificar se pertence ao cliente
$stmt = $db->prepare("
    SELECT c.*, e.nome_evento, e.cliente_id, e.id as evento_id
    FROM convites c
    JOIN eventos e ON c.evento_id = e.id
    WHERE c.id = ? AND e.cliente_id = ?
");
$stmt->execute([$conviteId, $clienteId]);
$convite = $stmt->fetch();

if (!$convite) {
    Session::setFlash('error', 'Convite não encontrado');
    redirect('/cliente/dashboard.php');
}

// Processar exclusão
if (isPost()) {
    try {
        $stmt = $db->prepare("DELETE FROM convites WHERE id = ?");
        $stmt->execute([$conviteId]);
        
        // Registrar log
        logAccess('cliente', $clienteId, 'deletar_convite', 
            "Convite deletado: {$convite['nome_convidado1']}" . 
            ($convite['nome_convidado2'] ? " e {$convite['nome_convidado2']}" : "")
        );
        
        Session::setFlash('success', 'Convite excluído com sucesso!');
        redirect('/cliente/evento-detalhes.php?id=' . $convite['evento_id']);
        
    } catch (PDOException $e) {
        Session::setFlash('error', 'Erro ao excluir convite. Tente novamente.');
        error_log("Erro ao deletar convite: " . $e->getMessage());
        redirect('/cliente/editar-convite.php?id=' . $conviteId);
    }
}

include '../includes/cliente_header.php';
?>

<div class="content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Deletar Convite</h1>
            <div class="page-breadcrumb">
                <a href="dashboard.php">Início</a>
                <span class="breadcrumb-separator">/</span>
                <a href="evento-detalhes.php?id=<?php echo $convite['evento_id']; ?>">
                    <?php echo truncate($convite['nome_evento'], 30); ?>
                </a>
                <span class="breadcrumb-separator">/</span>
                <span>Deletar Convite</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-3"></div>
        <div class="col-6">
            <div class="card" style="border: 2px solid var(--danger-color);">
                <div class="card-header" style="background: var(--danger-color); color: white;">
                    <h3 class="card-title" style="color: white; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        ⚠️ Confirmar Exclusão
                    </h3>
                </div>
                <div class="card-body" style="padding: 2rem;">
                    <div class="alert alert-danger">
                        <div class="alert-icon">⚠️</div>
                        <div class="alert-content">
                            <div class="alert-title">Atenção!</div>
                            <p class="alert-message">
                                Esta ação é <strong>permanente</strong> e <strong>não pode ser desfeita</strong>. 
                                Todos os dados deste convite serão perdidos.
                            </p>
                        </div>
                    </div>

                    <div style="background: var(--gray-lighter); padding: 1.5rem; border-radius: var(--border-radius-sm); margin-bottom: 2rem;">
                        <h4 style="margin-bottom: 1rem; color: var(--dark-color);">Informações do Convite:</h4>
                        
                        <div style="margin-bottom: 1rem;">
                            <strong>Código:</strong> 
                            <span style="color: var(--primary-color);"><?php echo $convite['codigo_convite']; ?></span>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <strong>Convidado Principal:</strong><br>
                            <?php echo Security::clean($convite['nome_convidado1']); ?>
                            <?php if ($convite['telefone1']): ?>
                                <br><small><?php echo Security::clean($convite['telefone1']); ?></small>
                            <?php endif; ?>
                        </div>

                        <?php if ($convite['nome_convidado2']): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Segundo Convidado:</strong><br>
                            <?php echo Security::clean($convite['nome_convidado2']); ?>
                            <?php if ($convite['telefone2']): ?>
                                <br><small><?php echo Security::clean($convite['telefone2']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-bottom: 1rem;">
                            <strong>Evento:</strong><br>
                            <?php echo Security::clean($convite['nome_evento']); ?>
                        </div>

                        <?php if ($convite['presente_convidado1'] || $convite['presente_convidado2']): ?>
                        <div style="background: rgba(239, 68, 68, 0.1); padding: 1rem; border-radius: var(--border-radius-sm); border-left: 3px solid var(--danger-color);">
                            <strong style="color: var(--danger-color);">⚠️ Atenção:</strong><br>
                            <small>
                                <?php if ($convite['presente_convidado1'] && $convite['presente_convidado2']): ?>
                                    Ambos os convidados já realizaram check-in no evento!
                                <?php elseif ($convite['presente_convidado1']): ?>
                                    O convidado principal já realizou check-in no evento!
                                <?php else: ?>
                                    O segundo convidado já realizou check-in no evento!
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" style="margin-top: 2rem;">
                        <div class="form-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="confirmar" name="confirmar" required>
                                <label for="confirmar">
                                    <strong>Eu entendo que esta ação é permanente e confirmo a exclusão deste convite</strong>
                                </label>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                            <a href="evento-detalhes.php?id=<?php echo $convite['evento_id']; ?>" class="btn btn-secondary btn-lg">
                                ← Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteBtn" disabled>
                                🗑️ Confirmar Exclusão
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body text-center">
                    <p style="color: var(--gray-medium); margin: 0;">
                        <strong>Dica:</strong> Se você só quer editar as informações do convite, 
                        <a href="editar-convite.php?id=<?php echo $convite['id']; ?>">clique aqui para editar</a>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-3"></div>
    </div>
</div>

<script>
// Habilitar botão apenas quando checkbox estiver marcado
document.getElementById('confirmar').addEventListener('change', function() {
    document.getElementById('deleteBtn').disabled = !this.checked;
});
</script>

<?php include '../includes/cliente_footer.php'; ?>