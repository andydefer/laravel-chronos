# 📋 Règles de Validation - Laravel Chronos

> **Note :** Le système fonctionne exclusivement en UTC. Toutes les dates et heures sont stockées et validées en UTC via `DateTimeZuluVO` et `TimeZuluVO`.

---

## 🔹 Règles pour Availability (Disponibilités)

---

### 1. AvailabilityRequiredFieldsRule

**Description :** Vérifie que tous les champs obligatoires sont présents lors de la création d'une disponibilité.

**Explication :** Une disponibilité doit avoir un nom, une plage horaire quotidienne et être associée à une entité propriétaire.

**Champs requis :**

| Champ | Type | Description |
|-------|------|-------------|
| `name` | `string` | Nom de la disponibilité (ex: "Consultations matin") |
| `daily_start` | `TimeZuluVO` | Heure de début dans la journée (ex: "09:00:00") |
| `daily_end` | `TimeZuluVO` | Heure de fin dans la journée (ex: "17:00:00") |
| `schedulable_type` | `string` | Type de l'entité propriétaire (ex: "App\Models\User") |
| `schedulable_id` | `int` | ID de l'entité propriétaire (ex: 1) |

**Supporte :** CREATE uniquement

**Exemple :**
```php
// ✅ Valide
$record = AvailabilityRecord::from([
    'name' => 'Consultations',
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('17:00:00'),
    'schedulable_type' => User::class,
    'schedulable_id' => 42,
]);

// ❌ Invalide - manque daily_start et daily_end
$record = AvailabilityRecord::from([
    'name' => 'Consultations',
    'schedulable_type' => User::class,
    'schedulable_id' => 42,
]);
```

**Erreur :** `The following fields are required for availability creation: daily_start, daily_end`

---

### 2. AvailabilityDaysFormatRule

**Description :** Valide que les jours de la semaine sont correctement formatés.

**Explication :** Les jours doivent être au format correct, en minuscules, et correspondre aux valeurs de l'enum `WeekDay`.

**Règles :**
- Le champ `days` doit être un `WeekDayCollection`
- Chaque jour doit être une valeur valide de l'enum `WeekDay`
- La collection ne doit pas être vide
- Pas de doublons dans la collection

**Supporte :** CREATE, UPDATE

**Exemples :**
```php
// ✅ Valide - WeekDayCollection
use AndyDefer\LaravelChronos\Collections\WeekDayCollection;

$days = WeekDayCollection::fromStrings(['monday', 'wednesday', 'friday']);

// ❌ Invalide - tableau brut
$record = AvailabilityRecord::from([
    'days' => ['monday', 'wednesday', 'friday'], // Erreur: doit être WeekDayCollection
]);

// ❌ Invalide - jours invalides
$days = WeekDayCollection::fromStrings(['monday', 'invalid']);

// ❌ Invalide - collection vide
$days = WeekDayCollection::fromStrings([]);

// ❌ Invalide - doublons
$days = WeekDayCollection::fromStrings(['monday', 'monday']);
```

**Erreurs :**
- `At least one day must be specified for availability.`
- `Days must be provided as a WeekDayCollection.`
- `Invalid day(s): invalid. Allowed days are: monday, tuesday...`
- `Duplicate day(s) found: monday`

---

### 3. DaysWithinValidityPeriodRule

**Description :** Vérifie que les jours sélectionnés existent réellement dans la période de validité définie.

**Explication :** Si une disponibilité est valide du 1er janvier au 7 janvier (7 jours), on ne peut pas y associer un jour qui tombe en dehors de cette période.

**Supporte :** CREATE, UPDATE

**Vérification :**
- Tous les jours spécifiés dans `days` doivent être compris entre `validity_start` et `validity_end`
- Si `validity_start` et `validity_end` ne sont pas définis → validité perpétuelle (tous les jours autorisés)

**Exemple :**
```php
// ✅ Valide - tous les jours dans la période
$record = AvailabilityRecord::from([
    'days' => WeekDayCollection::fromStrings(['monday', 'tuesday', 'wednesday']),
    'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-01-10T23:59:59Z'),
]);

// ❌ Invalide - samedi n'est pas dans la période (01/01 au 05/01)
$record = AvailabilityRecord::from([
    'days' => WeekDayCollection::fromStrings(['saturday']),
    'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-01-05T23:59:59Z'),
]);
```

**Erreur :** `Day(s) saturday are not within the validity period (2024-01-01 to 2024-01-05).`

---

### 4. AvailabilityNoOverlapRule

**Description :** Empêche la création de disponibilités qui se chevauchent pour la même entité.

**Explication :** Une entité (ex: un médecin) ne peut pas avoir deux disponibilités qui se chevauchent dans le temps.

**Vérifications :**
- Même `schedulable_type` et `schedulable_id`
- Partage au moins un jour commun dans `days`
- Les plages horaires (`daily_start` - `daily_end`) se chevauchent
- Les périodes de validité (`validity_start` - `validity_end`) se chevauchent
- Exclut la disponibilité en cours de modification (pour les updates)

**Supporte :** CREATE, UPDATE

**Exemple :**
```php
// Disponibilité A
$recordA = AvailabilityRecord::from([
    'days' => WeekDayCollection::fromStrings(['monday']),
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('12:00:00'),
    'validity_start' => DateTimeZuluVO::from('2024-01-01T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-01-31T23:59:59Z'),
]);

// ❌ Invalide - chevauchement avec A
$recordB = AvailabilityRecord::from([
    'days' => WeekDayCollection::fromStrings(['monday']),
    'daily_start' => TimeZuluVO::from('10:00:00'),
    'daily_end' => TimeZuluVO::from('11:00:00'),
    'validity_start' => DateTimeZuluVO::from('2024-01-15T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-01-20T23:59:59Z'),
]);
```

**Erreur :** `Availability overlaps with existing availability #123 for the same schedulable entity.`

---

### 5. AvailabilityMinimumDurationRule

**Description :** Valide que la durée d'une disponibilité respecte la durée minimale configurée.

**Explication :** On ne peut pas créer une disponibilité de 5 minutes si la durée minimale est de 15 minutes.

**Supporte :** CREATE, UPDATE

**Règle :**
- `daily_end` - `daily_start` ≥ `min_durations.availability`

**Exemple :**
```php
// ✅ Valide (30 minutes)
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('09:30:00'),
]);

// ❌ Invalide (5 minutes < 15 min)
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('09:05:00'),
]);
```

**Erreur :** `Availability duration must be at least 15 minutes. Current duration: 5 minutes.`

---

### 6. AvailabilityValidDateRangeRule

**Description :** Vérifie l'intégrité des plages horaires d'une disponibilité.

**Explication :** Une plage horaire doit être logique : l'heure de fin doit être après l'heure de début (ou cross-day), et la date de fin après la date de début.

**Supporte :** CREATE, UPDATE

**Vérifications :**
- `daily_start` < `daily_end` ou cross-day (daily_start > daily_end)
- `daily_start` != `daily_end` (durée nulle interdite)
- `validity_start` < `validity_end`
- Pour CREATE : `validity_start` et `validity_end` sont obligatoires
- Pour UPDATE : `validity_start` et `validity_end` sont optionnels mais si présents, doivent être valides

**Exemples :**
```php
// ✅ Valide - plage normale
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('17:00:00'),
]);

// ✅ Valide - cross-day
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('22:00:00'),
    'daily_end' => TimeZuluVO::from('02:00:00'),
]);

// ❌ Invalide - durée nulle
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('09:00:00'),
    'daily_end' => TimeZuluVO::from('09:00:00'),
]);

// ❌ Invalide - validity_start > validity_end
$record = AvailabilityRecord::from([
    'validity_start' => DateTimeZuluVO::from('2024-12-31T00:00:00Z'),
    'validity_end' => DateTimeZuluVO::from('2024-01-01T23:59:59Z'),
]);
```

**Erreurs :**
- `Daily start time must be before daily end time.`
- `Validity start date must be before validity end date.`
- `Validity start date is required for availability creation.`
- `Validity end date is required for availability creation.`

---

### 7. NoFutureBookingsOnDeleteRule

**Description :** Empêche la suppression d'une disponibilité qui a déjà des réservations futures.

**Explication :** On ne peut pas supprimer une disponibilité si elle contient déjà des rendez-vous programmés dans le futur.

**Supporte :** DELETE uniquement

**Vérification :**
- La disponibilité ne doit pas avoir de `Schedule` avec `start_datetime` > maintenant (UTC)

**Exemple :**
```php
// Disponibilité avec rendez-vous futur
$availability = Availability::find(1);
$availability->schedules()->create([
    'start_datetime' => '2025-01-15 10:00:00',
    'end_datetime' => '2025-01-15 11:00:00',
]);

// ❌ Invalide - ne peut pas être supprimée
$service->delete($availability->id); // ValidationException
```

**Erreur :** `Cannot delete availability that has future bookings.`

---

### 8. CrossDayAvailabilityRule

**Description :** Valide les disponibilités qui traversent minuit (cross-day).

**Explication :** Certains services fonctionnent de nuit. Une disponibilité peut donc commencer un jour et se terminer le lendemain.

**Supporte :** CREATE, UPDATE

**Règles :**
- Lorsque `daily_start` > `daily_end` (cross-day)
- Les jours `days` doivent être consécutifs
- Au moins 2 jours consécutifs pour couvrir le début et la fin

**Exemple :**
```php
// ✅ Valide - cross-day avec jours consécutifs
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('22:00:00'),
    'daily_end' => TimeZuluVO::from('02:00:00'),
    'days' => WeekDayCollection::fromStrings(['monday', 'tuesday']),
]);

// ❌ Invalide - jours non consécutifs
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('22:00:00'),
    'daily_end' => TimeZuluVO::from('02:00:00'),
    'days' => WeekDayCollection::fromStrings(['monday', 'wednesday']),
]);

// ❌ Invalide - un seul jour pour cross-day
$record = AvailabilityRecord::from([
    'daily_start' => TimeZuluVO::from('22:00:00'),
    'daily_end' => TimeZuluVO::from('02:00:00'),
    'days' => WeekDayCollection::fromStrings(['monday']),
]);
```

**Erreur :** `Availability crosses midnight but days array is not consecutive. Days: monday, wednesday`

---

### 9. SchedulableExistsRule

**Description :** Vérifie que l'entité `schedulable` existe en base de données.

**Explication :** Évite les références à des entités supprimées ou inexistantes.

**Supporte :** CREATE, UPDATE

**Vérifications :**
- `schedulable_type` est une classe existante
- `schedulable_id` existe dans la table correspondante

**Exemple :**
```php
// ❌ Invalide - utilisateur inexistant
$record = AvailabilityRecord::from([
    'schedulable_type' => User::class,
    'schedulable_id' => 99999,
]);
```

**Erreur :** `Schedulable entity #99999 of type "App\Models\User" does not exist.`

---

## 🔹 Règles pour Schedule et Impediment (Partagées)

---

### 10. EntityOwnershipConsistencyRule

**Description :** Vérifie que les schedules et impediments appartiennent à la même entité que leur disponibilité parente.

**Explication :** Un rendez-vous doit appartenir au même propriétaire que la disponibilité sur laquelle il est créé.

**Supporte :** CREATE, UPDATE

**Vérification :**
- `schedulable_type` du schedule = `schedulable_type` de la disponibilité
- `schedulable_id` du schedule = `schedulable_id` de la disponibilité

**Exemple :**
```php
// Disponibilité pour User#1
$availability = Availability::create([
    'schedulable_type' => User::class,
    'schedulable_id' => 1,
]);

// ❌ Invalide - schedule pour Doctor#2
$record = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'schedulable_type' => Doctor::class, // Ne correspond pas
    'schedulable_id' => 2,
]);
```

**Erreur :** `The schedule entity (Doctor#2) does not match the parent availability entity (User#1).`

---

### 11. AvailabilityOwnershipValidationRule

**Description :** Vérifie que la disponibilité référencée existe bien et appartient à l'entité.

**Explication :** Avant de créer un rendez-vous, on vérifie que la disponibilité sélectionnée existe et qu'elle appartient bien à l'utilisateur qui la demande.

**Supporte :** CREATE uniquement (Schedule et Impediment)

**Vérifications :**
- La disponibilité avec `availability_id` existe
- La disponibilité appartient bien à l'entité (`schedulable_type` et `schedulable_id`)

**Exemple :**
```php
// ❌ Invalide - availability_id n'existe pas
$record = ScheduleRecord::from([
    'availability_id' => 99999,
]);

// ❌ Invalide - availability_id n'appartient pas à l'entité
$record = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'schedulable_type' => Doctor::class,
    'schedulable_id' => 2,
]);
```

**Erreurs :**
- `Availability #99999 does not exist.`
- `Availability #123 does not belong to this schedulable entity (Doctor#2).`

---

### 12. TimeSlotWithinAvailabilityRule

**Description :** Valide les contraintes temporelles des schedules et impediments par rapport à leur disponibilité parente.

**Explication :** Un rendez-vous doit respecter toutes les contraintes de la disponibilité : horaires, jours, période de validité.

**Supporte :** CREATE, UPDATE

**Vérifications :**
- L'événement est dans la plage horaire (`daily_start` - `daily_end`)
- L'événement est sur un jour autorisé (`days`)
- L'événement est dans la période de validité (`validity_start` - `validity_end`)

**Exemple :**
```php
// Disponibilité : Lundi, Mercredi, Vendredi, 09:00-17:00
$availability = Availability::create([
    'days' => ['monday', 'wednesday', 'friday'],
    'daily_start' => '09:00:00',
    'daily_end' => '17:00:00',
]);

// ✅ Valide - rendez-vous le lundi
$record = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'start_datetime' => '2024-01-15 10:00:00', // Lundi
    'end_datetime' => '2024-01-15 11:00:00',
]);

// ❌ Invalide - mardi non autorisé
$record = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'start_datetime' => '2024-01-16 10:00:00', // Mardi
    'end_datetime' => '2024-01-16 11:00:00',
]);
```

**Erreurs :**
- `Time slot is outside the validity period of the availability (2024-01-01 to 2024-12-31).`
- `Time slot is outside the daily bounds of the availability (09:00 to 17:00).`
- `Day "tuesday" is not allowed for this availability. Allowed days: monday, wednesday, friday`

---

### 13. NoTemporalConflictRule

**Description :** Empêche les conflits entre différents événements sur la même disponibilité.

**Explication :** On ne peut pas avoir deux rendez-vous qui se chevauchent sur la même disponibilité, ni un impediment qui chevauche un rendez-vous.

**Supporte :** CREATE, UPDATE

**Vérifications :**
- Pas de chevauchement avec d'autres `Schedule`
- Pas de chevauchement avec d'autres `Impediment`
- Pas de chevauchement entre `Schedule` et `Impediment`
- Exclut l'événement en cours de modification (pour les updates)

**Exemple :**
```php
// Disponibilité : Lundi 09:00-17:00
// Schedule A existe : 10:00-11:00

// ❌ Invalide - chevauchement avec Schedule A
$recordB = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'start_datetime' => '2024-01-15 10:30:00',
    'end_datetime' => '2024-01-15 11:30:00',
]);
```

**Erreur :** `Time slot 10:30 to 11:30 conflicts with existing schedule #456 (10:00 to 11:00).`

---

### 14. TimeSlotChronologyRule

**Description :** Valide l'intégrité chronologique des dates/heures d'un rendez-vous.

**Explication :** La date/heure de début doit être avant la date/heure de fin.

**Supporte :** CREATE, UPDATE

**Vérifications :**
- `start_datetime` < `end_datetime`
- Durée > 0 minutes

**Exemple :**
```php
// ✅ Valide
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 10:00:00',
    'end_datetime' => '2024-01-15 11:00:00',
]);

// ❌ Invalide - start > end
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 11:00:00',
    'end_datetime' => '2024-01-15 10:00:00',
]);

// ❌ Invalide - durée nulle
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 10:00:00',
    'end_datetime' => '2024-01-15 10:00:00',
]);
```

**Erreurs :**
- `Start datetime must be before end datetime.`
- `Duration must be greater than 0 minutes.`

---

### 15. BufferTimeRule

**Description :** Empêche la création de rendez-vous trop rapprochés en imposant un temps de marge (buffer) entre les réservations.

**Explication :** Nécessaire pour les cas de nettoyage (salles), préparation (consultations médicales), ou temps de déplacement.

**Supporte :** CREATE, UPDATE

**Vérification :**
- Temps minimum entre la fin d'un événement et le début du suivant
- S'applique aux schedules ET aux impediments

**Exemple :**
```php
// Disponibilité : 09:00-12:00, buffer = 15 minutes
// Schedule A : 09:00-10:00

// ✅ Valide - buffer de 15 minutes
$recordB = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'start_datetime' => '2024-01-15 10:15:00',
    'end_datetime' => '2024-01-15 11:00:00',
]);

// ❌ Invalide - buffer non respecté (0 minutes)
$recordB = ScheduleRecord::from([
    'availability_id' => $availability->id,
    'start_datetime' => '2024-01-15 10:00:00',
    'end_datetime' => '2024-01-15 11:00:00',
]);
```

**Erreur :** `Buffer time of 15 minutes not respected between previous schedule #123 (ending at 10:00) and the new slot.`

---

### 16. MaxDurationRule

**Description :** Limite la durée maximale d'un créneau.

**Explication :** Empêcher les utilisateurs de réserver des créneaux excessifs qui bloqueraient le planning.

**Supporte :** CREATE, UPDATE

**Règle :**
- `end_datetime` - `start_datetime` ≤ `max_duration` (configurable)

**Exemple :**
```php
// Configuration : max_duration = 240 minutes (4h)

// ✅ Valide - 3 heures
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 09:00:00',
    'end_datetime' => '2024-01-15 12:00:00',
]);

// ❌ Invalide - 6 heures
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 09:00:00',
    'end_datetime' => '2024-01-15 15:00:00',
]);
```

**Erreur :** `Duration (6 hours) exceeds maximum allowed duration (4 hours).`

---

### 17. MinSlotSearchDurationRule

**Description :** Valide que la durée de recherche de slots respecte le minimum configuré.

**Explication :** Empêche les recherches trop granulaires qui pourraient générer des millions de résultats et ralentir le système.

**Supporte :** CREATE, UPDATE (Schedule et Impediment)

**Règle :**
- Durée de recherche ≥ `min_durations.slot_search` (configurable)

**Exemple :**
```php
// Configuration : slot_search = 5 minutes

// ✅ Valide - 30 minutes
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 09:00:00',
    'end_datetime' => '2024-01-15 09:30:00',
]);

// ❌ Invalide - 1 minute
$record = ScheduleRecord::from([
    'start_datetime' => '2024-01-15 09:00:00',
    'end_datetime' => '2024-01-15 09:01:00',
]);
```

**Erreur :** `Slot duration (1 minutes) is too short. Minimum allowed duration for slot search is 5 minutes.`

---

## 📊 Tableau Récapitulatif

| # | Règle | Entité | Opérations |
|---|-------|--------|------------|
| 1 | AvailabilityRequiredFieldsRule | Availability | CREATE |
| 2 | AvailabilityDaysFormatRule | Availability | CREATE, UPDATE |
| 3 | DaysWithinValidityPeriodRule | Availability | CREATE, UPDATE |
| 4 | AvailabilityNoOverlapRule | Availability | CREATE, UPDATE |
| 5 | AvailabilityMinimumDurationRule | Availability | CREATE, UPDATE |
| 6 | AvailabilityValidDateRangeRule | Availability | CREATE, UPDATE |
| 7 | NoFutureBookingsOnDeleteRule | Availability | DELETE |
| 8 | CrossDayAvailabilityRule | Availability | CREATE, UPDATE |
| 9 | SchedulableExistsRule | Availability/Schedule/Impediment | CREATE, UPDATE |
| 10 | EntityOwnershipConsistencyRule | Schedule/Impediment | CREATE, UPDATE |
| 11 | AvailabilityOwnershipValidationRule | Schedule/Impediment | CREATE |
| 12 | TimeSlotWithinAvailabilityRule | Schedule/Impediment | CREATE, UPDATE |
| 13 | NoTemporalConflictRule | Schedule/Impediment | CREATE, UPDATE |
| 14 | TimeSlotChronologyRule | Schedule/Impediment | CREATE, UPDATE |
| 15 | BufferTimeRule | Schedule/Impediment | CREATE, UPDATE |
| 16 | MaxDurationRule | Schedule/Impediment | CREATE, UPDATE |
| 17 | MinSlotSearchDurationRule | Schedule/Impediment | CREATE, UPDATE |

---

## 🔧 Configuration des règles

```php
// config/chronos.php
return [
    'min_durations' => [
        'availability' => 15,      // Durée minimale pour Availability
        'schedule' => 15,          // Durée minimale pour Schedule
        'impediment' => 15,        // Durée minimale pour Impediment
        'slot_search' => 5,        // Durée minimale pour les recherches de slots
    ],
    'max_duration' => 240,         // Durée maximale en minutes (4 heures)
    'buffer_time' => 15,           // Buffer en minutes
];
```

---

## 🔗 Intégration avec le ServiceProvider

Toutes les règles sont enregistrées dans `LaravelChronosServiceProvider` :

```php
// Availability Rules
$validator->addRules(EntityType::AVAILABILITY, [
    new AvailabilityRequiredFieldsRule(),
    new AvailabilityDaysFormatRule(),
    new DaysWithinValidityPeriodRule($helper),
    new AvailabilityNoOverlapRule($helper),
    new AvailabilityMinimumDurationRule($helper, $config),
    new AvailabilityValidDateRangeRule(),
    new NoFutureBookingsOnDeleteRule(),
    new CrossDayAvailabilityRule($helper),
    new SchedulableExistsRule(),
]);

// Schedule Rules
$validator->addRules(EntityType::SCHEDULE, [
    new EntityOwnershipConsistencyRule(),
    new AvailabilityOwnershipValidationRule(),
    new TimeSlotWithinAvailabilityRule($helper),
    new NoTemporalConflictRule(),
    new TimeSlotChronologyRule(),
    new BufferTimeRule($helper, $config),
    new MaxDurationRule($helper, $config),
    new MinSlotSearchDurationRule($config),
]);

// Impediment Rules (identique à Schedule)
$validator->addRules(EntityType::IMPEDIMENT, [
    // ... mêmes règles
]);
```

---

## Voir aussi

- `ValidationRule` - Interface des règles
- `ValidatorInterface` - Interface du validateur
- `ValidationHelperService` - Service d'aide
- `ValidationErrorRecord` - Record d'erreur
- `ValidationResult` - Résultat de validation
- `ValidationContext` - Contexte de validation
- `EntityType` - Énumération des entités
- `OperationType` - Énumération des opérations