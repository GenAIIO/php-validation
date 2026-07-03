<?php

namespace GenAI\Validation\Attribute;

use GenAI\Validation\Processor\Constraint;

/**
 * The value must not be null/empty (after trim). The one constraint that fails on
 * an empty value — every other constraint passes empties so they can be combined.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class NotBlank extends Constraint
{
    public function rule(): array
    {
        return array('type' => 'notblank', 'message' => $this->message);
    }
}
