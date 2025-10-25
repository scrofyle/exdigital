-- ============================================
-- SISTEMA DE GESTÃO DE EVENTOS - DATABASE
-- Versão Comercial 2.0
-- ============================================

CREATE DATABASE IF NOT EXISTS gestao_eventos_pro 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE gestao_eventos_pro;

-- ============================================
-- TABELA: Níveis de Acesso
-- ============================================
CREATE TABLE niveis_acesso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao TEXT,
    permissoes JSON,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO niveis_acesso (nome, descricao, permissoes) VALUES
('super_admin', 'Acesso total ao sistema', '{"all": true}'),
('admin_financeiro', 'Gestão financeira e pagamentos', '{"financeiro": true, "relatorios": true}'),
('admin_tecnico', 'Suporte técnico e configurações', '{"eventos": true, "usuarios": true, "logs": true}'),
('cliente', 'Cliente do sistema', '{"eventos_proprios": true, "convites": true}'),
('fornecedor', 'Fornecedor de serviços', '{"equipe": true, "servicos": true}');

-- ============================================
-- TABELA: Administradores
-- ============================================
CREATE TABLE administradores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    nivel_acesso_id INT NOT NULL,
    foto_perfil VARCHAR(255),
    status ENUM('ativo', 'inativo', 'suspenso') DEFAULT 'ativo',
    ultimo_acesso DATETIME,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (nivel_acesso_id) REFERENCES niveis_acesso(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin padrão
INSERT INTO administradores (nome_completo, email, senha, nivel_acesso_id) VALUES
('Super Administrador', 'scrofyle@exdigital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
-- Senha: admin123 (ALTERAR EM PRODUÇÃO!)

-- ============================================
-- TABELA: Clientes
-- ============================================
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    nif VARCHAR(20),
    endereco TEXT,
    cidade VARCHAR(100),
    provincia VARCHAR(100),
    pais VARCHAR(100) DEFAULT 'Angola',
    foto_perfil VARCHAR(255),
    empresa VARCHAR(150),
    status ENUM('ativo', 'inativo', 'suspenso', 'inadimplente') DEFAULT 'ativo',
    data_nascimento DATE,
    ultimo_acesso DATETIME,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Planos de Assinatura
-- ============================================
CREATE TABLE planos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    preco_aoa DECIMAL(10,2) NOT NULL,
    preco_usd DECIMAL(10,2),
    max_convites INT NOT NULL,
    max_fornecedores INT NOT NULL,
    validade_dias INT NOT NULL,
    recursos JSON,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO planos (nome, descricao, preco_aoa, preco_usd, max_convites, max_fornecedores, validade_dias, recursos) VALUES
('Básico', 'Ideal para eventos pequenos', 15000.00, 18.00, 100, 3, 30, '{"qr_code": true, "relatorios": true}'),
('Padrão', 'Perfeito para eventos médios', 30000.00, 36.00, 250, 6, 60, '{"qr_code": true, "relatorios": true, "exportacao": true}'),
('Premium', 'Para eventos grandes e profissionais', 50000.00, 60.00, 500, 10, 90, '{"qr_code": true, "relatorios": true, "exportacao": true, "suporte_prioritario": true}'),
('Empresarial', 'Solução completa para empresas', 100000.00, 120.00, 9999, 99, 365, '{"qr_code": true, "relatorios": true, "exportacao": true, "suporte_prioritario": true, "api": true}');

-- ============================================
-- TABELA: Eventos
-- ============================================
CREATE TABLE eventos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    plano_id INT NOT NULL,
    nome_evento VARCHAR(200) NOT NULL,
    tipo_evento ENUM('casamento', 'aniversario', 'noivado', 'corporativo', 'batizado', 'formatura', 'outro') NOT NULL,
    descricao TEXT,
    data_evento DATETIME NOT NULL,
    hora_inicio TIME,
    hora_fim TIME,
    local_nome VARCHAR(200),
    local_endereco TEXT,
    local_cidade VARCHAR(100),
    orcamento_total DECIMAL(12,2) DEFAULT 0,
    numero_convidados_esperado INT,
    codigo_evento VARCHAR(20) UNIQUE NOT NULL,
    qr_code_path VARCHAR(255),
    status ENUM('rascunho', 'ativo', 'em_andamento', 'concluido', 'cancelado') DEFAULT 'rascunho',
    pago BOOLEAN DEFAULT FALSE,
    data_pagamento DATETIME,
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id) REFERENCES planos(id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_status (status),
    INDEX idx_data_evento (data_evento),
    INDEX idx_codigo (codigo_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Categorias de Despesas
-- ============================================
CREATE TABLE categorias_despesas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    icone VARCHAR(50),
    cor VARCHAR(7),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias_despesas (nome, icone, cor) VALUES
('Fotografia', 'camera', '#3498db'),
('DJ/Música', 'music', '#e74c3c'),
('Decoração', 'palette', '#9b59b6'),
('Catering/Buffet', 'utensils', '#f39c12'),
('Bolos e Doces', 'birthday-cake', '#e67e22'),
('Segurança', 'shield', '#34495e'),
('Transporte', 'car', '#16a085'),
('Convites Físicos', 'envelope', '#95a5a6'),
('Flores', 'leaf', '#27ae60'),
('Local/Aluguel', 'building', '#2c3e50'),
('Outros', 'ellipsis-h', '#7f8c8d');

-- ============================================
-- TABELA: Despesas do Evento
-- ============================================
CREATE TABLE despesas_evento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    categoria_id INT NOT NULL,
    descricao VARCHAR(200) NOT NULL,
    fornecedor VARCHAR(150),
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento DATE,
    status_pagamento ENUM('pendente', 'pago', 'atrasado', 'cancelado') DEFAULT 'pendente',
    metodo_pagamento VARCHAR(50),
    comprovante VARCHAR(255),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias_despesas(id),
    INDEX idx_evento (evento_id),
    INDEX idx_status (status_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Fornecedores do Evento
-- ============================================
CREATE TABLE fornecedores_evento (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    nome_responsavel VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    telefone VARCHAR(20),
    senha VARCHAR(255),
    empresa VARCHAR(150),
    descricao_servico TEXT,
    valor_contratado DECIMAL(10,2),
    codigo_acesso VARCHAR(20) UNIQUE,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_evento (evento_id),
    INDEX idx_codigo (codigo_acesso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Equipe dos Fornecedores
-- ============================================
CREATE TABLE equipe_fornecedor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fornecedor_id INT NOT NULL,
    nome_completo VARCHAR(150) NOT NULL,
    funcao VARCHAR(100),
    telefone VARCHAR(20),
    documento VARCHAR(50),
    foto VARCHAR(255),
    horario_entrada TIME,
    horario_saida TIME,
    presente BOOLEAN DEFAULT FALSE,
    hora_checkin DATETIME,
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores_evento(id) ON DELETE CASCADE,
    INDEX idx_fornecedor (fornecedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Convites
-- ============================================
CREATE TABLE convites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    evento_id INT NOT NULL,
    codigo_convite VARCHAR(20) UNIQUE NOT NULL,
    nome_convidado1 VARCHAR(150) NOT NULL,
    telefone1 VARCHAR(20),
    email1 VARCHAR(150),
    nome_convidado2 VARCHAR(150),
    telefone2 VARCHAR(20),
    email2 VARCHAR(150),
    tipo_convidado ENUM('vip', 'normal', 'familia', 'amigo', 'trabalho') DEFAULT 'normal',
    mesa_numero VARCHAR(20),
    presente_convidado1 BOOLEAN DEFAULT FALSE,
    presente_convidado2 BOOLEAN DEFAULT FALSE,
    hora_checkin1 DATETIME,
    hora_checkin2 DATETIME,
    observacoes TEXT,
    qr_code_path VARCHAR(255),
    enviado_email BOOLEAN DEFAULT FALSE,
    enviado_whatsapp BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    INDEX idx_evento (evento_id),
    INDEX idx_codigo (codigo_convite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Pagamentos
-- ============================================
CREATE TABLE pagamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    evento_id INT,
    plano_id INT NOT NULL,
    referencia VARCHAR(50) UNIQUE NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    moeda VARCHAR(3) DEFAULT 'AOA',
    metodo_pagamento ENUM('express', 'referencia', 'paypal', 'transferencia', 'multicaixa') NOT NULL,
    status ENUM('pendente', 'processando', 'aprovado', 'rejeitado', 'cancelado', 'expirado') DEFAULT 'pendente',
    dados_pagamento JSON,
    comprovante VARCHAR(255),
    data_vencimento DATETIME,
    data_aprovacao DATETIME,
    ip_address VARCHAR(45),
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (evento_id) REFERENCES eventos(id),
    FOREIGN KEY (plano_id) REFERENCES planos(id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_referencia (referencia),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Logs de Acesso
-- ============================================
CREATE TABLE logs_acesso (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_tipo ENUM('admin', 'cliente', 'fornecedor') NOT NULL,
    usuario_id INT NOT NULL,
    acao VARCHAR(100) NOT NULL,
    descricao TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_tipo, usuario_id),
    INDEX idx_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Notificações
-- ============================================
CREATE TABLE notificacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_tipo ENUM('admin', 'cliente', 'fornecedor') NOT NULL,
    usuario_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('info', 'sucesso', 'alerta', 'erro') DEFAULT 'info',
    link VARCHAR(255),
    lida BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_tipo, usuario_id),
    INDEX idx_lida (lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: Configurações do Sistema
-- ============================================
CREATE TABLE configuracoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    tipo VARCHAR(50),
    descricao TEXT,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor, tipo, descricao) VALUES
('site_nome', 'eXDigital Pro', 'text', 'Nome do sistema'),
('site_email', 'contato@exdigital.com', 'email', 'Email de contato'),
('site_telefone', '+244 948 005 566', 'text', 'Telefone de contato'),
('moeda_padrao', 'AOA', 'text', 'Moeda padrão do sistema'),
('taxa_express', '2.5', 'number', 'Taxa de pagamento Express (%)'),
('taxa_referencia', '1.5', 'number', 'Taxa de pagamento por Referência (%)'),
('dias_expiracao_pagamento', '3', 'number', 'Dias para expirar pagamento pendente'),
('email_smtp_host', '', 'text', 'Servidor SMTP'),
('email_smtp_port', '587', 'number', 'Porta SMTP'),
('email_smtp_user', '', 'email', 'Usuário SMTP'),
('email_smtp_pass', '', 'password', 'Senha SMTP'),
('paypal_client_id', '', 'text', 'PayPal Client ID'),
('paypal_secret', '', 'password', 'PayPal Secret'),
('express_api_key', '', 'password', 'Express API Key'),
('multicaixa_entity', '', 'text', 'Entidade Multicaixa'),
('manutencao_ativo', '0', 'boolean', 'Modo manutenção ativo');

-- ============================================
-- VIEWS ÚTEIS
-- ============================================

-- View de Estatísticas por Evento
CREATE VIEW vw_estatisticas_evento AS
SELECT 
    e.id,
    e.codigo_evento,
    e.nome_evento,
    e.data_evento,
    COUNT(DISTINCT c.id) as total_convites,
    SUM(CASE WHEN c.nome_convidado2 IS NOT NULL THEN 2 ELSE 1 END) as total_convidados,
    SUM(CASE WHEN c.presente_convidado1 = 1 THEN 1 ELSE 0 END + 
        CASE WHEN c.presente_convidado2 = 1 THEN 1 ELSE 0 END) as total_presentes,
    COUNT(DISTINCT f.id) as total_fornecedores,
    COUNT(DISTINCT ef.id) as total_equipe,
    COALESCE(SUM(d.valor), 0) as total_despesas,
    COALESCE(SUM(CASE WHEN d.status_pagamento = 'pago' THEN d.valor ELSE 0 END), 0) as despesas_pagas
FROM eventos e
LEFT JOIN convites c ON e.id = c.evento_id
LEFT JOIN fornecedores_evento f ON e.id = f.evento_id
LEFT JOIN equipe_fornecedor ef ON f.id = ef.fornecedor_id
LEFT JOIN despesas_evento d ON e.id = d.evento_id
GROUP BY e.id;

-- View de Dashboard Financeiro
CREATE VIEW vw_dashboard_financeiro AS
SELECT 
    DATE_FORMAT(p.criado_em, '%Y-%m') as mes_ano,
    COUNT(DISTINCT p.id) as total_pagamentos,
    COUNT(DISTINCT CASE WHEN p.status = 'aprovado' THEN p.id END) as pagamentos_aprovados,
    SUM(p.valor) as valor_total,
    SUM(CASE WHEN p.status = 'aprovado' THEN p.valor ELSE 0 END) as valor_aprovado,
    SUM(CASE WHEN p.status = 'pendente' THEN p.valor ELSE 0 END) as valor_pendente
FROM pagamentos p
GROUP BY DATE_FORMAT(p.criado_em, '%Y-%m')
ORDER BY mes_ano DESC;

-- ============================================
-- TRIGGERS
-- ============================================

DELIMITER $$

-- Atualizar status do evento após pagamento
CREATE TRIGGER trg_pagamento_aprovado
AFTER UPDATE ON pagamentos
FOR EACH ROW
BEGIN
    IF NEW.status = 'aprovado' AND OLD.status != 'aprovado' THEN
        UPDATE eventos 
        SET pago = TRUE, data_pagamento = NOW(), status = 'ativo'
        WHERE id = NEW.evento_id;
    END IF;
END$$

-- Gerar código único para evento
CREATE TRIGGER trg_gerar_codigo_evento
BEFORE INSERT ON eventos
FOR EACH ROW
BEGIN
    IF NEW.codigo_evento IS NULL OR NEW.codigo_evento = '' THEN
        SET NEW.codigo_evento = CONCAT('EVT-', YEAR(NEW.data_evento), '-', LPAD(FLOOR(RAND() * 999999), 6, '0'));
    END IF;
END$$

-- Gerar código único para convite
CREATE TRIGGER trg_gerar_codigo_convite
BEFORE INSERT ON convites
FOR EACH ROW
BEGIN
    IF NEW.codigo_convite IS NULL OR NEW.codigo_convite = '' THEN
        SET NEW.codigo_convite = CONCAT('CNV-', LPAD(FLOOR(RAND() * 9999999), 7, '0'));
    END IF;
END$$

DELIMITER ;

-- ============================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ============================================

CREATE INDEX idx_eventos_cliente_status ON eventos(cliente_id, status);
CREATE INDEX idx_convites_evento_presenca ON convites(evento_id, presente_convidado1, presente_convidado2);
CREATE INDEX idx_pagamentos_status_data ON pagamentos(status, criado_em);
CREATE INDEX idx_logs_data ON logs_acesso(criado_em);

-- ============================================
-- FIM DO SCRIPT
-- ============================================