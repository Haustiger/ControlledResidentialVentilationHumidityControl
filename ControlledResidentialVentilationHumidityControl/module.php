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

        // === Debug & Status Variablen (Build 5 – unverändert) ===
        $this->RegisterVariableFloat("AbsHumidityIndoorAvg", "Absolute Feuchte innen Ø (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMin24h", "Absolute Feuchte innen Min (24h)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMax24h", "Absolute Feuchte innen Max (24h)");
        $this->RegisterVariableFloat("AbsHumidityOutdoor", "Absolute Feuchte außen (g/m³)");

        $this->RegisterVariableInteger("VentilationStage", "Lüftungsstufe");
        $this->RegisterVariableInteger("VentilationPercent", "Lüftungsleistung (%)", "~Intensity.100");

        $this->RegisterVariableString("LastHumidityJump", "Letzter Feuchtesprung");
        $this->RegisterVariableString("LastControlRun", "Letzte Regelung");

        $this->EnableAction("Run");

        // Sicherheitskonformer Timer
        $this->RegisterTimer(
            "ControlTimer",
            300000,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === "Run") {
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

        if (count($values) > 0) {
            $avg = array_sum($values) / count($values);
            SetValue($this->GetIDForIdent("AbsHumidityIndoorAvg"), round($avg, 2));
        }

        // === FIX Build 6: Außenfeuchte wird zuverlässig berechnet ===
        $oh = $this->ReadPropertyInteger("OutdoorHumidity");
        $ot = $this->ReadPropertyInteger("OutdoorTemperature");

        if (
            $oh > 0 && $ot > 0 &&
            IPS_VariableExists($oh) &&
            IPS_VariableExists($ot)
        ) {
            $absOut = $this->CalcAbsHumidity(GetValue($ot), GetValue($oh));
            SetValue($this->GetIDForIdent("AbsHumidityOutdoor"), round($absOut, 2));
        }

        SetValue(
            $this->GetIDForIdent("LastControlRun"),
            date("d.m.Y H:i:s")
        );

        IPS_LogMessage("CRVHC", "Version 3.2 Build 6: Regelung ausgeführt");
    }

    private function CalcAbsHumidity(float $temp, float $rh): float
    {
        $sdd = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $dd  = $rh / 100 * $sdd;
        return 216.7 * ($dd / (273.15 + $temp));
    }
}
