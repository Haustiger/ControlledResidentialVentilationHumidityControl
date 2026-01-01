<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // =========================
        // Grundeinstellungen
        // =========================
        $this->RegisterPropertyInteger('CycleMinutes', 10);

        // Innen-Sensoren (bis 10)
        $this->RegisterPropertyInteger('IndoorSensorCount', 3);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity_$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature_$i", 0);
        }

        // Außen-Sensor
        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);

        // Absolute-Feuchte-Differenz
        $this->RegisterPropertyFloat('OutsideDeltaThreshold', 1.0);

        // Feuchtesprung
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 2.0);
        $this->RegisterPropertyInteger('HumidityJumpMinutes', 5);
        $this->RegisterPropertyInteger('HumidityJumpMaxRuntime', 60);

        // Nachtabschaltung
        $this->RegisterPropertyInteger('NightOffSwitch', 0);
        $this->RegisterPropertyInteger('NightOffStart', 0);
        $this->RegisterPropertyInteger('NightOffEnd', 0);

        // Stellwert
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);
        $this->RegisterPropertyInteger('VentilationFeedbackID', 0);

        // =========================
        // Status-Variablen
        // =========================
        $this->RegisterProfiles();

        $this->RegisterVariableInteger(
            'Status',
            'Lüftungsstatus',
            'CRVStatus',
            10
        );

        $this->RegisterVariableFloat(
            'AbsoluteHumidityIndoor',
            'Absolute Feuchte Innen (Ø)',
            '~Humidity',
            20
        );

        $this->RegisterVariableFloat(
            'AbsoluteHumidityOutdoor',
            'Absolute Feuchte Außen',
            '~Humidity',
            30
        );

        $this->RegisterVariableBoolean(
            'NightOverrideActive',
            'Nacht-Override aktiv',
            '~Switch',
            40
        );

        // =========================
        // Timer
        // =========================
        $this->RegisterTimer(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60 * 1000,
            'CRV_Run($_IPS[\'TARGET\']);'
        );
    }

    // ======================================================
    // Profile
    // ======================================================
    private function RegisterProfiles(): void
    {
        if (!IPS_VariableProfileExists('CRVStatus')) {
            IPS_CreateVariableProfile('CRVStatus', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('CRVStatus', 0, 'Aus', 'Power', 0x808080);
            IPS_SetVariableProfileAssociation('CRVStatus', 1, 'Normal', 'Ventilation', 0x00FF00);
            IPS_SetVariableProfileAssociation('CRVStatus', 2, 'Feuchtesprung', 'Drops', 0xFFA500);
            IPS_SetVariableProfileAssociation('CRVStatus', 3, 'Nachtabschaltung', 'Moon', 0x0000FF);
            IPS_SetVariableProfileAssociation('CRVStatus', 4, 'Fehler', 'Warning', 0xFF0000);
        }
    }

    // ======================================================
    // Hauptregelung
    // ======================================================
    public function Run()
    {
        // -------------------------
        // Absolute Feuchte innen
        // -------------------------
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $absValues = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity_$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature_$i");

            if ($hID > 0 && $tID > 0 && @IPS_VariableExists($hID) && @IPS_VariableExists($tID)) {
                $absValues[] = $this->CalculateAbsoluteHumidity(
                    floatval(GetValue($tID)),
                    floatval(GetValue($hID))
                );
            }
        }

        if (count($absValues) === 0) {
            $this->SetValue('Status', 4);
            return;
        }

        $absIndoor = array_sum($absValues) / count($absValues);
        $this->SetValue('AbsoluteHumidityIndoor', round($absIndoor, 2));

        // -------------------------
        // Absolute Feuchte außen
        // -------------------------
        $outH = $this->ReadPropertyInteger('OutdoorHumidity');
        $outT = $this->ReadPropertyInteger('OutdoorTemperature');

        if ($outH > 0 && $outT > 0 && IPS_VariableExists($outH) && IPS_VariableExists($outT)) {
            $absOutdoor = $this->CalculateAbsoluteHumidity(
                floatval(GetValue($outT)),
                floatval(GetValue($outH))
            );
        } else {
            $absOutdoor = $absIndoor;
        }

        $this->SetValue('AbsoluteHumidityOutdoor', round($absOutdoor, 2));

        // -------------------------
        // Nachtabschaltung
        // -------------------------
        $nightActive = false;
        $nightSwitch = $this->ReadPropertyInteger('NightOffSwitch');

        if ($nightSwitch > 0 && IPS_VariableExists($nightSwitch)) {
            $nightActive = GetValueBoolean($nightSwitch);
        }

        // -------------------------
        // Feuchtesprung
        // -------------------------
        $jumpThreshold = $this->ReadPropertyFloat('HumidityJumpThreshold');
        $jump = ($absIndoor - $absOutdoor) >= $jumpThreshold;

        // -------------------------
        // Entscheidungslogik
        // -------------------------
        if ($jump) {
            $this->SetValue('Status', 2);
            $this->SetValue('NightOverrideActive', true);
            $this->SetVentilation(96);
            return;
        }

        $this->SetValue('NightOverrideActive', false);

        if ($nightActive) {
            $this->SetValue('Status', 3);
            $this->SetVentilation(0);
            return;
        }

        // -------------------------
        // Normalbetrieb
        // -------------------------
        $delta = $absIndoor - $absOutdoor;
        if ($delta >= $this->ReadPropertyFloat('OutsideDeltaThreshold')) {
            $this->SetValue('Status', 1);
            $this->SetVentilation($this->CalculateStagePercent($delta));
        } else {
            $this->SetValue('Status', 0);
            $this->SetVentilation(0);
        }
    }

    // ======================================================
    // Hilfsfunktionen
    // ======================================================
    private function CalculateAbsoluteHumidity(float $temp, float $rh): float
    {
        $ps = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $p  = ($rh / 100.0) * $ps;
        return (216.7 * $p) / ($temp + 273.15);
    }

    private function CalculateStagePercent(float $delta): int
    {
        $steps = [12, 24, 36, 48, 60, 72, 84, 96];
        $index = min(7, max(0, intval($delta)));
        return $steps[$index];
    }

    private function SetVentilation(int $percent): void
    {
        $id = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($id > 0 && IPS_VariableExists($id)) {
            SetValue($id, $percent);
        }
    }
}
