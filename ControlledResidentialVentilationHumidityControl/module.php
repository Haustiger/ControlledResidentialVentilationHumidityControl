<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

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

        $this->RegisterTimer('ControlTimer', 0, 'CRV_Control($_IPS[\'TARGET\']);');

        $this->CreateStatusProfile();

        $this->RegisterVariableInteger('VentilationStatus', 'Lüftungsstatus', '~CRV.Status', 10);
        $this->RegisterVariableInteger('CurrentPercent', 'Lüftungsstellwert (%)', '~Intensity.100', 20);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $cycle = $this->ReadPropertyInteger('CycleTime');
        $this->SetTimerInterval('ControlTimer', $cycle * 60 * 1000);

        $this->SetStatus(102);
    }

    public function Control()
    {
        if ($this->IsNightDisabled()) {
            SetValue($this->GetIDForIdent('VentilationStatus'), 5);
            return;
        }

        $sensors = $this->GetIndoorSensors();
        if (count($sensors) === 0) {
            SetValue($this->GetIDForIdent('VentilationStatus'), 4);
            return;
        }

        $maxAbsHumidity = 0.0;
        foreach ($sensors as $s) {
            $abs = $this->CalculateAbsoluteHumidity($s['temperature'], $s['humidity']);
            $maxAbsHumidity = max($maxAbsHumidity, $abs);
        }

        $percent = min(96, max(12, round($maxAbsHumidity * 10)));
        $this->WriteOutput($percent);

        SetValue($this->GetIDForIdent('CurrentPercent'), $percent);
        SetValue($this->GetIDForIdent('VentilationStatus'), 1);
    }

    private function IsNightDisabled(): bool
    {
        $id = $this->ReadPropertyInteger('NightDisableVariable');
        return ($id > 0 && IPS_VariableExists($id) && GetValueBoolean($id));
    }

    private function WriteOutput(int $percent)
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
            $h = $this->ReadPropertyInteger("HumiditySensor$i");
            $t = $this->ReadPropertyInteger("TemperatureSensor$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $result[] = [
                    'humidity' => GetValueFloat($h),
                    'temperature' => GetValueFloat($t)
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

    private function CreateStatusProfile()
    {
        if (!IPS_VariableProfileExists('~CRV.Status')) {
            IPS_CreateVariableProfile('~CRV.Status', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('~CRV.Status', 0, 'Aus', '', 0x808080);
            IPS_SetVariableProfileAssociation('~CRV.Status', 1, 'Betrieb', '', 0x00FF00);
            IPS_SetVariableProfileAssociation('~CRV.Status', 4, 'Fehler', '', 0xFF0000);
            IPS_SetVariableProfileAssociation('~CRV.Status', 5, 'Nacht', '', 0x0000FF);
        }
    }
}
