<?php

namespace GenAI\Validation\Attribute;

use GenAI\Validation\Processor\Constraint;

/**
 * The value must match a PCRE regex, e.g. #[Pattern('/^[a-z0-9_]+$/i')]. Passes
 * when empty (combine with #[NotBlank] to require it).
 *
 * BUILD-TIME ONLY (PHP 8).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Pattern extends Constraint
{
    public function __construct(public string $regex, ?string $message = null)
    {
        parent::__construct($message);
    }

    public function rule(): array
    {
        return array('type' => 'pattern', 'regex' => $this->regex, 'message' => $this->message);
    }
}
