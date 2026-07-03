<?php

namespace GenAI\Validation\Attribute;

/**
 * Marks a form/DTO class for validation. Its public properties carry the
 * constraints (#[NotBlank], #[Email], ...); ValidationProcessor compiles them into
 * Cache\Validation, and Validator->validate($form) checks a populated instance.
 *
 *   #[Validate]
 *   class SignupForm {
 *       #[NotBlank]            public $name;
 *       #[NotBlank] #[Email]   public $email;
 *       #[Length(min: 6)]      public $password;
 *   }
 *
 * BUILD-TIME ONLY (PHP 8); a comment on the PHP 5.3 runtime.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Validate
{
}
