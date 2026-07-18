<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Services;

use AndyDefer\LaravelChronos\Collections\SlotVOCollection;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;
use Illuminate\Support\Collection;

final class SlotService implements SlotServiceInterface
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly ScheduleServiceInterface $scheduleService,
        private readonly ImpedimentServiceInterface $impedimentService,
    ) {}

    public function findNextSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $slots = $this->findSlotsInRange(
            $schedulableType,
            $schedulableId,
            $after,
            $after->addDays(30),
            $durationInMinutes,
            $availabilityId
        );

        return $slots->firstSlot();
    }

    public function findPreviousSlot(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $before,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?SlotVO {
        $start = $before->subDays(30);
        $slots = $this->findSlotsInRange(
            $schedulableType,
            $schedulableId,
            $start,
            $before,
            $durationInMinutes,
            $availabilityId
        );

        return $slots->lastSlot();
    }

    public function findSlotsInRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $availabilities = $this->getAvailabilitiesInRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end,
            $availabilityId
        );

        $blockedPeriods = $this->getBlockedPeriods(
            $schedulableType,
            $schedulableId,
            $start,
            $end,
            $availabilityId
        );

        $slots = new SlotVOCollection;

        foreach ($availabilities as $availability) {
            $availabilitySlots = $this->generateSlotsForAvailability(
                $availability,
                $start,
                $end,
                $durationInMinutes,
                $blockedPeriods
            );

            foreach ($availabilitySlots as $slot) {
                $slots->add($slot);
            }
        }

        return $slots->sortByStart();
    }

    public function findSlotsForDay(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): SlotVOCollection {
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        return $this->findSlotsInRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end,
            $durationInMinutes,
            $availabilityId
        );
    }

    public function isSlotAvailable(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): bool {
        $duration = (int) $start->diffInMinutes($end);

        $slots = $this->findSlotsInRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end,
            $duration,
            $availabilityId
        );

        foreach ($slots as $slot) {
            if ($slot->getStart()->isEqual($start) && $slot->getEnd()->isEqual($end)) {
                return true;
            }
        }

        return false;
    }

    public function getNextAvailableStart(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $after,
        int $durationInMinutes,
        ?int $availabilityId = null
    ): ?DateTimeZuluVO {
        $slot = $this->findNextSlot(
            $schedulableType,
            $schedulableId,
            $after,
            $durationInMinutes,
            $availabilityId
        );

        return $slot?->getStart();
    }

    public function hasAvailabilityOnDate(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $date
    ): bool {
        $availabilities = $this->availabilityService->findActiveAtDate(
            $schedulableType,
            $schedulableId,
            $date
        );

        return $availabilities->isNotEmpty();
    }

    public function getBlockedPeriods(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): array {
        $blocked = [];

        // Récupérer les schedules
        $schedules = $availabilityId !== null
            ? $this->scheduleService->findByAvailability($availabilityId)
            : $this->scheduleService->findBySchedulable($schedulableType, $schedulableId);

        foreach ($schedules as $schedule) {
            if ($schedule->start_datetime && $schedule->end_datetime) {
                $scheduleStart = DateTimeZuluVO::fromCarbon($schedule->start_datetime);
                $scheduleEnd = DateTimeZuluVO::fromCarbon($schedule->end_datetime);

                if ($this->overlapsRange($scheduleStart, $scheduleEnd, $start, $end)) {
                    $blocked[] = [
                        'start' => $scheduleStart,
                        'end' => $scheduleEnd,
                        'type' => 'schedule',
                        'id' => $schedule->id,
                    ];
                }
            }
        }

        // Récupérer les impediments
        $impediments = $availabilityId !== null
            ? $this->impedimentService->findByAvailability($availabilityId)
            : $this->impedimentService->findBySchedulable($schedulableType, $schedulableId);

        foreach ($impediments as $impediment) {
            if ($impediment->start_datetime && $impediment->end_datetime) {
                $impedimentStart = DateTimeZuluVO::fromCarbon($impediment->start_datetime);
                $impedimentEnd = DateTimeZuluVO::fromCarbon($impediment->end_datetime);

                if ($this->overlapsRange($impedimentStart, $impedimentEnd, $start, $end)) {
                    $blocked[] = [
                        'start' => $impedimentStart,
                        'end' => $impedimentEnd,
                        'type' => 'impediment',
                        'id' => $impediment->id,
                    ];
                }
            }
        }

        // Trier par start
        usort($blocked, function ($a, $b) {
            return $a['start']->diffInSeconds($b['start']) <=> 0;
        });

        return $blocked;
    }

    public function generateSlotsFromSlot(SlotVO $slot, int $chunkDuration): SlotVOCollection
    {
        $chunks = $slot->split($chunkDuration);

        $collection = new SlotVOCollection;
        foreach ($chunks as $chunk) {
            $collection->add($chunk);
        }

        return $collection;
    }

    /**
     * Récupère les disponibilités actives dans une plage de dates.
     *
     * @return Collection<int, Availability>
     */
    private function getAvailabilitiesInRange(
        string $schedulableType,
        int $schedulableId,
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        ?int $availabilityId = null
    ): Collection {
        if ($availabilityId !== null) {
            $availability = $this->availabilityService->find($availabilityId);

            return $availability !== null
                ? new Collection([$availability])
                : new Collection;
        }

        return $this->availabilityService->findActiveInDateRange(
            $schedulableType,
            $schedulableId,
            $start,
            $end
        );
    }

    /**
     * Vérifie si deux plages se chevauchent.
     */
    private function overlapsRange(
        DateTimeZuluVO $start1,
        DateTimeZuluVO $end1,
        DateTimeZuluVO $start2,
        DateTimeZuluVO $end2
    ): bool {
        return $start1->isBefore($end2) && $end1->isAfter($start2);
    }

    /**
     * Génère les créneaux pour une disponibilité donnée.
     *
     * @param  array<array{start: DateTimeZuluVO, end: DateTimeZuluVO}>  $blockedPeriods
     * @return array<SlotVO>
     */
    private function generateSlotsForAvailability(
        Availability $availability,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd,
        int $durationInMinutes,
        array $blockedPeriods
    ): array {
        $slots = [];
        $validityStart = $availability->getValidityStart();
        $validityEnd = $availability->getValidityEnd();
        $dailyStart = $availability->getDailyStart();
        $dailyEnd = $availability->getDailyEnd();
        $days = $availability->getDays();

        if ($dailyStart === null || $dailyEnd === null || $days->isEmpty()) {
            return [];
        }

        $current = $rangeStart->startOfDay();

        while ($current->isBefore($rangeEnd) || $current->isEqual($rangeEnd)) {
            $dayName = strtolower($current->format('l'));

            // Vérifier si le jour est autorisé
            if (! $this->isDayAllowed($dayName, $days)) {
                $current = $current->addDays(1);

                continue;
            }

            // Vérifier si le jour est dans la période de validité
            if (! $this->isWithinValidityPeriod($current, $validityStart, $validityEnd)) {
                $current = $current->addDays(1);

                continue;
            }

            // Créer les slots pour ce jour
            $daySlots = $this->generateSlotsForDay(
                $current,
                $dailyStart,
                $dailyEnd,
                $durationInMinutes,
                $blockedPeriods,
                $rangeStart,
                $rangeEnd
            );

            foreach ($daySlots as $slot) {
                $slots[] = $slot;
            }

            $current = $current->addDays(1);
        }

        return $slots;
    }

    /**
     * Vérifie si un jour est autorisé.
     *
     * @param  WeekDayCollection<int, WeekDay>  $days
     */
    private function isDayAllowed(string $dayName, WeekDayCollection $days): bool
    {
        foreach ($days as $day) {
            if ($day->value === $dayName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si une date est dans la période de validité.
     */
    private function isWithinValidityPeriod(
        DateTimeZuluVO $date,
        ?DateTimeZuluVO $validityStart,
        ?DateTimeZuluVO $validityEnd
    ): bool {
        if ($validityStart !== null && $date->isBefore($validityStart)) {
            return false;
        }

        if ($validityEnd !== null && $date->isAfter($validityEnd)) {
            return false;
        }

        return true;
    }

    /**
     * Génère les créneaux pour un jour spécifique.
     *
     * @param  array<array{start: DateTimeZuluVO, end: DateTimeZuluVO}>  $blockedPeriods
     * @return array<SlotVO>
     */
    private function generateSlotsForDay(
        DateTimeZuluVO $date,
        TimeZuluVO $dailyStart,
        TimeZuluVO $dailyEnd,
        int $durationInMinutes,
        array $blockedPeriods,
        DateTimeZuluVO $rangeStart,
        DateTimeZuluVO $rangeEnd
    ): array {
        $slots = [];

        // Définir la plage horaire du jour
        $dayStart = DateTimeZuluVO::from(
            $date->toDateString().'T'.$dailyStart->toTimeString().'Z'
        );
        $dayEnd = DateTimeZuluVO::from(
            $date->toDateString().'T'.$dailyEnd->toTimeString().'Z'
        );

        // Ajuster pour les disponibilités cross-day
        if ($dailyStart->isAfter($dailyEnd)) {
            $dayEnd = $dayEnd->addDays(1);
        }

        // Ajuster à la plage de recherche
        if ($dayStart->isBefore($rangeStart)) {
            $dayStart = $rangeStart;
        }

        if ($dayEnd->isAfter($rangeEnd)) {
            $dayEnd = $rangeEnd;
        }

        if ($dayStart->isAfter($dayEnd)) {
            return [];
        }

        // Filtrer les périodes bloquées qui chevauchent ce jour
        $dayBlocked = array_filter($blockedPeriods, function ($blocked) use ($dayStart, $dayEnd) {
            return $blocked['start']->isBefore($dayEnd) && $blocked['end']->isAfter($dayStart);
        });

        // Trier les périodes bloquées
        usort($dayBlocked, function ($a, $b) {
            return $a['start']->diffInSeconds($b['start']) <=> 0;
        });

        // Générer des slots de la durée demandée dans les plages disponibles
        $currentStart = $dayStart;

        foreach ($dayBlocked as $blocked) {
            // Ajuster les périodes bloquées à la plage du jour
            $blockStart = $blocked['start']->isBefore($dayStart) ? $dayStart : $blocked['start'];
            $blockEnd = $blocked['end']->isAfter($dayEnd) ? $dayEnd : $blocked['end'];

            // Générer des slots avant le blocage
            if ($currentStart->isBefore($blockStart)) {
                $slots = array_merge(
                    $slots,
                    $this->generateSlotsInInterval($currentStart, $blockStart, $durationInMinutes)
                );
            }

            // Passer après le blocage
            if ($blockEnd->isAfter($currentStart)) {
                $currentStart = $blockEnd;
            }
        }

        // Générer des slots après le dernier blocage
        if ($currentStart->isBefore($dayEnd)) {
            $slots = array_merge(
                $slots,
                $this->generateSlotsInInterval($currentStart, $dayEnd, $durationInMinutes)
            );
        }

        return $slots;
    }

    /**
     * Génère tous les slots possibles dans un intervalle de temps.
     *
     * @param  DateTimeZuluVO  $start  Le début de l'intervalle
     * @param  DateTimeZuluVO  $end  La fin de l'intervalle
     * @param  int  $durationInMinutes  La durée de chaque slot
     * @return array<SlotVO>
     */
    private function generateSlotsInInterval(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes
    ): array {
        $slots = [];
        $current = $start;

        while ($current->addMinutes($durationInMinutes)->isBeforeOrEqual($end)) {
            $slots[] = SlotVO::fromDuration($current, $durationInMinutes);
            $current = $current->addMinutes($durationInMinutes);
        }

        return $slots;
    }

    /**
     * Essaye de créer un slot d'une durée donnée.
     */
    private function tryCreateSlot(
        DateTimeZuluVO $start,
        DateTimeZuluVO $end,
        int $durationInMinutes
    ): ?SlotVO {
        $availableDuration = (int) $start->diffInMinutes($end);

        if ($availableDuration >= $durationInMinutes) {
            return SlotVO::fromDuration($start, $durationInMinutes);
        }

        return null;
    }
}
