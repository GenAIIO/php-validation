<?php

namespace GenAI\Validation\Processor;

/**
 * Base for the constraint attributes (#[NotBlank], #[Email], #[Length], ...).
 * Each subclass returns its rule() — a plain array (type + params + message) that
 * the processor bakes into Cache\Validation and the runtime Validator interprets.
 *
 * Not an attribute itself (only its subclasses are #[\Attribute]); it's processor
 * plumbing, so it lives next to the processor. BUILD-TIME ONLY (PHP 8); never
 * loaded on the PHP 5.3 runtime.
 */
class Constraint
{
    public function __construct(public ?string $message = null)
    {
    }

    /**
     * @return array the rule as data: array('type' => ..., 'message' => ..., ...params)
     */
    public function rule(): array
    {
        return array('message' => $this->message);
    }
}
