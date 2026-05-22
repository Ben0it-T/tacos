<?php
declare(strict_types=1);

namespace App\Helper;

final class RoundingHelper
{
    /**
     * Round a DateTime to the nearest interval.
     *
     * Modes:
     * - floor
     * - ceil
     * - closest
     * - none (no rounding)
     * 
     * @param \DateTime $datetime
     * @param int $minutes
     * @param string $mode
     * @return \DateTime
     */
    public function roundDateTime(\DateTime $datetime, int $minutes, string $mode): \DateTime {
        $datetime = clone $datetime;

        if ($minutes <= 0) {
            return $datetime;
        }

        $seconds = $minutes * 60;
        $timestamp = $datetime->getTimestamp();
        $diff = $timestamp % $seconds;

        if ($diff === 0) {
            return $datetime;
        }

        switch ($mode) {
            case 'floor':
                $datetime->setTimestamp($timestamp - $diff);
                break;

            case 'ceil':
                $datetime->setTimestamp($timestamp - $diff + $seconds);
                break;

            case 'closest':
                $datetime->setTimestamp(
                    $diff > ($seconds / 2)
                        ? $timestamp - $diff + $seconds
                        : $timestamp - $diff
                );
                break;

            case 'none':
            default:
                // no rounding
                break;
        }

        return $datetime;
    }
}
