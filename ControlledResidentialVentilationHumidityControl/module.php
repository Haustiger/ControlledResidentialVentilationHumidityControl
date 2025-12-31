<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    /* ============================================================
     * CREATE
     * ============================================================ */
    public function Create()
    {
        parent::Create();

        /* ---------- Eigenschaften ---------- */
        $this->RegisterPropertyInteger('SensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("HumiditySensor$i", 0);
            $this->RegisterPropertyInteger("TemperatureSensor$i", 0);
        }

        $this->RegisterPropertyBoolean('UseOutsideReference', false);
        $this->RegisterPropertyInteger('OutsideHumidity', 0);
        $this->RegisterPropertyInteger('OutsideTemperature', 0);

        $this->RegisterPropertyInteger('CycleTime', 10);

        $this->RegisterPropertyFloat('HumidityJumpThreshold', 10.0);
        $this->RegisterPropertyInteger('HumidityJumpMinutes', 5);

        $this->RegisterPropertyInteger('NightDisableVariable', 0);

        $this->RegisterPropertyInteger('TargetPercentVariable', 0);
        $this->RegisterPropertyInteger('FeedbackPercentVariable', 0);

        /* ---------- Timer ---------- */
        $this->RegisterTimer(
            'ControlTimer',
            0,
            'CRVHC_Control($_IPS["TARGET"]);'
        );

        /* ---------- Profile ---------- */
        $this->CreateStatusProfile();

        /* ---------- Variablen ---------- */
        $this->RegisterVariableInteger(
            'VentilationStatus',
            'Lüftungsstatus',
            '~CRV_Status',
            10
        );

        $this->RegisterVariableInteger(
            'CurrentPercent',
            'Lüftungsstellwert (%)',
            '~Intensity.100',
            20
        );

        $this->RegisterVariableFloat(
            'MaxAbsoluteHumidity',
            'Max. absolute Feuchte (g/m³)',
            '',
            30
        );

        $this->RegisterVariableFloat(
            'LastHumidityReference',
            'Referenz Feuchte (% rF)',
            '',
            40
        );

        $this->RegisterVariableInteger(
            'NightOverrideUntil',
            'Nacht-Override bis (Unix)',
            '',
            50
        );
    }

    /* ============================================================
     * APPLY CHANGES
     * ============================================================ */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $cycleTime = $this->ReadPropertyInteger('CycleTime');
        $this->SetTimerInterval('ControlTimer', $cycleTime * 60 * 1000);

        $this->SetStatus(102);
    }

    /* ============================================================
     * MAIN CONTROL
     * ============================================================ */
    public function Control()
    {
        $now = time();

        /* ---------- Sensoren ---------- */
        $sensors = $this->GetIndoorSensors();
        if (count($sensors) === 0) {
            SetValue($this->GetIDForIdent('VentilationStatus'), 4);
            return;
        }

        $maxAbsHumidity = 0.0;
        $maxRelHumidity = 0.0;

        foreach ($sensors as $s) {
            $abs = $this->CalculateAbsoluteHumidity(
                $s['temperature'],
                $s['humidity']
            );
            $maxAbsHumidity = max($maxAbsHumidity, $abs);
            $maxRelHumidity = max($maxRelHumidity, $s['humidity']);
        }

        SetValue(
            $this->GetIDForIdent('MaxAbsoluteHumidity'),
            round($maxAbsHumidity, 2)
        );

        /* ========================================================
         * FEUCHTESPRUNG-ERKENNUNG
         * ======================================================== */
        $lastRel = GetValue($this->GetIDForIdent('LastHumidityReference'));
        $threshold = $this->ReadPropertyFloat('HumidityJumpThreshold');

        $humidityJump = false;

        if ($lastRel > 0 && ($maxRelHumidity - $lastRel) >= $threshold) {
            $humidityJump = true;
            // Override 60 Minuten
            SetValue(
                $this->GetIDForIdent('NightOverrideUntil'),
                $now + 3600
            );
        }

        SetValue(
            $this->GetIDForIdent('LastHumidityReference'),
            $maxRelHumidity
        );

        /* ========================================================
         * NACHTABSCHALTUNG + OVERRIDE
         * ======================================================== */
        $nightDisabled = $this->IsNightDisabled();
        $overrideUntil = GetValue(
            $this->GetIDForIdent('NightOverrideUntil')
        );

        if ($nightDisabled && !$humidityJump && $overrideUntil <= $now) {
            SetValue(
                $this->GetIDForIdent('VentilationStatus'),
                5
            );
            return;
        }

        /* ========================================================
         * ZIEL-STELLWERT
         * ======================================================== */
        $currentPercent = GetValue(
            $this->GetIDForIdent('CurrentPercent')
        );

        if ($humidityJump) {
            $targetPercent = min(96, $currentPercent + (3 * 12));
            $status = 3; // Feuchtesprung
        } else {
            $targetPercent = $this->MapHumidityToPercent($maxAbsHumidity);
            $status = 1; // Betrieb
        }

        $this->WriteOutput($targetPercent);

        SetValue(
            $this->GetIDForIdent('CurrentPercent'),
            $targetPercent
        );

        SetValue(
            $this->GetIDForIdent('VentilationStatus'),
            $status
        );
    }

    /* ============================================================
     * HELPER
     * ============================================================ */

    private function IsNightDisabled(): bool
    {
        $id = $this->ReadPropertyInteger('NightDisableVariable');
        return ($id > 0 && IPS_VariableExists($id) && GetValueBoolean($id));
    }

    private function WriteOutput(int $percent): void
    {
        $id = $this->ReadPropertyInteger('TargetPercentVariable');
        if ($id > 0 && IPS_VariableExists($id)) {
            RequestAction($id, $percent);
        }
    }

    private function GetIndoorSensors(): array
    {
        $count = $this->ReadPropertyInteger('SensorCount');
        $result = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("HumiditySensor$i");
            $tID = $this->ReadPropertyInteger("TemperatureSensor$i");

            if ($hID > 0 && $tID > 0 &&
                IPS_VariableExists($hID) &&
                IPS_VariableExists($tID)) {

                $result[] = [
                    'humidity' => GetValueFloat($hID),
                    'temperature' => GetValueFloat($tID)
                ];
            }
        }

        return $result;
    }

    private function CalculateAbsoluteHumidity(float $temp, float $rel): float
    {
        $es = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        return (216.7 * ($rel / 100) * $es) / (273.15 + $temp);
    }

    private function MapHumidityToPercent(float $abs): int
    {
        $percent = (int)round(($abs - 4) * 6);
        return min(96, max(12, $percent));
    }

    private function CreateStatusProfile(): void
    {
        if (!IPS_VariableProfileExists('~CRV_Status')) {

            IPS_CreateVariableProfile('~CRV_Status', VARIABLETYPE_INTEGER);

            IPS_SetVariableProfileAssociation('~CRV_Status', 0, 'Aus', '', 0x808080);
            IPS_SetVariableProfileAssociation('~CRV_Status', 1, 'Betrieb', '', 0x00FF00);
            IPS_SetVariableProfileAssociation('~CRV_Status', 3, 'Feuchtesprung', '', 0xFF8000);
            IPS_SetVariableProfileAssociation('~CRV_Status', 4, 'Fehler', '', 0xFF0000);
            IPS_SetVariableProfileAssociation('~CRV_Status', 5, 'Nachtabschaltung', '', 0x0000FF);
        }
    }
}
