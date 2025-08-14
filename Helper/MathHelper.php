<?php

namespace Algolia\AlgoliaSearch\Helper;

class MathHelper
{
    /**
     * @param array $values
     * @return int
     */
    static public function getRoundedAverage(array $values): int
    {
        if (empty($values)) {
            return 0.0;
        }

        return (int) round(array_sum(array_values($values)) / count($values));
    }

    /**
     * @param array $values
     * @param int $average
     * @return float
     */
    static public function getSampleStandardDeviation(array $values, int $average): float
    {
        if (count($values) <= 1) {
            return 0.0;
        }

        $sum = 0;
        foreach ($values as $value) {
            $sum += pow($value - $average, 2);
        }

        return round(sqrt($sum / (count($values) - 1)), 2);
    }
}
