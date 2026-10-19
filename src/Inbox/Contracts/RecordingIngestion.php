<?php

namespace Platform\Core\Inbox\Contracts;

/**
 * Aufnahme-Ingestion-Naht: ein Aufnahme-Produzent (whisper heute, künftig andere)
 * übergibt eine fertig transkribierte Aufnahme (Transkript + Segmente + Speaker +
 * Audio-Ref) in den Eingang. Der Produzent kennt weder das Inbox-Schema noch das
 * Materialisierungs-Modul — er nennt nur ein neutrales Payload und bekommt die
 * inbox_item-ID zurück.
 *
 * Payload (wie bisher an InboxAudioIngestionService::ingest):
 *  - team_id, user_id (int, erforderlich)
 *  - source_type (string), source_id (int)      Ursprung (z.B. 'whisper_recording')
 *  - title (string), body (string)              Titel + Transkript-Volltext
 *  - language (string, optional)
 *  - audio_duration_seconds (int, optional)
 *  - audio_recorded_at (mixed, optional)        Aufnahmezeitpunkt (für Korrelation)
 *  - speakers (array), segments (array)         [{label,display_name,entity_id}], [{start,end,speaker_label,text}]
 *  - audio_file (array, optional)               {context_file_id}
 *
 * Ohne Implementierung (NullRecordingIngestion) ein No-op → null.
 */
interface RecordingIngestion
{
    /**
     * @param  array<string,mixed>  $payload
     * @return int|null  ID des erzeugten inbox_item; null bei Fehler/fehlender Impl.
     */
    public function ingest(array $payload): ?int;
}
