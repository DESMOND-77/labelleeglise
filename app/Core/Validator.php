<?php

namespace App\Core;

/**
 * Validation centralisée des données de formulaire.
 */
class Validator
{
    private array $errors = [];

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** @param array $rules ['champ' => ['required', 'email', ...]] */
    public function validate(array $data, array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ((array) $fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
    }

    private function applyRule(string $field, $value, string $rule): void
    {
        if ($rule === 'required' && ($value === null || $value === '')) {
            $this->errors[$field][] = 'Le champ "' . $field . '" est obligatoire.';
            return;
        }
        if ($rule === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'Adresse email invalide.';
        }
    }
}
