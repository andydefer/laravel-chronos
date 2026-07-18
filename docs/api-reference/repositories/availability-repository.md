# AvailabilityRepository - Référence Technique

## Description

Repository pour la gestion des entités `Availability` dans la base de données. Fournit des méthodes spécialisées pour rechercher des disponibilités selon différents critères : entité planifiable, jours, plages horaires, périodes de validité, etc.

## Hiérarchie

```
AbstractChronosRepository
    └── AvailabilityRepository
        └── AvailabilityRepositoryInterface
```

## Rôle principal

Gérer l'accès aux données des disponibilités en encapsulant les requêtes complexes. Le repository fournit une couche d'abstraction entre la logique métier et la base de données, avec des méthodes dédiées pour les cas d'usage courants.

---

## API

### `findBySchedulable(Model $schedulable): Collection`

Retourne toutes les disponibilités pour une entité planifiable donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable (ex: `User::find(42)`) |

**Retourne :** `Collection<int, Availability>` - Collection de disponibilités

**Exemple :**
```php
$user = User::find(42);
$availabilities = $repository->findBySchedulable($user);
```

---

### `findByDay(Model $schedulable, WeekDay $day): Collection`

Retourne les disponibilités pour un jour spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$day` | `WeekDay` | Jour de la semaine |

**Retourne :** `Collection<int, Availability>` - Disponibilités pour ce jour

**Exemple :**
```php
$user = User::find(42);
$mondaySlots = $repository->findByDay($user, WeekDay::MONDAY);
```

---

### `findOverlapping(...): Collection`

Trouve les disponibilités qui chevauchent une plage horaire donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$day` | `WeekDay` | Jour de la semaine |
| `$startTime` | `TimeZuluVO` | Heure de début |
| `$endTime` | `TimeZuluVO` | Heure de fin |
| `$validityStart` | `DateTimeZuluVO` | Début de la période de validité |
| `$validityEnd` | `DateTimeZuluVO` | Fin de la période de validité |
| `$excludeId` | `int|null` | ID à exclure (pour les mises à jour) |

**Retourne :** `Collection<int, Availability>` - Disponibilités en conflit

**Exemple :**
```php
$user = User::find(42);
$overlapping = $repository->findOverlapping(
    $user,
    WeekDay::MONDAY,
    TimeZuluVO::from('09:00:00'),
    TimeZuluVO::from('17:00:00'),
    DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    DateTimeZuluVO::from('2024-12-31T23:59:59Z')
);
```

---

### `findActiveAtDate(Model $schedulable, DateTimeZuluVO $date): Collection`

Trouve les disponibilités actives à une date donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$date` | `DateTimeZuluVO` | Date à vérifier |

**Retourne :** `Collection<int, Availability>` - Disponibilités actives

**Exemple :**
```php
$user = User::find(42);
$todaySlots = $repository->findActiveAtDate($user, DateTimeZuluVO::now());
```

---

### `findActiveInDateRange(...): Collection`

Trouve les disponibilités actives dans une plage de dates.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$excludeId` | `int|null` | ID à exclure |

**Retourne :** `Collection<int, Availability>` - Disponibilités dans la plage

---

### `findCrossDayAvailabilities(Model $schedulable): Collection`

Trouve les disponibilités qui chevauchent minuit (daily_start > daily_end).

**Retourne :** `Collection<int, Availability>` - Disponibilités cross-day

**Exemple :**
```php
$user = User::find(42);
$crossDay = $repository->findCrossDayAvailabilities($user);
// Ex: 22:00 - 06:00
```

---

### `findShortDurations(Model $schedulable, int $minMinutes): Collection`

Trouve les disponibilités avec une durée inférieure à un seuil.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$minMinutes` | `int` | Durée minimale en minutes |

**Retourne :** `Collection<int, Availability>` - Disponibilités trop courtes

---

### `findInvalidDateRanges(Model $schedulable): Collection`

Trouve les disponibilités avec des plages de dates invalides.

**Retourne :** `Collection<int, Availability>` - Disponibilités invalides

**Cas détectés :**
- daily_start >= daily_end
- validity_start >= validity_end
- validity_start ou validity_end null

---

### `findWithFutureSchedules(int $availabilityId, DateTimeZuluVO $now): bool`

Vérifie si une disponibilité a des rendez-vous futurs.

**Retourne :** `bool` - True si des rendez-vous futurs existent

**Exemple :**
```php
$hasFuture = $repository->findWithFutureSchedules(
    $availabilityId,
    DateTimeZuluVO::now()
);
```

---

### `findByType(string $type): Collection`

Trouve les disponibilités par type.

**Retourne :** `Collection<int, Availability>` - Disponibilités du type

---

### `schedulableExists(Model $schedulable): bool`

Vérifie si une entité planifiable existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |

**Retourne :** `bool` - True si l'entité existe

**Exemple :**
```php
$user = User::find(42);
if ($repository->schedulableExists($user)) {
    // L'utilisateur existe
}
```

---

### `getSchedulableModel(Model $schedulable): ?string`

Retourne le nom de la classe du modèle planifiable si l'entité existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |

**Retourne :** `string|null` - Nom de la classe ou null si inexistante

**Exemple :**
```php
$user = User::find(42);
$class = $repository->getSchedulableModel($user);
// 'App\Models\User'
```

---

## Cas d'utilisation

### Cas 1 : Vérification des conflits

```php
$user = User::find(42);

$conflicts = $repository->findOverlapping(
    $user,
    WeekDay::MONDAY,
    TimeZuluVO::from('10:00:00'),
    TimeZuluVO::from('11:00:00'),
    DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    DateTimeZuluVO::from('2024-12-31T23:59:59Z')
);

if ($conflicts->isEmpty()) {
    // Créer la disponibilité
}
```

### Cas 2 : Planning hebdomadaire

```php
$user = User::find(42);

$allAvailabilities = $repository->findBySchedulable($user);

$mondaySlots = $repository->findByDay($user, WeekDay::MONDAY);
$tuesdaySlots = $repository->findByDay($user, WeekDay::TUESDAY);
```

### Cas 3 : Validation des données

```php
$user = User::find(42);

$invalid = $repository->findInvalidDateRanges($user);

foreach ($invalid as $availability) {
    $repository->delete($availability->id);
}
```

### Cas 4 : Vérification d'existence

```php
$user = User::find(42);

if ($repository->schedulableExists($user)) {
    $class = $repository->getSchedulableModel($user);
    echo "L'entité $class existe";
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Entité planifiable inexistante | `Throwable` | Exception du modèle |
| ID inexistant | `ModelNotFoundException` | `No query results for model [Availability]` |
| Filtres invalides | `InvalidArgumentException` | Variable selon le contexte |

---

## Performance

| Aspect | Considération |
|--------|---------------|
| **Index recommandés** | `schedulable_type`, `schedulable_id`, `validity_start`, `validity_end` |
| **Requêtes** | Optimisées avec des `where` conditionnels |
| **Collections** | Utilisation de `Collection` pour la manipulation des résultats |
| **Cache** | Non utilisé - données en temps réel |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelChronos\Repositories\AvailabilityRepository;
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;
use AndyDefer\LaravelChronos\Enums\WeekDay;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\ValueObjects\TimeZuluVO;

$repository = new AvailabilityRepository();
$user = User::find(42);

// Créer une disponibilité
$record = AvailabilityRecord::from([
    'name' => 'Working Hours',
    'type' => 'standard',
    'days' => WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('17:00:00'),
    'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-12-31T23:59:59Z'),
    'schedulable_type' => get_class($user),
    'schedulable_id' => $user->id,
]);

$availability = $repository->create($record);

// Vérifier les conflits
$conflicts = $repository->findOverlapping(
    $user,
    WeekDay::MONDAY,
    TimeZuluVO::from('09:00:00'),
    TimeZuluVO::from('12:00:00'),
    DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    DateTimeZuluVO::from('2024-12-31T23:59:59Z')
);

if ($conflicts->isNotEmpty()) {
    echo 'Conflit détecté pour ' . $conflicts->first()->name;
}

// Vérifier la disponibilité pour aujourd'hui
$today = DateTimeZuluVO::now();
$active = $repository->findActiveAtDate($user, $today);

echo 'Disponibilités actives aujourd\'hui: ' . $active->count();

// Vérifier si l'utilisateur existe
if ($repository->schedulableExists($user)) {
    echo "L'utilisateur existe";
}
```

---

## Voir aussi

- `AbstractChronosRepository` - Classe parente
- `AvailabilityRepositoryInterface` - Interface implémentée
- `AvailabilityRecord` - Record de données
- `Availability` - Modèle Eloquent
- `WeekDay` - Énumération des jours
- `TimeZuluVO` - Value Object pour les heures
- `DateTimeZuluVO` - Value Object pour les dates
- `WeekDayCollection` - Collection des jours