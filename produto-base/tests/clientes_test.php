<?php
/**
 * Testes de integração do módulo de clientes.
 * $pdo já aponta para a base de testes (ver tests/run.php).
 */

$svc = new ClienteService($pdo);

// 1. Criar
$id = $svc->create([
    'nome'   => 'Maria Silva',
    'email'  => 'maria@exemplo.com',
    'telefone' => '+351 912 345 678',
    'cidade' => 'Porto',
]);
assertTrue($id > 0, 'create() devolve um id > 0');
assertEquals('maria@exemplo.com', $svc->find($id)['email'], 'find() lê o email criado');

// 2. Validar dados por defeito (campos vazios)
$id2 = $svc->create(['nome' => 'João']);
$row = $svc->find($id2);
assertEquals('', $row['telefone'], 'create() preenche campos em falta com vazio');

// 3. Atualizar
$ok = $svc->update($id, [
    'nome'   => 'Maria Silva Atualizada',
    'email'  => 'maria@novo.com',
    'telefone' => '',
    'cidade' => 'Lisboa',
    'observacoes' => '',
]);
assertTrue($ok, 'update() devolve true');
assertEquals('Maria Silva Atualizada', $svc->find($id)['nome'], 'update() altera o nome');

// 4. Listar e pesquisar
$all = $svc->listAll();
assertEquals(2, count($all), 'listAll() devolve os 2 clientes');
$found = $svc->listAll('maria@novo');
assertEquals(1, count($found), 'listAll() pesquisa por termo');

// 5. Eliminar
assertTrue($svc->delete($id), 'delete() devolve true');
assertEquals(null, $svc->find($id), 'find() devolve null após delete');
assertEquals(1, count($svc->listAll()), 'listAll() reflecte o delete');
