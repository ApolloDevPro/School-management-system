<?php
declare(strict_types=1);

final class Validator
{
    private array $data;
    private array $rules = [];
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function rule(string $field, string $ruleSet): self
    {
        $this->rules[$field] = explode('|', $ruleSet);
        return $this;
    }

    public function rules(array $fieldRules): self
    {
        foreach ($fieldRules as $field => $ruleSet) {
            $this->rule($field, $ruleSet);
        }
        return $this;
    }

    public function passes(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            $isRequired = in_array('required', $rules, true);

            if ($isRequired && ($value === null || $value === '' || $value === [])) {
                $this->errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                continue;
            }

            if (!$isRequired && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === '') {
                    continue;
                }

                $result = $this->applyRule($field, $value, $rule);
                if ($result !== true) {
                    $this->errors[$field] = $result;
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return empty($this->errors);
    }
    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(string $field = null): mixed
    {
        if ($field === null) {
            return $this->validated;
        }
        return $this->validated[$field] ?? null;
    }

    private function applyRule(string $field, mixed $value, string $rule): bool|string
    {
        $ruleName = $rule;
        $ruleParams = [];
        if (str_contains($rule, ':')) {
            [$ruleName, $paramStr] = explode(':', $rule, 2);
            $ruleParams = explode(',', $paramStr);
        }

        return match ($ruleName) {
            'string' => $this->validateString($value),
            'integer' => $this->validateInteger($value),
            'numeric' => $this->validateNumeric($value),
            'email' => $this->validateEmail($value),
            'min' => $this->validateMin($value, $ruleParams),
            'max' => $this->validateMax($value, $ruleParams),
            'in' => $this->validateIn($value, $ruleParams),
            'boolean' => $this->validateBoolean($value),
            'alpha' => $this->validateAlpha($value),
            'alpha_num' => $this->validateAlphaNum($value),
            'date' => $this->validateDate($value),
            default => true,
        };
    }

    private function validateString(mixed $value): bool|string
    {
        if (!is_string($value)) {
            return 'Must be a string.';
        }
        return true;
    }

    private function validateInteger(mixed $value): bool|string
    {
        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return true;
        }
        return 'Must be a valid integer.';
    }

    private function validateNumeric(mixed $value): bool|string
    {
        if (is_numeric($value)) {
            return true;
        }
        return 'Must be numeric.';
    }

    private function validateEmail(mixed $value): bool|string
    {
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        return 'Must be a valid email address.';
    }

    private function validateMin(mixed $value, array $params): bool|string
    {
        if (empty($params)) {
            return true;
        }
        $min = (int)$params[0];

        if (is_string($value)) {
            if (mb_strlen($value) >= $min) {
                return true;
            }
            return 'Minimum length is ' . $min . ' characters.';
        }

        if (is_numeric($value)) {
            if ((float)$value >= $min) {
                return true;
            }
            return 'Minimum value is ' . $min . '.';
        }

        return true;
    }

    private function validateMax(mixed $value, array $params): bool|string
    {
        if (empty($params)) {
            return true;
        }
        $max = (int)$params[0];

        if (is_string($value)) {
            if (mb_strlen($value) <= $max) {
                return true;
            }
            return 'Maximum length is ' . $max . ' characters.';
        }

        if (is_numeric($value)) {
            if ((float)$value <= $max) {
                return true;
            }
            return 'Maximum value is ' . $max . '.';
        }

        return true;
    }

    private function validateIn(mixed $value, array $params): bool|string
    {
        if (empty($params)) {
            return true;
        }
        if (in_array($value, $params, true)) {
            return true;
        }
        return 'Must be one of: ' . implode(', ', $params) . '.';
    }

    private function validateBoolean(mixed $value): bool|string
    {
        if (in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on'], true)) {
            return true;
        }
        return 'Must be a boolean value.';
    }

    private function validateAlpha(mixed $value): bool|string
    {
        if (is_string($value) && ctype_alpha((string)$value)) {
            return true;
        }
        return 'Must contain only alphabetic characters.';
    }

    private function validateAlphaNum(mixed $value): bool|string
    {
        if (is_string($value) && ctype_alnum((string)$value)) {
            return true;
        }
        return 'Must contain only alphanumeric characters.';
    }

    private function validateDate(mixed $value): bool|string
    {
        if (!is_string($value)) {
            return 'Must be a valid date (YYYY-MM-DD).';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 'Must be a valid date (YYYY-MM-DD).';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return 'Must be a valid date (YYYY-MM-DD).';
        }
        return true;
    }
}