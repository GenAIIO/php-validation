<?php

namespace GenAI\Validation\Bundle;

use GenAI\Di\Bean;
use GenAI\Di\Configuration;
use GenAI\Validation\Validator;

/**
 * Auto-wires the Validator bean as the compiled Cache\Validation (the rule table
 * baked from your #[Validate] forms). Discovered via extra.genai.scan, so an app
 * just `require genai/validation` and injects GenAI\Validation\Validator.
 *
 * Cache\Validation is always emitted by the processor (empty when there are no
 * forms), so this bean always resolves. Runtime class (PHP 5.3-safe); the #[...]
 * lines are comments on 5.3.
 */
#[Configuration]
class ValidationConfig
{
    #[Bean(Validator::class)]
    public function validator()
    {
        return new \Cache\Validation();
    }
}
