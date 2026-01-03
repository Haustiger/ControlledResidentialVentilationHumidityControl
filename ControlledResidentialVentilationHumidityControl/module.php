<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        /* ================= Eigenschaften ================= */

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        // Außensensoren
        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);

        // Stellwert-Ziel
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        /* ================= Status / Debug ================= */

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
            'AbsHumidityOutdoor',
            'Absolute Feuchte außen (g/m³)',
            '',
            40
        );

        $this->RegisterVariableInteger(
            'VentilationStage',
            'Lüftungsstufe',
            '',
            50
        );

        $this->RegisterVariableFloat(
            'VentilationSetpointPercent',
            'Lüftungs-Stellwert (%)',
            '',
            60
        );

        $this->RegisterVariableInteger(
            'LastCalcTimestamp',
            'Letzte Regelung',
            '~UnixTimestamp',
            70
        );

        /* ================= Timer ================= */

        $this->RegisterTimer(
            'ControlTimer',
            300000,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Run') {
            $this->Run();
        }
    }

    /* =================================================== */

    public function Run()
    {
        /* -------- Innen -------- */
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $absIndoor = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 &&
                IPS_VariableExists($hID) &&
                IPS_VariableExists($tID)
            ) {
                $rh = floatval(GetValue($hID));
                $temp = floatval(GetValue($tID));

                if ($rh > 1) {
                    $rh = $rh / 100.0;
                }

                $absIndoor[] = $this->CalcAbsoluteHumidity($temp, $rh);
            }
        }

        if (count($absIndoor) === 0) {
            return;
        }

        $avgIndoor = round(array_sum($absIndoor) / count($absIndoor), 2);
        SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), $avgIndoor);
        $this->UpdateMinMax24h($avgIndoor);

        /* -------- Außen -------- */
        $absOutdoor = 0.0;
        $hOut = $this->ReadPropertyInteger('OutdoorHumidity');
        $tOut = $this->ReadPropertyInteger('OutdoorTemperature');

        if ($hOut > 0 && $tOut > 0 &&
            IPS_VariableExists($hOut) &&
            IPS_VariableExists($tOut)
        ) {
            $rhOut = floatval(GetValue($hOut));
            $tOutVal = floatval(GetValue($tOut));

            if ($rhOut > 1) {
                $rhOut = $rhOut / 100.0;
            }

            $absOutdoor = round(
                $this->CalcAbsoluteHumidity($tOutVal, $rhOut),
                2
            );

            SetValue($this->GetIDForIdent('AbsHumidityOutdoor'), $absOutdoor);
        }

        /* -------- 8-Stufen-Kennlinie -------- */

        $stage = $this->DetermineStage($avgIndoor, $absOutdoor);
        $percent = $this->StageToPercent($stage);

        SetValue($this->GetIDForIdent('VentilationStage'), $stage);
        SetValue($this->GetIDForIdent('VentilationSetpointPercent'), $percent);
        SetValue($this->GetIDForIdent('LastCalcTimestamp'), time());

        $targetID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($targetID > 0 && IPS_VariableExists($targetID)) {
            @RequestAction($targetID, $percent);
        }

        IPS_LogMessage(
            'CRVHC',
            'Build 4: Regelung -> Stufe ' . $stage . ' (' . $percent . '%)'
        );
    }

    /* =================================================== */

    private function DetermineStage(float $absIndoor, float $absOutdoor): int
    {
        // Außenfeuchte wird berücksichtigt, aber noch nicht begrenzend
        if ($absIndoor < 6.5) return 1;
        if ($absIndoor < 7.0) return 2;
        if ($absIndoor < 7.5) return 3;
        if ($absIndoor < 8.0) return 4;
        if ($absIndoor < 8.5) return 5;
        if ($absIndoor < 9.0) return 6;
        if ($absIndoor < 9.5) return 7;
        return 8;
    }

    private function StageToPercent(int $stage): int
    {
        return $stage * 12;
    }

    private function UpdateMinMax24h(float $value)
    {
        $minID = $this->GetIDForIdent('AbsHumidityIndoorMin24h');
        $maxID = $this->GetIDForIdent('AbsHumidityIndoorMax24h');

        if (GetValue($minID) == 0 || $value < GetValue($minID)) {
            SetValue($minID, $value);
        }
        if ($value > GetValue($maxID)) {
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
