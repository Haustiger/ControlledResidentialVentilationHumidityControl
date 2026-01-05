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

        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        // Feuchtesprung
        $this->RegisterPropertyFloat('HumidityJumpThreshold', 10.0);

        /* ================= Variablen ================= */

        $this->RegisterVariableFloat('AbsHumidityIndoorAvg', 'Absolute Feuchte innen Ø (g/m³)', '', 10);
        $this->RegisterVariableFloat('AbsHumidityIndoorMin24h', 'Absolute Feuchte innen MIN 24h (g/m³)', '', 20);
        $this->RegisterVariableFloat('AbsHumidityIndoorMax24h', 'Absolute Feuchte innen MAX 24h (g/m³)', '', 30);
        $this->RegisterVariableFloat('AbsHumidityOutdoor', 'Absolute Feuchte außen (g/m³)', '', 40);

        $this->RegisterVariableInteger('VentilationStage', 'Lüftungsstufe', '', 50);
        $this->RegisterVariableFloat('VentilationSetpointPercent', 'Lüftungs-Stellwert (%)', '', 60);

        $this->RegisterVariableString('LastCalcTime', 'Letzte Regelung', '', 70);

        /* -------- Feuchtesprung Debug -------- */

        $this->RegisterVariableBoolean('HumidityJumpActive', 'Feuchtesprung aktiv', '', 80);
        $this->RegisterVariableString('HumidityJumpDetectedAt', 'Letzter Feuchtesprung', '', 81);
        $this->RegisterVariableString('HumidityJumpUntil', 'Feuchtesprung aktiv bis', '', 82);
        $this->RegisterVariableFloat('HumidityJumpDelta', 'Feuchtesprung Δ rF (%)', '', 83);
        $this->RegisterVariableFloat('HumidityJumpThresholdUsed', 'Feuchtesprung Schwellwert (%)', '', 84);
        $this->RegisterVariableInteger('HumidityJumpTargetStage', 'Feuchtesprung Zielstufe', '', 85);

        $this->RegisterVariableFloat('LastAvgRelHumidity', 'Ø rel. Feuchte vor 5 Min (%)', '', 90);

        /* -------- Außenbewertung Debug (neu Build 8) -------- */

        $this->RegisterVariableString('ClimateMode', 'Betriebsmodus', '', 100);
        $this->RegisterVariableFloat('OutdoorWeight', 'Außenbewertung Faktor', '', 101);
        $this->RegisterVariableBoolean('OutdoorAllowsVentilation', 'Außenklima erlaubt Lüften', '', 102);

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
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $absIndoor = [];
        $relIndoor = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $rh = floatval(GetValue($hID));
                $temp = floatval(GetValue($tID));
                if ($rh > 1) $rh /= 100.0;

                $relIndoor[] = $rh * 100;
                $absIndoor[] = $this->CalcAbsoluteHumidity($temp, $rh);
            }
        }

        if (count($absIndoor) === 0) return;

        $avgAbs = round(array_sum($absIndoor) / count($absIndoor), 2);
        $avgRel = round(array_sum($relIndoor) / count($relIndoor), 2);

        SetValue($this->GetIDForIdent('AbsHumidityIndoorAvg'), $avgAbs);
        $this->UpdateMinMax24h($avgAbs);

        /* ===== Außenklima ===== */

        $outH = $this->ReadPropertyInteger('OutdoorHumidity');
        $outT = $this->ReadPropertyInteger('OutdoorTemperature');

        $absOut = null;
        $outTemp = null;

        if ($outH > 0 && $outT > 0 && IPS_VariableExists($outH) && IPS_VariableExists($outT)) {
            $rh = floatval(GetValue($outH));
            $outTemp = floatval(GetValue($outT));
            if ($rh > 1) $rh /= 100.0;
            $absOut = round($this->CalcAbsoluteHumidity($outTemp, $rh), 2);
            SetValue($this->GetIDForIdent('AbsHumidityOutdoor'), $absOut);
        }

        /* ===== Sommer / Winter Bewertung ===== */

        $mode = 'Übergang';
        $weight = 1.0;

        if ($outTemp !== null) {
            if ($outTemp <= 12) {
                $mode = 'Winter';
                $weight = 1.0;
            } elseif ($outTemp >= 18) {
                $mode = 'Sommer';
                $weight = 1.0;
            } else {
                $mode = 'Übergang';
                $weight = (18 - $outTemp) / 6;
            }
        }

        $allowVent = true;
        if ($absOut !== null) {
            if ($mode === 'Winter' && $absOut >= $avgAbs) $allowVent = false;
            if ($mode === 'Sommer' && $absOut > $avgAbs) $allowVent = false;
        }

        SetValue($this->GetIDForIdent('ClimateMode'), $mode);
        SetValue($this->GetIDForIdent('OutdoorWeight'), round($weight, 2));
        SetValue($this->GetIDForIdent('OutdoorAllowsVentilation'), $allowVent);

        /* ===== Feuchtesprung (unverändert Build 7) ===== */

        $lastRel = GetValue($this->GetIDForIdent('LastAvgRelHumidity'));
        $delta = round($avgRel - $lastRel, 2);
        $threshold = $this->ReadPropertyFloat('HumidityJumpThreshold');
        $now = time();

        if ($delta >= $threshold) {
            SetValue($this->GetIDForIdent('HumidityJumpActive'), true);
            SetValue($this->GetIDForIdent('HumidityJumpDetectedAt'), date('d.m.Y H:i:s', $now));
            SetValue($this->GetIDForIdent('HumidityJumpUntil'), date('d.m.Y H:i:s', $now + 900));
            $base = $this->DetermineStage($avgAbs);
            SetValue($this->GetIDForIdent('HumidityJumpTargetStage'), min(8, $base + 3));
        }

        if (GetValue($this->GetIDForIdent('HumidityJumpActive')) &&
            $now > strtotime(GetValue($this->GetIDForIdent('HumidityJumpUntil')))
        ) {
            SetValue($this->GetIDForIdent('HumidityJumpActive'), false);
        }

        SetValue($this->GetIDForIdent('LastAvgRelHumidity'), $avgRel);

        /* ===== Lüftungsstufe ===== */

        $stage = $this->DetermineStage($avgAbs);
        $current = GetValue($this->GetIDForIdent('VentilationStage'));

        if (GetValue($this->GetIDForIdent('HumidityJumpActive'))) {
            $target = GetValue($this->GetIDForIdent('HumidityJumpTargetStage'));
            if ($current < $target) $stage = $current + 1;
        }

        if (!$allowVent) {
            $stage = max(1, $stage - 1);
        }

        $percent = $this->StageToPercent($stage);

        SetValue($this->GetIDForIdent('VentilationStage'), $stage);
        SetValue($this->GetIDForIdent('VentilationSetpointPercent'), $percent);
        SetValue($this->GetIDForIdent('LastCalcTime'), date('d.m.Y H:i:s'));

        $targetID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($targetID > 0 && IPS_VariableExists($targetID)) {
            @RequestAction($targetID, $percent);
        }

        IPS_LogMessage('CRVHC', 'Build 8: Regelung -> Stufe ' . $stage . ' (' . $percent . '%)');
    }

    /* =================================================== */

    private function DetermineStage(float $absIndoor): int
    {
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

        if (GetValue($minID) == 0 || $value < GetValue($minID)) SetValue($minID, $value);
        if ($value > GetValue($maxID)) SetValue($maxID, $value);
    }

    private function CalcAbsoluteHumidity(float $tempC, float $relHum): float
    {
        $sat = 6.112 * exp((17.62 * $tempC) / (243.12 + $tempC));
        $vap = $sat * $relHum;
        return (216.7 * $vap) / (273.15 + $tempC);
    }
}
