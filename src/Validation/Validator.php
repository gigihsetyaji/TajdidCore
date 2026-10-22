<?php
declare(strict_types=1);
namespace Tajdid\Core\Validation;
final class Validator
{
    /** Returns only fields present in the rule set. Length limits count bytes. */
    public function validate(array $input, array $rules): array
    {
        $errors = $valid = [];
        foreach ($rules as $field => $definition) {
            $checks = is_array($definition) ? $definition : explode('|', $definition);
            $present = array_key_exists($field, $input);
            $value = $input[$field] ?? null;
            foreach ($checks as $check) {
                [$rule, $limit] = array_pad(explode(':', $check, 2), 2, null);
                if (!in_array($rule, ['required', 'nullable', 'string', 'integer', 'email', 'min', 'max', 'in'], true)) {
                    throw new \InvalidArgumentException("Unknown validation rule: $rule");
                }
                if (in_array($rule, ['min', 'max'], true) && ($limit === null || !ctype_digit($limit))) {
                    throw new \InvalidArgumentException('Length limits must be non-negative integers');
                }
                if ($rule === 'in' && $limit === null) { throw new \InvalidArgumentException('in requires allowed values'); }
                if ($rule !== 'required' && (!$present || ($value === null && in_array('nullable', $checks, true)))) { continue; }
                $ok = match ($rule) {
                    'required' => $present && $value !== null && $value !== '' && $value !== [],
                    'nullable' => true,
                    'string' => is_string($value),
                    'integer' => is_int($value) || (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false),
                    'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'min' => is_string($value) && strlen($value) >= (int) $limit,
                    'max' => is_string($value) && strlen($value) <= (int) $limit,
                    'in' => is_string($value) && in_array($value, explode(',', $limit), true),
                };
                if (!$ok) { $errors[$field][] = $rule; }
            }
            if ($present) { $valid[$field] = $value; }
        }
        if ($errors) { throw new ValidationException($errors); }
        return $valid;
    }
}
