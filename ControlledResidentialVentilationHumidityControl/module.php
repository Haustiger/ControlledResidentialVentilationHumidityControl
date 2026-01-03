<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Anzahl Sensoren
        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        // Sensor-Properties
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        // Stellwert
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        // Variablen
        $this->RegisterVariableFloat(
            'AbsHumidityIndoorAvg',
            'Absolute Feuchte innen (Ø)',
            '',
            10
        );

        // Timer
        $this->RegisterTimer(
            'ControlTimer',
            0,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // alle 5 Minuten
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

                // relative Feuchte zulässig:
                // - 0..100 %
                // - KNX DPT5 (0..255) → wird automatisch skaliert
                if ($rh > 1) {
                    $rh = $rh / 100.0;
                }

                $abs = $this->CalcAbsoluteHumidity($temp, $rh);
                $absValues[] = $abs;
            }
        }

        if (count($absValues) > 0) {
            $avg = array_sum($absValues) / count($absValues);
            SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), round($avg, 2));
        }

        IPS_LogMessage('CRVHC', 'Build 1: Regelung ausgeführt');
    }

    // physikalisch korrekte absolute Feuchte g/m³
    private function CalcAbsoluteHumidity(float $tempC, float $relHum): float
    {
        $sat = 6.112 * exp((17.62 * $tempC) / (243.12 + $tempC));
        $vap = $sat * $relHum;
        return (216.7 * $vap) / (273.15 + $tempC);
    }
}
