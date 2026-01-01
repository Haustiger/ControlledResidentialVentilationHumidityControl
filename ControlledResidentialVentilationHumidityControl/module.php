<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    private const STAGES = [
        1 => 12,
        2 => 24,
        3 => 36,
        4 => 48,
        5 => 60,
        6 => 72,
        7 => 84,
        8 => 96
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger('IndoorSensor' . $i, 0);
        }
        $this->RegisterPropertyInteger('OutdoorAbsHumidity', 0);
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        if (!IPS_VariableProfileExists('CRV.AbsHumidity')) {
            IPS_CreateVariableProfile('CRV.AbsHumidity', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV.AbsHumidity', '', ' g/m³');
            IPS_SetVariableProfileDigits('CRV.AbsHumidity', 2);
        }

        $this->RegisterVariableFloat('IndoorAbs', 'Absolute Feuchte innen', 'CRV.AbsHumidity', 10);
        $this->RegisterVariableFloat('OutdoorAbs', 'Absolute Feuchte außen', 'CRV.AbsHumidity', 20);
        $this->RegisterVariableInteger('Stage', 'Lüftungsstufe', '', 30);
        $this->RegisterVariableInteger('Setpoint', 'Lüftungsstellwert %', '~Intensity.100', 40);
        $this->RegisterVariableBoolean('JumpActive', 'Feuchtesprung aktiv', '~Switch', 50);
        $this->RegisterVariableString('JumpUntilText', 'Feuchtesprung aktiv bis', '', 60);

        $this->RegisterVariableFloat('LastIndoorAbs', 'Letzte Innenfeuchte', 'CRV.AbsHumidity', 90);
        $this->RegisterVariableInteger('LastStage', 'Letzte Stufe', '', 91);
        $this->RegisterVariableInteger('JumpUntil', 'JumpUntil', '', 92);

        $this->RegisterTimer('ControlTimer', 300, 'IPS_RequestAction($_IPS["TARGET"], "TimerRun", 0);');
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'ManualRun' || $Ident === 'TimerRun') {
            $this->Run();
        }
    }

    private function Run()
    {
        $now = time();

        $values = [];
        for ($i = 1; $i <= $this->ReadPropertyInteger('IndoorSensorCount'); $i++) {
            $id = $this->ReadPropertyInteger('IndoorSensor' . $i);
            if ($id > 0 && IPS_VariableExists($id)) {
                $values[] = floatval(GetValue($id));
            }
        }
        if (!$values) return;

        $avg = array_sum($values) / count($values);
        $max = max($values);

        $outID = $this->ReadPropertyInteger('OutdoorAbsHumidity');
        $out = ($outID > 0 && IPS_VariableExists($outID)) ? floatval(GetValue($outID)) : $avg;

        SetValue($this->GetIDForIdent('IndoorAbs'), $avg);
        SetValue($this->GetIDForIdent('OutdoorAbs'), $out);

        // Feuchtesprung
        if ($max - GetValueFloat($this->GetIDForIdent('LastIndoorAbs')) >= 1.5) {
            SetValue($this->GetIDForIdent('JumpUntil'), $now + 1800);
        }

        $jumpActive = GetValueInteger($this->GetIDForIdent('JumpUntil')) > $now;
        SetValue($this->GetIDForIdent('JumpActive'), $jumpActive);
        SetValue(
            $this->GetIDForIdent('JumpUntilText'),
            $jumpActive ? date('d.m.Y H:i', GetValueInteger($this->GetIDForIdent('JumpUntil'))) : '—'
        );

        // Basis-Stufe
        if ($avg < 7.0) $stage = 1;
        elseif ($avg < 8.5) $stage = 2;
        elseif ($avg < 10.0) $stage = 4;
        elseif ($avg < 11.5) $stage = 6;
        else $stage = 7;

        if ($jumpActive) $stage = max($stage, 5);

        // Sanftes Abregeln
        $lastStage = GetValueInteger($this->GetIDForIdent('LastStage'));
        if ($stage < $lastStage) {
            $stage = $lastStage - 1;
        }

        $stage = max(1, min(8, $stage));

        SetValue($this->GetIDForIdent('Stage'), $stage);
        SetValue($this->GetIDForIdent('Setpoint'), self::STAGES[$stage]);

        SetValue($this->GetIDForIdent('LastStage'), $stage);
        SetValue($this->GetIDForIdent('LastIndoorAbs'), $avg);

        $outVar = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($outVar > 0 && IPS_VariableExists($outVar)) {
            RequestAction($outVar, self::STAGES[$stage]);
        }
    }
}
