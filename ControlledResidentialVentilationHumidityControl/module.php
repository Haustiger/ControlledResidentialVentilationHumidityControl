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

        $this->RegisterVariableFloat("AbsHumidityIndoorAvg", "Absolute Feuchte innen Ø (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMin", "Absolute Feuchte innen Min (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityIndoorMax", "Absolute Feuchte innen Max (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityOutdoor", "Absolute Feuchte außen (g/m³)");

        $this->RegisterVariableInteger("VentilationStage", "Lüftungsstufe");
        $this->RegisterVariableInteger("VentilationPercent", "Lüftungsleistung (%)", "~Intensity.100");

        $this->RegisterTimer(
            "ControlTimer",
            300000,
            "IPS_RequestAction($_IPS['TARGET'], 'Run', 0);"
        );

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
        $values = [];
        $count = $this->ReadPropertyInteger("IndoorSensorCount");

        for ($i = 1; $i <= $count; $i++) {
            $h = $this->ReadPropertyInteger("IndoorHumidity$i");
            $t = $this->ReadPropertyInteger("IndoorTemperature$i");

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $values[] = $this->CalcAbsHumidity(GetValue($t), GetValue($h));
            }
        }

        if (count($values) > 0) {
            $avg = array_sum($values) / count($values);
            SetValue($this->GetIDForIdent("AbsHumidityIndoorAvg"), round($avg, 2));

            $minID = $this->GetIDForIdent("AbsHumidityIndoorMin");
            $maxID = $this->GetIDForIdent("AbsHumidityIndoorMax");

            if (GetValue($minID) == 0 || $avg < GetValue($minID)) {
                SetValue($minID, round($avg, 2));
            }
            if ($avg > GetValue($maxID)) {
                SetValue($maxID, round($avg, 2));
            }
        }

        // BUGFIX Build 6: Außenfeuchte
        $oh = $this->ReadPropertyInteger("OutdoorHumidity");
        $ot = $this->ReadPropertyInteger("OutdoorTemperature");

        if ($oh > 0 && $ot > 0 && IPS_VariableExists($oh) && IPS_VariableExists($ot)) {
            $absOut = $this->CalcAbsHumidity(GetValue($ot), GetValue($oh));
            SetValue($this->GetIDForIdent("AbsHumidityOutdoor"), round($absOut, 2));
        }

        IPS_LogMessage("CRVHC", "Build 6: Regelung ausgeführt");
    }

    private function CalcAbsHumidity(float $temp, float $rh): float
    {
        $sdd = 6.112 * exp((17.62 * $temp) / (243.12 + $temp));
        $dd  = $rh / 100 * $sdd;
        return 216.7 * ($dd / (273.15 + $temp));
    }
}
