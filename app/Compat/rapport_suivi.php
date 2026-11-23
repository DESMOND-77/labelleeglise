<?php

/**
 * Compatibilité - accès aux services du module « Suivi des Rapports du Jour ».
 * Même convention que le reste du dossier : un accesseur par service,
 * instance mise en cache par requête via _repo().
 */

declare(strict_types=1);

use App\Services\RapportOccurrenceService;
use App\Repositories\RapportOccurrenceRepository;
use App\Services\RapportAnalyticsService;
use App\Services\RapportReminderService;
use App\Services\RapportScoringService;
use App\Services\RapportScopeService;
use App\Services\RapportSettingsService;

function rapport_occurrence_service(): RapportOccurrenceService
{
    return _repo(RapportOccurrenceService::class);
}

function rapport_analytics_service(): RapportAnalyticsService
{
    return _repo(RapportAnalyticsService::class);
}

function rapport_reminder_service(): RapportReminderService
{
    return _repo(RapportReminderService::class);
}

function rapport_scoring_service(): RapportScoringService
{
    return _repo(RapportScoringService::class);
}

function rapport_scope_service(): RapportScopeService
{
    return _repo(RapportScopeService::class);
}

function rapport_settings_service(): RapportSettingsService
{
    return _repo(RapportSettingsService::class);
}

function rapport_audit_repository(): \App\Repositories\RapportAuditRepository
{
    return _repo(\App\Repositories\RapportAuditRepository::class);
}

function rapport_occurrence_repository(): RapportOccurrenceRepository
{
    return _repo(RapportOccurrenceRepository::class);
}

/** Libellés FR des statuts d'occurrence (checklist, export, vues). */
function rapport_status_labels(): array
{
    return [
        'pending' => 'À venir',
        'due' => 'À remplir',
        'overdue' => 'En retard',
        'filled_on_time' => 'Rempli à temps',
        'filled_late' => 'Rempli en retard',
        'cancelled' => 'Annulé',
    ];
}

/** Libellés FR des types de rappel. */
function rapport_reminder_type_labels(): array
{
    return [
        'preventif' => 'Veille (préventif)',
        'echeance' => 'Jour J (échéance)',
        'relance' => 'Lendemain (relance)',
        'manuel' => 'Relance manuelle',
    ];
}

/**
 * Responsables ayant au moins une occurrence sur la période (liste de filtre).
 * @param array<string,mixed> $filters
 * @return array<int,array{id:int,nom:string}>
 */
function rapport_occurrence_repository_for_filters(array $filters, ?array $pairs): array
{
    return rapport_occurrence_repository()->responsablesForPeriod(
        (string) ($filters['from'] ?? ''),
        (string) ($filters['to'] ?? ''),
        $pairs
    );
}
