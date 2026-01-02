<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // =========================
        // Eigenschaften
        // =========================
        $this->RegisterPropertyInteger('IndoorSensorCount', 1);

        for ($i = 1; $i <= 10; $i++) {
            $this->RegisterPropertyInteger('IndoorHumidity' . $i, 0);
            $this->RegisterPropertyInteger('IndoorTemp' . $i, 0);
        }

        $this->RegisterPropertyInteger('OutdoorHumidity', 0);
        $this->RegisterPropertyInteger('OutdoorTemp', 0);

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);
        $this->RegisterPropertyInteger('VentilationActualID', 0);

        // =========================
        // Variablen
        // =========================
        $this->RegisterVariableFloat('AbsHumidityIndoor', 'Absolute Feuchte innen (Ø)', 'Humidity.Abs', 10);
        $this->RegisterVariableFloat('AbsHumidityMax24h', 'Absolute Feuchte max. (24h)', 'Humidity.Abs', 20);
        $this->RegisterVariableFloat('AbsHumidityMin24h', 'Absolute Feuchte min. (24h)', 'Humidity.Abs', 30);

        $this->RegisterVariableInteger('VentilationLevel', 'Lüftungsstufe (%)', '~Intensity.100', 40);

        // Lernstatus
        $this->RegisterVariableBoolean('LearningActive', 'Selbstlernen aktiv', '~Switch', 50);

        // Zeitstempel intern (Unix)
        $this->RegisterVariableInteger('LastLearningTS', 'Letzter Lernzeitpunkt (intern)', '', 60);
        $this->RegisterVariableInteger('HumidityJumpUntilTS', 'Feuchtesprung bis (intern)', '', 70);

        // Menschlich lesbare Anzeige
        $this->RegisterVariableString('LastLearningReadable', 'Letzter Lernzeitpunkt', '', 61);
        $this->RegisterVariableString('HumidityJumpUntilReadable', 'Feuchtesprung bis', '', 71);

        // =========================
        // Timer
        // =========================
        $this->RegisterTimer('ControlTimer', 300000, 'CRV_Run($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
    }

    // ==========================================================
    // Hauptregelung
    // ==========================================================
    public function Run()
    {
        $now = time();

        // --------------------------
        // Sensoren erfassen
        // --------------------------
        $count = $this->ReadPropertyInteger('IndoorSensorCount');
        $absValues = [];

        for ($i = 1; $i <= $count; $i++) {
            $hID = $this->ReadPropertyInteger('IndoorHumidity' . $i);
            $tID = $this->ReadPropertyInteger('IndoorTemp' . $i);

            if ($hID > 0 && $tID > 0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $rh = GetValue($hID);
                $temp = GetValue($tID);

                $abs = $this->CalcAbsoluteHumidity($temp, $rh);
                $absValues[] = $abs;
            }
        }

        if (count($absValues) === 0) {
            return;
        }

        $avg = array_sum($absValues) / count($absValues);
        $max = max($absValues);
        $min = min($absValues);

        SetValue($this->GetIDForIdent('AbsHumidityIndoor'), round($avg, 2));
        SetValue($this->GetIDForIdent('AbsHumidityMax24h'), round($max, 2));
        SetValue($this->GetIDForIdent('AbsHumidityMin24h'), round($min, 2));

        // --------------------------
        // Feuchtesprung
        // --------------------------
        $jumpUntil = GetValue($this->GetIDForIdent('HumidityJumpUntilTS'));

        if ($max >= ($avg + 1.0)) {
            $jumpUntil = $now + 3600;
            SetValue($this->GetIDForIdent('HumidityJumpUntilTS'), $jumpUntil);
        }

        // Menschlich lesbar
        SetValue(
            $this->GetIDForIdent('HumidityJumpUntilReadable'),
            $jumpUntil > 0 ? date('d.m.Y H:i:s', $jumpUntil) : '-'
        );

        // --------------------------
        // Lernzeitpunkt
        // --------------------------
        $lastLearn = GetValue($this->GetIDForIdent('LastLearningTS'));

        if ($lastLearn === 0 || ($now - $lastLearn) > 3600) {
            SetValue($this->GetIDForIdent('LastLearningTS'), $now);
            SetValue($this->GetIDForIdent('LearningActive'), true);
        }

        SetValue(
            $this->GetIDForIdent('LastLearningReadable'),
            date('d.m.Y H:i:s', GetValue($this->GetIDForIdent('LastLearningTS')))
        );

        // --------------------------
        // Lüftungsstufe bestimmen
        // --------------------------
        $percent = $this->MapHumidityToVentilation($avg);

        SetValue($this->GetIDForIdent('VentilationLevel'), $percent);

        $setID = $this->ReadPropertyInteger('VentilationSetpointID');
        if ($setID > 0 && IPS_VariableExists($setID)) {
            RequestAction($setID, $percent);
        }
    }

    // ==========================================================
    // Hilfsfunktionen
    // ==========================================================
    private function CalcAbsoluteHumidity(float $temp, float $rh): float
    {
        $sdd = 6.1078 * pow(10, (7.5 * $temp) / (237.3 + $temp));
        $dd = $rh / 100 * $sdd;
        return round(216.7 * ($dd / (273.15 + $temp)), 2);
    }

    private function MapHumidityToVentilation(float $abs): int
    {
        if ($abs < 7.0) return 12;
        if ($abs < 8.0) return 24;
        if ($abs < 9.0) return 36;
        if ($abs < 10.0) return 48;
        if ($abs < 11.0) return 60;
        if ($abs < 12.0) return 72;
        if ($abs < 13.0) return 84;
        return 96;
    }
}

// Wrapper für Timer / Button
function CRV_Run($id)
{
    IPS_RequestAction($id, 'Run', 0);
}
