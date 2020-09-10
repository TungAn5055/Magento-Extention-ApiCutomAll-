<?php

namespace Modernrugs\Log\Formatter;

use Exception;

class CsvLineFormatter extends \Monolog\Formatter\LineFormatter
{
    const SIMPLE_HEADER = "Time,Channel-Level,Message,Context,Extra\n";
    const SIMPLE_FORMAT = "%datetime%,%channel%-%level_name%,%message%,%context%,%extra%\n";

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $output = parent::format($record);
        return $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function normalizeException($e)
    {
        return parent::normalizeException($e);
    }

    /**
     * Get header of csv
     * @return string
     */
    public function getHeader()
    {
        return static::SIMPLE_HEADER;
    }

    /**
     * Escape double quote
     * @param array $values
     * @return array
     */
    public function csvEscapeDoubleQuote(array $values)
    {
        foreach ($values as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $values[$key] = '"' . str_replace('"', '""', $value) . '"';
        }
        return $values;
    }
}
