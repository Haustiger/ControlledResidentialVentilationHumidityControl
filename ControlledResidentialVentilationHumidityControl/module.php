<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    /* ===================== Lüftungsstufen ===================== */
    private array $stageMap = [
        1 => 12, 2 => 24, 3 => 36, 4 => 48,
        5 => 60, 6 => 72, 7 => 84, 8 => 96
    ];

    /* ===================== CREATE ===================== */
    public function Create()
    {
        parent::Create();

        /* ===== Regelparameter ===== */
        $this->RegisterPropertyInteger("CycleTime", 10);
        $this->RegisterPropertyFloat("HumidityJump", 10.0);
        $this->RegisterPropertyInteger("HumidityJumpTime", 10);

        /* ===== Sensoren ===== */
        $this->RegisterPropertyInteger("SensorCount", 1);
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("HumiditySensor" . $i, 0);
            $this->RegisterPropertyInteger("TemperatureSensor" . $i, 0);
        }

        /* ===== Außenreferenz ===== */
        $this->RegisterPropertyBoolean("UseOutsideReference", false);
        $this->RegisterPropertyInteger("OutsideHumidity", 0);
        $this->RegisterPropertyInteger("OutsideTemperature", 0);
        $this->RegisterPropertyFloat("AbsoluteDelta", 1.0);

        /* ===== Nachtabschaltung ===== */
        $this->RegisterPropertyInteger("NightDisableVariable", 0);
        $this->RegisterPropertyString("NightStart", "22:00");
        $this->RegisterPropertyString("NightEnd", "06:00");
        $this->RegisterPropertyInteger("MaxNightOverride", 60);

        /* ===== Ausgänge ===== */
        $this->RegisterPropertyInteger("TargetPercentVariable", 0);
        $this->RegisterPropertyInteger("FeedbackPercentVariable", 0);

        /* ===== Statusvariablen ===== */
        $this->RegisterVariableInteger("OperationState", "Status Lüftung", "~Status");
        $this->RegisterVariableString("OperationText", "Status Text");
        $this->RegisterVariableString("TrafficLight", "Lüftungsampel");
        $this->RegisterVariableBoolean("NightOverrideActive", "Nachtübersteuerung aktiv");

        $this->RegisterVariableFloat("AbsHumidityInside", "Absolute Feuchte innen (g/m³)");
        $this->RegisterVariableFloat("AbsHumidityOutside", "Absolute Feuchte außen (g/m³)");
        $this->RegisterVariableInteger("CurrentStage", "Lüftungsstufe");
        $this->RegisterVariableInteger("CurrentPercent", "Stellwert (%)", "~Intensity.100");

        /* ===== Timer ===== */
        $this->RegisterTimer("UpdateTimer", 0, "CRVHC_Update(\$_IPS['TARGET']);");

        /* ===== Buffer ===== */
        $this->SetBuffer("LastMaxRH", "");
        $this->SetBuffer("LastRHTime", "");
        $this->SetBuffer("NightOverrideUntil", "");
    }

    /* ===================== APPLY CHANGES ===================== */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $cycle = min(30, max(5, $this->ReadPropertyInteger("CycleTime")));
        $this->SetTimerInterval("UpdateTimer", $cycle * 60000);

        $errors = [];
        $warnings = [];

        /* ===== Sensorvalidierung ===== */
        for ($i = 1; $i <= $this->ReadPropertyInteger("SensorCount"); $i++) {
            $h = $this->ReadPropertyInteger("HumiditySensor" . $i);
            $t = $this->ReadPropertyInteger("TemperatureSensor" . $i);

            if ($h == 0 || $t == 0) {
                $warnings[] = "Sensor $i ist nicht vollständig konfiguriert.";
                continue;
            }

            $this->ValidateVariable($h, VARIABLETYPE_FLOAT, true, false, "Feuchte Sensor $i", $errors);
            $this->ValidateVariable($t, VARIABLETYPE_FLOAT, true, false, "Temperatur Sensor $i", $errors);
        }

        /* ===== Außenluft ===== */
        if ($this->ReadPropertyBoolean("UseOutsideReference")) {
            $this->ValidateVariable(
                $this->ReadPropertyInteger("OutsideHumidity"),
                VARIABLETYPE_FLOAT,
                true,
                false,
                "Außenfeuchte",
                $errors
            );
            $this->ValidateVariable(
                $this->ReadPropertyInteger("OutsideTemperature"),
                VARIABLETYPE_FLOAT,
                true,
                false,
                "Außentemperatur",
                $errors
            );
        }

        /* ===== Nachtabschaltung ===== */
        if ($this->ReadPropertyInteger("NightDisableVariable") > 0) {
            $this->ValidateVariable(
                $this->ReadPropertyInteger("NightDisableVariable"),
                VARIABLETYPE_BOOLEAN,
                true,
                false,
                "Nachtabschaltung",
                $errors
            );
        }

        /* ===== Stellwert ===== */
        $out = $this->ReadPropertyInteger("TargetPercentVariable");
        if ($out == 0) {
            $errors[] = "Keine Stellwert-Ausgabevariable definiert.";
        } else {
            $this->ValidateVariable($out, VARIABLETYPE_INTEGER, true, true, "Stellwert-Ausgabe", $errors);
            $this->ValidatePercentProfile($out, "Stellwert-Ausgabe", $warnings);
        }

        /* ===== Status ===== */
        if (!empty($errors)) {
            foreach ($errors as $e) {
                $this->LogMessage($e, KL_ERROR);
            }
            $this->SetStatus(201);
            return;
        }

        if (!empty($warnings)) {
            foreach ($warnings as $w) {
                $this->LogMessage($w, KL_WARNING);
            }
            $this->SetStatus(200);
            return;
        }

        $this->SetStatus(102);
    }

    /* ===================== UPDATE ===================== */
    public function Update()
    {
        $now = time();

        /* ===== Nachtabschaltung ===== */
        $overrideUntil = intval($this->GetBuffer("NightOverrideUntil"));
        $nightOverride = ($overrideUntil > $now);
        $this->SetValue("NightOverrideActive", $nightOverride);

        if ($this->IsNightDisabled() && !$nightOverride) {
            $this->SetState(0, "Nachtabschaltung aktiv", "⚫");
            return;
        }

        /* ===== Sensoren auswerten ===== */
        $maxAbs = 0.0;
        $maxRH = 0.0;

        for ($i = 1; $i <= $this->ReadPropertyInteger("SensorCount"); $i++) {
            $h = $this->ReadPropertyInteger("HumiditySensor" . $i);
            $t = $this->ReadPropertyInteger("TemperatureSensor" . $i);

            if ($h > 0 && $t > 0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $rh = GetValue($h);
                $abs = $this->CalcAbs(GetValue($t), $rh);

                if ($abs > $maxAbs) {
                    $maxAbs = $abs;
                    $maxRH = $rh;
                }
            }
        }

        $this->SetValue("AbsHumidityInside", $maxAbs);

        /* ===== Feuchtesprung ===== */
        $lastRH = floatval($this->GetBuffer("LastMaxRH"));
        $lastTime = intval($this->GetBuffer("LastRHTime"));

        if ($lastRH > 0 &&
            ($maxRH - $lastRH) >= $this->ReadPropertyFloat("HumidityJump") &&
            ($now - $lastTime) <= ($this->ReadPropertyInteger("HumidityJumpTime") * 60)) {

            if ($this->IsNightDisabled()) {
                $this->SetBuffer(
                    "NightOverrideUntil",
                    (string)($now + $this->ReadPropertyInteger("MaxNightOverride") * 60)
                );
                $this->SetState(2, "Feuchtesprung – Nachtübersteuerung", "🔵");
            } else {
                $this->SetState(2, "Feuchtesprung erkannt", "🔴");
            }

            $this->ApplyStage(min(8, $this->GetValue("CurrentStage") + 3));
        }

        $this->SetBuffer("LastMaxRH", (string)$maxRH);
        $this->SetBuffer("LastRHTime", (string)$now);

        /* ===== Außenvergleich ===== */
        if ($this->ReadPropertyBoolean("UseOutsideReference")) {
            $absOut = $this->CalcAbs(
                GetValue($this->ReadPropertyInteger("OutsideTemperature")),
                GetValue($this->ReadPropertyInteger("OutsideHumidity"))
            );
            $this->SetValue("AbsHumidityOutside", $absOut);

            if ($maxAbs <= ($absOut + $this->ReadPropertyFloat("AbsoluteDelta"))) {
                $this->SetState(1, "Außenluft ungünstig", "🟡");
                return;
            }
        }

        /* ===== Normale Regelung ===== */
        $stage = min(8, max(1, intval(($maxAbs - 6) * 1.3)));
        $this->ApplyStage($stage);
        $this->SetState(2, "Lüftung aktiv", "🟢");
    }

    /* ===================== HILFSFUNKTIONEN ===================== */

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

        $now = strtotime(date("H:i"));
        $start = strtotime($this->ReadPropertyString("NightStart"));
        $end = strtotime($this->ReadPropertyString("NightEnd"));

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

    private function ValidateVariable(
        int $varId,
        int $expectedType,
        bool $mustReadable,
        bool $mustWritable,
        string $name,
        array &$messages
    ) {
        if ($varId == 0 || !IPS_VariableExists($varId)) {
            $messages[] = "$name: Variable existiert nicht.";
            return;
        }

        $var = IPS_GetVariable($varId);

        if ($var['VariableType'] !== $expectedType) {
            $messages[] = "$name: Falscher Variablentyp.";
        }

        if ($mustWritable && $var['VariableAction'] == 0) {
            $messages[] = "$name: Variable ist nicht schreibbar.";
        }
    }

    private function ValidatePercentProfile(int $varId, string $name, array &$warnings)
    {
        $var = IPS_GetVariable($varId);
        if ($var['VariableProfile'] !== "~Intensity.100") {
            $warnings[] = "$name: Empfohlenes Profil ~Intensity.100 fehlt.";
        }
    }
}
