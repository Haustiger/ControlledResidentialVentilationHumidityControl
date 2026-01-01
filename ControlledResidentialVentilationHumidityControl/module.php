<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    /* ==========================================================
     * CREATE
     * ========================================================== */
    public function Create()
    {
        parent::Create();

        /* --------------------------
         * Grundeinstellungen
         * -------------------------- */
        $this->RegisterPropertyInteger('CycleMinutes', 10);

        /* --------------------------
         * Innensensoren (max. 10)
         * -------------------------- */
        $this->RegisterPropertyInteger('IndoorSensorCount', 3);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity_$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature_$i", 0);
        }

        /* --------------------------
         * Außensensoren
         * -------------------------- */
        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);
        $this->RegisterPropertyFloat('OutsideDeltaThreshold', 1.0);

        /* --------------------------
         * Feuchtesprung
         * -------------------------- */
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 2.0);
        $this->RegisterPropertyInteger('HumidityJumpMinutes', 5);
        $this->RegisterPropertyInteger('HumidityJumpMaxRuntime', 60);

        /* --------------------------
         * Nachtabschaltung
         * -------------------------- */
        $this->RegisterPropertyInteger('NightOffSwitch', 0);

        /* --------------------------
         * Lüftungssteuerung
         * -------------------------- */
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);
        $this->RegisterPropertyInteger('VentilationFeedbackID', 0);

        /* --------------------------
         * Profile & Statusvariablen
         * -------------------------- */
        $this->RegisterProfiles();

        $this->RegisterVariableInteger(
            'Status',
            'Lüftungsstatus',
            'CRVStatus',
            10
        );

        $this->RegisterVariableFloat(
            'AbsoluteHumidityIndoor',
            'Absolute Feuchte Innen (g/m³)',
            '~Humidity',
            20
        );

        $this->RegisterVariableFloat(
            'AbsoluteHumidityOutdoor',
            'Absolute Feuchte Außen (g/m³)',
            '~Humidity',
            30
        );

        $this->RegisterVariableBoolean(
            'NightOverrideActive',
            'Nacht-Override aktiv',
            '~Switch',
            40
        );

        /* --------------------------
         * Timer
         * -------------------------- */
        $this->RegisterTimer(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60 * 1000,
            'CRV_Run($_IPS[\'TARGET\']);'
        );
    }

    /* ==========================================================
     * APPLY CHANGES
     * ========================================================== */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer-Intervall aktualisieren
        $this->SetTimerInterval(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60 * 1000
        );
    }

    /* ==========================================================
     * PROFILE
     * ========================================================== */
    private function RegisterProfiles(): void
    {
        if (!IPS_VariableProfileExists('CRVStatus')) {
            IPS_CreateVariableProfile('CRVStatus', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('CRVStatus', 0, 'Aus', 'Power', 0x808080);
            IPS_SetVariableProfileAssociation('CRVStatus', 1, 'Normalbetrieb', 'Ventilation', 0x00FF00);
            IPS_SetVariableProfileAssociation('CRVStatus', 2, 'Feuchtesprung', 'Drops', 0xFFA500);
            IPS_SetVariableProfileAssociation('CRVStatus', 3, 'Nachtabschaltung', 'Moon', 0x0000FF);
            IPS_SetVariableProfileAssociation('CRVStatus', 4, 'Fehler', 'Warning', 0xFF0000);
        }
    }

    /* ==========================================================
     * MANUELLER AUFRUF (Form Button / Timer)
     * ========================================================== */
    public function CRV_Run()
    {
        $this->SendDebug('CRV', 'Regelung gestartet', 0);
        $this->Run();
    }

    /* ==========================================================
     * HAUPTLOGIK
     * ========================================================== */
    private function Run()
    {
        /* --------------------------
         * Absolute Feuchte innen
         * -------------------------- */
        $sensorCount = $this->ReadPropertyInteger('IndoorSensorCount');
        $absIndoorValues = [];

        for ($i = 1; $i <= $sensorCount; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity_$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature_$i");

            if ($hID > 0 && $tID > 0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $absIndoorValues[] = $this->CalculateAbsoluteHumidity(
                    floatval(GetValue($tID)),
                    floatval(GetValue($hID))
                );
            }
        }

        if (count($absIndoorValues) === 0) {
            $this->SetValue('Status', 4);
            return;
        }

        $absIndoor = array_sum($absIndoorValues) / count($absIndoorValues);
        $this->SetValue('AbsoluteHumidityIndoor', round($absIndoor, 2));

        /* --------------------------
         * Absolute Feuchte außen
         * -------------------------- */
        $absOutdoor = $absIndoor;

        $outH = $this->ReadPropertyInteger('OutdoorHumidity');
        $outT = $this->ReadPropertyInteger('OutdoorTemperature');

        if ($outH > 0 && $outT > 0 && IPS_VariableExists($outH) && IPS_VariableExists($outT)) {
            $absOutdoor = $this->CalculateAbsoluteHumidity(
                floatval(GetValue($outT)),
                floatval(GetValue($outH))
            );
        }

        $this->SetValue('AbsoluteHumidityOutdoor', round($absOutdoor, 2));

        /* --------------------------
         * Nachtabschaltung
         * -------------------------- */
        $nightActive = false;
        $nightSwitch = $this->ReadPropertyInteger('NightOffSwitch');
        if ($nightSwitch > 0 && IPS_VariableExists($nightSwitch)) {
            $nightActive = GetValueBoolean($nightSwitch);
        }

        /* --------------------------
         * Feuchtesprung (Override!)
         * -------------------------- */
        $jumpThreshold = $this->ReadPropertyFloat('HumidityJumpThreshold');
        $jumpDetected = ($absIndoor - $absOutdoor) >= $jumpThreshold;

        if ($jumpDetected) {
            $this->SetValue('Status', 2);
            $this->SetValue('NightOverrideActive', true);
            $this->SetVentilation(96);
            return;
        }

        $this->SetValue('NightOverrideActive', false);

        /* --------------------------
         * Nachtabschaltung greift
         * -------------------------- */
        if ($nightActive) {
            $this->SetValue('Status', 3);
            $this->SetVentilation(0);
            return;
        }

        /* --------------------------
         * Normalbetrieb (Sommer/Winter)
         * -------------------------- */
        $delta = $absIndoor - $absOutdoor;
        if ($delta >= $this->ReadPropertyFloat('OutsideDeltaThreshold')) {
            $this->SetValue('Status', 1);
            $this->SetVentilation($this->CalculateStagePercent($delta));
        } else {
            $this->SetValue('Status', 0);
            $this->SetVentilation(0);
        }
    }

    /* ==========================================================
     * HILFSFUNKTIONEN
     * ========================================================== */
    private function CalculateAbsoluteHumidity(float $temp, float $rh): float
    {
        // Magnus-Formel
        $ps = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $p  = ($rh / 100.0) * $ps;
        return (216.7 * $p) / ($temp + 273.15);
    }

    private function CalculateStagePercent(float $delta): int
    {
        // 8 Stufen: 12–96 %
        $stages = [12, 24, 36, 48, 60, 72, 84, 96];
        $index = min(7, max(0, intval(round($delta))));
        return $stages[$index];
    }

    private function SetVentilation(int $percent): void
    {
        $id = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($id > 0 && IPS_VariableExists($id)) {
            SetValue($id, $percent);
        }
    }
}
