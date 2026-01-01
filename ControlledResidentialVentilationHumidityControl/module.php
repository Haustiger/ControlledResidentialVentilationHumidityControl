<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger('IndoorSensor' . $i, 0);
        }

        $this->RegisterPropertyInteger('OutdoorAbsHumidity', 0);
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        $this->RegisterTimer('ControlTimer', 300, 'IPS_RequestAction($_IPS["TARGET"], "TimerRun", 0);');

        $this->RegisterVariableFloat('Debug_IndoorAbs', 'Absolute Feuchte innen', 'Humidity.Abs', 10);
        $this->RegisterVariableFloat('Debug_OutdoorAbs', 'Absolute Feuchte außen', 'Humidity.Abs', 20);
        $this->RegisterVariableInteger('Debug_Setpoint', 'Lüftungsstellwert %', '', 30);
        $this->RegisterVariableBoolean('Debug_Jump', 'Feuchtesprung aktiv', '~Switch', 40);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'ManualRun' || $Ident === 'TimerRun') {
            $this->Run();
        }
    }

    private function Run()
    {
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $values = [];

        for ($i = 1; $i <= $count; $i++) {
            $id = $this->ReadPropertyInteger('IndoorSensor' . $i);
            if ($id > 0 && IPS_VariableExists($id)) {
                $values[] = GetValueFloat($id);
            }
        }

        if (count($values) === 0) {
            return;
        }

        $avgIndoor = array_sum($values) / count($values);
        $maxIndoor = max($values);

        $outID = $this->ReadPropertyInteger('OutdoorAbsHumidity');
        $outdoor = ($outID > 0 && IPS_VariableExists($outID)) ? GetValueFloat($outID) : $avgIndoor;

        SetValue($this->GetIDForIdent('Debug_IndoorAbs'), $avgIndoor);
        SetValue($this->GetIDForIdent('Debug_OutdoorAbs'), $outdoor);

        // Basiskennlinie
        if ($avgIndoor < 7.0) $target = 10;
        elseif ($avgIndoor < 8.5) $target = 25;
        elseif ($avgIndoor < 10.0) $target = 45;
        elseif ($avgIndoor < 11.5) $target = 65;
        else $target = 85;

        // Außenbewertung
        $delta = $avgIndoor - $outdoor;
        if ($delta < 0) $target -= 20;
        elseif ($delta > 3) $target += 10;

        $target = max(0, min(100, $target));

        SetValue($this->GetIDForIdent('Debug_Setpoint'), $target);

        $out = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($out > 0 && IPS_VariableExists($out)) {
            RequestAction($out, $target);
        }
    }
}
