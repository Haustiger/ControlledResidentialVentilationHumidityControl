<?php

declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        /* Eigenschaften */
        $this->RegisterPropertyInteger('SensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("HumiditySensor$i", 0);
            $this->RegisterPropertyInteger("TemperatureSensor$i", 0);
        }

        $this->RegisterPropertyInteger('CycleTime', 10);
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 10.0);
        $this->RegisterPropertyInteger('NightDisableVariable', 0);
        $this->RegisterPropertyInteger('TargetPercentVariable', 0);

        /* Timer */
        $this->RegisterTimer(
            'ControlTimer',
            0,
            'CRVHC_Control($_IPS["TARGET"]);'
        );

        /* Profil */
        $this->CreateStatusProfile();

        /* Variablen (WICHTIG: KEIN #!) */
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
            'Nacht-Override bis',
            '',
            50
        );

        /* Initialwerte */
        $this->SafeSetValue('VentilationStatus', 0);
        $this->SafeSetValue('CurrentPercent', 12);
        $this->SafeSetValue('LastHumidityReference', 0.0);
        $this->SafeSetValue('NightOverrideUntil', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $cycle = max(5, min(30, $this->ReadPropertyInteger('CycleTime')));
        $this->SetTimerInterval('ControlTimer', $cycle * 60 * 1000);

        $this->SetStatus(102);
    }

    public function Control()
    {
        $now = time();

        $sensors = $this->GetIndoorSensors();
        if (count($sensors) === 0) {
            $this->SafeSetValue('VentilationStatus', 4);
            return;
        }

        $maxAbs = 0.0;
        $maxRel = 0.0;

        foreach ($sensors as $s) {
            $abs = $this->CalculateAbsoluteHumidity($s['temperature'], $s['humidity']);
            $maxAbs = max($maxAbs, $abs);
            $maxRel = max($maxRel, $s['humidity']);
        }

        $this->SafeSetValue('MaxAbsoluteHumidity', round($maxAbs, 2));

        $lastRel = $this->SafeGetValueFloat('LastHumidityReference');
        $jump = ($lastRel > 0 && ($maxRel - $lastRel) >= $this->ReadPropertyFloat('HumidityJumpThreshold'));

        if ($jump) {
            $this->SafeSetValue('NightOverrideUntil', $now + 3600);
        }

        $this->SafeSetValue('LastHumidityReference', $maxRel);

        if ($this->IsNightDisabled() && !$jump) {
            if ($this->SafeGetValueInt('NightOverrideUntil') <= $now) {
                $this->SafeSetValue('VentilationStatus', 5);
                return;
            }
        }

        $current = $this->SafeGetValueInt('CurrentPercent');

        if ($jump) {
            $target = min(96, $current + 36);
            $status = 3;
        } else {
            $target = $this->MapHumidityToPercent($maxAbs);
            $status = 1;
        }

        $this->WriteOutput($target);
        $this->SafeSetValue('CurrentPercent', $target);
        $this->SafeSetValue('VentilationStatus', $status);
    }

    /* ===================== Helpers ===================== */

    private function SafeSetValue(string $ident, $value): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id > 0) {
            SetValue($id, $value);
        }
    }

    private function SafeGetValueInt(string $ident): int
    {
        $id = @$this->GetIDForIdent($ident);
        return ($id > 0) ? (int)GetValue($id) : 0;
    }

    private function SafeGetValueFloat(string $ident): float
    {
        $id = @$this->GetIDForIdent($ident);
        return ($id > 0) ? (float)GetValue($id) : 0.0;
    }

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
        $out = [];

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("HumiditySensor$i");
            $t = $this->ReadPropertyInteger("TemperatureSensor$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $out[] = [
                    'humidity' => GetValueFloat($h),
                    'temperature' => GetValueFloat($t)
                ];
            }
        }
        return $out;
    }

    private function CalculateAbsoluteHumidity(float $temp, float $rel): float
    {
        $es = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        return (216.7 * ($rel / 100) * $es) / (273.15 + $temp);
    }

    private function MapHumidityToPercent(float $abs): int
    {
        return min(96, max(12, (int)round(($abs - 4) * 6)));
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
