CREATE DATABASE IF NOT EXISTS meu_projeto
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE meu_projeto;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Inserir admin padrão (password: admin123)
INSERT INTO users (nome, email, password_hash, role) VALUES
('Administrador', 'admin@exemplo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
