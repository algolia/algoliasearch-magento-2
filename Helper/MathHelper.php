<?php

namespace Algolia\AlgoliaSearch\Helper;

class MathHelper
{
    /**
     * @param array $values
     * @return float
     */
    static public function getAverage(array $values): float
    {
        if (empty($values)) {
            return 0.00;
        }

        return round(array_sum(array_values($values)) / count($values), 2);
    }

    /**
     * @param array $values
     * @return float
     */
    static public function getSampleStandardDeviation(array $values): float
    {
        if (count($values) <= 1) {
            return 0.0;
        }

        $average = self::getAverage($values);

        $sum = 0;
        foreach ($values as $value) {
            $sum += pow($value - $average, 2);
        }

        return round(sqrt($sum / (count($values) - 1)), 2);
    }
}
