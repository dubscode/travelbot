<?php

namespace App\Service\Trait;

/**
 * Trait for normalizing Claude AI's inconsistent response formats
 * where arrays are sometimes returned instead of scalar values
 */
trait ArrayNormalizerTrait
{
    /**
     * Convert array or scalar value to string, handling Claude AI's inconsistent response format
     */
    protected function arrayToString($value): string
    {
        if (is_array($value)) {
            return !empty($value) ? (string)$value[0] : '';
        }
        return (string)$value;
    }

    /**
     * Convert array or scalar value to int, handling Claude AI's inconsistent response format
     */
    protected function arrayToInt($value): int
    {
        if (is_array($value)) {
            return !empty($value) ? (int)$value[0] : 0;
        }
        return (int)$value;
    }

    /**
     * Convert array or scalar value to float, handling Claude AI's inconsistent response format
     */
    protected function arrayToFloat($value): float
    {
        if (is_array($value)) {
            return !empty($value) ? (float)$value[0] : 0.0;
        }
        return (float)$value;
    }

    /**
     * Safely check if a value is empty, handling both arrays and scalars
     */
    protected function isValueEmpty($value): bool
    {
        if (is_array($value)) {
            return empty($value) || (count($value) === 1 && empty($value[0]));
        }
        return empty($value);
    }

    /**
     * Safely divide two values, handling arrays and preventing division by zero
     */
    protected function safeDivide($numerator, $denominator, float $default = 0.0): float
    {
        $num = $this->arrayToFloat($numerator);
        $denom = $this->arrayToFloat($denominator);
        
        if ($denom == 0) {
            return $default;
        }
        
        return $num / $denom;
    }
}