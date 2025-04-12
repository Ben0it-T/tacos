<?php
declare(strict_types=1);

namespace App\Helper;

final class RoundingHelper
{
    /**
     * Round time
     * 
     * @param datetime $datetime
     * @param integer $minutes
     * @param string $mode
     * @return datetime
     */
    public function roundDateTime($datetime, $minutes, $mode) {
        $seconds = $minutes * 60;
        $timestamp = $datetime->getTimestamp();
        $diff = $timestamp % $seconds;

        if ($mode === "ceil") {
            $datetime->setTimestamp($timestamp - $diff + $seconds);
        }
        elseif ($mode === "floor") {
            $datetime->setTimestamp($timestamp - $diff);
        }
        elseif ($mode === "closest") {
            if ($diff > ($seconds / 2)) {
                $datetime->setTimestamp($timestamp - $diff + $seconds);
            }
            else {
                $datetime->setTimestamp($timestamp - $diff);
            }
        }

        return $datetime;
    }
}
