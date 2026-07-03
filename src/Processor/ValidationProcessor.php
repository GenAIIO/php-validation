<?php

namespace GenAI\Validation\Processor;

use GenAI\Attribute\AttributeProcessor;
use GenAI\Attribute\Context;
use GenAI\Validation\Attribute\Validate;
use GenAI\Validation\Rule;

/**
 * Compiles every #[Validate] form class into one reflection-free Cache\Validation
 * (a Validator subclass with the rule table baked in):
 *
 *   class Validation extends \GenAI\Validation\Validator {
 *       public function __construct() {
 *           $this->setRules(array(
 *               'App\\Form\\SignupForm' => array(
 *                   // getter null = public prop; else a getXxx()/isXxx() name
 *                   'email' => array('getter'=>null, 'rules'=>array(
 *                       array('type'=>'notblank',...), array('type'=>'email',...))),
 *                   ...
 *               ),
 *           ));
 *       }
 *   }
 *
 * Each property's constraints (#[NotBlank], #[Email], ...) are matched via
 * IS_INSTANCEOF on the Constraint base and turned into data by their rule(). Each
 * field also records how to read it (public property, or a getter for a
 * private/protected one). Always emits Cache\Validation (empty when no forms), so
 * the Validator bean always resolves. Build-time only (PHP 8); 5.3-safe output.
 */
class ValidationProcessor implements AttributeProcessor
{
    /** @var array<string, array> class => (field => list of rule arrays) */
    private array $rules = [];

    public function getAttributeClass(): string
    {
        return Validate::class;
    }

    public function process(object $attribute, \Reflector $target): void
    {
        /** @var \ReflectionClass $target */

        // How to read each property at runtime: null = public (read directly),
        // else a getter name (for private/protected). Resolved here so #[Matches]
        // can also reach its target field.
        $accessors = array();
        foreach ($target->getProperties() as $property) {
            $accessors[$property->getName()] = $this->accessorFor($target, $property);
        }

        $fields = array();
        foreach ($target->getProperties() as $property) {
            $constraints = $property->getAttributes(Constraint::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (empty($constraints)) {
                continue;
            }
            $rules = array();
            foreach ($constraints as $constraint) {
                $rule = $constraint->newInstance()->rule();
                if (isset($rule['type']) && $rule['type'] === 'matches') {
                    $rule['getter'] = isset($accessors[$rule['field']]) ? $accessors[$rule['field']] : null;
                }
                if (isset($rule['validator']) && $rule['validator'] !== '') {
                    $this->assertRuleClass($rule['validator'], $target->getName(), $property->getName());
                }
                $rules[] = $rule;
            }
            $fields[$property->getName()] = array(
                'getter' => $accessors[$property->getName()],
                'rules'  => $rules,
            );
        }

        $this->rules[$target->getName()] = $fields;
    }

    /**
     * Decide how the runtime reads a property: null for public, otherwise a
     * public no-arg getXxx()/isXxx() getter. A private property without one is a
     * build error (the reflection-free runtime couldn't read it).
     */
    private function accessorFor(\ReflectionClass $class, \ReflectionProperty $property)
    {
        if ($property->isPublic()) {
            return null;
        }

        $name = ucfirst($property->getName());
        foreach (array('get' . $name, 'is' . $name) as $getter) {
            if ($class->hasMethod($getter)) {
                $method = $class->getMethod($getter);
                if ($method->isPublic() && !$method->isStatic() && $method->getNumberOfRequiredParameters() === 0) {
                    return $getter;
                }
            }
        }

        throw new \LogicException(
            $class->getName() . '::$' . $property->getName() . ' is not public and has no get'
            . $name . '()/is' . $name . '() getter — validation cannot read it.'
        );
    }

    /**
     * A custom constraint named a runtime validator class (its rule()'s 'validator'
     * key). Fail the BUILD if that class is missing or doesn't implement the Rule
     * contract — so a typo or a wrong class is caught at compile time, not silently
     * passed at runtime (the Validator only knows how to call Rule::validate()).
     */
    private function assertRuleClass(string $class, string $owner, string $field): void
    {
        if (!class_exists($class)) {
            throw new \LogicException(
                $owner . '::$' . $field . ' uses custom validator "' . $class
                . '", which does not exist. It must be a class implementing ' . Rule::class . '.'
            );
        }
        if (!is_a($class, Rule::class, true)) {
            throw new \LogicException(
                $owner . '::$' . $field . ' uses custom validator "' . $class
                . '", which must implement ' . Rule::class . ' (define a validate($value, $rule, $object) method).'
            );
        }
    }

    public function compile(Context $context): void
    {
        $source = "<?php\n\n"
            . "namespace Cache;\n\n"
            . "// Generated by GenAI\\Validation\\Processor\\ValidationProcessor - do not edit by hand.\n"
            . "// new \\Cache\\Validation() is ready: \$validator->validate(\$formObject).\n\n"
            . "class Validation extends \\GenAI\\Validation\\Validator\n"
            . "{\n"
            . "    public function __construct()\n"
            . "    {\n"
            . '        $this->setRules(' . var_export($this->rules, true) . ");\n"
            . "    }\n"
            . "}\n";

        $path  = $context->output('Validation.php');
        $bytes = @file_put_contents($path, $source);
        if ($bytes === false) {
            throw new \RuntimeException('Could not write validation rules to "' . $path . '".');
        }
    }
}
