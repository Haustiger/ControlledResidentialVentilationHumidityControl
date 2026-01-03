<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        // Profile
        if (!IPS_VariableProfileExists('CRV_HumidityAbs')) {
            IPS_CreateVariableProfile('CRV_HumidityAbs', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV_HumidityAbs', '', ' g/m³');
            IPS_SetVariableProfileDigits('CRV_HumidityAbs', 2);
        }

        // Variables
        $this->RegisterVariableFloat(
            'AbsHumidityIndoor',
            'Absolute Feuchte innen',
            'CRV_HumidityAbs',
            10
        );

        $this->RegisterVariableInteger(
            'VentilationPercent',
            'Lüftungsstellwert (%)',
            '~Intensity.100',
            20
        );

        // Timer (5 Minuten)
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

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Run') {
            $this->Run();
        }
    }

    public function Run()
    {
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $values = [];

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("IndoorHumidity$i");
            $t = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $values[] = $this->calcAbsHumidity(
                    GetValue($t),
                    GetValue($h)
                );
            }
        }

        if (count($values) === 0) {
            return;
        }

        $avg = array_sum($values) / count($values);
        SetValue($this->GetIDForIdent('AbsHumidityIndoor'), $avg);

        $percent = $this->mapToStage($avg);
        SetValue($this->GetIDForIdent('VentilationPercent'), $percent);

        $target = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($target > 0 && IPS_VariableExists($target)) {
            RequestAction($target, $percent);
        }
    }

    private function calcAbsHumidity(float $temp, float $rh): float
    {
        $sdd = 6.1078 * pow(10, (7.5 * $temp) / (237.3 + $temp));
        return round((216.7 * ($rh / 100 * $sdd)) / (273.15 + $temp), 2);
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
