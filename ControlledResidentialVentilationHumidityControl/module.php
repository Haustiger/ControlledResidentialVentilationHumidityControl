<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // ===== Profile IMMER zuerst =====
        $this->CreateProfiles();

        // ===== Statusvariablen =====
        $this->RegisterVariableInteger(
            'VentilationStatus',
            'Status Lüftung',
            '~CRVStatus',
            10
        );

        $this->RegisterVariableInteger(
            'VentilationPercent',
            'Lüftungsstellwert (%)',
            '~Intensity.100',
            20
        );

        // ===== Timer (noch ohne Logik) =====
        $this->RegisterTimer(
            'ControlTimer',
            0,
            'CRVHC_Control($_IPS[\'TARGET\']);'
        );
    }

    /**
     * Profile zentral und statisch anlegen
     */
    private function CreateProfiles(): void
    {
        // === Statusprofil ===
        if (!IPS_VariableProfileExists('~CRVStatus')) {

            IPS_CreateVariableProfile('~CRVStatus', VARIABLETYPE_INTEGER);

            IPS_SetVariableProfileAssociation('~CRVStatus', 0, 'Aus', '', 0x808080);
            IPS_SetVariableProfileAssociation('~CRVStatus', 1, 'Betrieb', '', 0x00FF00);
            IPS_SetVariableProfileAssociation('~CRVStatus', 2, 'Feuchtesprung', '', 0xFFA500);
            IPS_SetVariableProfileAssociation('~CRVStatus', 3, 'Nachtabschaltung', '', 0x0000FF);
            IPS_SetVariableProfileAssociation('~CRVStatus', 4, 'Fehler', '', 0xFF0000);
        }
    }

    /**
     * Zentrale Regel-Funktion (Platzhalter)
     */
    public function Control(): void
    {
        // Noch keine Regelung aktiv
        // Status bewusst auf "Aus"
        $this->SetValue('VentilationStatus', 0);
    }
}
