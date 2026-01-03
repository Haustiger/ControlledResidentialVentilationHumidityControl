<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        /* ---------- Eigenschaften ---------- */

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        // Stellwert-Zielvariable (SYMCON-ID, z. B. KNX-Ausgang)
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        /* ---------- Status / Debug Variablen ---------- */

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorAvg',
            'Absolute Feuchte innen Ø (g/m³)',
            '',
            10
        );

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorMin24h',
            'Absolute Feuchte innen MIN 24h (g/m³)',
            '',
            20
        );

        $this->RegisterVariableFloat(
            'AbsHumidityIndoorMax24h',
            'Absolute Feuchte innen MAX 24h (g/m³)',
            '',
            30
        );

        $this->RegisterVariableFloat(
            'VentilationSetpointPercent',
            'Lüftungs-Stellwert (%)',
            '',
            40
        );

        $this->RegisterVariableInteger(
            'LastCalcTimestamp',
            'Letzte Regelung',
            '~UnixTimestamp',
            50
        );

        /* ---------- Timer ---------- */

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

    /* ========================================================= */

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
            IPS_LogMessage('CRVHC', 'Build 3: Keine gültigen Sensordaten');
            return;
        }

        $avg = round(array_sum($absValues) / count($absValues), 2);

        SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), $avg);
        SetValue($this->GetIDForIdent('LastCalcTimestamp'), time());

        $this->UpdateMinMax24h($avg);

        /* ---------- einfache Stellwert-Logik (Build 3) ---------- */
        // bewusst simpel und stabil – wird später ersetzt

        $percent = $this->MapHumidityToPercent($avg);
        SetValue($this->GetIDForIdent('VentilationSetpointPercent'), $percent);

        $targetID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($targetID > 0 && IPS_VariableExists($targetID)) {
            @RequestAction($targetID, $percent);
        }

        IPS_LogMessage('CRVHC', 'Build 3: Regelung ausgeführt (' . $percent . '%)');
    }

    /* ========================================================= */

    private function UpdateMinMax24h(float $value)
    {
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

    private function MapHumidityToPercent(float $abs): int
    {
        // einfache lineare Kennlinie (vorläufig)
        if ($abs < 6.5) return 20;
        if ($abs < 7.5) return 30;
        if ($abs < 8.5) return 40;
        if ($abs < 9.5) return 50;
        if ($abs < 10.5) return 60;
        return 70;
    }

    private function CalcAbsoluteHumidity(float $tempC, float $relHum): float
    {
        $sat = 6.112 * exp((17.62 * $tempC) / (243.12 + $tempC));
        $vap = $sat * $relHum;
        return (216.7 * $vap) / (273.15 + $tempC);
    }
}
