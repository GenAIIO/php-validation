# genai/validation

Attribute-based validation, **compiled to a reflection-free validator** — the same
build-time pattern as `genai/dto` / `genai/sql-mapper`.

## Define a form
```php
use GenAI\Validation\Attribute\Validate;
use GenAI\Validation\Attribute\NotBlank;
use GenAI\Validation\Attribute\Email;
use GenAI\Validation\Attribute\Length;
use GenAI\Validation\Attribute\Matches;

#[Validate]
class SignupForm
{
    #[NotBlank]                 public $name;
    #[NotBlank] #[Email]        public $email;
    #[Length(min: 6)]           public $password;
    #[Matches('password')]      public $passwordConfirm;
}
```
The `#[...]` lines are build-only (comments on PHP 5.3).

**Public or private fields.** Public properties are read directly. A `private`/
`protected` field is read through a `getXxx()`/`isXxx()` getter (auto-detected at
compile; a private field without one is a build error) — same approach as `#[Dto]`.
Note the *runtime* still has to populate the form, so a private field also needs a
setter or constructor; public props are the least ceremony.

## Use it
```php
// bind() populates declared fields from the request (public prop set directly,
// private via its setXxx() — where the form can normalize), then validate().
$form   = $validator->bind(new SignupForm(), $request->getParsedBody());
$errors = $validator->validate($form);   // array('email' => 'Please enter a valid email address.')
if (!$errors) {
    // valid — use $form->getEmail(), etc.
}
```
`bind()` only touches declared fields (no mass-assignment of stray request keys)
and is reflection-free (it uses the same compiled field map as `validate()`). Put
trimming/normalization in the form's setters and it runs automatically on bind.
Inject `GenAI\Validation\Validator` — it's auto-wired (the package's bundle provides
the bean as the compiled `Cache\Validation`). One first message per field.

## Constraints
| Attribute | Checks |
|---|---|
| `#[NotBlank]` | not null / not empty (after trim) |
| `#[Email]` | valid email |
| `#[Length(min, max)]` | string length in range (either bound optional) |
| `#[Pattern('/regex/')]` | matches a PCRE regex |
| `#[Matches('otherField')]` | equals another field (e.g. password confirm) |

All except `#[NotBlank]` **pass an empty value**, so combine `#[NotBlank] #[Email]`
to both require *and* format-check. Every constraint takes an optional
`message:` to override the default.

## How it compiles
`ValidationProcessor` reads each `#[Validate]` class's property constraints (matched
via the `Constraint` base + `IS_INSTANCEOF`) and bakes a rule table into
`Cache\Validation extends GenAI\Validation\Validator`. At runtime the `Validator`
reads the form's public props and walks that table with a `type` switch — no
reflection. `Cache\Validation` is always generated (empty when no forms), so the
bean always resolves.
