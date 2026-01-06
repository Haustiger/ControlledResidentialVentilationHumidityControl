<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        /* ===== Eigenschaften ===== */

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        // Feuchtesprung
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 10.0);

        // Außenbewertung
        $this->RegisterPropertyBoolean('OutdoorEvaluationEnabled', true);
        $this->RegisterPropertyInteger('OutdoorWeightPercent', 50);

        /* ===== Variablen ===== */

        $this->RegisterVariableFloat('AbsHumidityIndoorAvg', 'Absolute Feuchte innen Ø (g/m³)', '', 10);
        $this->RegisterVariableFloat('AbsHumidityIndoorMin24h', 'Absolute Feuchte innen MIN ab Start (g/m³)', '', 20);
        $this->RegisterVariableFloat('AbsHumidityIndoorMax24h', 'Absolute Feuchte innen MAX ab Start (g/m³)', '', 30);
        $this->RegisterVariableFloat('AbsHumidityOutdoor', 'Absolute Feuchte außen (g/m³)', '', 40);

        $this->RegisterVariableInteger('VentilationStage', 'Lüftungsstufe', '', 50);
        $this->RegisterVariableFloat('VentilationSetpointPercent', 'Lüftungs-Stellwert (%)', '', 60);

        $this->RegisterVariableString('LastCalcTime', 'Letzte Regelung', '', 70);

        /* --- Außenbewertung Debug --- */
        $this->RegisterVariableBoolean('OutdoorEvaluationActive', 'Außenbewertung aktiv', '', 75);
        $this->RegisterVariableFloat('OutdoorWeightUsed', 'Außen Gewichtung (%)', '', 76);

        /* --- Feuchtesprung Debug --- */
        $this->RegisterVariableBoolean('HumidityJumpActive', 'Feuchtesprung aktiv', '', 80);
        $this->RegisterVariableString('HumidityJumpDetectedAt', 'Letzter Feuchtesprung', '', 81);
        $this->RegisterVariableString('HumidityJumpUntil', 'Feuchtesprung aktiv bis', '', 82);
        $this->RegisterVariableFloat('HumidityJumpDelta', 'Feuchtesprung Δ rF (%)', '', 83);
        $this->RegisterVariableFloat('HumidityJumpThresholdUsed', 'Feuchtesprung Schwellwert (%)', '', 84);
        $this->RegisterVariableFloat('LastAvgRelHumidity', 'Ø rel. Feuchte vor 5 Min (%)', '', 90);

        /* ===== Timer ===== */

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

    /* ================================================= */

    public function Run()
    {
        $count = $this->ReadPropertyInteger('IndoorSensorCount');

        $absIndoor = [];
        $relIndoor = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $rh = floatval(GetValue($hID));
                $temp = floatval(GetValue($tID));

                if ($rh > 1) {
                    $rh = $rh / 100.0;
                }

                $relIndoor[] = $rh * 100;
                $absIndoor[] = $this->CalcAbsoluteHumidity($temp, $rh);
            }
        }

        if (count($absIndoor) === 0) {
            return;
        }

        $avgAbs = round(array_sum($absIndoor) / count($absIndoor), 2);
        $avgRel = round(array_sum($relIndoor) / count($relIndoor), 2);

        SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), $avgAbs);
        $this->UpdateMinMax($avgAbs);

        /* ===== Außenbewertung ===== */

        $outAbs = 0;
        $outEvalActive = false;

        if ($this->ReadPropertyBoolean('OutdoorEvaluationEnabled')) {
            $hOut = $this->ReadPropertyInteger('OutdoorHumidity');
            $tOut = $this->ReadPropertyInteger('OutdoorTemperature');

            if ($hOut > 0 && $tOut > 0 && IPS_VariableExists($hOut) && IPS_VariableExists($tOut)) {
                $rhOut = floatval(GetValue($hOut));
                if ($rhOut > 1) $rhOut /= 100.0;
                $outAbs = round(
                    $this->CalcAbsoluteHumidity(floatval(GetValue($tOut)), $rhOut),
                    2
                );
                SetValue($this->GetIDForIdent('AbsHumidityOutdoor'), $outAbs);
                $outEvalActive = true;
            }
        }

        SetValue($this->GetIDForIdent('OutdoorEvaluationActive'), $outEvalActive);
        SetValue($this->GetIDForIdent('OutdoorWeightUsed'), $this->ReadPropertyInteger('OutdoorWeightPercent'));

        /* ===== Feuchtesprung ===== */

        $lastRel = GetValue($this->GetIDForIdent('LastAvgRelHumidity'));
        $delta = round($avgRel - $lastRel, 2);
        $threshold = $this->ReadPropertyFloat('HumidityJumpThreshold');

        SetValue($this->GetIDForIdent('HumidityJumpDelta'), $delta);
        SetValue($this->GetIDForIdent('HumidityJumpThresholdUsed'), $threshold);

        $now = time();

        if ($delta >= $threshold) {
            SetValue($this->GetIDForIdent('HumidityJumpActive'), true);
            SetValue($this->GetIDForIdent('HumidityJumpDetectedAt'), date('d.m.Y H:i:s', $now));
            SetValue($this->GetIDForIdent('HumidityJumpUntil'), date('d.m.Y H:i:s', $now + 900));
        }

        if (GetValue($this->GetIDForIdent('HumidityJumpActive')) &&
            $now > strtotime(GetValue($this->GetIDForIdent('HumidityJumpUntil')))
        ) {
            SetValue($this->GetIDForIdent('HumidityJumpActive'), false);
        }

        SetValue($this->GetIDForIdent('LastAvgRelHumidity'), $avgRel);

        /* ===== Lüftungsstufe ===== */

        $effectiveAbs = $avgAbs;

        if ($outEvalActive) {
            $w = $this->ReadPropertyInteger('OutdoorWeightPercent') / 100.0;
            $effectiveAbs = round(($avgAbs * (1 - $w)) + ($outAbs * $w), 2);
        }

        $stage = $this->DetermineStage($effectiveAbs);

        if (GetValue($this->GetIDForIdent('HumidityJumpActive'))) {
            $stage = min(8, $stage + 3);
        }

        $percent = $this->StageToPercent($stage);

        SetValue($this->GetIDForIdent('VentilationStage'), $stage);
        SetValue($this->GetIDForIdent('VentilationSetpointPercent'), $percent);
        SetValue($this->GetIDForIdent('LastCalcTime'), date('d.m.Y H:i:s'));

        $targetID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($targetID > 0 && IPS_VariableExists($targetID)) {
            @RequestAction($targetID, $percent);
        }

        IPS_LogMessage('CRVHC', 'Build 11: Regelung -> Stufe ' . $stage . ' (' . $percent . '%)');
    }

    /* ===== Hilfsfunktionen ===== */

    private function DetermineStage(float $abs): int
    {
        if ($abs < 6.5) return 1;
        if ($abs < 7.0) return 2;
        if ($abs < 7.5) return 3;
        if ($abs < 8.0) return 4;
        if ($abs < 8.5) return 5;
        if ($abs < 9.0) return 6;
        if ($abs < 9.5) return 7;
        return 8;
    }

    private function StageToPercent(int $stage): int
    {
        return $stage * 12;
    }

    private function UpdateMinMax(float $value)
    {
        $min = $this->GetIDForIdent('AbsHumidityIndoorMin24h');
        $max = $this->GetIDForIdent('AbsHumidityIndoorMax24h');

        if (GetValue($min) == 0 || $value < GetValue($min)) SetValue($min, $value);
        if ($value > GetValue($max)) SetValue($max, $value);
    }

    private function CalcAbsoluteHumidity(float $tempC, float $relHum): float
    {
        $sat = 6.112 * exp((17.62 * $tempC) / (243.12 + $tempC));
        return (216.7 * ($sat * $relHum)) / (273.15 + $tempC);
    }
}
