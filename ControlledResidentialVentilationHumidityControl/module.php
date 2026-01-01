<?php
declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    /* =========================================================
     * CREATE
     * ========================================================= */
    public function Create()
    {
        parent::Create();

        /* Grundeinstellungen */
        $this->RegisterPropertyInteger('CycleMinutes', 10);

        /* Innensensoren */
        $this->RegisterPropertyInteger('IndoorSensorCount', 3);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity_$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature_$i", 0);
        }

        /* Außensensoren */
        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);
        $this->RegisterPropertyFloat('OutsideDeltaThreshold', 1.0);

        /* Feuchtesprung */
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 2.0);
        $this->RegisterPropertyInteger('HumidityJumpMinutes', 5);
        $this->RegisterPropertyInteger('HumidityJumpMaxRuntime', 60);

        /* Nachtabschaltung */
        $this->RegisterPropertyInteger('NightOffSwitch', 0);

        /* Lüftungssteuerung */
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        /* Profile */
        $this->RegisterProfiles();

        /* Statusvariablen */
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

        /* Timer */
        $this->RegisterTimer(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60 * 1000,
            'IPS_RequestAction(' . $this->InstanceID . ', "TimerRun", 1);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60 * 1000
        );
    }

    /* =========================================================
     * PROFILE
     * ========================================================= */
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

    /* =========================================================
     * REQUEST ACTION (BUTTON + TIMER)
     * ========================================================= */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ManualRun':
            case 'TimerRun':
                $this->RunControl();
                break;
        }
    }

    /* =========================================================
     * HAUPTLOGIK
     * ========================================================= */
    private function RunControl()
    {
        /* Innere absolute Feuchte */
        $values = [];
        $count = $this->ReadPropertyInteger('IndoorSensorCount');

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("IndoorHumidity_$i");
            $t = $this->ReadPropertyInteger("IndoorTemperature_$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $values[] = $this->CalculateAbsoluteHumidity(
                    floatval(GetValue($t)),
                    floatval(GetValue($h))
                );
            }
        }

        if (count($values) === 0) {
            $this->SetValue('Status', 4);
            return;
        }

        $absIndoor = array_sum($values) / count($values);
        $this->SetValue('AbsoluteHumidityIndoor', round($absIndoor, 2));

        /* Außen */
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

        /* Nachtabschaltung */
        $night = false;
        $nightSwitch = $this->ReadPropertyInteger('NightOffSwitch');
        if ($nightSwitch > 0 && IPS_VariableExists($nightSwitch)) {
            $night = GetValueBoolean($nightSwitch);
        }

        /* Feuchtesprung (Override!) */
        if (($absIndoor - $absOutdoor) >= $this->ReadPropertyFloat('HumidityJumpThreshold')) {
            $this->SetValue('Status', 2);
            $this->SetValue('NightOverrideActive', true);
            $this->SetVentilation(96);
            return;
        }

        $this->SetValue('NightOverrideActive', false);

        if ($night) {
            $this->SetValue('Status', 3);
            $this->SetVentilation(0);
            return;
        }

        /* Sommer/Winter Automatik */
        $delta = $absIndoor - $absOutdoor;
        if ($delta >= $this->ReadPropertyFloat('OutsideDeltaThreshold')) {
            $this->SetValue('Status', 1);
            $this->SetVentilation($this->CalculateStagePercent($delta));
        } else {
            $this->SetValue('Status', 0);
            $this->SetVentilation(0);
        }
    }

    /* =========================================================
     * HILFSFUNKTIONEN
     * ========================================================= */
    private function CalculateAbsoluteHumidity(float $temp, float $rh): float
    {
        $ps = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $p = ($rh / 100.0) * $ps;
        return (216.7 * $p) / ($temp + 273.15);
    }

    private function CalculateStagePercent(float $delta): int
    {
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
