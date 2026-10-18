<?php
declare(strict_types=1);

namespace App\Helper;

final class SqlHelper
{
    /**
     * Build IN clause
     *
     * @param array<int, int> $values Ids to match
     * @param string $prefix Placeholder prefix (e.g. 'usersId' gives :usersId0, :usersId1...)
     * @param string $column Column to match (e.g. 't.`user_id`')
     *
     * @return array{0: string, 1: array<string, int>} [clause, PDO params]; ['', []] when $values is empty
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
