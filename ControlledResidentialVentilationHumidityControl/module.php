<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemp$i", 0);
        }

        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemp', 0);

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);
        $this->RegisterPropertyInteger('VentilationActualID', 0);

        // Profile
        if (!IPS_VariableProfileExists('CRV_HumidityAbs')) {
            IPS_CreateVariableProfile('CRV_HumidityAbs', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV_HumidityAbs', '', ' g/m³');
            IPS_SetVariableProfileDigits('CRV_HumidityAbs', 2);
            IPS_SetVariableProfileValues('CRV_HumidityAbs', 0, 30, 0);
        }

        // Variables
        $this->RegisterVariableFloat('AbsHumidityIndoor', 'Absolute Feuchte innen (Ø)', 'CRV_HumidityAbs', 10);
        $this->RegisterVariableFloat('AbsHumidityMax24h', 'Absolute Feuchte max. (24h)', 'CRV_HumidityAbs', 20);
        $this->RegisterVariableFloat('AbsHumidityMin24h', 'Absolute Feuchte min. (24h)', 'CRV_HumidityAbs', 30);
        $this->RegisterVariableInteger('VentilationLevel', 'Lüftungsstufe (%)', '~Intensity.100', 40);

        // Timer
        $this->RegisterTimer(
            'ControlTimer',
            300000,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    // 🔧 ZENTRALER FIX
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {

            case 'Run':
            case 'ManualRun':   // <<< FIX
                $this->Run();
                break;

            default:
                IPS_LogMessage('CRVHC', 'Unbekannter Ident: ' . $Ident);
        }
    }

    public function Run()
    {
        IPS_LogMessage('CRVHC', 'Regelung gestartet');

        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $values = [];

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("IndoorHumidity$i");
            $t = $this->ReadPropertyInteger("IndoorTemp$i");

            if ($h && $t && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $values[] = $this->calcAbsHumidity(GetValue($t), GetValue($h));
            }
        }

        if (count($values) === 0) return;

        $avg = array_sum($values) / count($values);
        $max = max($values);
        $min = min($values);

        SetValue($this->GetIDForIdent('AbsHumidityIndoor'), $avg);
        SetValue($this->GetIDForIdent('AbsHumidityMax24h'), $max);
        SetValue($this->GetIDForIdent('AbsHumidityMin24h'), $min);

        $percent = $this->mapToStage($avg);
        SetValue($this->GetIDForIdent('VentilationLevel'), $percent);

        $target = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($target && IPS_VariableExists($target)) {
            RequestAction($target, $percent);
        }
    }

    private function calcAbsHumidity(float $t, float $rh): float
    {
        $sdd = 6.1078 * pow(10, (7.5 * $t) / (237.3 + $t));
        return round(216.7 * (($rh / 100 * $sdd) / (273.15 + $t)), 2);
    }

    private function mapToStage(float $h): int
    {
        if ($h < 7.0) return 12;
        if ($h < 8.0) return 25;
        if ($h < 9.0) return 37;
        if ($h < 10.0) return 50;
        if ($h < 11.0) return 62;
        if ($h < 12.0) return 75;
        if ($h < 13.0) return 87;
        return 100;
    }
}
