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

// 1. Créer une disponibilité
$availability = $availabilityService->create(AvailabilityRecord::from([
    'name' => 'Consultations',
    'days' => ['monday', 'wednesday', 'friday'],
    'daily_start' => '09:00:00',
    'daily_end' => '17:00:00',
    'validity_start' => '2024-01-01T00:00:00Z',
    'validity_end' => '2024-12-31T23:59:59Z',
    'schedulable_type' => Doctor::class,
    'schedulable_id' => $doctor->id,
]));

// 2. Créer un rendez-vous
$schedule = $scheduleService->create(ScheduleRecord::from([
    'availability_id' => $availability->id,
    'schedulable_type' => Doctor::class,
    'schedulable_id' => $doctor->id,
    'title' => 'Consultation patient',
    'start_datetime' => '2024-01-15T10:00:00Z',
    'end_datetime' => '2024-01-15T10:30:00Z',
    'status' => ScheduleStatus::BOOKED,
]));

// 3. Trouver le prochain créneau disponible
$nextSlot = $slotService->findNextSlot($doctor, now(), 30);
```

---

## 📅 Cas d'usage complets

### Scénario 1 : Gestion d'un cabinet médical

#### 1.1 Créer le planning d'un médecin

```php
use AndyDefer\LaravelChronos\Services\AvailabilityService;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;

class DoctorScheduleSetup
{
    public function __construct(private AvailabilityService $availabilityService) {}

    public function setup(Doctor $doctor): void
    {
        // Matin : Consultations classiques
        $this->availabilityService->create(AvailabilityRecord::from([
            'name' => 'Consultations matin',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '12:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => Doctor::class,
            'schedulable_id' => $doctor->id,
        ]));

        // Après-midi : Consultations uniquement certains jours
        $this->availabilityService->create(AvailabilityRecord::from([
            'name' => 'Consultations après-midi',
            'days' => ['monday', 'tuesday', 'thursday'],
            'daily_start' => '14:00:00',
            'daily_end' => '18:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => Doctor::class,
            'schedulable_id' => $doctor->id,
        ]));

        // Permanence téléphonique
        $this->availabilityService->create(AvailabilityRecord::from([
            'name' => 'Permanence téléphonique',
            'days' => ['monday', 'wednesday'],
            'daily_start' => '19:00:00',
            'daily_end' => '21:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-06-30T23:59:59Z',
            'schedulable_type' => Doctor::class,
            'schedulable_id' => $doctor->id,
        ]));
    }
}
```

#### 1.2 Prendre un rendez-vous

```php
use AndyDefer\LaravelChronos\Services\SlotService;
use AndyDefer\LaravelChronos\Services\ScheduleService;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;

class BookingService
{
    public function __construct(
        private SlotService $slotService,
        private ScheduleService $scheduleService
    ) {}

    public function book(Doctor $doctor, Patient $patient, int $duration): Schedule
    {
        // Trouver le prochain créneau disponible
        $slot = $this->slotService->findNextSlot($doctor, now(), $duration);

        if (!$slot) {
            throw new \RuntimeException('Aucun créneau disponible');
        }

        // Vérifier que le créneau est toujours disponible
        if (!$this->slotService->isSlotAvailable($doctor, $slot->getStart(), $slot->getEnd())) {
            throw new \RuntimeException('Le créneau n\'est plus disponible');
        }

        // Créer le rendez-vous
        return $this->scheduleService->create(ScheduleRecord::from([
            'availability_id' => $this->getAvailabilityId($doctor, $slot->getStart()),
            'schedulable_type' => Doctor::class,
            'schedulable_id' => $doctor->id,
            'title' => 'Consultation - ' . $patient->name,
            'start_datetime' => $slot->getStart()->getValue(),
            'end_datetime' => $slot->getEnd()->getValue(),
            'status' => ScheduleStatus::BOOKED,
        ]));
    }

    private function getAvailabilityId(Doctor $doctor, DateTimeZuluVO $date): int
    {
        return $this->availabilityService->findActiveAtDate($doctor, $date)->first()->id;
    }
}
```

#### 1.3 Annuler un rendez-vous

```php
class AppointmentManager
{
    public function __construct(private ScheduleService $scheduleService) {}

    public function cancel(Schedule $schedule): void
    {
        // Vérifier si annulable
        if (!$this->scheduleService->canBeCancelled($schedule)) {
            throw new \RuntimeException(
                'Impossible d\'annuler (statut: ' . $schedule->status->value . ')'
            );
        }

        // Annuler
        $this->scheduleService->cancel($schedule->id);
    }

    public function complete(Schedule $schedule): void
    {
        // Vérifier si complétable
        if (!$this->scheduleService->canBeCompleted($schedule)) {
            throw new \RuntimeException(
                'Impossible de compléter (statut: ' . $schedule->status->value . ')'
            );
        }

        $this->scheduleService->complete($schedule->id);
    }
}
```

#### 1.4 Rechercher des rendez-vous

```php
class ScheduleSearchService
{
    public function __construct(private ScheduleService $scheduleService) {}

    // Rendez-vous du jour
    public function today(Doctor $doctor): Collection
    {
        return $this->scheduleService->findByDate($doctor, DateTimeZuluVO::today());
    }

    // Rendez-vous par statut
    public function byStatus(Doctor $doctor, ScheduleStatus $status): Collection
    {
        return $this->scheduleService->findByStatus($status);
    }

    // Rendez-vous par titre
    public function search(Doctor $doctor, string $query): Collection
    {
        return $this->scheduleService->searchByTitle($query);
    }

    // Rendez-vous sur une période
    public function inRange(Doctor $doctor, string $start, string $end): Collection
    {
        return $this->scheduleService->findInDateRange(
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );
    }
}
```

---

### Scénario 2 : Gestion des empêchements

#### 2.1 Bloquer une période pour formation

```php
use AndyDefer\LaravelChronos\Services\ImpedimentService;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;

class ImpedimentManager
{
    public function __construct(
        private ImpedimentService $impedimentService,
        private SlotService $slotService
    ) {}

    public function blockForTraining(Doctor $doctor, string $start, string $end): void
    {
        // Vérifier les conflits
        $conflicts = $this->slotService->getBlockedPeriods(
            $doctor,
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );

        if ($conflicts->isNotEmpty()) {
            throw new \RuntimeException('Des rendez-vous existent déjà sur cette période');
        }

        // Créer l'empêchement
        $availability = $this->getAvailability($doctor, $start);
        $this->impedimentService->create(ImpedimentRecord::from([
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
class ImpactAnalyzer
{
    public function __construct(private ImpedimentService $impedimentService) {}

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

#### 2.3 Rechercher des empêchements

```php
class ImpedimentSearchService
{
    public function __construct(private ImpedimentService $impedimentService) {}

    // Empêchements actifs
    public function active(Doctor $doctor): Collection
    {
        return $this->impedimentService->findActive();
    }

    // Par motif
    public function byReason(string $reason): Collection
    {
        return $this->impedimentService->searchByReason($reason);
    }

    // Par date
    public function byDate(Doctor $doctor, string $date): Collection
    {
        return $this->impedimentService->findByDate(DateTimeZuluVO::from($date));
    }

    // Par plage
    public function inRange(Doctor $doctor, string $start, string $end): Collection
    {
        return $this->impedimentService->findInDateRange(
            DateTimeZuluVO::from($start),
            DateTimeZuluVO::from($end)
        );
    }
}
```

---

### Scénario 3 : Recherche avancée de créneaux

#### 3.1 Trouver les créneaux sur une semaine

```php
class WeekScheduleService
{
    public function __construct(private SlotService $slotService) {}

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
```

#### 3.2 Analyser les périodes bloquées

```php
class BlockedPeriodsService
{
    public function __construct(private SlotService $slotService) {}

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
```

#### 3.3 Découper des créneaux

```php
class SlotSplitService
{
    public function __construct(private SlotService $slotService) {}

    public function splitIntoChunks(SlotVO $slot, int $chunkDuration): SlotVOCollection
    {
        return $this->slotService->generateSlotsFromSlot($slot, $chunkDuration);
    }

    // Vérifier si un créneau est disponible
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

### Scénario 4 : Gestion des disponibilités

#### 4.1 Mettre à jour une disponibilité

```php
class AvailabilityUpdater
{
    public function __construct(private AvailabilityService $availabilityService) {}

    public function extendHours(Availability $availability, string $newEnd): Availability
    {
        return $this->availabilityService->update($availability->id, AvailabilityRecord::from([
            'daily_end' => $newEnd,
        ]));
    }

    public function addDays(Availability $availability, array $newDays): Availability
    {
        return $this->availabilityService->update($availability->id, AvailabilityRecord::from([
            'days' => $newDays,
        ]));
    }

    public function extendValidity(Availability $availability, string $newEnd): Availability
    {
        return $this->availabilityService->update($availability->id, AvailabilityRecord::from([
            'validity_end' => $newEnd,
        ]));
    }
}
```

#### 4.2 Supprimer une disponibilité

```php
class AvailabilityDeleter
{
    public function __construct(private AvailabilityService $availabilityService) {}

    public function softDelete(Availability $availability): void
    {
        // Suppression avec validation (empêche si rendez-vous futurs)
        $this->availabilityService->delete($availability->id);
    }

    public function forceDelete(Availability $availability): void
    {
        // Suppression forcée (ignore la validation)
        $this->availabilityService->delete($availability->id, true);
    }
}
```

#### 4.3 Rechercher des disponibilités

```php
class AvailabilitySearchService
{
    public function __construct(private AvailabilityService $availabilityService) {}

    // Toutes les disponibilités d'un médecin
    public function forDoctor(Doctor $doctor): Collection
    {
        return $this->availabilityService->findBySchedulable($doctor);
    }

    // Disponibilités actives aujourd'hui
    public function activeToday(Doctor $doctor): Collection
    {
        return $this->availabilityService->findActiveAtDate($doctor, DateTimeZuluVO::today());
    }

    // Par type
    public function byType(Doctor $doctor, string $type): Collection
    {
        return $this->availabilityService->findByType($type);
    }

    // Vérifier si le médecin existe
    public function doctorExists(Doctor $doctor): bool
    {
        return $this->availabilityService->schedulableExists($doctor);
    }
}
```

---

### Scénario 5 : Gestion des cross-day (permanence de nuit)

```php
class NightShiftService
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private SlotService $slotService
    ) {}

    // Créer une permanence de nuit (22h-2h)
    public function createNightShift(Doctor $doctor): Availability
    {
        return $this->availabilityService->create(AvailabilityRecord::from([
            'name' => 'Permanence de nuit',
            'days' => ['monday', 'tuesday'], // Jours consécutifs pour cross-day
            'daily_start' => '22:00:00',
            'daily_end' => '02:00:00', // Cross-day automatiquement détecté
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => Doctor::class,
            'schedulable_id' => $doctor->id,
        ]));
    }

    // Rechercher les créneaux de nuit
    public function findNightSlots(Doctor $doctor, string $date): Collection
    {
        return $this->slotService->findSlotsForDay(
            $doctor,
            DateTimeZuluVO::from($date),
            30
        );
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

### AvailabilityService - Toutes les méthodes avec exemples

```php
use AndyDefer\LaravelChronos\Contracts\Services\AvailabilityServiceInterface;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;

$availabilityService = app(AvailabilityServiceInterface::class);
$doctor = Doctor::find(42);

// 1. create() - Créer une disponibilité
$availability = $availabilityService->create(AvailabilityRecord::from([
    'name' => 'Consultations',
    'days' => ['monday', 'wednesday', 'friday'],
    'daily_start' => '09:00:00',
    'daily_end' => '17:00:00',
    'validity_start' => '2024-01-01T00:00:00Z',
    'validity_end' => '2024-12-31T23:59:59Z',
    'schedulable_type' => Doctor::class,
    'schedulable_id' => $doctor->id,
]));

// 2. update() - Mettre à jour une disponibilité
$updated = $availabilityService->update($availability->id, AvailabilityRecord::from([
    'daily_end' => '18:00:00',
]));

// 3. delete() - Supprimer une disponibilité
$deleted = $availabilityService->delete($availability->id); // Avec validation
$forceDeleted = $availabilityService->delete($availability->id, true); // Forcé

// 4. find() - Trouver par ID
$availability = $availabilityService->find(42);

// 5. findBySchedulable() - Toutes les disponibilités d'une entité
$doctor = Doctor::find(42);
$availabilities = $availabilityService->findBySchedulable($doctor);

// 6. findByType() - Par type
$consultations = $availabilityService->findByType('consultation');

// 7. findActiveAtDate() - Disponibilités actives à une date
$today = DateTimeZuluVO::today();
$active = $availabilityService->findActiveAtDate($doctor, $today);

// 8. findActiveInDateRange() - Disponibilités actives sur une période
$start = DateTimeZuluVO::from('2024-01-01T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-31T23:59:59Z');
$activeInRange = $availabilityService->findActiveInDateRange($doctor, $start, $end);

// 9. schedulableExists() - Vérifier si l'entité existe
if ($availabilityService->schedulableExists($doctor)) {
    echo "Le médecin existe";
}

// 10. getSchedulableModel() - Récupérer le modèle
$model = $availabilityService->getSchedulableModel($doctor);
```

### ScheduleService - Toutes les méthodes avec exemples

```php
use AndyDefer\LaravelChronos\Contracts\Services\ScheduleServiceInterface;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;

$scheduleService = app(ScheduleServiceInterface::class);

// 1. create() - Créer un rendez-vous
$schedule = $scheduleService->create(ScheduleRecord::from([
    'availability_id' => $availability->id,
    'schedulable_type' => Doctor::class,
    'schedulable_id' => $doctor->id,
    'title' => 'Consultation patient',
    'start_datetime' => '2024-01-15T10:00:00Z',
    'end_datetime' => '2024-01-15T10:30:00Z',
    'status' => ScheduleStatus::BOOKED,
]));

// 2. update() - Mettre à jour un rendez-vous
$updated = $scheduleService->update($schedule->id, ScheduleRecord::from([
    'title' => 'Consultation urgente',
    'status' => ScheduleStatus::BOOKED,
]));

// 3. delete() - Supprimer un rendez-vous
$deleted = $scheduleService->delete($schedule->id);

// 4. find() - Trouver par ID
$schedule = $scheduleService->find(42);

// 5. findByAvailability() - Par disponibilité
$schedules = $scheduleService->findByAvailability($availability->id);

// 6. findBySchedulable() - Par entité
$doctor = Doctor::find(42);
$schedules = $scheduleService->findBySchedulable($doctor);

// 7. findByStatus() - Par statut
$booked = $scheduleService->findByStatus(ScheduleStatus::BOOKED);
$completed = $scheduleService->findByStatus(ScheduleStatus::COMPLETED);

// 8. findByDate() - Par date
$today = DateTimeZuluVO::today();
$todaySchedules = $scheduleService->findByDate($today);

// 9. findInDateRange() - Sur une période
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-20T23:59:59Z');
$schedules = $scheduleService->findInDateRange($start, $end);

// 10. searchByTitle() - Recherche par titre
$results = $scheduleService->searchByTitle('Consultation');

// 11. cancel() - Annuler un rendez-vous
$cancelled = $scheduleService->cancel($schedule->id);

// 12. complete() - Compléter un rendez-vous
$completed = $scheduleService->complete($schedule->id);

// 13. canBeCancelled() - Vérifier si annulable
if ($scheduleService->canBeCancelled($schedule)) {
    $scheduleService->cancel($schedule->id);
}

// 14. canBeCompleted() - Vérifier si complétable
if ($scheduleService->canBeCompleted($schedule)) {
    $scheduleService->complete($schedule->id);
}
```

### ImpedimentService - Toutes les méthodes avec exemples

```php
use AndyDefer\LaravelChronos\Contracts\Services\ImpedimentServiceInterface;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;

$impedimentService = app(ImpedimentServiceInterface::class);

// 1. create() - Créer un empêchement
$impediment = $impedimentService->create(ImpedimentRecord::from([
    'availability_id' => $availability->id,
    'reason' => 'Formation obligatoire',
    'start_datetime' => '2024-01-15T14:00:00Z',
    'end_datetime' => '2024-01-15T16:00:00Z',
]));

// 2. update() - Mettre à jour un empêchement
$updated = $impedimentService->update($impediment->id, ImpedimentRecord::from([
    'reason' => 'Formation reportée',
    'start_datetime' => '2024-01-16T14:00:00Z',
    'end_datetime' => '2024-01-16T16:00:00Z',
]));

// 3. delete() - Supprimer un empêchement
$deleted = $impedimentService->delete($impediment->id);

// 4. find() - Trouver par ID
$impediment = $impedimentService->find(42);

// 5. findByAvailability() - Par disponibilité
$impediments = $impedimentService->findByAvailability($availability->id);

// 6. findBySchedulable() - Par entité
$doctor = Doctor::find(42);
$impediments = $impedimentService->findBySchedulable($doctor);

// 7. findByDate() - Par date
$today = DateTimeZuluVO::today();
$impediments = $impedimentService->findByDate($today);

// 8. findInDateRange() - Sur une période
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-20T23:59:59Z');
$impediments = $impedimentService->findInDateRange($start, $end);

// 9. findActive() - Empêchements actifs
$active = $impedimentService->findActive();

// 10. searchByReason() - Par motif
$results = $impedimentService->searchByReason('formation');

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
$blocked = $impedimentService->getBlockedSchedules($impediment);
foreach ($blocked as $schedule) {
    echo $schedule->title . "\n";
}

// 14. getFullyBlockedSchedules() - Totalement bloqués
$fullyBlocked = $impedimentService->getFullyBlockedSchedules($impediment);

// 15. getPartiallyBlockedSchedules() - Partiellement bloqués
$partiallyBlocked = $impedimentService->getPartiallyBlockedSchedules($impediment);
```

### SlotService - Toutes les méthodes avec exemples

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

// 3. findSlotsInRange() - Créneaux sur une période
$start = DateTimeZuluVO::from('2024-01-15T00:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T23:59:59Z');
$slots = $slotService->findSlotsInRange($doctor, $start, $end, 30);
foreach ($slots as $slot) {
    echo $slot->getStart() . " - " . $slot->getEnd() . "\n";
}

// 4. findSlotsForDay() - Créneaux du jour
$today = DateTimeZuluVO::today();
$slots = $slotService->findSlotsForDay($doctor, $today, 30);
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

// 8. getBlockedPeriods() - Périodes bloquées
$blocked = $slotService->getBlockedPeriods($doctor, $start, $end);
foreach ($blocked as $period) {
    echo "Bloqué par " . $period->getType() . " #" . $period->getId() . "\n";
    echo "De " . $period->getStart() . " à " . $period->getEnd() . "\n";
}

// 9. generateSlotsFromSlot() - Découper un créneau
$slot = SlotVO::fromDuration(DateTimeZuluVO::from('2024-01-15T10:00:00Z'), 60);
$chunks = $slotService->generateSlotsFromSlot($slot, 15);
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

### ✅ Utilisation de from() avec strings

```php
// BON - from() convertit automatiquement
$record = AvailabilityRecord::from([
    'daily_start' => '09:00:00', // Auto-converti en TimeZuluVO
    'validity_start' => '2024-01-01T00:00:00Z', // Auto-converti en DateTimeZuluVO
]);

// ÉVITER - Instanciation manuelle des Value Objects
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('09:00:00'), // Inutile
]);
```

### ✅ Gestion des erreurs

```php
try {
    $schedule = $scheduleService->create($record);
} catch (ValidationException $e) {
    // Récupérer les erreurs détaillées
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
$totalMinutes = $slots->getTotalAvailableMinutes(); // Helpers disponibles
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