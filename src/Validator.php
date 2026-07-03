<?php

namespace GenAI\Validation;

/**
 * Validates a populated form object against rules baked at build time. The
 * compiled Cache\Validation subclass calls setRules() with the rule table; this
 * base applies the checks — driven by a plain array + a type switch, so there is
 * no runtime reflection.
 *
 *   $form = new SignupForm();
 *   $form->email = $post['email']; ...
 *   $errors = $validator->validate($form);   // array('email' => 'Please enter a valid email address.')
 *   if (!$errors) { ...all good... }
 *
 * Reads each field by the accessor the processor baked in: a public property
 * directly, or a getXxx()/isXxx() getter for a private/protected one — no
 * reflection. One (first) message per field. Compatible with PHP 5.3.29.
 */
class Validator
{
    /** @var array class => (field => list of rule arrays) */
    private $rules = array();

    public function setRules($rules)
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * @param object $object a populated form object
     * @return array field => error message (empty when valid)
     */
    public function validate($object)
    {
        $class  = get_class($object);
        $errors = array();
        if (!isset($this->rules[$class])) {
            return $errors;
        }

        foreach ($this->rules[$class] as $field => $spec) {
            $getter = isset($spec['getter']) ? $spec['getter'] : null;
            $value  = $this->read($object, $field, $getter);
            foreach ($spec['rules'] as $constraint) {
                $message = $this->check($constraint, $value, $object);
                if ($message !== null) {
                    $errors[$field] = $message;
                    break; // first failure per field
                }
            }
        }

        return $errors;
    }

    /**
     * Populate a form object from request data, keyed by field name. Only the
     * declared fields are bound (no mass-assignment of stray keys): a public field
     * is set directly; a private one goes through its setXxx() setter (where the
     * form can normalize — trim, lowercase, etc.). Reflection-free.
     *
     * @param object $object a fresh form instance
     * @param mixed  $data   request body (array; anything else is ignored)
     * @return object the same object, populated
     */
    public function bind($object, $data)
    {
        $class = get_class($object);
        if (!isset($this->rules[$class]) || !is_array($data)) {
            return $object;
        }

        foreach ($this->rules[$class] as $field => $spec) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $getter = isset($spec['getter']) ? $spec['getter'] : null;
            if ($getter === null) {
                $object->$field = $data[$field];          // public property
            } else {
                $setter = 'set' . ucfirst($field);        // private -> setter (may normalize)
                if (method_exists($object, $setter)) {
                    $object->$setter($data[$field]);
                }
            }
        }

        return $object;
    }

    /** Read a field: via its compiled getter (private prop) or directly (public). */
    private function read($object, $field, $getter)
    {
        if ($getter !== null && $getter !== '') {
            return $object->$getter();
        }
        return isset($object->$field) ? $object->$field : null;
    }

    /**
     * Resolve a message for output. If genai/i18n is present, the message is run
     * through the global __() — so a constraint's message: can be a translation
     * key (e.g. 'valid.email'), and the built-in defaults are keys too. When the
     * key isn't translated (or i18n isn't installed), $fallback (or the message
     * itself) is used, with :name / {name} placeholders interpolated either way.
     */
    private function tr($message, $params = array(), $fallback = null)
    {
        $text = function_exists('__') ? __($message, $params) : null;

        if ($text === null || $text === $message) {   // no i18n, or key not found
            $text = ($fallback !== null) ? $fallback : $message;
            foreach ($params as $name => $value) {
                $text = str_replace(array(':' . $name, '{' . $name . '}'), array($value, $value), $text);
            }
        }

        return $text;
    }

    /**
     * Run one rule. Returns an error string, or null when the value passes.
     */
    private function check($c, $value, $object)
    {
        $type    = isset($c['type']) ? $c['type'] : '';
        $message = isset($c['message']) ? $c['message'] : null;

        if ($type === 'notblank') {
            if ($value === null || trim((string) $value) === '') {
                return $message !== null
                    ? $this->tr($message)
                    : $this->tr('validation.required', array(), 'This field is required.');
            }
            return null;
        }

        // Every other rule passes an empty value (let #[NotBlank] own emptiness).
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === 'email') {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                return null;
            }
            return $message !== null
                ? $this->tr($message)
                : $this->tr('validation.email', array(), 'Please enter a valid email address.');
        }

        if ($type === 'length') {
            $len    = strlen((string) $value);
            $params = array('min' => isset($c['min']) ? $c['min'] : '', 'max' => isset($c['max']) ? $c['max'] : '');
            if (isset($c['min']) && $c['min'] !== null && $len < $c['min']) {
                return $message !== null
                    ? $this->tr($message, $params)
                    : $this->tr('validation.length_min', $params, 'Must be at least :min characters.');
            }
            if (isset($c['max']) && $c['max'] !== null && $len > $c['max']) {
                return $message !== null
                    ? $this->tr($message, $params)
                    : $this->tr('validation.length_max', $params, 'Must be at most :max characters.');
            }
            return null;
        }

        if ($type === 'pattern') {
            return preg_match($c['regex'], (string) $value)
                ? null
                : ($message !== null ? $this->tr($message) : $this->tr('validation.pattern', array(), 'Invalid format.'));
        }

        if ($type === 'matches') {
            $other = $this->read($object, $c['field'], isset($c['getter']) ? $c['getter'] : null);
            return ((string) $value === (string) $other)
                ? null
                : ($message !== null ? $this->tr($message) : $this->tr('validation.matches', array(), 'Values do not match.'));
        }

        // Custom constraints — the app-extensible escape hatch. A constraint whose
        // rule() carries a 'validator' (a runtime class name) is dispatched here, so
        // apps add validators WITHOUT editing this switch. The class implements:
        //   validate($value, array $rule, $object)  // null/true = ok; string = message/i18n key
        // A constraint's own message: (if set) overrides the validator's default.
        if (isset($c['validator']) && $c['validator'] !== '') {
            $result = call_user_func(array(new $c['validator'](), 'validate'), $value, $c, $object);
            if ($result === null || $result === true) {
                return null;
            }
            $key = ($message !== null) ? $message : (is_string($result) ? $result : 'validation.invalid');
            return $this->tr($key, isset($c['params']) ? $c['params'] : array(), 'Invalid value.');
        }

        return null;
    }
}
