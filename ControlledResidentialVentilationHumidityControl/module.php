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

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorAvg',
            'Absolute Feuchte innen Ø (g/m³)',
            '',
            10
        );

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorMin24h',
            'Absolute Feuchte innen MIN (24h)',
            '',
            20
        );

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorMax24h',
            'Absolute Feuchte innen MAX (24h)',
            '',
            30
        );

        $this->RegisterVariableInteger(
            'LastCalcTimestamp',
            'Letzte Berechnung',
            '~UnixTimestamp',
            40
        );

        $this->RegisterTimer(
            'ControlTimer',
            0,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('ControlTimer', 300000);
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
        $absValues = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 &&
                IPS_VariableExists($hID) &&
                IPS_VariableExists($tID)
            ) {
                $rh = floatval(GetValue($hID));
                $temp = floatval(GetValue($tID));

                // KNX DPT5 (0..255) oder Prozent
                if ($rh > 1) {
                    $rh = $rh / 100.0;
                }

                $absValues[] = $this->CalcAbsoluteHumidity($temp, $rh);
            }
        }

        if (count($absValues) === 0) {
            return;
        }

        $avg = array_sum($absValues) / count($absValues);
        $avg = round($avg, 2);

        SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), $avg);
        SetValue($this->GetIDForIdent('LastCalcTimestamp'), time());

        $this->UpdateMinMax24h($avg);

        IPS_LogMessage('CRVHC', 'Build 2: Regelung ausgeführt');
    }

    private function UpdateMinMax24h(float $value)
    {
        $now = time();
        $minID = $this->GetIDForIdent('AbsHumidityIndoorMin24h');
        $maxID = $this->GetIDForIdent('AbsHumidityIndoorMax24h');

        $min = GetValue($minID);
        $max = GetValue($maxID);

        if ($min == 0 || $value < $min) {
            SetValue($minID, $value);
        }

        if ($value > $max) {
            SetValue($maxID, $value);
        }
    }

    private function CalcAbsoluteHumidity(float $tempC, float $relHum): float
    {
        $sat = 6.112 * exp((17.62 * $tempC) / (243.12 + $tempC));
        $vap = $sat * $relHum;
        return (216.7 * $vap) / (273.15 + $tempC);
    }
}
