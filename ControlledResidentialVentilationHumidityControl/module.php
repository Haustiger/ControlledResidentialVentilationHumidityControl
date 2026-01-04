<?php

class CRVHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Konfiguration
        $this->RegisterPropertyInteger("IndoorSensorCount", 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemperature$i", 0);
        }

        $this->RegisterPropertyInteger("OutdoorHumidity", 0);
        $this->RegisterPropertyInteger("OutdoorTemperature", 0);
        $this->RegisterPropertyInteger("VentilationSetpointID", 0);

        // Variablen
        $this->RegisterVariableFloat("AbsHumidityIndoorAvg", "Absolute Feuchte innen Ø (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMin", "Absolute Feuchte innen Min (ab Start)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMax", "Absolute Feuchte innen Max (ab Start)");
        $this->RegisterVariableFloat("AbsHumidityOutdoor", "Absolute Feuchte außen (g/m³)");

        $this->RegisterVariableInteger("VentilationStage", "Lüftungsstufe");
        $this->RegisterVariableInteger("VentilationPercent", "Lüftungsleistung (%)", "~Intensity.100");

        $this->RegisterVariableString("LastControlRun", "Letzte Regelung");

        // Aktion
        $this->EnableAction("ManualRun");

        // Timer (5 Minuten)
        $this->RegisterTimer(
            "ControlTimer",
            300000,
            'IPS_RequestAction($_IPS["TARGET"], "ManualRun", 1);'
        );
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === "ManualRun") {
            $this->Run();
        }
    }

    public function Run()
    {
        $values = [];
        $count = $this->ReadPropertyInteger("IndoorSensorCount");

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger("IndoorHumidity$i");
            $tID = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($hID > 0 && $tID > 0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $values[] = $this->CalcAbsHumidity(GetValue($tID), GetValue($hID));
            }
        }

        if (count($values) == 0) {
            IPS_LogMessage("CRV Humidity Control", "Keine gültigen Innensensoren");
            return;
        }

        $avg = array_sum($values) / count($values);
        SetValue($this->GetIDForIdent("AbsHumidityIndoorAvg"), round($avg, 2));

        // Min / Max korrekt initialisieren (ab Modulstart)
        $minID = $this->GetIDForIdent("AbsHumidityIndoorMin");
        $maxID = $this->GetIDForIdent("AbsHumidityIndoorMax");

        if (GetValue($minID) == 0) {
            SetValue($minID, $avg);
        }
        if (GetValue($maxID) == 0) {
            SetValue($maxID, $avg);
        }

        SetValue($minID, min(GetValue($minID), $avg));
        SetValue($maxID, max(GetValue($maxID), $avg));

        // Außenfeuchte
        $oh = $this->ReadPropertyInteger("OutdoorHumidity");
        $ot = $this->ReadPropertyInteger("OutdoorTemperature");

        if ($oh > 0 && $ot > 0 && IPS_VariableExists($oh) && IPS_VariableExists($ot)) {
            $absOut = $this->CalcAbsHumidity(GetValue($ot), GetValue($oh));
            SetValue($this->GetIDForIdent("AbsHumidityOutdoor"), round($absOut, 2));
        }

        // Lüftungskennlinie (8 Stufen)
        $stage = 1;
        if ($avg >= 10.0) $stage = 8;
        elseif ($avg >= 9.0) $stage = 7;
        elseif ($avg >= 8.5) $stage = 6;
        elseif ($avg >= 8.0) $stage = 5;
        elseif ($avg >= 7.5) $stage = 4;
        elseif ($avg >= 7.0) $stage = 3;
        elseif ($avg >= 6.5) $stage = 2;

        $percent = $stage * 12;

        SetValue($this->GetIDForIdent("VentilationStage"), $stage);
        SetValue($this->GetIDForIdent("VentilationPercent"), $percent);

        // Stellwert setzen – FIX
        $id = $this->ReadPropertyInteger("VentilationSetpointID");
        if ($id > 0 && IPS_VariableExists($id)) {
            RequestAction($id, $percent);
        }

        SetValue($this->GetIDForIdent("LastControlRun"), date("d.m.Y H:i:s"));

        IPS_LogMessage(
            "CRV Humidity Control",
            "Build 6: Regelung ausgeführt – Stufe $stage ($percent %)"
        );
    }

    private function CalcAbsHumidity(float $temp, float $rh): float
    {
        $sdd = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $dd  = ($rh / 100) * $sdd;
        return 216.7 * ($dd / (273.15 + $temp));
    }
}
