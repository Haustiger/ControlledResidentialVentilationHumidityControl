<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    /* =========================================================
     * CREATE
     * ========================================================= */
    public function Create()
    {
        parent::Create();

        /* ---------- Eigenschaften ---------- */
        $this->RegisterPropertyInteger("SensorCount", 1); // 1..10
        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger("HumiditySensor" . $i, 0);
            $this->RegisterPropertyInteger("TemperatureSensor" . $i, 0);
        }

        $this->RegisterPropertyBoolean("UseOutsideReference", false);
        $this->RegisterPropertyInteger("OutsideHumidity", 0);
        $this->RegisterPropertyInteger("OutsideTemperature", 0);

        $this->RegisterPropertyInteger("CycleTime", 10); // Minuten (5–30)

        $this->RegisterPropertyFloat("HumidityJumpThreshold", 10.0); // % rF
        $this->RegisterPropertyInteger("HumidityJumpMinutes", 5);

        $this->RegisterPropertyInteger("NightDisableVariable", 0);
        $this->RegisterPropertyInteger("NightFeedbackVariable", 0);

        $this->RegisterPropertyInteger("TargetPercentVariable", 0);   // SYMCON ID (write)
        $this->RegisterPropertyInteger("FeedbackPercentVariable", 0); // SYMCON ID (read)

        /* ---------- Timer ---------- */
        $this->RegisterTimer("UpdateTimer", 0, 'CRVHC_Update($_IPS["TARGET"]);');
        $this->RegisterTimer("FeedbackWatchdog", 0, 'CRVHC_CheckFeedback($_IPS["TARGET"]);');

        /* ---------- Profile ---------- */
        $this->CreateProfiles();

        /* ---------- Status- & Diagnosevariablen ---------- */
        $this->RegisterVariableInteger("VentilationStatus", "Status Lüftung", "CRVHC.Status", 1);
        $this->RegisterVariableInteger("VentilationPercent", "Lüftungsstellwert (%)", "~Intensity.100", 2);
        $this->RegisterVariableInteger("VentilationStage", "Lüftungsstufe", "CRVHC.Stage", 3);

        $this->RegisterVariableBoolean("NightActive", "Nachtabschaltung aktiv", "~Switch", 10);
        $this->RegisterVariableBoolean("HumidityJumpActive", "Feuchtesprung aktiv", "~Alert", 11);
        $this->RegisterVariableString("Diagnosis", "Diagnose", "", 100);
    }

    /* =========================================================
     * APPLY CHANGES
     * ========================================================= */
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        /* ---------- Timer ---------- */
        $cycle = min(30, max(5, $this->ReadPropertyInteger("CycleTime")));
        $this->SetTimerInterval("UpdateTimer", $cycle * 60000);

        $this->SetTimerInterval("FeedbackWatchdog", 300000); // 5 Minuten

        /* ---------- Validierung ---------- */
        $errors = [];
        $warnings = [];

        $sensorCount = min(10, max(1, $this->ReadPropertyInteger("SensorCount")));

        for ($i = 1; $i <= $sensorCount; $i++) {
            $h = $this->ReadPropertyInteger("HumiditySensor" . $i);
            $t = $this->ReadPropertyInteger("TemperatureSensor" . $i);

            if ($h == 0 || $t == 0) {
                $warnings[] = "Sensor $i ist nicht vollständig konfiguriert.";
                continue;
            }

            $this->ValidateVariable($h, VARIABLETYPE_FLOAT, true, false, "Feuchtesensor $i", $errors);
            $this->ValidateVariable($t, VARIABLETYPE_FLOAT, true, false, "Temperatursensor $i", $errors);
        }

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

        $out = $this->ReadPropertyInteger("TargetPercentVariable");
        if ($out == 0) {
            $errors[] = "Keine Stellwert-Ausgabevariable definiert.";
        } else {
            $this->ValidateVariable($out, VARIABLETYPE_INTEGER, true, true, "Stellwert-Ausgabe", $errors);
        }

        $fb = $this->ReadPropertyInteger("FeedbackPercentVariable");
        if ($fb > 0) {
            $this->ValidateVariable($fb, VARIABLETYPE_INTEGER, true, false, "Stellwert-Rückmeldung", $warnings);
        }

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

    /* =========================================================
     * UPDATE (Regelzyklus)
     * ========================================================= */
    public function Update()
    {
        // Platzhalter – eigentliche Regelung (absolute Feuchte,
        // Feuchtesprung, Sommer/Winter, Selbstlernen)
        // wird hier zyklisch aufgerufen
    }

    /* =========================================================
     * FEEDBACK WATCHDOG
     * ========================================================= */
    public function CheckFeedback()
    {
        $fb = $this->ReadPropertyInteger("FeedbackPercentVariable");
        if ($fb == 0 || !IPS_VariableExists($fb)) {
            return;
        }

        $last = IPS_GetVariable($fb)['VariableUpdated'];
        if (time() - $last > 300) {
            SetValue($this->GetIDForIdent("VentilationStatus"), 4); // Fehler
        }
    }

    /* =========================================================
     * PROFILE
     * ========================================================= */
    private function CreateProfiles()
    {
        if (!IPS_VariableProfileExists("CRVHC.Status")) {
            IPS_CreateVariableProfile("CRVHC.Status", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation("CRVHC.Status", 0, "Aus", "", 0xA0A0A0);
            IPS_SetVariableProfileAssociation("CRVHC.Status", 1, "Ein", "", 0x00C000);
            IPS_SetVariableProfileAssociation("CRVHC.Status", 2, "Nacht", "", 0x0080FF);
            IPS_SetVariableProfileAssociation("CRVHC.Status", 3, "Feuchtesprung", "", 0xFFC000);
            IPS_SetVariableProfileAssociation("CRVHC.Status", 4, "Fehler", "", 0xFF0000);
        }

        if (!IPS_VariableProfileExists("CRVHC.Stage")) {
            IPS_CreateVariableProfile("CRVHC.Stage", VARIABLETYPE_INTEGER);
            for ($i = 1; $i <= 8; $i++) {
                IPS_SetVariableProfileAssociation("CRVHC.Stage", $i, "Stufe $i", "", -1);
            }
        }
    }

    /* =========================================================
     * VALIDIERUNG
     * ========================================================= */
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

        $v = IPS_GetVariable($varId);

        if ($v['VariableType'] !== $expectedType) {
            $messages[] = "$name: Falscher Variablentyp.";
        }

        if ($mustWritable && $v['VariableAction'] == 0 && $v['VariableCustomAction'] == 0) {
            $messages[] = "$name: Variable ist nicht schreibbar.";
        }

        if ($mustReadable && !$v['VariableIsReadable']) {
            $messages[] = "$name: Variable ist nicht lesbar.";
        }
    }
}
