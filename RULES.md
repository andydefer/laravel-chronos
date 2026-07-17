# 📋 Règles de Validation - Laravel Chronos

> **Note :** Le système fonctionne exclusivement en UTC. Toutes les dates et heures sont stockées et validées en UTC via `DateTimeZuluVO` et `TimeZuluVO`.

---

## 🔹 Règles pour Availability (Disponibilités)

---

### 1. AvailabilityRequiredFieldsRule

**Description :** Vérifie que tous les champs obligatoires sont présents lors de la création ou mise à jour d'une disponibilité.

**Explication :** Une disponibilité doit avoir un nom, une plage horaire quotidienne et être associée à une entité propriétaire.

**Champs requis :**

| Champ | Description |
|-------|-------------|
| `name` | Nom de la disponibilité (ex: "Consultations matin") |
| `daily_start` | Heure de début dans la journée (ex: "09:00:00") |
| `daily_end` | Heure de fin dans la journée (ex: "17:00:00") |
| `schedulable_type` | Type de l'entité propriétaire (ex: "App\Models\User") |
| `schedulable_id` | ID de l'entité propriétaire (ex: 1) |

**Erreur :** `The field "name" is required for availability.`

---

### 2. AvailabilityDaysFormatRule

**Description :** Valide que les jours de la semaine sont correctement formatés.

**Explication :** Les jours doivent être au format correct, en minuscules, et correspondre aux valeurs de l'enum `WeekDay`.

**Règles :**
- Le champ `days` doit être un tableau (JSON)
- Chaque jour doit être une valeur valide de l'enum `WeekDay`
- Le tableau ne doit pas être vide
- Les jours doivent être en minuscules
- Pas de doublons dans le tableau

**Exemples :**

```php
// ✅ Valide
['monday', 'wednesday', 'friday']

// ❌ Invalide (majuscules)
['Monday', 'Wednesday']

// ❌ Invalide (vide)
[]

// ❌ Invalide (doublon)
['monday', 'monday']
```

**Erreur :** `Invalid day(s) provided. Allowed days are: monday, tuesday, wednesday, thursday, friday, saturday, sunday.`

---

### 3. DaysWithinValidityPeriodRule

**Description :** Vérifie que les jours sélectionnés existent réellement dans la période de validité définie.

**Explication :** Si une disponibilité est valide du 1er janvier au 7 janvier (7 jours), on ne peut pas y associer un jour qui tombe en dehors de cette période.

**Vérification :**
- Tous les jours spécifiés dans `days` doivent être compris entre `validity_start` et `validity_end`
- Si `validity_start` et `validity_end` ne sont pas définis → validité perpétuelle (tous les jours autorisés)

**Exemple :**

| Période | Jours autorisés |
|---------|-----------------|
| 01/01/2024 - 07/01/2024 | lundi, mardi, mercredi, jeudi, vendredi, samedi, dimanche |
| 01/01/2024 - 05/01/2024 | lundi, mardi, mercredi, jeudi, vendredi |
| 03/01/2024 - 05/01/2024 | mercredi, jeudi, vendredi |

**Erreur :** `Day "saturday" is not within the validity period (2024-01-01 to 2024-01-05).`

---

### 4. AvailabilityNoOverlapRule

**Description :** Empêche la création de disponibilités qui se chevauchent pour la même entité.

**Explication :** Une entité (ex: un médecin) ne peut pas avoir deux disponibilités qui se chevauchent dans le temps. Cela éviterait le double-booking.

**Vérifications :**
- Même `schedulable_type` et `schedulable_id`
- Partage au moins un jour commun dans `days`
- Les plages horaires (`daily_start` - `daily_end`) se chevauchent
- Les périodes de validité (`validity_start` - `validity_end`) se chevauchent
- Exclure la disponibilité en cours de modification (pour les updates)

**Exemple :**

```
Disponibilité A : Lundi 09:00-12:00, valide du 01/01 au 31/01
Disponibilité B : Lundi 10:00-11:00, valide du 15/01 au 20/01
→ ❌ CHEVAUCHEMENT DÉTECTÉ
```

**Erreur :** `Availability overlaps with existing availability #123 for the same schedulable entity.`

---

### 5. AvailabilityMinimumDurationRule

**Description :** Valide que la durée d'une disponibilité respecte la durée minimale configurée.

**Explication :** On ne peut pas créer une disponibilité de 5 minutes si la durée minimale est de 15 minutes.

**Règle :**
- `daily_end` - `daily_start` ≥ `min_duration` (15 minutes par défaut)

**Exemple :**

```php
// ✅ Valide (30 minutes)
daily_start = "09:00:00"
daily_end = "09:30:00"

// ❌ Invalide (5 minutes < 15 min)
daily_start = "09:00:00"
daily_end = "09:05:00"
```

**Configurable :** Oui (`min_duration` en minutes)

**Erreur :** `Availability duration must be at least 15 minutes.`

---

### 6. AvailabilityValidDateRangeRule

**Description :** Vérifie l'intégrité des plages horaires d'une disponibilité.

**Explication :** Une plage horaire doit être logique : l'heure de fin doit être après l'heure de début, et la date de fin après la date de début.

**Vérifications :**
- `daily_start` < `daily_end` (l'heure de fin après l'heure de début)
- `validity_start` < `validity_end` (la date de fin après la date de début)
- Si `validity_start` n'est pas défini → disponibilité invalide car il faut déterminer le début
- Si `validity_end` n'est pas défini → disponibilité invalide car il faut déterminer la fin
- `daily_start` et `daily_end` au format `H:i:s`

**Erreurs :**
- `Daily start time must be before daily end time.`
- `Validity start date must be before validity end date.`

---

### 7. NoFutureBookingsOnDeleteRule

**Description :** Empêche la suppression d'une disponibilité qui a déjà des réservations futures.

**Explication :** On ne peut pas supprimer une disponibilité si elle contient déjà des rendez-vous programmés dans le futur.

**Vérification :**
- La disponibilité ne doit pas avoir de `Schedule` avec `start_datetime` > maintenant (UTC)

**Option :** Forcer la suppression avec un paramètre `force` en cas de besoin exceptionnel.

**Erreur :** `Cannot delete availability that has future bookings.`

---

### 8. CrossDayAvailabilityRule

**Description :** Permet et valide les disponibilités qui traversent minuit.

**Explication :** Certains services fonctionnent de nuit (hôpitaux, services d'urgence, support technique 24h/24). Une disponibilité peut donc commencer un jour et se terminer le lendemain.

**Règles :**
- La disponibilité peut s'étendre sur plusieurs jours (ex: 22:00-02:00)
- La durée totale est calculée correctement
- Les jours `days` doivent inclure le jour de début ET le jour de fin

**Exemple :**

```
Availability : 22:00-02:00 (disponibilité de nuit)
days: ['monday', 'tuesday']  // Commence lundi, finit mardi
→ ✅ Valide

Availability : 22:00-02:00
days: ['monday']  // Ne finit pas mardi
→ ❌ Invalide - manque le jour de fin
```

**Erreur :** `Availability crosses midnight but missing end day in days array.`

---

### 9. SchedulableExistsRule

**Description :** Vérifie que l'entité `schedulable` existe en base de données.

**Explication :** Éviter les références à des entités supprimées ou inexistantes qui causeraient des erreurs ou des incohérences dans le système.

**Vérifications :**
- `schedulable_type` est une classe existante
- `schedulable_id` existe dans la table correspondante
- L'entité implémente l'interface `Schedulable`

**Exemple :**

```
schedulable_type = App\Models\User
schedulable_id = 999 (utilisateur inexistant)
→ ❌ Invalide - utilisateur non trouvé
```

**Erreur :** `Schedulable entity #999 of type App\Models\User does not exist.`

---

## 🔹 Règles pour Schedule et Impediment

---

### 10. EntityOwnershipConsistencyRule

**Description :** Vérifie que les schedules et impediments appartiennent à la même entité que leur disponibilité parente.

**Explication :** Un rendez-vous (schedule) ou un impediment doit appartenir au même propriétaire que la disponibilité sur laquelle il est créé.

**Vérification :**
- `schedulable_type` du schedule = `schedulable_type` de la disponibilité
- `schedulable_id` du schedule = `schedulable_id` de la disponibilité

**Erreur :** `The schedule entity does not match the parent availability entity. Expected: App\Models\User#1, got: App\Models\Doctor#2.`

---

### 11. AvailabilityOwnershipValidationRule

**Description :** Vérifie que la disponibilité référencée existe bien et appartient à l'entité.

**Explication :** Avant de créer un rendez-vous, on vérifie que la disponibilité sélectionnée existe et qu'elle appartient bien à l'utilisateur qui la demande.

**Vérifications :**
- La disponibilité avec `availability_id` existe
- La disponibilité appartient bien à l'entité (`schedulable_type` et `schedulable_id`)

**Erreur :** `Availability #123 does not exist or does not belong to this schedulable entity.`

---

### 12. TimeSlotWithinAvailabilityRule

**Description :** Valide les contraintes temporelles des schedules et impediments par rapport à leur disponibilité parente.

**Explication :** Un rendez-vous doit respecter toutes les contraintes de la disponibilité : horaires, jours, période de validité.

**Vérifications :**
- `start_datetime` < `end_datetime`
- L'événement ne dépasse pas 24 heures
- L'événement est dans la plage horaire (`daily_start` - `daily_end`)
- L'événement est sur un jour autorisé (`days`)
- L'événement est dans la période de validité (`validity_start` - `validity_end`)
- Tous les jours couverts par l'événement sont dans `days`

**Exemple :**

```
Disponibilité : Lundi, Mercredi, Vendredi, 09:00-17:00

✅ Rendez-vous le lundi 10:00-11:00 (Valide)
✅ Rendez-vous le mercredi 10:00-11:00 (Valide)
❌ Rendez-vous le mardi 10:00-11:00 (Invalide - mardi non autorisé)
```

**Erreur :** `Time slot from 14:00 to 15:00 on Tuesday is not within the availability (Monday, Wednesday, Friday, 09:00-17:00).`

---

### 13. NoTemporalConflictRule

**Description :** Empêche les conflits entre différents événements sur la même disponibilité.

**Explication :** On ne peut pas avoir deux rendez-vous qui se chevauchent sur la même disponibilité, ni un impediment qui chevauche un rendez-vous.

**Vérifications :**
- Pas de chevauchement avec d'autres `Schedule`
- Pas de chevauchement avec d'autres `Impediment`
- Pas de chevauchement entre `Schedule` et `Impediment`
- Exclut l'événement en cours de modification (pour les updates)

**Exemple :**

```
Disponibilité : Lundi 09:00-17:00
Schedule A : 10:00-11:00 (✅ OK)
Schedule B : 10:30-11:30 (❌ CHEVAUCHEMENT)
Impediment : 10:15-10:45 (❌ CHEVAUCHEMENT)
```

**Erreur :** `Time slot 10:30-11:30 conflicts with existing schedule #456 (10:00-11:00).`

---

### 14. TimeSlotChronologyRule

**Description :** Valide l'intégrité chronologique des dates/heures d'un rendez-vous.

**Explication :** La date/heure de début doit être avant la date/heure de fin. Pour les mises à jour partielles, on combine les nouvelles valeurs avec les anciennes pour validation.

**Vérifications :**
- `start_datetime` < `end_datetime`
- Les formats de date sont valides (`Y-m-d H:i:s`)
- Pour les mises à jour partielles, combine les nouvelles valeurs avec les anciennes
- Durée > 0 minutes

**Erreur :** `Start datetime must be before end datetime.`

---

### 15. BufferTimeRule

**Description :** Empêche la création de rendez-vous trop rapprochés en imposant un temps de marge (buffer) entre les réservations.

**Explication :** Nécessaire pour les cas de nettoyage (salles), préparation (consultations médicales), ou temps de déplacement (visites à domicile). Le buffer s'applique entre la fin d'un événement et le début du suivant.

**Vérification :**
- Temps minimum entre la fin d'un événement et le début du suivant
- S'applique aux schedules ET aux impediments

**Exemple :**

```
Disponibilité : 09:00-12:00, buffer = 15 minutes
Schedule A : 09:00-10:00 (✅ OK)
Schedule B : 10:15-11:00 (✅ OK - 15 min buffer)
Schedule C : 10:00-11:00 (❌ Buffer non respecté - 0 minutes)
```

**Configurable :** Oui (`buffer_time` en minutes, 15 par défaut)

**Erreur :** `Buffer time of 15 minutes not respected between schedules #456 and #789.`

---

### 16. MaxDurationRule

**Description :** Limite la durée maximale d'un créneau.

**Explication :** Empêcher les utilisateurs de réserver des créneaux excessifs qui bloqueraient le planning (ex: réunion de 8h).

**Règle :**
- `end_datetime` - `start_datetime` ≤ `max_duration`

**Exemple :**

```
Max duration = 4 heures
Schedule A : 09:00-12:00 (✅ OK - 3h)
Schedule B : 09:00-15:00 (❌ Invalide - 6h > 4h)
```

**Configurable :** Oui (`max_duration` en minutes, 240 par défaut)

**Erreur :** `Schedule duration (6 hours) exceeds maximum allowed duration (4 hours).`

---

## 📊 Tableau Récapitulatif Final

| # | Règle | Entité | Priorité |
|---|-------|--------|----------|
| 1 | AvailabilityRequiredFieldsRule | Availability | Haute |
| 2 | AvailabilityDaysFormatRule | Availability | Haute |
| 3 | DaysWithinValidityPeriodRule | Availability | Haute |
| 4 | AvailabilityNoOverlapRule | Availability | Haute |
| 5 | AvailabilityMinimumDurationRule | Availability | Haute |
| 6 | AvailabilityValidDateRangeRule | Availability | Haute |
| 7 | NoFutureBookingsOnDeleteRule | Availability | Haute |
| 8 | CrossDayAvailabilityRule | Availability | Moyenne |
| 9 | SchedulableExistsRule | Availability/Schedule/Impediment | Haute |
| 10 | EntityOwnershipConsistencyRule | Schedule/Impediment | Haute |
| 11 | AvailabilityOwnershipValidationRule | Schedule/Impediment | Haute |
| 12 | TimeSlotWithinAvailabilityRule | Schedule/Impediment | Haute |
| 13 | NoTemporalConflictRule | Schedule/Impediment | Haute |
| 14 | TimeSlotChronologyRule | Schedule/Impediment | Haute |
| 15 | BufferTimeRule | Schedule/Impediment | Haute |
| 16 | MaxDurationRule | Schedule/Impediment | Haute |

---

## 🔧 Configuration des règles

### Configurations disponibles

| Configuration | Défaut | Description |
|---------------|--------|-------------|
| `min_duration` | 15 minutes | Durée minimale d'un créneau |
| `max_duration` | 240 minutes (4h) | Durée maximale d'un créneau |
| `buffer_time` | 0 minutes | Temps de marge entre les réservations |

### Exemple de configuration

```php
// config/chronos.php
return [
    'rules' => [
        'min_duration' => 15,          // minutes
        'max_duration' => 240,         // minutes
        'buffer_time' => 15,           // minutes
    ],
];
```

---

**Total : 16 règles de validation essentielles**