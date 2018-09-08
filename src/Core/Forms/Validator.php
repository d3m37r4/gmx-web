<?php

namespace GameX\Core\Forms;

use \GameX\Core\Lang\Language;
use \GameX\Core\Forms\Rules\Trim;
use \GameX\Core\Forms\Rules\Required;

class Validator {
    
    const CHECK_EMPTY = 'empty';
    const CHECK_LENGTH = 'length';
    const CHECK_ARRAY = 'array';
    
    /**
     * @var Language
     */
    protected $language;
    
    /**
     * @var bool[]
     */
    protected $required = [];
    
    /**
     * @var Rule[][]
     */
    protected $rules = [];
    
    /**
     * @var array
     */
    protected $options = [];
    
    /**
     * @var bool
     */
    protected $isValid = true;
    
    /**
     * @param Language $language
     */
    public function __construct(Language $language) {
        $this->language = $language;
    }
    
    /**
     * @param string $key
     * @param bool $required
     * @param array $rules
     * @return Validator
     * @throws \Exception
     */
    public function set($key, $required, array $rules = [], array $options = []) {
        foreach ($rules as $rule) {
            if (!($rule instanceof Rule)) {
                throw new \Exception('Rules must implement Rule interface');
            }
        }
        
        $this->required[$key] = (bool) $required;
        $this->rules[$key] = $rules;
        $this->options[$key] = array_merge([
            'check' => Validator::CHECK_LENGTH,
            'trim' => true,
        ], $options);
        
        return $this;
    }
    
    /**
     * @param $key
     * @param Rule $rule
     * @return Validator
     */
    public function add($key, Rule $rule) {
        if (!array_key_exists($key, $this->rules)) {
            $this->rules[$key] = [];
        }
        if (!array_key_exists($key, $this->required)) {
            $this->required[$key] = false;
        }
        
        // TODO: Remove this
        if ($rule instanceof Trim) {
            //
        } elseif ($rule instanceof Required) {
            $this->required[$key] = true;
        } else {
            $this->rules[$key][] = $rule;
        }
    
        return $this;
    }
    
    /**
     * @param array $values
     * @return ValidationResult
     */
    public function validate(array $values) {
        $isValid = true;
        $errors = array_fill_keys(array_keys($this->rules), null);
        foreach ($this->rules as $key => $rules) {
            $value = array_key_exists($key, $values) ? $values[$key] : null;
            if ($this->options[$key]['trim']) {
                $value = trim($value);
            }
            if ($value !== null && $this->checkEmpty($key, $value)) {
                foreach ($rules as $rule) {
                    $value = $rule->validate($value, $values);
                    if ($value === null) {
                        $errors[$key] = $rule->getError($this->language);
                        $isValid = false;
                        break;
                    }
                }
            } elseif ($this->required[$key]) {
                $errors[$key] = $this->language->format('forms', 'required');
                $isValid = false;
                $value = null;
            } else {
                $value = null;
            }
            $values[$key] = $value;
        }
        
        return new ValidationResult($values, $errors, $isValid);
    }
    
    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    protected function checkEmpty($key, $value) {
        switch ($this->options[$key]['check']) {
            case self::CHECK_EMPTY: {
                return !empty($value);
            }
            
            case self::CHECK_ARRAY: {
                return is_array($value) && count($value) > 0;
            }
    
            case self::CHECK_LENGTH:
            default: {
                return is_string($value) && strlen($value) > 0;
            }
        }
    }
}
