<?php
/**
 * CLIENTE - CENTRO DE AJUDA
 * FAQ, Tutoriais e Suporte
 */

define('SYSTEM_INIT', true);
require_once '../config.php';

// Verificar autenticação
if (!Session::isLoggedIn() || Session::getUserType() !== 'cliente') {
    redirect('/login.php');
}

$db = Database::getInstance()->getConnection();
$clienteId = Session::getUserId();

// Buscar informações do cliente
$stmt = $db->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch();

// Categorias de ajuda
$categorias = [
    'primeiros-passos' => [
        'titulo' => '🚀 Primeiros Passos',
        'icon' => 'bi-rocket-takeoff',
        'cor' => '#10B981'
    ],
    'eventos' => [
        'titulo' => '📅 Gestão de Eventos',
        'icon' => 'bi-calendar-event',
        'cor' => '#3B82F6'
    ],
    'convites' => [
        'titulo' => '🎫 Convites',
        'icon' => 'bi-ticket-perforated',
        'cor' => '#F59E0B'
    ],
    'pagamentos' => [
        'titulo' => '💳 Pagamentos',
        'icon' => 'bi-credit-card',
        'cor' => '#EF4444'
    ],
    'fornecedores' => [
        'titulo' => '🏢 Fornecedores',
        'icon' => 'bi-building',
        'cor' => '#8B5CF6'
    ],
    'relatorios' => [
        'titulo' => '📊 Relatórios',
        'icon' => 'bi-graph-up',
        'cor' => '#EC4899'
    ]
];

// FAQ por categoria
$faqs = [
    'primeiros-passos' => [
        [
            'pergunta' => 'Como criar meu primeiro evento?',
            'resposta' => 'Para criar seu primeiro evento, siga estes passos:<br><br>
                <ol>
                    <li>Clique em "Criar Evento" no menu lateral</li>
                    <li>Preencha os dados do evento (nome, tipo, data, local). Lembrando que o nome do evento deve incorporar as caracteríticas do mesmo(ex: 3º Aniversário da Uzima, Casamento de João e Maria, etc.).</li>
                    <li>Escolha um plano de acordo com o número de convidados</li>
                    <li>Clique em "Criar Evento"</li>
                    <li>Realize o pagamento para ativar o evento</li>
                </ol>
                Após o pagamento ser aprovado, seu evento estará ativo e você poderá adicionar convidados!'
        ],
        [
            'pergunta' => 'Quais são os limites de cada plano?',
            'resposta' => 'Temos 4 planos disponíveis:<br><br>
                <strong>Básico (15.000 Kz):</strong> Até 100 convites, 3 fornecedores, 30 dias<br>
                <strong>Padrão (30.000 Kz):</strong> Até 250 convites, 6 fornecedores, 60 dias<br>
                <strong>Premium (50.000 Kz):</strong> Até 500 convites, 10 fornecedores, 90 dias<br>
                <strong>Empresarial (100.000 Kz):</strong> Ilimitado, recursos avançados, 365 dias'
        ],
        [
            'pergunta' => 'Como funciona o sistema de pagamento?',
            'resposta' => 'Aceitamos 4 formas de pagamento:<br><br>
                <strong>1. Referência Multicaixa:</strong> Gere uma referência e pague em qualquer ATM<br>
                <strong>2. Multicaixa Express:</strong> Pagamento online instantâneo<br>
                <strong>3. PayPal:</strong> Cartão de crédito/débito internacional<br>
                <strong>4. Transferência Bancária:</strong> Transfira para o IBAN fornecido e envie o comprovante para aprovação<br><br>
                Após confirmação, seu evento é ativado automaticamente!<br>
                <strong>Nota:</strong> No caso da Transferência Bancária, o cliente pode aguardar até 24h para aprovação.'
        ]
    ],
    'eventos' => [
        [
            'pergunta' => 'Como editar um evento já criado?',
            'resposta' => 'Para editar um evento:<br><br>
                <ol>
                    <li>Acesse "Meus Eventos"</li>
                    <li>Clique no evento desejado</li>
                    <li>Clique no botão "Editar Evento"</li>
                    <li>Faça as alterações necessárias</li>
                    <li>Clique em "Salvar Alterações"</li>
                </ol>
                <strong>Atenção:</strong> Não é possível alterar a data do evento se faltar menos de 48h.'
        ],
        [
            'pergunta' => 'Posso cancelar um evento?',
            'resposta' => 'Sim! Você pode cancelar um evento a qualquer momento:<br><br>
                <ol>
                    <li>Acesse "Meus Eventos"</li>
                    <li>Clique no evento</li>
                    <li>Clique em "Cancelar Evento"</li>
                    <li>Confirme o cancelamento</li>
                </ol>
                <strong>Importante:</strong> Cancelamentos não geram reembolso do pagamento realizado.'
        ],
        [
            'pergunta' => 'O que acontece após o evento terminar?',
            'resposta' => 'Após o evento:<br><br>
                - O status muda automaticamente para "Concluído"<br>
                - Você pode gerar relatórios completos<br>
                - Os dados ficam disponíveis por 90 dias<br>
                - É possível exportar a lista de presença<br>
                - Todos os QR Codes ficam inativos'
        ]
    ],
    'convites' => [
        [
            'pergunta' => 'Como adicionar convidados?',
            'resposta' => 'Para adicionar convidados:<br><br>
                <ol>
                    <li>Acesse o evento em "Meus Eventos"</li>
                    <li>Clique em "Adicionar Convite"</li>
                    <li>Preencha os dados (nome, telefone, email)</li>
                    <li>Você pode adicionar até 2 pessoas por convite</li>
                    <li>Escolha o tipo (VIP, Normal, Família, etc)</li>
                    <li>Clique em "Adicionar Convite"</li>
                </ol>
                Um QR Code único será gerado automaticamente!'
        ],
        [
            'pergunta' => 'Como funciona o QR Code?',
            'resposta' => 'Cada convite tem um QR Code único que pode ser:<br><br>
                - <strong>Escaneado na entrada:</strong> Equipe de segurança valida e marca presença<br>
                - <strong>Enviado por WhatsApp/Email:</strong> Convidado apresenta na entrada<br>
                - <strong>Impresso:</strong> Você pode imprimir convites físicos<br><br>
                O QR Code contém todas as informações do convite de forma segura.'
        ],
        [
            'pergunta' => 'Posso editar ou deletar convites?',
            'resposta' => 'Sim! Você tem controle total:<br><br>
                <strong>Editar:</strong> Clique no convite e em "Editar" - pode alterar nomes, telefones, tipo<br>
                <strong>Deletar:</strong> Clique em "Deletar" com confirmação de segurança<br><br>
                <strong>Dica:</strong> Deletar um convite libera espaço para adicionar novos dentro do limite do seu plano.'
        ],
        [
            'pergunta' => 'Como marcar presença dos convidados?',
            'resposta' => 'Existem 3 formas de marcar presença:<br><br>
                <strong>1. QR Code (Recomendado):</strong> Equipe de segurança escaneia na entrada<br>
                <strong>2. Manual no Sistema:</strong> Você marca presença diretamente no painel<br>
                <strong>3. Código do Convite:</strong> Buscar por código e marcar manualmente<br><br>
                A presença fica registrada com data e hora exatas!'
        ]
    ],
    'pagamentos' => [
        [
            'pergunta' => 'Quanto tempo leva para aprovar o pagamento?',
            'resposta' => 'O tempo varia por método:<br><br>
                <strong>Multicaixa Express:</strong> Aprovação instantânea (segundos)<br>
                <strong>PayPal:</strong> Aprovação instantânea (segundos)<br>
                <strong>Referência Multicaixa:</strong> Até 24 horas após pagamento<br><br>
                Você receberá email e notificação quando for aprovado!'
        ],
        [
            'pergunta' => 'O que fazer se o pagamento não foi aprovado?',
            'resposta' => 'Se após 24h seu pagamento não foi aprovado:<br><br>
                <ol>
                    <li>Verifique se pagou o valor correto</li>
                    <li>Confirme que usou a referência correta</li>
                    <li>Entre em contato conosco pelo email: ' . ADMIN_EMAIL . '</li>
                    <li>Envie comprovante de pagamento</li>
                </ol>
                Nossa equipe resolverá em até 2 horas úteis!'
        ],
        [
            'pergunta' => 'Posso mudar o método de pagamento?',
            'resposta' => 'Sim! Se um pagamento ainda estiver pendente:<br><br>
                <ol>
                    <li>Cancele o pagamento pendente</li>
                    <li>Clique em "Processar Pagamento" novamente</li>
                    <li>Escolha outro método</li>
                    <li>Complete o pagamento</li>
                </ol>
                <strong>Atenção:</strong> Pagamentos já aprovados não podem ser alterados.'
        ]
    ],
    'fornecedores' => [
        [
            'pergunta' => 'Como adicionar fornecedores ao evento?',
            'resposta' => 'Para adicionar fornecedores:<br><br>
                <ol>
                    <li>Acesse o evento</li>
                    <li>Clique em "Adicionar Fornecedor"</li>
                    <li>Preencha dados (categoria, nome, contato)</li>
                    <li>Clique em "Cadastrar"</li>
                </ol>
                Um <strong>código de acesso</strong> e <strong>senha temporária</strong> serão gerados. Compartilhe com o fornecedor!'
        ],
        [
            'pergunta' => 'O que o fornecedor pode fazer no sistema?',
            'resposta' => 'Fornecedores podem:<br><br>
                - Ver informações do evento<br>
                - Cadastrar membros da equipe<br>
                - Fazer check-in da equipe<br>
                - <strong>Segurança:</strong> Escanear QR Codes e marcar presença de convidados<br><br>
                Cada fornecedor tem acesso limitado apenas ao seu evento!'
        ],
        [
            'pergunta' => 'Como funciona a equipe de Segurança?',
            'resposta' => 'Fornecedores com categoria "Segurança" têm recursos especiais:<br><br>
                <strong>✅ Check-in de Convidados:</strong><br>
                - Scanner de QR Code em tempo real<br>
                - Busca por código do convite<br>
                - Marcar/desmarcar presença<br>
                - Ver dados dos convidados<br>
                - Estatísticas de entrada<br><br>
                Ideal para controle de acesso profissional!'
        ]
    ],
    'relatorios' => [
        [
            'pergunta' => 'Como gerar relatórios do evento?',
            'resposta' => 'Para gerar relatórios:<br><br>
                <ol>
                    <li>Acesse o evento em "Meus Eventos"</li>
                    <li>Clique em "Relatório do Evento"</li>
                    <li>Visualize estatísticas completas</li>
                    <li>Clique em "Imprimir" ou "Exportar PDF"</li>
                </ol>
                O relatório inclui: convites, presença, despesas e fornecedores!'
        ],
        [
            'pergunta' => 'Posso exportar a lista de convidados?',
            'resposta' => 'Sim! Você pode exportar em diferentes formatos:<br><br>
                <strong>Excel/CSV:</strong> Clique em "Exportar CSV" na lista de convites<br>
                <strong>PDF:</strong> Gere o relatório e imprima como PDF<br>
                <strong>Impressão:</strong> Imprima direto do navegador<br><br>
                A lista inclui: nomes, contatos, tipo, status de presença, mesa!'
        ],
        [
            'pergunta' => 'Quais estatísticas posso ver?',
            'resposta' => 'Você tem acesso a estatísticas completas:<br><br>
                <strong>Convites:</strong> Total, confirmados, VIP, por tipo<br>
                <strong>Presença:</strong> Taxa de comparecimento, hora de chegada<br>
                <strong>Despesas:</strong> Total gasto, por categoria, pendentes<br>
                <strong>Fornecedores:</strong> Quantidade, valores, equipes<br><br>
                Tudo atualizado em tempo real!'
        ]
    ]
];

include '../includes/cliente_header.php';
?>

<div class="content">
    <div class="page-header">
        <h1 class="page-title">❓ Centro de Ajuda</h1>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <span class="breadcrumb-separator">/</span>
            <span>Ajuda</span>
        </div>
    </div>

    <!-- Banner de Boas-vindas -->
    <div class="card mb-4" style="background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); color: white;">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-8">
                    <h2 style="color: white; margin-bottom: 1rem;">👋 Olá, <?php echo Security::clean($cliente['nome_completo']); ?>!</h2>
                    <p style="margin: 0; font-size: 1.125rem; opacity: 0.95;">
                        Como podemos ajudar você hoje? Encontre respostas rápidas nas perguntas frequentes abaixo.
                    </p>
                </div>
                <div class="col-4 text-center">
                    <i class="bi bi-question-circle" style="font-size: 5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Busca -->
    <div class="card mb-4">
        <div class="card-body">
            <div style="position: relative;">
                <input type="text" 
                       id="search-help" 
                       class="form-control" 
                       placeholder="🔍 Buscar na central de ajuda..."
                       style="font-size: 1.125rem; padding: 1rem 1rem 1rem 3rem;">
                <i class="bi bi-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.25rem; color: var(--gray-medium);"></i>
            </div>
        </div>
    </div>

    <!-- Categorias -->
    <div class="row mb-4">
        <?php foreach ($categorias as $key => $cat): ?>
        <div class="col-4">
            <a href="#categoria-<?php echo $key; ?>" 
               class="card categoria-card" 
               style="text-decoration: none; transition: all 0.3s ease; cursor: pointer;"
               onclick="scrollToCategory('<?php echo $key; ?>')">
                <div class="card-body text-center">
                    <i class="bi <?php echo $cat['icon']; ?>" 
                       style="font-size: 3rem; color: <?php echo $cat['cor']; ?>; margin-bottom: 1rem;"></i>
                    <h5 style="margin: 0; color: var(--dark-color);"><?php echo $cat['titulo']; ?></h5>
                    <small class="text-muted"><?php echo count($faqs[$key]); ?> artigos</small>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ por Categoria -->
    <?php foreach ($categorias as $key => $cat): ?>
    <div id="categoria-<?php echo $key; ?>" class="card mb-4 categoria-section">
        <div class="card-header" style="background: <?php echo $cat['cor']; ?>; color: white;">
            <h3 class="card-title" style="color: white; margin: 0;">
                <i class="bi <?php echo $cat['icon']; ?>"></i> <?php echo $cat['titulo']; ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="accordion">
                <?php foreach ($faqs[$key] as $index => $faq): ?>
                <div class="accordion-item faq-item" style="margin-bottom: 1rem; border: 1px solid var(--gray-lighter); border-radius: var(--border-radius);">
                    <div class="accordion-header" 
                         style="padding: 1.25rem; cursor: pointer; display: flex; justify-content: between; align-items: center;"
                         onclick="toggleAccordion(this)">
                        <strong style="flex: 1; color: var(--dark-color);"><?php echo $faq['pergunta']; ?></strong>
                        <i class="bi bi-chevron-down" style="font-size: 1.25rem; color: var(--primary-color); transition: transform 0.3s ease;"></i>
                    </div>
                    <div class="accordion-content" style="display: none; padding: 0 1.25rem 1.25rem 1.25rem; color: var(--gray-dark);">
                        <?php echo $faq['resposta']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Contato -->
    <div class="row">
        <div class="col-6">
            <div class="card">
                <div class="card-header" style="background: #10B981; color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        📧 Ainda precisa de ajuda?
                    </h3>
                </div>
                <div class="card-body">
                    <p>Nossa equipe está pronta para ajudar você!</p>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong>📧 Email:</strong><br>
                        <a href="mailto:<?php echo ADMIN_EMAIL; ?>" style="color: var(--primary-color);">
                            <?php echo ADMIN_EMAIL; ?>
                        </a>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <strong>📞 Telefone:</strong><br>
                        <a href="tel:+244948005566" style="color: var(--primary-color);">
                            +244 948 005 566
                        </a>
                    </div>

                    <div>
                        <strong>💬 WhatsApp:</strong><br>
                        <a href="https://wa.me/244948005566" target="_blank" style="color: var(--success-color);">
                            <i class="bi bi-whatsapp"></i> Abrir Chat
                        </a>
                    </div>

                    <hr class="my-3">

                    <p style="margin: 0; font-size: 0.875rem; color: var(--gray-medium);">
                        <strong>Horário de Atendimento:</strong><br>
                        Segunda a Sexta: 09h - 18h<br>
                        Sábado: 09h - 13h
                    </p>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="card">
                <div class="card-header" style="background: #3B82F6; color: white;">
                    <h3 class="card-title" style="color: white; margin: 0;">
                        🎥 Tutoriais em Vídeo
                    </h3>
                </div>
                <div class="card-body">
                    <p>Aprenda visualmente com nossos tutoriais:</p>
                    
                    <div class="list-group">
                        <a href="#" class="list-group-item" style="border: 1px solid var(--gray-lighter); border-radius: var(--border-radius); margin-bottom: 0.5rem; padding: 1rem;">
                            <i class="bi bi-play-circle" style="color: var(--danger-color); font-size: 1.5rem; margin-right: 0.75rem;"></i>
                            <strong>Como Criar Seu Primeiro Evento</strong>
                            <small class="text-muted" style="display: block; margin-left: 2.25rem;">5:30 min</small>
                        </a>

                        <a href="#" class="list-group-item" style="border: 1px solid var(--gray-lighter); border-radius: var(--border-radius); margin-bottom: 0.5rem; padding: 1rem;">
                            <i class="bi bi-play-circle" style="color: var(--danger-color); font-size: 1.5rem; margin-right: 0.75rem;"></i>
                            <strong>Gerenciando Convites e QR Codes</strong>
                            <small class="text-muted" style="display: block; margin-left: 2.25rem;">8:15 min</small>
                        </a>

                        <a href="#" class="list-group-item" style="border: 1px solid var(--gray-lighter); border-radius: var(--border-radius); padding: 1rem;">
                            <i class="bi bi-play-circle" style="color: var(--danger-color); font-size: 1.5rem; margin-right: 0.75rem;"></i>
                            <strong>Processando Pagamentos</strong>
                            <small class="text-muted" style="display: block; margin-left: 2.25rem;">4:20 min</small>
                        </a>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">Em breve mais tutoriais!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.categoria-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.accordion-header:hover {
    background: var(--gray-lighter);
}

.accordion-header.active i {
    transform: rotate(180deg);
}

.list-group-item {
    display: flex;
    align-items: center;
    text-decoration: none;
    color: var(--dark-color);
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background: var(--gray-lighter);
    transform: translateX(5px);
}

.faq-item {
    transition: all 0.2s ease;
}

.faq-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
</style>

<script>
// Toggle Accordion
function toggleAccordion(header) {
    const content = header.nextElementSibling;
    const icon = header.querySelector('i');
    const isOpen = content.style.display === 'block';
    
    if (isOpen) {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        header.classList.remove('active');
    } else {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
        header.classList.add('active');
    }
}

// Busca na Ajuda
document.getElementById('search-help').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const faqItems = document.querySelectorAll('.faq-item');
    const categories = document.querySelectorAll('.categoria-section');
    
    if (searchTerm === '') {
        // Mostrar tudo
        faqItems.forEach(item => item.style.display = 'block');
        categories.forEach(cat => cat.style.display = 'block');
    } else {
        // Filtrar
        categories.forEach(cat => {
            let hasVisibleItems = false;
            const items = cat.querySelectorAll('.faq-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = 'block';
                    hasVisibleItems = true;
                } else {
                    item.style.display = 'none';
                }
            });
            
            cat.style.display = hasVisibleItems ? 'block' : 'none';
        });
    }
});

// Scroll suave para categoria
function scrollToCategory(categoryId) {
    const element = document.getElementById('categoria-' + categoryId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Highlight temporário
        element.style.transition = 'all 0.3s ease';
        element.style.transform = 'scale(1.02)';
        setTimeout(() => {
            element.style.transform = 'scale(1)';
        }, 300);
    }
}

// Expandir todos ao buscar
document.getElementById('search-help').addEventListener('input', function(e) {
    if (e.target.value.length > 0) {
        document.querySelectorAll('.accordion-content').forEach(content => {
            content.style.display = 'block';
            const header = content.previousElementSibling;
            header.querySelector('i').style.transform = 'rotate(180deg)';
            header.classList.add('active');
        });
    }
});
</script>

<?php include '../includes/cliente_footer.php'; ?>