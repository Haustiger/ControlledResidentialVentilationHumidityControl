<?php

class CRVHC extends IPSModule
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
        $this->RegisterPropertyBoolean('OutdoorEvaluationEnabled', true);
        $this->RegisterPropertyInteger('OutdoorWeightPercent', 50);

        $this->RegisterVariableFloat('AbsHumidityIndoorAvg', 'Absolute Feuchte innen Ø (g/m³)', '', 10);
        $this->RegisterVariableFloat('AbsHumidityIndoorMin24h', 'Absolute Feuchte innen MIN ab Start (g/m³)', '', 20);
        $this->RegisterVariableFloat('AbsHumidityIndoorMax24h', 'Absolute Feuchte innen MAX ab Start (g/m³)', '', 30);
        $this->RegisterVariableFloat('AbsHumidityOutdoor', 'Absolute Feuchte außen (g/m³)', '', 40);

        $this->RegisterVariableInteger('VentilationStage', 'Lüftungsstufe', '', 50);
        $this->RegisterVariableFloat('VentilationSetpointPercent', 'Lüftungs-Stellwert (%)', '', 60);
        $this->RegisterVariableString('LastCalcTime', 'Letzte Regelung', '', 70);

        $this->RegisterVariableBoolean('OutdoorEvaluationActive', 'Außenbewertung aktiv', '', 75);
        $this->RegisterVariableFloat('OutdoorWeightUsed', 'Außen Gewichtung (%)', '', 76);

        $this->RegisterVariableBoolean('HumidityJumpActive', 'Feuchtesprung aktiv', '', 80);
        $this->RegisterVariableString('HumidityJumpDetectedAt', 'Letzter Feuchtesprung', '', 81);
        $this->RegisterVariableString('HumidityJumpUntil', 'Feuchtesprung aktiv bis', '', 82);
        $this->RegisterVariableFloat('HumidityJumpDelta', 'Feuchtesprung Δ rF (%)', '', 83);
        $this->RegisterVariableFloat('HumidityJumpThresholdUsed', 'Feuchtesprung Schwellwert (%)', '', 84);
        $this->RegisterVariableFloat('LastAvgRelHumidity', 'Ø rel. Feuchte vor 5 Min (%)', '', 90);

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

    /* ===== REST DES CODES UNVERÄNDERT AUS BUILD 11 ===== */
}
