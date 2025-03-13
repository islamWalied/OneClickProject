<?php

namespace App\Traits;

use DateTimeInterface;
use Carbon\Carbon;

trait HasTimezoneConversion
{
    private function getUserTimezone()
    {
        $timezone = request()->header('timezone');
        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Exception $e) {
            return config('app.timezone');
        }
    }

    private function convertToUserTimezone($datetime, string $format = 'Y-m-d H:i:s')
    {
        if (!$datetime) {
            return null;
        }

        $userTimezone = $this->getUserTimezone();

        if ($datetime instanceof Carbon) {
            return $datetime->copy()->setTimezone($userTimezone)->format($format);
        }

        if ($datetime instanceof \DateTime) {
            return Carbon::instance($datetime)->setTimezone($userTimezone)->format($format);
        }

        try {
            return Carbon::parse($datetime)
                ->setTimezone($userTimezone)
                ->format($format);
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    protected function serializeDate(DateTimeInterface $date)
    {
        return $this->convertToUserTimezone($date);
    }

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($value instanceof \DateTime ||
            (isset($this->casts[$key]) && in_array($this->casts[$key], ['datetime', 'date', 'time']))) {
            $format = 'Y-m-d H:i:s';

            if (isset($this->casts[$key])) {
                if ($this->casts[$key] === 'time') {
                    $format = 'H:i:s'; // Only time for 'time' cast
                } elseif (is_array($this->casts[$key]) && isset($this->casts[$key]['format'])) {
                    $format = $this->casts[$key]['format'];
                } elseif (strpos($this->casts[$key], ':') !== false) {
                    $parts = explode(':', $this->casts[$key], 2);
                    if ($parts[0] === 'datetime' && isset($parts[1])) {
                        $format = $parts[1];
                    } elseif ($parts[0] === 'time' && isset($parts[1])) {
                        $format = $parts[1];
                    }
                }
            }

            return $this->convertToUserTimezone($value, $format);
        }

        return $value;
    }
}
