<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // 1. Eigene Profile anlegen (OHNE ~)
        $this->CreateProfiles();

        // 2. Statusvariable
        $this->RegisterVariableInteger(
            'VentilationStatus',
            'Status Lüftung',
            'CRVStatus',
            10
        );

        // 3. Stellwert in Prozent
        $this->RegisterVariableInteger(
            'VentilationPercent',
            'Lüftungsstellwert (%)',
            '~Intensity.100',
            20
        );

        // 4. Timer (vorbereitet)
        $this->RegisterTimer(
            'ControlTimer',
            0,
            'CRVHC_Control($_IPS[\'TARGET\']);'
        );
    }

    /**
     * Eigene Profile anlegen (keine Systemprofile!)
     */
    private function CreateProfiles(): void
    {
        if (!IPS_VariableProfileExists('CRVStatus')) {

            IPS_CreateVariableProfile('CRVStatus', VARIABLETYPE_INTEGER);

            IPS_SetVariableProfileAssociation('CRVStatus', 0, 'Aus', '', 0x808080);
            IPS_SetVariableProfileAssociation('CRVStatus', 1, 'Betrieb', '', 0x00FF00);
            IPS_SetVariableProfileAssociation('CRVStatus', 2, 'Feuchtesprung', '', 0xFFA500);
            IPS_SetVariableProfileAssociation('CRVStatus', 3, 'Nachtabschaltung', '', 0x0000FF);
            IPS_SetVariableProfileAssociation('CRVStatus', 4, 'Fehler', '', 0xFF0000);
        }
    }

    /**
     * Zentrale Regel-Funktion (noch ohne Logik)
     */
    public function Control(): void
    {
        // Aktuell: Modul im Ruhezustand
        $this->SetValue('VentilationStatus', 0);
    }
}
