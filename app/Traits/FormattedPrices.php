<?php

namespace App\Traits;

use NumberFormatter;

trait FormattedPrices
{
    public function __get($key)
    {
        if (str_ends_with((string) $key, '_label')) {
            $column = substr((string) $key, 0, -6);
            $formattableColumns = property_exists(static::class, 'formattable_columns')
                ? (static::$formattable_columns ?? [])
                : [];

            if (in_array($column, $formattableColumns, true)) {
                $value = $this->getAttribute($column);
                if ($value === null) {
                    return null;
                }

                $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);

                return $formatter->formatCurrency((float) $value, 'MAD');
            }
        }

        return parent::__get($key);
    }
}
