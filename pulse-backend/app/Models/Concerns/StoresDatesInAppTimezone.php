<?php

namespace App\Models\Concerns;

use DateTimeInterface;

trait StoresDatesInAppTimezone
{
    /**
     * Persist datetimes using the application timezone wall-clock.
     *
     * Laravel's default fromDateTime() formats the Carbon instance in its own
     * timezone (often UTC from ISO input), then reads it back as APP_TIMEZONE.
     * That shifts schedules by the UTC offset (e.g. 17:15 Tehran → stored 13:45).
     */
    public function fromDateTime($value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        $date = $this->asDateTime($value);

        if ($date instanceof DateTimeInterface) {
            $date = $date->timezone(config('app.timezone'));
        }

        return $date->format($this->getDateFormat());
    }
}
