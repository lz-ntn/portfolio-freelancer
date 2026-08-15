<?php
/**
 * Validação de formulários.
 *
 * Uso:
 *   $errors = validate($_POST, [
 *       'nome'  => ['required', 'max:150'],
 *       'email' => ['email', 'max:255'],
 *   ]);
 *
 * Regras disponíveis: required, email, max:N, min:N, numeric
 * Devolve um array ['campo' => 'mensagem'] vazio quando passa.
 */

function validate(array $data, array $rules): array
{
    $errors = [];

    foreach ($rules as $field => $fieldRules) {
        $value = $data[$field] ?? '';

        foreach ($fieldRules as $rule) {
            $error = validateField($field, $value, $rule);
            if ($error !== null) {
                $errors[$field] = $error;
                break;
            }
        }
    }

    return $errors;
}

function validateField(string $field, $value, string $rule): ?string
{
    if (strpos($rule, ':') !== false) {
        [$name, $param] = explode(':', $rule, 2);
    } else {
        $name = $rule;
        $param = null;
    }

    switch ($name) {
        case 'required':
            return trim((string)$value) === '' ? 'Campo obrigatório.' : null;

        case 'email':
            if (trim((string)$value) === '') {
                return null;
            }
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Email inválido.';

        case 'max':
            return mb_strlen((string)$value) > (int)$param ? 'Máximo de ' . $param . ' caracteres.' : null;

        case 'min':
            return mb_strlen((string)$value) < (int)$param ? 'Mínimo de ' . $param . ' caracteres.' : null;

        case 'numeric':
            return is_numeric($value) ? null : 'Deve ser um número.';

        default:
            return null;
    }
}
