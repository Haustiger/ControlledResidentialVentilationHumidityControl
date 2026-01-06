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

        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemperature', 0);
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        $this->RegisterPropertyFloat('HumidityJumpThreshold', 10.0);
        $this->RegisterPropertyFloat('OutdoorWeightFactor', 0.5);

        $this->RegisterVariableFloat('AbsHumidityIndoorAvg', 'Absolute Feuchte innen Ø (g/m³)', '', 10);
        $this->RegisterVariableFloat('AbsHumidityIndoorMin24h', 'Absolute Feuchte innen MIN 24h (g/m³)', '', 20);
        $this->RegisterVariableFloat('AbsHumidityIndoorMax24h', 'Absolute Feuchte innen MAX 24h (g/m³)', '', 30);
        $this->RegisterVariableFloat('AbsHumidityOutdoor', 'Absolute Feuchte außen (g/m³)', '', 40);

        $this->RegisterVariableBoolean('OutdoorEvaluationActive', 'Außenbewertung aktiv', '', 41);
        $this->RegisterVariableFloat('OutdoorHumidityDifference', 'Feuchte-Differenz innen/außen (g/m³)', '', 42);
        $this->RegisterVariableString('OutdoorEvaluationText', 'Außenbewertung', '', 43);

        /* DEBUG Gewichtung */
        $this->RegisterVariableFloat('OutdoorWeightApplied', 'Außen-Gewichtung angewandt', '', 44);
        $this->RegisterVariableInteger('OutdoorStageCorrection', 'Außen-Stufenkorrektur', '', 45);

        $this->RegisterVariableInteger('VentilationStage', 'Lüftungsstufe', '', 50);
        $this->RegisterVariableFloat('VentilationSetpointPercent', 'Lüftungs-Stellwert (%)', '', 60);
        $this->RegisterVariableString('LastCalcTime', 'Letzte Regelung', '', 70);

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

    public function Run()
    {
        /* ================= Innen ================= */

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
                    $rh /= 100.0;
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
        $this->UpdateMinMax24h($avgAbs);

        /* ================= Außen ================= */

        $outH = $this->ReadPropertyInteger('OutdoorHumidity');
        $outT = $this->ReadPropertyInteger('OutdoorTemperature');

        if ($outH > 0 && $outT > 0 && IPS_VariableExists($outH) && IPS_VariableExists($outT)) {
            $rhOut = floatval(GetValue($outH));
            $tOut  = floatval(GetValue($outT));

            if ($rhOut > 1) {
                $rhOut /= 100.0;
            }

            $absOut = round($this->CalcAbsoluteHumidity($tOut, $rhOut), 2);
            SetValue($this->GetIDForIdent('AbsHumidityOutdoor'), $absOut);

            $diff = round($avgAbs - $absOut, 2);
            SetValue($this->GetIDForIdent('OutdoorHumidityDifference'), $diff);

            if ($diff > 0.5) {
                SetValue($this->GetIDForIdent('OutdoorEvaluationActive'), true);
                SetValue($this->GetIDForIdent('OutdoorEvaluationText'), 'Außenluft günstiger');
            } else {
                SetValue($this->GetIDForIdent('OutdoorEvaluationActive'), false);
                SetValue($this->GetIDForIdent('OutdoorEvaluationText'), 'Keine Außenlüftung empfohlen');
            }
        }

        /* ================= Lüftungsstufe ================= */

        $stage = $this->DetermineStage($avgAbs);

        $weight = $this->ReadPropertyFloat('OutdoorWeightFactor');
        SetValue($this->GetIDForIdent('OutdoorWeightApplied'), $weight);

        $correction = 0;
        if (GetValue($this->GetIDForIdent('OutdoorEvaluationActive'))) {
            $correction = (int)round(-2 * $weight);
        }

        $correction = max(-2, min(0, $correction));
        SetValue($this->GetIDForIdent('OutdoorStageCorrection'), $correction);

        $stage = max(1, min(8, $stage + $correction));
        $percent = $this->StageToPercent($stage);

        SetValue($this->GetIDForIdent('VentilationStage'), $stage);
        SetValue($this->GetIDForIdent('VentilationSetpointPercent'), $percent);
        SetValue($this->GetIDForIdent('LastCalcTime'), date('d.m.Y H:i:s'));

        $targetID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($targetID > 0 && IPS_VariableExists($targetID)) {
            @RequestAction($targetID, $percent);
        }
    }

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
