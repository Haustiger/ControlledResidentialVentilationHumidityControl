<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    private array $stageMap = [
        1 => 12, 2 => 24, 3 => 36, 4 => 48,
        5 => 60, 6 => 72, 7 => 84, 8 => 96
    ];

    public function Create()
    {
        parent::Create();

        // ================= Eigenschaften =================
        $this->RegisterPropertyInteger("CycleTime", 10);            // 5–30 Minuten
        $this->RegisterPropertyFloat("HumidityJump", 10.0);        // % rF
        $this->RegisterPropertyInteger("MaxNightOverride", 60);    // Minuten

        $this->RegisterPropertyInteger("SensorCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("HumiditySensor" . $i, 0);
            $this->RegisterPropertyInteger("TemperatureSensor" . $i, 0);
        }

        $this->RegisterPropertyBoolean("UseOutsideReference", true);
        $this->RegisterPropertyInteger("OutsideHumidity", 0);
        $this->RegisterPropertyInteger("OutsideTemperature", 0);
        $this->RegisterPropertyFloat("AbsoluteDelta", 1.0);

        // Nachtabschaltung
        $this->RegisterPropertyInteger("NightDisableVariable", 0); // DPT 1.001
        $this->RegisterPropertyString("NightStart", "22:00");
        $this->RegisterPropertyString("NightEnd", "06:00");

        // KNX
        $this->RegisterPropertyInteger("TargetPercentVariable", 0);   // DPT 5.001
        $this->RegisterPropertyInteger("FeedbackPercentVariable", 0); // DPT 5.001

        // ================= Statusvariablen =================
        $this->RegisterVariableInteger("OperationState", "Status Lüftung", "~Alert", 1);
        $this->RegisterVariableString("OperationText", "Status Text", "", 2);
        $this->RegisterVariableFloat("AbsHumidityInside", "Absolute Feuchte innen (g/m³)", "", 3);
        $this->RegisterVariableFloat("AbsHumidityOutside", "Absolute Feuchte außen (g/m³)", "", 4);
        $this->RegisterVariableInteger("CurrentStage", "Lüftungsstufe", "", 5);
        $this->RegisterVariableInteger("CurrentPercent", "Stellwert (%)", "~Intensity.100", 6);
        $this->RegisterVariableString("TrafficLight", "Lüftungsampel", "", 7);
        $this->RegisterVariableBoolean("NightOverrideActive", "Nachtübersteuerung aktiv", "", 8);

        // ================= Timer =================
        $this->RegisterTimer("UpdateTimer", 0, "CRVHC_Update(\$_IPS['TARGET']);");

        // ================= Buffer =================
        $this->SetBuffer("LastMaxRH", "");
        $this->SetBuffer("LastRHTime", "");
        $this->SetBuffer("NightOverrideUntil", "");
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $cycle = min(30, max(5, $this->ReadPropertyInteger("CycleTime")));
        $this->SetTimerInterval("UpdateTimer", $cycle * 60000);
    }

    // ================= Hauptlogik =================
    public function Update()
    {
        $now = time();

        // Nachtübersteuerung aktiv?
        $overrideUntil = intval($this->GetBuffer("NightOverrideUntil"));
        $nightOverride = ($overrideUntil > $now);
        $this->SetValue("NightOverrideActive", $nightOverride);

        if ($this->IsNightDisabled() && !$nightOverride) {
            $this->SetState(0, "Nachtabschaltung aktiv", "⚫");
            return;
        }

        $maxAbs = 0.0;
        $maxRH  = 0.0;

        for ($i = 1; $i <= $this->ReadPropertyInteger("SensorCount"); $i++) {
            $h = $this->ReadPropertyInteger("HumiditySensor" . $i);
            $t = $this->ReadPropertyInteger("TemperatureSensor" . $i);

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $rh  = GetValue($h);
                $abs = $this->CalcAbs(GetValue($t), $rh);

                if ($abs > $maxAbs) {
                    $maxAbs = $abs;
                    $maxRH  = $rh;
                }
            }
        }

        $this->SetValue("AbsHumidityInside", $maxAbs);

        // ===== Feuchtesprung mit Zeitfenster =====
        $lastRH     = floatval($this->GetBuffer("LastMaxRH"));
        $lastRHTime = intval($this->GetBuffer("LastRHTime"));

        if ($lastRH > 0 &&
            ($maxRH - $lastRH) >= $this->ReadPropertyFloat("HumidityJump") &&
            ($now - $lastRHTime) <= ($this->ReadPropertyInteger("CycleTime") * 60)) {

            // Nachtübersteuerung aktivieren
            if ($this->IsNightDisabled()) {
                $this->SetBuffer(
                    "NightOverrideUntil",
                    (string)($now + $this->ReadPropertyInteger("MaxNightOverride") * 60)
                );
                $this->SetValue("NightOverrideActive", true);
                $this->SetState(2, "Feuchtesprung – Nachtübersteuerung", "🔵");
            } else {
                $this->SetState(2, "Feuchtesprung erkannt", "🔴");
            }

            $newStage = min(8, $this->GetValue("CurrentStage") + 3);
            $this->ApplyStage($newStage);

            $this->SetBuffer("LastMaxRH", (string)$maxRH);
            $this->SetBuffer("LastRHTime", (string)$now);
            return;
        }

        $this->SetBuffer("LastMaxRH", (string)$maxRH);
        $this->SetBuffer("LastRHTime", (string)$now);

        // ===== Außenreferenz =====
        if ($this->ReadPropertyBoolean("UseOutsideReference")) {
            $oh = $this->ReadPropertyInteger("OutsideHumidity");
            $ot = $this->ReadPropertyInteger("OutsideTemperature");

            if ($oh > 0 && $ot > 0 && IPS_VariableExists($oh) && IPS_VariableExists($ot)) {
                $absOut = $this->CalcAbs(GetValue($ot), GetValue($oh));
                $this->SetValue("AbsHumidityOutside", $absOut);

                if ($maxAbs <= ($absOut + $this->ReadPropertyFloat("AbsoluteDelta"))) {
                    $this->SetState(1, "Außenluft ungünstig", "🟡");
                    return;
                }
            }
        }

        // ===== Normale Regelung =====
        $stage = min(8, max(1, intval(($maxAbs - 6) * 1.3)));
        $this->ApplyStage($stage);
        $this->SetState(2, "Lüftung aktiv", "🟢");
    }

    private function CalcAbs(float $t, float $rh): float
    {
        $s = 6.112 * exp((17.62 * $t) / (243.12 + $t));
        return round((216.7 * ($rh / 100 * $s)) / (273.15 + $t), 2);
    }

    private function ApplyStage(int $stage)
    {
        $percent = $this->stageMap[$stage];
        $this->SetValue("CurrentStage", $stage);
        $this->SetValue("CurrentPercent", $percent);

        $out = $this->ReadPropertyInteger("TargetPercentVariable");
        if ($out > 0 && IPS_VariableExists($out)) {
            SetValue($out, $percent);
        }
    }

    private function IsNightDisabled(): bool
    {
        $var = $this->ReadPropertyInteger("NightDisableVariable");
        if ($var == 0 || !IPS_VariableExists($var) || !GetValue($var)) {
            return false;
        }

        $now   = strtotime(date("H:i"));
        $start = strtotime($this->ReadPropertyString("NightStart"));
        $end   = strtotime($this->ReadPropertyString("NightEnd"));

        return ($start < $end)
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);
    }

    private function SetState(int $state, string $text, string $icon)
    {
        $this->SetValue("OperationState", $state);
        $this->SetValue("OperationText", $text);
        $this->SetValue("TrafficLight", $icon);
    }
}
