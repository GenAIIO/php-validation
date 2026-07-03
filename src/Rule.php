<?php

namespace GenAI\Validation;

/**
 * Contract every custom validator must implement — the logic behind a custom
 * constraint whose rule() names it in its 'validator' key. The Validator new's the
 * class and calls validate(); the ValidationProcessor checks at BUILD time that the
 * named class implements this interface, so a typo or a wrong class fails the
 * compile instead of silently passing at runtime.
 *
 *   class PhoneRule implements \GenAI\Validation\Rule {
 *       public function validate($value, $rule, $object) {
 *           return preg_match('/^\+?[0-9]{7,15}$/', $value) ? null : 'valid.phone';
 *       }
 *   }
 *
 * Implementations are instantiated with `new` (no DI), so keep them self-contained
 * with no constructor dependencies. Runtime contract — PHP 5.3-safe (no type hints).
 */
interface Rule
{
    /**
     * @param mixed  $value  the field value (never empty — the Validator
     *                       short-circuits null/'' so #[NotBlank] owns "required")
     * @param array  $rule   the baked rule array (type, message, any custom keys)
     * @param object $object the whole form object, for cross-field checks
     * @return string|null   null/true when valid; else an error message or i18n key
     */
    public function validate($value, $rule, $object);
}
