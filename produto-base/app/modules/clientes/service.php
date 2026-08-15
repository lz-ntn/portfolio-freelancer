<?php
/**
 * ClienteService — lógica de negócio do módulo de clientes.
 *
 * Separado do controller para ser testável: recebe um PDO injectado,
 * portanto nos testes podemos usar uma base de testes dedicada.
 */

class ClienteService
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listAll(string $term = ''): array
    {
        $sql = 'SELECT id, nome, email, telefone, cidade, observacoes, created_at
                FROM clientes';
        $params = [];

        if ($term !== '') {
            $sql .= ' WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ?';
            $like = '%' . $term . '%';
            $params = [$like, $like, $like];
        }

        $sql .= ' ORDER BY nome ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM clientes WHERE id = ?');
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();
        return $cliente ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO clientes (nome, email, telefone, cidade, observacoes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['nome'],
            $data['email'] ?? '',
            $data['telefone'] ?? '',
            $data['cidade'] ?? '',
            $data['observacoes'] ?? '',
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE clientes
             SET nome = ?, email = ?, telefone = ?, cidade = ?, observacoes = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['nome'],
            $data['email'] ?? '',
            $data['telefone'] ?? '',
            $data['cidade'] ?? '',
            $data['observacoes'] ?? '',
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM clientes WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
