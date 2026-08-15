-- Migração 001: schema inicial
-- Regra: nunca alterar à mão a base de produção; mudanças = novo ficheiro.

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    telefone VARCHAR(30) NOT NULL DEFAULT '',
    cidade VARCHAR(100) NOT NULL DEFAULT '',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Admin padrão (password: admin123)
INSERT INTO users (nome, email, password_hash, role) VALUES
('Administrador', 'admin@exemplo.com', '$2y$10$qzLygRqQCzQNgyYvz9w9Au6Y56Kvj20w7THs8jd8JZhTlBj9Vl5LG', 'admin');
