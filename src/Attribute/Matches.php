<?php

namespace GenAI\Validation\Attribute;

use GenAI\Validation\Processor\Constraint;

/**
 * The value must equal another field on the same form — e.g. a password confirm:
 * #[Matches('password')] on $passwordConfirm. Passes when empty.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Matches extends Constraint
{
    public function __construct(public string $field, ?string $message = null)
    {
        parent::__construct($message);
    }

    public function rule(): array
    {
        return array('type' => 'matches', 'field' => $this->field, 'message' => $this->message);
    }
}
