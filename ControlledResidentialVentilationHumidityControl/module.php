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

        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        $this->RegisterVariableFloat(
            'AbsHumidityIndoor',
            'Absolute Feuchte innen (Ø)',
            '',
            10
        );

        $this->RegisterVariableInteger(
            'VentilationPercent',
            'Lüftungsstellwert (%)',
            '~Intensity.100',
            20
        );

        $this->RegisterTimer(
            'ControlTimer',
            0,
            'IPS_RequestAction($_IPS["TARGET"], "Run", 0);'
        );
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetTimerInterval('ControlTimer', 300000);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'Run') {
            $this->Run();
        }
    }

    public function Run()
    {
        IPS_LogMessage(
            'CRVHC',
            'Regelung ausgeführt (Version 3.2 / Build 19)'
        );
    }
}
