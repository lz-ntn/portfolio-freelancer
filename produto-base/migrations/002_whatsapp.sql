-- Migração 002: módulo WhatsApp Business
-- Regra: nunca alterar à mão a base de produção; mudanças = novo ficheiro.
-- NOTA: direction/status são VARCHAR (sem ENUM) para portabilidade de testes.

CREATE TABLE IF NOT EXISTS conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    numero_e164 VARCHAR(20) NOT NULL UNIQUE,   -- ex.: +351912345678
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conv_cli FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    INDEX idx_conv_cli (cliente_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    external_id VARCHAR(64) NULL UNIQUE,      -- id da Meta (idempotência)
    direction VARCHAR(10) NOT NULL DEFAULT 'out',  -- 'in' | 'out'
    text TEXT NOT NULL,
    is_template TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(10) NOT NULL DEFAULT 'pending', -- 'pending'|'sent'|'delivered'|'read'|'failed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversa_id) REFERENCES conversas(id),
    INDEX idx_msg_conv (conversa_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,        -- nome aprovado na Meta
    language_code VARCHAR(10) NOT NULL DEFAULT 'pt_PT',
    exemplo VARCHAR(255) NOT NULL DEFAULT '',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Template de exemplo (deve existir aprovado na Meta antes de usar em produção)
INSERT IGNORE INTO whatsapp_templates (nome, language_code, exemplo) VALUES
('follow_up', 'pt_PT', 'Olá {{1}}! Seguimento do teu pedido...');
