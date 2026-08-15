<?php
/**
 * Controller do módulo de clientes.
 * Acções: list, form, save, delete
 */

function clientes_list(): void
{
    requireAuth();

    $svc = new ClienteService(getDB());
    $clientes = $svc->listAll(sanitize($_GET['q'] ?? ''));

    require APP_ROOT . '/app/modules/clientes/views/list.php';
}

function clientes_form(): void
{
    requireAuth();

    $id = (int)($_GET['id'] ?? 0);
    $cliente = [];

    if ($id > 0) {
        $svc = new ClienteService(getDB());
        $cliente = $svc->find($id);
        if (!$cliente) {
            flash('error', 'Cliente não encontrado.');
            redirect('index.php?page=clientes');
        }
    }

    require APP_ROOT . '/app/modules/clientes/views/form.php';
}

function clientes_save(): void
{
    requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php?page=clientes');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token inválido. Tenta novamente.');
        redirect('index.php?page=clientes&action=form');
    }

    $id = (int)($_POST['id'] ?? 0);

    $errors = validate($_POST, [
        'nome'   => ['required', 'max:150'],
        'email'  => ['email', 'max:255'],
        'telefone' => ['max:30'],
        'cidade' => ['max:100'],
    ]);

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('index.php?page=clientes&action=form' . ($id > 0 ? '&id=' . $id : ''));
    }

    $data = [
        'nome'        => sanitize($_POST['nome']),
        'email'       => sanitize($_POST['email']),
        'telefone'    => sanitize($_POST['telefone']),
        'cidade'      => sanitize($_POST['cidade']),
        'observacoes' => trim($_POST['observacoes'] ?? ''),
    ];

    $svc = new ClienteService(getDB());
    if ($id > 0) {
        $svc->update($id, $data);
        flash('success', 'Cliente atualizado.');
    } else {
        $svc->create($data);
        flash('success', 'Cliente criado.');
    }

    redirect('index.php?page=clientes');
}

function clientes_delete(): void
{
    requireAuth();
    if (!can(currentUser() ?? [], 'clientes.delete')) {
        flash('error', 'Sem permissão para remover clientes.');
        redirect('index.php?page=clientes');
    }

    $id = (int)($_GET['id'] ?? 0);
    $svc = new ClienteService(getDB());
    $svc->delete($id);
    flash('success', 'Cliente removido.');

    redirect('index.php?page=clientes');
}
