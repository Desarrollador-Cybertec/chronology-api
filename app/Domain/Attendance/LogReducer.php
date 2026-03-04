<?php

namespace App\Domain\Attendance;

use Illuminate\Support\Collection;

class LogReducer
{
    private const DEFAULT_NOISE_WINDOW = 60;

    /**
     * Reduce noise from raw log entries.
     *
     * Groups consecutive punches within noiseWindowMinutes (compared
     * against the first mark in each group) and keeps only the first
     * mark from each group.
     *
     * @param  Collection  $logs  Collection of RawLog models
     * @param  int  $noiseWindowMinutes  Minimum gap to consider a new event
     * @return Collection Filtered collection of RawLog models
     */
    public function reduce(Collection $logs, int $noiseWindowMinutes = self::DEFAULT_NOISE_WINDOW): Collection
    {
        if ($logs->isEmpty()) {
            return collect();
        }

        $sorted = $logs->sortBy('check_time')->values();
        $result = collect();
        $groupAnchor = $sorted->first();
        $result->push($groupAnchor);

        for ($i = 1; $i < $sorted->count(); $i++) {
            $current = $sorted[$i];
            $diffMinutes = (int) $groupAnchor->check_time->diffInMinutes($current->check_time);

            if ($diffMinutes >= $noiseWindowMinutes) {
                $groupAnchor = $current;
                $result->push($groupAnchor);
            }
        }

        return $result->values();
    }
}
