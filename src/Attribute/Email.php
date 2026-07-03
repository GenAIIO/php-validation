<?php

namespace GenAI\Validation\Attribute;

use GenAI\Validation\Processor\Constraint;

/**
 * The value must be a valid email address (filter_var FILTER_VALIDATE_EMAIL).
 * Passes when empty — combine with #[NotBlank] to also require it.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Email extends Constraint
{
    public function rule(): array
    {
        return array('type' => 'email', 'message' => $this->message);
    }
}
