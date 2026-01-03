<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger("IndoorSensorCount", 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        $this->RegisterPropertyInteger("OutdoorHumidity", 0);
        $this->RegisterPropertyInteger("OutdoorTemperature", 0);

        $this->RegisterPropertyInteger("VentilationSetpointID", 0);

        $this->RegisterPropertyFloat("HumidityJumpThreshold", 10.0);

        $this->RegisterTimer("ControlTimer", 300000, "CRVHC_Run($_IPS['TARGET']);");

        // Status / Debug
        $this->RegisterVariableFloat("AbsHumidityAvg", "Absolute Feuchte innen Ø (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityMin", "Absolute Feuchte innen Min 24h (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityMax", "Absolute Feuchte innen Max 24h (g/m³)");

        $this->RegisterVariableInteger("CurrentStage", "Aktuelle Lüftungsstufe");
        $this->RegisterVariableInteger("TargetStage", "Ziel-Lüftungsstufe");

        $this->RegisterVariableString("LastHumidityJump", "Letzter Feuchtesprung");
        $this->RegisterVariableString("Debug", "Debug Status");

        $this->EnableAction("Run");
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === "Run") {
            $this->Run();
        }
    }

    public function Run()
    {
        $sensorCount = $this->ReadPropertyInteger("IndoorSensorCount");
        $absValues = [];

        for ($i = 1; $i <= $sensorCount; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 &&
                IPS_VariableExists($hID) && IPS_VariableExists($tID)) {

                $rh = GetValue($hID);
                $temp = GetValue($tID);

                $absValues[] = $this->CalcAbsHumidity($temp, $rh);
            }
        }

        if (count($absValues) == 0) {
            $this->LogMessage("Keine gültigen Sensoren", KL_WARNING);
            return;
        }

        $avg = array_sum($absValues) / count($absValues);
        SetValue($this->GetIDForIdent("AbsHumidityAvg"), round($avg, 2));

        $this->UpdateMinMax($avg);

        $targetStage = $this->DetermineStage($avg);
        $currentStage = GetValue($this->GetIDForIdent("CurrentStage"));

        // Feuchtesprung-Erkennung
        $jumpThreshold = $this->ReadPropertyFloat("HumidityJumpThreshold");

        if ($this->DetectHumidityJump($jumpThreshold)) {
            $targetStage = min(8, $currentStage + 3);
            SetValue($this->GetIDForIdent("LastHumidityJump"), date("d.m.Y H:i:s"));
            $this->SendDebug("Feuchtesprung", "Zielstufe $targetStage", 0);
        }

        // Rampenlogik: max +1 Stufe
        if ($targetStage > $currentStage) {
            $currentStage++;
        } elseif ($targetStage < $currentStage) {
            $currentStage--;
        }

        SetValue($this->GetIDForIdent("CurrentStage"), $currentStage);
        SetValue($this->GetIDForIdent("TargetStage"), $targetStage);

        $percent = $currentStage * 12;
        $outID = $this->ReadPropertyInteger("VentilationSetpointID");

        if ($outID > 0 && IPS_VariableExists($outID)) {
            RequestAction($outID, $percent);
        }

        IPS_LogMessage("CRVHC", "Build 6: Regelung ausgeführt – Stufe $currentStage ($percent %)");
    }

    private function CalcAbsHumidity($temp, $rh)
    {
        $sdd = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $dd = $rh / 100 * $sdd;
        return 216.7 * ($dd / (273.15 + $temp));
    }

    private function DetermineStage($abs)
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

    private function DetectHumidityJump($threshold)
    {
        static $lastAvg = null;

        $current = GetValue($this->GetIDForIdent("AbsHumidityAvg"));
        if ($lastAvg === null) {
            $lastAvg = $current;
            return false;
        }

        $delta = $current - $lastAvg;
        $lastAvg = $current;

        return ($delta >= ($threshold / 10));
    }

    private function UpdateMinMax($value)
    {
        $minID = $this->GetIDForIdent("AbsHumidityMin");
        $maxID = $this->GetIDForIdent("AbsHumidityMax");

        if (GetValue($minID) == 0 || $value < GetValue($minID)) {
            SetValue($minID, round($value, 2));
        }
        if ($value > GetValue($maxID)) {
            SetValue($maxID, round($value, 2));
        }
    }
}
