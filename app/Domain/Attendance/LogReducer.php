<?php

namespace App\Domain\Attendance;

use Illuminate\Support\Collection;

class LogReducer
{
    private const DEFAULT_NOISE_WINDOW = 60;

    /**
     * Reduce noise from raw log entries.
     *
     * Groups consecutive punches within noiseWindowMinutes and
     * keeps only the first and last from each group.
     *
     * @param  Collection  $logs  Collection of RawLog models
     * @param  int  $noiseWindowMinutes  Minimum gap to consider a new event
     * @return Collection Filtered collection of RawLog models
     */
    public function reduce(Collection $logs, int $noiseWindowMinutes = self::DEFAULT_NOISE_WINDOW): Collection
    {
        if ($logs->count() <= 2) {
            return $logs->sortBy('check_time')->values();
        }

        $sorted = $logs->sortBy('check_time')->values();
        $groups = collect();
        $currentGroup = collect([$sorted->first()]);

        for ($i = 1; $i < $sorted->count(); $i++) {
            $current = $sorted[$i];
            $lastInGroup = $currentGroup->last();

            $diffMinutes = $lastInGroup->check_time->diffInMinutes($current->check_time);

            if ($diffMinutes < $noiseWindowMinutes) {
                $currentGroup->push($current);
            } else {
                $groups->push($currentGroup);
                $currentGroup = collect([$current]);
            }
        }

        $groups->push($currentGroup);

        $result = collect();

        foreach ($groups as $group) {
            $result->push($group->first());

            if ($group->count() > 1) {
                $result->push($group->last());
            }
        }

        return $result->sortBy('check_time')->values();
    }
}
