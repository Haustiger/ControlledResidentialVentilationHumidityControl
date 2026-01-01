<?php
declare(strict_types=1);

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        /* ================= Grundeinstellungen ================= */
        $this->RegisterPropertyInteger('CycleMinutes', 10);

        /* ================= Innensensoren ================= */
        $this->RegisterPropertyInteger('IndoorSensorCount', 3);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity_$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature_$i", 0);
        }

        /* ================= Außensensor ================= */
        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);
        $this->RegisterPropertyFloat('OutsideDeltaThreshold', 1.0);

        /* ================= Feuchtesprung ================= */
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 2.0);

        /* ================= Nachtabschaltung ================= */
        $this->RegisterPropertyInteger('NightOffSwitch', 0);
        $this->RegisterPropertyInteger('NightStartMinute', 1320); // 22:00
        $this->RegisterPropertyInteger('NightEndMinute', 360);   // 06:00

        /* ================= Lüftung ================= */
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);
        $this->RegisterPropertyInteger('VentilationFeedbackID', 0);

        /* ================= Status ================= */
        $this->RegisterProfiles();

        $this->RegisterVariableInteger('Status', 'Lüftungsstatus', 'CRVStatus', 10);
        $this->RegisterVariableFloat('AbsoluteHumidityIndoor', 'Absolute Feuchte innen (g/m³)', '', 20);
        $this->RegisterVariableFloat('AbsoluteHumidityOutdoor', 'Absolute Feuchte außen (g/m³)', '', 30);
        $this->RegisterVariableBoolean('NightOverrideActive', 'Nacht-Override aktiv', '~Switch', 40);
        $this->RegisterVariableInteger('DebugSetpoint', 'Letzter Stellwert (%)', '', 50);
        $this->RegisterVariableString('DebugReason', 'Regelgrund', '', 60);

        /* ================= Timer ================= */
        $this->RegisterTimer(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60000,
            'IPS_RequestAction(' . $this->InstanceID . ', "TimerRun", 1);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval(
            'ControlTimer',
            $this->ReadPropertyInteger('CycleMinutes') * 60000
        );
    }

    /* ================= Profile ================= */
    private function RegisterProfiles()
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

    /* ================= Button / Timer ================= */
    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'ManualRun' || $Ident === 'TimerRun') {
            $this->RunControl();
        }
    }

    /* ================= Hauptlogik ================= */
    private function RunControl()
    {
        $values = [];
        $count = $this->ReadPropertyInteger('IndoorSensorCount');

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("IndoorHumidity_$i");
            $t = $this->ReadPropertyInteger("IndoorTemperature_$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $values[] = $this->AbsHumidity(GetValue($t), GetValue($h));
            }
        }

        if (count($values) === 0) {
            $this->SetValue('Status', 4);
            $this->SetValue('DebugReason', 'Keine gültigen Innensensoren');
            return;
        }

        $absIn = array_sum($values) / count($values);
        $this->SetValue('AbsoluteHumidityIndoor', round($absIn, 2));

        $absOut = $absIn;
        if ($this->ReadPropertyInteger('OutdoorHumidity') > 0 &&
            $this->ReadPropertyInteger('OutdoorTemperature') > 0) {
            $absOut = $this->AbsHumidity(
                GetValue($this->ReadPropertyInteger('OutdoorTemperature')),
                GetValue($this->ReadPropertyInteger('OutdoorHumidity'))
            );
        }
        $this->SetValue('AbsoluteHumidityOutdoor', round($absOut, 2));

        /* Nachtzeit */
        $nowMin = intval(date('G')) * 60 + intval(date('i'));
        $night = false;
        if ($this->ReadPropertyInteger('NightOffSwitch') > 0 &&
            GetValueBoolean($this->ReadPropertyInteger('NightOffSwitch'))) {

            $start = $this->ReadPropertyInteger('NightStartMinute');
            $end   = $this->ReadPropertyInteger('NightEndMinute');

            $night = ($start < $end)
                ? ($nowMin >= $start && $nowMin < $end)
                : ($nowMin >= $start || $nowMin < $end);
        }

        /* Feuchtesprung */
        if (($absIn - $absOut) >= $this->ReadPropertyFloat('HumidityJumpThreshold')) {
            $this->SetValue('Status', 2);
            $this->SetValue('NightOverrideActive', true);
            $this->SetVentilation(96, 'Feuchtesprung');
            return;
        }

        $this->SetValue('NightOverrideActive', false);

        if ($night) {
            $this->SetValue('Status', 3);
            $this->SetVentilation(0, 'Nachtabschaltung');
            return;
        }

        if (($absIn - $absOut) >= $this->ReadPropertyFloat('OutsideDeltaThreshold')) {
            $this->SetValue('Status', 1);
            $this->SetVentilation(60, 'Normalbetrieb');
        } else {
            $this->SetValue('Status', 0);
            $this->SetVentilation(0, 'Keine Lüftung sinnvoll');
        }
    }

    private function SetVentilation(int $percent, string $reason)
    {
        $this->SetValue('DebugSetpoint', $percent);
        $this->SetValue('DebugReason', $reason);

        $id = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($id > 0 && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableIsWritable']) {
            SetValue($id, $percent);
        }
    }

    private function AbsHumidity(float $t, float $rh): float
    {
        $ps = 6.112 * exp((17.62 * $t) / (243.12 + $t));
        return (216.7 * (($rh / 100) * $ps)) / ($t + 273.15);
    }
}
