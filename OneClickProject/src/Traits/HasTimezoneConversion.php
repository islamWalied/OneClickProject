<?php

namespace App\Traits;

use Carbon\Carbon;
use DateTimeInterface;

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

    private function convertToUserTimezone($datetime)
    {
        if (!$datetime) {
            return null;
        }

        $userTimezone = $this->getUserTimezone();

        if ($datetime instanceof Carbon) {
            return $datetime->copy()->setTimezone($userTimezone)->format('Y-m-d H:i:s');
        }

        if ($datetime instanceof \DateTime) {
            return Carbon::instance($datetime)->setTimezone($userTimezone)->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($datetime)
                ->setTimezone($userTimezone)
                ->format('Y-m-d H:i:s');
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

        // Check if the attribute is a datetime field
        if ($value instanceof \DateTime ||
            (isset($this->casts[$key]) && in_array($this->casts[$key], ['datetime', 'date']))) {
            return $this->convertToUserTimezone($value);
        }

        return $value;
    }
}
