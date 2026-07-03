<?php

namespace GenAI\Validation\Attribute;

use GenAI\Validation\Processor\Constraint;

/**
 * The string length must be within [min, max] (either bound optional), e.g.
 * #[Length(min: 6)] or #[Length(min: 2, max: 50)]. Passes when empty.
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length extends Constraint
{
    public function __construct(public ?int $min = null, public ?int $max = null, ?string $message = null)
    {
        parent::__construct($message);
    }

    public function rule(): array
    {
        return array('type' => 'length', 'min' => $this->min, 'max' => $this->max, 'message' => $this->message);
    }
}
