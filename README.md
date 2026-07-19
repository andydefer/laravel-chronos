# Laravel Chronos

**Un moteur de planification avancé pour Laravel. Gestion des disponibilités, rendez-vous et empêchements avec validation métier exhaustive.**

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10.x%20%7C%2011.x%20%7C%2012.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## 📦 Installation

```bash
composer require andydefer/laravel-chronos
php artisan vendor:publish --tag=chronos-config
php artisan vendor:publish --tag=chronos-migrations
php artisan migrate
```

---

## 🎯 Pourquoi Laravel Chronos ?

**Le problème :** Vous gérez des disponibilités, des rendez-vous et des empêchements dans votre application. Vous devez :
- Vérifier qu'un créneau est disponible avant de le réserver
- Empêcher les doubles réservations
- Gérer les indisponibilités temporaires (congés, formations)
- Valider les règles métier (durée minimale, buffer entre rendez-vous)
- Trouver le prochain créneau disponible automatiquement

**La solution :** Laravel Chronos vous offre un moteur de planification complet avec :
- ✅ Disponibilités récurrentes (jours, plages horaires, périodes)
- ✅ Rendez-vous avec états (réservé, annulé, complété)
- ✅ Empêchements pour bloquer des créneaux
- ✅ Recherche automatique de créneaux
- ✅ **17 règles de validation métier** exhaustives
- ✅ Protection contre les boucles infinies (durée minimale absolue)
- ✅ Collections typées (`SlotVOCollection`, `BlockedPeriodCollection`)

---

## 🚀 Démarrage rapide

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;

$availabilityService = app(AvailabilityServiceInterface::class);
$scheduleService = app(ScheduleServiceInterface::class);
$slotService = app(SlotServiceInterface::class);

// 1. Créer une disponibilité avec scoping
$availability = $availabilityService
    ->for($doctor)
    ->create(AvailabilityRecord::from([
        'name' => 'Consultations',
        'days' => ['monday', 'wednesday', 'friday'],
        'daily_start' => '09:00:00',
        'daily_end' => '17:00:00',
        'validity_start' => '2024-01-01T00:00:00Z',
        'validity_end' => '2024-12-31T23:59:59Z',
    ]));

// 2. Créer un rendez-vous avec scoping
$schedule = $scheduleService
    ->for($doctor)
    ->create(ScheduleRecord::from([
        'availability_id' => $availability->id,
        'title' => 'Consultation patient',
        'start_datetime' => '2024-01-15T10:00:00Z',
        'end_datetime' => '2024-01-15T10:30:00Z',
        'status' => ScheduleStatus::BOOKED,
    ]));

// 3. Trouver le prochain créneau disponible
$nextSlot = $slotService->findNextSlot($doctor, DateTimeZuluVO::now(), 30);
```

---

## 📅 Cas d'usage complets

### Scénario 1 : Gestion d'un cabinet médical

#### 1.1 Créer le planning d'un médecin avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;

class DoctorScheduleSetup
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService
    ) {}

    public function setup(Doctor $doctor): void
    {
        // Matin : Consultations classiques
        $this->availabilityService
            ->for($doctor)
            ->create(AvailabilityRecord::from([
                'name' => 'Consultations matin',
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'daily_start' => '09:00:00',
                'daily_end' => '12:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));

        // Après-midi : Consultations uniquement certains jours
        $this->availabilityService
            ->for($doctor)
            ->create(AvailabilityRecord::from([
                'name' => 'Consultations après-midi',
                'days' => ['monday', 'tuesday', 'thursday'],
                'daily_start' => '14:00:00',
                'daily_end' => '18:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));

        // Permanence téléphonique
        $this->availabilityService
            ->for($doctor)
            ->create(AvailabilityRecord::from([
                'name' => 'Permanence téléphonique',
                'days' => ['monday', 'wednesday'],
                'daily_start' => '19:00:00',
                'daily_end' => '21:00:00',
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-06-30T23:59:59Z',
            ]));
    }
}
```

#### 1.2 Prendre un rendez-vous avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

class BookingService
{
    public function __construct(
        private SlotServiceInterface $slotService,
        private ScheduleServiceInterface $scheduleService,
        private AvailabilityServiceInterface $availabilityService
    ) {}

    public function book(Doctor $doctor, Patient $patient, int $duration): Schedule
    {
        // Trouver le prochain créneau disponible
        $slot = $this->slotService->findNextSlot($doctor, DateTimeZuluVO::now(), $duration);

        if (!$slot) {
            throw new \RuntimeException('Aucun créneau disponible');
        }

        // Vérifier que le créneau est toujours disponible
        if (!$this->slotService->isSlotAvailable($doctor, $slot->getStart(), $slot->getEnd())) {
            throw new \RuntimeException('Le créneau n\'est plus disponible');
        }

        // Récupérer la disponibilité pour ce créneau
        $availability = $this->availabilityService
            ->for($doctor)
            ->findActiveAtDate($doctor, $slot->getStart())
            ->first();

        // Créer le rendez-vous avec scoping
        return $this->scheduleService
            ->for($doctor)
            ->create(ScheduleRecord::from([
                'availability_id' => $availability->id,
                'title' => 'Consultation - ' . $patient->name,
                'start_datetime' => $slot->getStart()->getValue(),
                'end_datetime' => $slot->getEnd()->getValue(),
                'status' => ScheduleStatus::BOOKED,
            ]));
    }
}
```

#### 1.3 Annuler un rendez-vous avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Models\Schedule;

class AppointmentManager
{
    public function __construct(
        private ScheduleServiceInterface $scheduleService
    ) {}

    public function cancel(Doctor $doctor, Schedule $schedule): void
    {
        // Vérifier si annulable
        if (!$this->scheduleService->canBeCancelled($schedule)) {
            throw new \RuntimeException(
                'Impossible d\'annuler (statut: ' . $schedule->status->value . ')'
            );
        }

        // Annuler avec scoping (vérifie l'appartenance)
        $this->scheduleService
            ->for($doctor)
            ->cancel($schedule->id);
    }

    public function complete(Doctor $doctor, Schedule $schedule): void
    {
        // Vérifier si complétable
        if (!$this->scheduleService->canBeCompleted($schedule)) {
            throw new \RuntimeException(
                'Impossible de compléter (statut: ' . $schedule->status->value . ')'
            );
        }

        // Compléter avec scoping
        $this->scheduleService
            ->for($doctor)
            ->complete($schedule->id);
    }
}
```

#### 1.4 Rechercher des rendez-vous avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

class ScheduleSearchService
{
    public function __construct(
        private ScheduleServiceInterface $scheduleService
    ) {}

    // Rendez-vous du jour avec scoping
    public function today(Doctor $doctor): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->findByDate(DateTimeZuluVO::today());
    }

    // Rendez-vous par statut avec scoping
    public function byStatus(Doctor $doctor, ScheduleStatus $status, int $limit = null): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->findByStatus($status, null, $limit);
    }

    // Rendez-vous par titre avec scoping
    public function search(Doctor $doctor, string $query, int $limit = null): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->searchByTitle($query, null, $limit);
    }

    // Rendez-vous sur une période avec scoping
    public function inRange(Doctor $doctor, string $start, string $end, int $limit = null): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->findInDateRange(
                DateTimeZuluVO::from($start),
                DateTimeZuluVO::from($end),
                null,
                $limit
            );
    }
}
```

---

### Scénario 2 : Gestion des empêchements avec scoping

#### 2.1 Bloquer une période pour formation

```php
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

class ImpedimentManager
{
    public function __construct(
        private ImpedimentServiceInterface $impedimentService,
        private SlotServiceInterface $slotService,
        private AvailabilityServiceInterface $availabilityService
    ) {}

    public function blockForTraining(Doctor $doctor, string $start, string $end): void
    {
        $startDate = DateTimeZuluVO::from($start);
        $endDate = DateTimeZuluVO::from($end);

        // Vérifier les conflits
        $conflicts = $this->slotService->getBlockedPeriods(
            $doctor,
            $startDate,
            $endDate
        );

        if ($conflicts->isNotEmpty()) {
            throw new \RuntimeException('Des rendez-vous existent déjà sur cette période');
        }

        // Récupérer la disponibilité
        $availability = $this->availabilityService
            ->for($doctor)
            ->findActiveAtDate($doctor, $startDate)
            ->first();

        // Créer l'empêchement avec scoping
        $this->impedimentService
            ->for($doctor)
            ->create(ImpedimentRecord::from([
                'availability_id' => $availability->id,
                'reason' => 'Formation médicale obligatoire',
                'start_datetime' => $start,
                'end_datetime' => $end,
            ]));
    }
}
```

#### 2.2 Analyser l'impact d'un empêchement

```php
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Models\Impediment;

class ImpactAnalyzer
{
    public function __construct(
        private ImpedimentServiceInterface $impedimentService
    ) {}

    public function analyze(Impediment $impediment): array
    {
        $blocked = $this->impedimentService->getBlockedSchedules($impediment);
        $fullyBlocked = $this->impedimentService->getFullyBlockedSchedules($impediment);
        $partiallyBlocked = $this->impedimentService->getPartiallyBlockedSchedules($impediment);

        return [
            'total_impact' => $blocked->count() . ' rendez-vous impactés',
            'fully_blocked' => $fullyBlocked->count() . ' rendez-vous totalement bloqués',
            'partially_blocked' => $partiallyBlocked->count() . ' rendez-vous partiellement bloqués',
            'details' => [
                'fully' => $fullyBlocked->pluck('title')->toArray(),
                'partially' => $partiallyBlocked->pluck('title')->toArray(),
            ]
        ];
    }

    public function isActive(Impediment $impediment): bool
    {
        return $this->impedimentService->isActive($impediment);
    }
}
```

#### 2.3 Rechercher des empêchements avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

class ImpedimentSearchService
{
    public function __construct(
        private ImpedimentServiceInterface $impedimentService
    ) {}

    // Empêchements actifs avec scoping
    public function active(Doctor $doctor, int $limit = null): Collection
    {
        return $this->impedimentService
            ->for($doctor)
            ->findActive(null, $limit);
    }

    // Par motif avec scoping
    public function byReason(Doctor $doctor, string $reason, int $limit = null): Collection
    {
        return $this->impedimentService
            ->for($doctor)
            ->searchByReason($reason, null, $limit);
    }

    // Par date avec scoping
    public function byDate(Doctor $doctor, string $date, int $limit = null): Collection
    {
        return $this->impedimentService
            ->for($doctor)
            ->findByDate(DateTimeZuluVO::from($date), null, $limit);
    }

    // Par plage avec scoping
    public function inRange(Doctor $doctor, string $start, string $end, int $limit = null): Collection
    {
        return $this->impedimentService
            ->for($doctor)
            ->findInDateRange(
                DateTimeZuluVO::from($start),
                DateTimeZuluVO::from($end),
                null,
                $limit
            );
    }
}
```

---

### Scénario 3 : Recherche avancée de créneaux avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;
use Illuminate\Support\Collection;

class WeekScheduleService
{
    public function __construct(
        private SlotServiceInterface $slotService
    ) {}

    public function findWeekSlots(Doctor $doctor, string $startDate): array
    {
        $weekSlots = [];
        $start = DateTimeZuluVO::from($startDate);

        for ($i = 0; $i < 7; $i++) {
            $date = $start->addDays($i);
            $slots = $this->slotService->findSlotsForDay($doctor, $date, 30);

            if ($slots->isNotEmpty()) {
                $weekSlots[$date->toDateString()] = $slots;
            }
        }

        return $weekSlots;
    }

    public function getFirstAvailable(Doctor $doctor, int $duration): ?SlotVO
    {
        return $this->slotService->findNextSlot($doctor, DateTimeZuluVO::now(), $duration);
    }
}

class BlockedPeriodsService
{
    public function __construct(
        private SlotServiceInterface $slotService
    ) {}

    public function getBusyPeriods(Doctor $doctor, string $start, string $end): array
    {
        $blocked = $this->slotService->getBlockedPeriods(
            $doctor,
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );

        $busyPeriods = [];
        foreach ($blocked as $period) {
            $busyPeriods[] = [
                'start' => $period->getStart()->toDateTimeString(),
                'end' => $period->getEnd()->toDateTimeString(),
                'type' => $period->getType(),
                'id' => $period->getId(),
                'duration' => $period->getDurationInMinutes() . ' minutes',
            ];
        }

        return $busyPeriods;
    }

    public function getTotalBlockedDuration(Doctor $doctor, string $start, string $end): int
    {
        return $this->slotService->getBlockedPeriods(
            $doctor,
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        )->getTotalDuration();
    }
}

class SlotSplitService
{
    public function __construct(
        private SlotServiceInterface $slotService
    ) {}

    public function splitIntoChunks(SlotVO $slot, int $chunkDuration, int $limit = null): SlotVOCollection
    {
        return $this->slotService->generateSlotsFromSlot($slot, $chunkDuration, $limit);
    }

    public function isSlotFree(Doctor $doctor, string $start, string $end): bool
    {
        return $this->slotService->isSlotAvailable(
            $doctor,
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );
    }
}
```

---

### Scénario 4 : Gestion des disponibilités avec scoping

#### 4.1 Mettre à jour une disponibilité

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\Models\Availability;

class AvailabilityUpdater
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService
    ) {}

    public function extendHours(Doctor $doctor, Availability $availability, string $newEnd): Availability
    {
        return $this->availabilityService
            ->for($doctor)
            ->update($availability->id, AvailabilityRecord::from([
                'daily_end' => $newEnd,
            ]));
    }

    public function addDays(Doctor $doctor, Availability $availability, array $newDays): Availability
    {
        return $this->availabilityService
            ->for($doctor)
            ->update($availability->id, AvailabilityRecord::from([
                'days' => $newDays,
            ]));
    }

    public function extendValidity(Doctor $doctor, Availability $availability, string $newEnd): Availability
    {
        return $this->availabilityService
            ->for($doctor)
            ->update($availability->id, AvailabilityRecord::from([
                'validity_end' => $newEnd,
            ]));
    }
}
```

#### 4.2 Supprimer une disponibilité avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Models\Availability;

class AvailabilityDeleter
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService
    ) {}

    public function softDelete(Doctor $doctor, Availability $availability): void
    {
        // Suppression avec validation (empêche si rendez-vous futurs)
        $this->availabilityService
            ->for($doctor)
            ->delete($availability->id);
    }

    public function forceDelete(Doctor $doctor, Availability $availability): void
    {
        // Suppression forcée (ignore la validation)
        $this->availabilityService
            ->for($doctor)
            ->delete($availability->id, true);
    }
}
```

#### 4.3 Rechercher des disponibilités avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use Illuminate\Support\Collection;

class AvailabilitySearchService
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService
    ) {}

    // Toutes les disponibilités d'un médecin avec scoping
    public function forDoctor(Doctor $doctor, int $limit = null): Collection
    {
        return $this->availabilityService
            ->for($doctor)
            ->findBySchedulable(null, $limit);
    }

    // Disponibilités actives aujourd'hui avec scoping
    public function activeToday(Doctor $doctor, int $limit = null): Collection
    {
        return $this->availabilityService
            ->for($doctor)
            ->findActiveAtDate($doctor, DateTimeZuluVO::today(), $limit);
    }

    // Par type avec scoping
    public function byType(Doctor $doctor, string $type, int $limit = null): Collection
    {
        return $this->availabilityService
            ->for($doctor)
            ->findByType($type, $limit);
    }

    // Vérifier si le médecin existe
    public function doctorExists(Doctor $doctor): bool
    {
        return $this->availabilityService->schedulableExists($doctor);
    }
}
```

---

### Scénario 5 : Gestion des cross-day (permanence de nuit) avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

class NightShiftService
{
    public function __construct(
        private AvailabilityServiceInterface $availabilityService,
        private SlotServiceInterface $slotService
    ) {}

    // Créer une permanence de nuit (22h-2h) avec scoping
    public function createNightShift(Doctor $doctor): Availability
    {
        return $this->availabilityService
            ->for($doctor)
            ->create(AvailabilityRecord::from([
                'name' => 'Permanence de nuit',
                'days' => ['monday', 'tuesday'], // Jours consécutifs pour cross-day
                'daily_start' => '22:00:00',
                'daily_end' => '02:00:00', // Cross-day automatiquement détecté
                'validity_start' => '2024-01-01T00:00:00Z',
                'validity_end' => '2024-12-31T23:59:59Z',
            ]));
    }

    // Rechercher les créneaux de nuit
    public function findNightSlots(Doctor $doctor, string $date, int $duration = 30): Collection
    {
        return $this->slotService->findSlotsForDay(
            $doctor,
            DateTimeZuluVO::from($date),
            $duration
        );
    }

    // Vérifier si un créneau de nuit est disponible
    public function isNightSlotAvailable(Doctor $doctor, string $start, string $end): bool
    {
        return $this->slotService->isSlotAvailable(
            $doctor,
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );
    }
}
```

---

### Scénario 6 : Utilisation des limites pour les grandes collections

```php
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;

class PaginatedScheduleService
{
    public function __construct(
        private ScheduleServiceInterface $scheduleService,
        private SlotServiceInterface $slotService
    ) {}

    // Récupérer les 10 premiers rendez-vous d'un médecin
    public function getRecentSchedules(Doctor $doctor, int $limit = 10): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->findBySchedulable(null, $limit);
    }

    // Récupérer les 5 premiers créneaux d'une journée
    public function getFirstSlotsOfDay(Doctor $doctor, string $date, int $limit = 5): SlotVOCollection
    {
        return $this->slotService->findSlotsForDay(
            $doctor,
            DateTimeZuluVO::from($date),
            30,
            null,
            $limit
        );
    }

    // Récupérer les 20 derniers rendez-vous complétés
    public function getCompletedSchedules(Doctor $doctor, int $limit = 20): Collection
    {
        return $this->scheduleService
            ->for($doctor)
            ->findByStatus(ScheduleStatus::COMPLETED, null, $limit);
    }
}
```

---

## ✅ Validation métier en action

### Exemples d'erreurs de validation

```php
// 1. Durée minimale non respectée
$record = AvailabilityRecord::from([
    'daily_start' => '09:00:00',
    'daily_end' => '09:05:00', // 5 minutes
]);
// ❌ "Availability duration must be at least 15 minutes. Current duration: 5 minutes."

// 2. Chevauchement de disponibilités
$record = AvailabilityRecord::from([
    'days' => ['monday'],
    'daily_start' => '10:00:00',
    'daily_end' => '12:00:00',
]);
// ❌ "Availability overlaps with existing availability #42"

// 3. Cross-day sans jours consécutifs
$record = AvailabilityRecord::from([
    'days' => ['monday', 'wednesday'],
    'daily_start' => '22:00:00',
    'daily_end' => '02:00:00',
]);
// ❌ "Availability crosses midnight but days array is not consecutive"

// 4. Buffer non respecté
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15T10:05:00Z',
    'end_datetime' => '2024-01-15T10:30:00Z',
]);
// ❌ "Buffer time of 15 minutes not respected between previous schedule #123"

// 5. Durée maximale dépassée
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15T09:00:00Z',
    'end_datetime' => '2024-01-15T17:00:00Z', // 8 heures
]);
// ❌ "Duration (8 hours) exceeds maximum allowed duration (4 hours)"
```

---

## 📚 API complète avec exemples de code

### AvailabilityService - Toutes les méthodes avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

$availabilityService = app(AvailabilityServiceInterface::class);
$doctor = Doctor::find(42);

// 1. create() - Créer une disponibilité avec scoping
$availability = $availabilityService
    ->for($doctor)
    ->create(AvailabilityRecord::from([
        'name' => 'Consultations',
        'days' => ['monday', 'wednesday', 'friday'],
        'daily_start' => '09:00:00',
        'daily_end' => '17:00:00',
        'validity_start' => '2024-01-01T00:00:00Z',
        'validity_end' => '2024-12-31T23:59:59Z',
    ]));

// 2. update() - Mettre à jour une disponibilité avec scoping
$updated = $availabilityService
    ->for($doctor)
    ->update($availability->id, AvailabilityRecord::from([
        'daily_end' => '18:00:00',
    ]));

// 3. delete() - Supprimer une disponibilité avec scoping
$deleted = $availabilityService
    ->for($doctor)
    ->delete($availability->id); // Avec validation
$forceDeleted = $availabilityService
    ->for($doctor)
    ->delete($availability->id, true); // Forcé

// 4. find() - Trouver par ID avec scoping
$availability = $availabilityService
    ->for($doctor)
    ->find(42);

// 5. findBySchedulable() - Toutes les disponibilités d'une entité avec scoping
$availabilities = $availabilityService
    ->for($doctor)
    ->findBySchedulable(null, 10); // Limit à 10

// 6. findByType() - Par type avec scoping
$consultations = $availabilityService
    ->for($doctor)
    ->findByType('consultation', 5);

// 7. findActiveAtDate() - Disponibilités actives à une date
$today = DateTimeZuluVO::today();
$active = $availabilityService
    ->for($doctor)
    ->findActiveAtDate($doctor, $today, 3);

// 8. findActiveInDateRange() - Disponibilités actives sur une période
$start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-31T23:59:59Z');
$activeInRange = $availabilityService
    ->for($doctor)
    ->findActiveInDateRange($doctor, $start, $end, 5);

// 9. schedulableExists() - Vérifier si l'entité existe
if ($availabilityService->schedulableExists($doctor)) {
    echo "Le médecin existe";
}

// 10. getSchedulableModel() - Récupérer le modèle
$model = $availabilityService->getSchedulableModel($doctor);
```

### ScheduleService - Toutes les méthodes avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

$scheduleService = app(ScheduleServiceInterface::class);
$doctor = Doctor::find(42);

// 1. create() - Créer un rendez-vous avec scoping
$schedule = $scheduleService
    ->for($doctor)
    ->create(ScheduleRecord::from([
        'availability_id' => $availability->id,
        'title' => 'Consultation patient',
        'start_datetime' => '2024-01-15T10:00:00Z',
        'end_datetime' => '2024-01-15T10:30:00Z',
        'status' => ScheduleStatus::BOOKED,
    ]));

// 2. update() - Mettre à jour un rendez-vous avec scoping
$updated = $scheduleService
    ->for($doctor)
    ->update($schedule->id, ScheduleRecord::from([
        'title' => 'Consultation urgente',
        'status' => ScheduleStatus::BOOKED,
    ]));

// 3. delete() - Supprimer un rendez-vous avec scoping
$deleted = $scheduleService
    ->for($doctor)
    ->delete($schedule->id);

// 4. find() - Trouver par ID avec scoping
$schedule = $scheduleService
    ->for($doctor)
    ->find(42);

// 5. findByAvailability() - Par disponibilité
$schedules = $scheduleService
    ->for($doctor)
    ->findByAvailability($availability->id, 10);

// 6. findBySchedulable() - Par entité avec scoping
$schedules = $scheduleService
    ->for($doctor)
    ->findBySchedulable(null, 10);

// 7. findByStatus() - Par statut avec scoping
$booked = $scheduleService
    ->for($doctor)
    ->findByStatus(ScheduleStatus::BOOKED, null, 5);
$completed = $scheduleService
    ->for($doctor)
    ->findByStatus(ScheduleStatus::COMPLETED, null, 5);

// 8. findByDate() - Par date avec scoping
$today = DateTimeZuluVO::today();
$todaySchedules = $scheduleService
    ->for($doctor)
    ->findByDate($today, null, 10);

// 9. findInDateRange() - Sur une période avec scoping
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-20T23:59:59Z');
$schedules = $scheduleService
    ->for($doctor)
    ->findInDateRange($start, $end, null, 20);

// 10. searchByTitle() - Recherche par titre avec scoping
$results = $scheduleService
    ->for($doctor)
    ->searchByTitle('Consultation', null, 5);

// 11. cancel() - Annuler un rendez-vous avec scoping
$cancelled = $scheduleService
    ->for($doctor)
    ->cancel($schedule->id);

// 12. complete() - Compléter un rendez-vous avec scoping
$completed = $scheduleService
    ->for($doctor)
    ->complete($schedule->id);

// 13. canBeCancelled() - Vérifier si annulable
if ($scheduleService->canBeCancelled($schedule)) {
    $scheduleService
        ->for($doctor)
        ->cancel($schedule->id);
}

// 14. canBeCompleted() - Vérifier si complétable
if ($scheduleService->canBeCompleted($schedule)) {
    $scheduleService
        ->for($doctor)
        ->complete($schedule->id);
}
```

### ImpedimentService - Toutes les méthodes avec scoping

```php
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

$impedimentService = app(ImpedimentServiceInterface::class);
$doctor = Doctor::find(42);

// 1. create() - Créer un empêchement avec scoping
$impediment = $impedimentService
    ->for($doctor)
    ->create(ImpedimentRecord::from([
        'availability_id' => $availability->id,
        'reason' => 'Formation obligatoire',
        'start_datetime' => '2024-01-15T14:00:00Z',
        'end_datetime' => '2024-01-15T16:00:00Z',
    ]));

// 2. update() - Mettre à jour un empêchement avec scoping
$updated = $impedimentService
    ->for($doctor)
    ->update($impediment->id, ImpedimentRecord::from([
        'reason' => 'Formation reportée',
        'start_datetime' => '2024-01-16T14:00:00Z',
        'end_datetime' => '2024-01-16T16:00:00Z',
    ]));

// 3. delete() - Supprimer un empêchement avec scoping
$deleted = $impedimentService
    ->for($doctor)
    ->delete($impediment->id);

// 4. find() - Trouver par ID avec scoping
$impediment = $impedimentService
    ->for($doctor)
    ->find(42);

// 5. findByAvailability() - Par disponibilité
$impediments = $impedimentService
    ->for($doctor)
    ->findByAvailability($availability->id, 10);

// 6. findBySchedulable() - Par entité avec scoping
$impediments = $impedimentService
    ->for($doctor)
    ->findBySchedulable(null, 10);

// 7. findByDate() - Par date avec scoping
$today = DateTimeZuluVO::today();
$impediments = $impedimentService
    ->for($doctor)
    ->findByDate($today, null, 5);

// 8. findInDateRange() - Sur une période avec scoping
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-20T23:59:59Z');
$impediments = $impedimentService
    ->for($doctor)
    ->findInDateRange($start, $end, null, 10);

// 9. findActive() - Empêchements actifs avec scoping
$active = $impedimentService
    ->for($doctor)
    ->findActive(null, 5);

// 10. searchByReason() - Par motif avec scoping
$results = $impedimentService
    ->for($doctor)
    ->searchByReason('formation', null, 3);

// 11. isActive() - Vérifier si actif
if ($impedimentService->isActive($impediment)) {
    echo "L'empêchement est en cours";
}

// 12. overlapsWith() - Vérifier chevauchement
$start = DateTimeZuluVO::from('2024-01-15T14:30:00Z');
$end = DateTimeZuluVO::from('2024-01-15T15:30:00Z');
if ($impedimentService->overlapsWith($impediment, $start, $end)) {
    echo "L'empêchement chevauche la période";
}

// 13. getBlockedSchedules() - Plannings bloqués
$blocked = $impedimentService
    ->for($doctor)
    ->getBlockedSchedules($impediment, 10);

// 14. getFullyBlockedSchedules() - Totalement bloqués
$fullyBlocked = $impedimentService
    ->for($doctor)
    ->getFullyBlockedSchedules($impediment, 10);

// 15. getPartiallyBlockedSchedules() - Partiellement bloqués
$partiallyBlocked = $impedimentService
    ->for($doctor)
    ->getPartiallyBlockedSchedules($impediment, 10);
```

### SlotService - Toutes les méthodes

```php
use AndyDefer\LaravelChronos\Contracts\Services\SlotServiceInterface;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\SlotVO;

$slotService = app(SlotServiceInterface::class);
$doctor = Doctor::find(42);

// 1. findNextSlot() - Prochain créneau disponible
$slot = $slotService->findNextSlot($doctor, DateTimeZuluVO::now(), 30);
if ($slot) {
    echo "Prochain créneau: " . $slot->getStart() . " - " . $slot->getEnd();
}

// 2. findPreviousSlot() - Dernier créneau avant une date
$slot = $slotService->findPreviousSlot($doctor, DateTimeZuluVO::now(), 30);

// 3. findSlotsInRange() - Créneaux sur une période avec limite
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');
$slots = $slotService->findSlotsInRange($doctor, $start, $end, 30, null, 10);
foreach ($slots as $slot) {
    echo $slot->getStart() . " - " . $slot->getEnd() . "\n";
}

// 4. findSlotsForDay() - Créneaux du jour avec limite
$today = DateTimeZuluVO::today();
$slots = $slotService->findSlotsForDay($doctor, $today, 30, null, 5);
echo "Total minutes disponibles: " . $slots->getTotalAvailableMinutes();

// 5. isSlotAvailable() - Vérifier disponibilité d'un créneau
$start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T10:30:00Z');
if ($slotService->isSlotAvailable($doctor, $start, $end)) {
    echo "Le créneau est disponible";
}

// 6. getNextAvailableStart() - Prochaine heure de début
$startTime = $slotService->getNextAvailableStart($doctor, DateTimeZuluVO::now(), 30);

// 7. hasAvailabilityOnDate() - Vérifier si disponibilité ce jour
if ($slotService->hasAvailabilityOnDate($doctor, $today)) {
    echo "Le médecin est disponible aujourd'hui";
}

// 8. getBlockedPeriods() - Périodes bloquées avec limite
$blocked = $slotService->getBlockedPeriods($doctor, $start, $end, null, 10);
foreach ($blocked as $period) {
    echo "Bloqué par " . $period->getType() . " #" . $period->getId() . "\n";
    echo "De " . $period->getStart() . " à " . $period->getEnd() . "\n";
}

// 9. generateSlotsFromSlot() - Découper un créneau avec limite
$slot = SlotVO::fromDuration(DateTimeZuluVO::from('2024-01-15T10:00:00Z'), 60);
$chunks = $slotService->generateSlotsFromSlot($slot, 15, 3);
foreach ($chunks as $chunk) {
    echo $chunk->getStart() . " - " . $chunk->getEnd() . "\n";
}
```

---

## 🔧 Bonnes pratiques

### ✅ Injection des interfaces

```php
// BON - Injection des interfaces
class BookingController
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly ScheduleServiceInterface $scheduleService,
        private readonly SlotServiceInterface $slotService
    ) {}
}

// ÉVITER - Résolution directe
class BookingController
{
    public function book(Request $request)
    {
        $service = app(ScheduleServiceInterface::class); // ❌
    }
}
```

### ✅ Utilisation du scoping

```php
// BON - Utilisation du scoping
$availability = $availabilityService
    ->for($doctor)
    ->create($record);
// schedulable_type et schedulable_id sont automatiquement injectés

// ÉVITER - Spécification manuelle
$availability = $availabilityService->create(AvailabilityRecord::from([
    ...$record->toArray(),
    'schedulable_type' => Doctor::class,
    'schedulable_id' => $doctor->id,
]));
```

### ✅ Utilisation des limites

```php
// BON - Utilisation des limites pour les grandes collections
$schedules = $scheduleService
    ->for($doctor)
    ->findBySchedulable(null, 10);

$slots = $slotService->findSlotsForDay($doctor, $today, 30, null, 5);

// ÉVITER - Récupération sans limite
$schedules = $scheduleService
    ->for($doctor)
    ->findBySchedulable(); // Peut être lourd
```

### ✅ Gestion des erreurs

```php
try {
    $schedule = $scheduleService
        ->for($doctor)
        ->create($record);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    foreach ($errors as $error) {
        Log::error('Validation échouée: ' . $error);
    }
    return response()->json(['errors' => $errors], 422);
} catch (ModelNotFoundException $e) {
    return response()->json(['error' => $e->getMessage()], 404);
}
```

### ✅ Utilisation des collections typées

```php
// Les collections typées offrent des helpers
$slots = $slotService->findSlotsForDay($doctor, $today, 30);
$totalMinutes = $slots->getTotalAvailableMinutes();
$firstSlot = $slots->firstSlot();
$earliest = $slots->getEarliestStart();
$latest = $slots->getLatestEnd();

// L'IDE vous donne l'autocomplétion sur SlotVOCollection
```

---

## ⚙️ Configuration

```php
// config/chronos.php
return [
    'min_durations' => [
        'availability' => 15,
        'schedule' => 15,
        'impediment' => 15,
        'slot_search' => 5,
    ],
    'max_duration' => 240,
    'buffer_time' => 0,
];
```

---

## 📄 Licence

MIT © [Andy Defer](https://github.com/andydefer)