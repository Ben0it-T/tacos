<?php
declare(strict_types=1);

namespace App\Helper;

final class SqlHelper
{
    /**
     * Build IN clause
     *
     * @param array  $values
     * @param string $prefix
     * @param string $column
     *
     * @return array [string $clause, array $params]
     */
    public static function buildInClause(array $values, string $prefix, string $column): array
    {
        if (empty($values)) {
            return ['', []];
        }

        $placeholders = [];
        $params = [];

        foreach ($values as $i => $value) {
            $key = ":{$prefix}{$i}";
            $placeholders[] = $key;
            $params[$key] = $value;
        }

        $clause = "$column IN (" . implode(',', $placeholders) . ")";
        return [$clause, $params];
    }
}
