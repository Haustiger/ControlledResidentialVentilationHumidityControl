<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    private const STAGES = [1=>12,2=>24,3=>36,4=>48,5=>60,6=>72,7=>84,8=>96];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount', 1);
        for ($i=1;$i<=10;$i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i", 0);
            $this->RegisterPropertyInteger("IndoorTemp$i", 0);
        }
        $this->RegisterPropertyInteger('VentilationSetpointID', 0);

        if (!IPS_VariableProfileExists('CRV.AbsHumidity')) {
            IPS_CreateVariableProfile('CRV.AbsHumidity', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV.AbsHumidity','', ' g/m³');
            IPS_SetVariableProfileDigits('CRV.AbsHumidity',2);
        }

        $this->RegisterVariableFloat('IndoorAbs','Absolute Feuchte innen','CRV.AbsHumidity',10);
        $this->RegisterVariableFloat('IndoorAbsMax','Max. Feuchte innen','CRV.AbsHumidity',11);
        $this->RegisterVariableFloat('IndoorAbsMin','Min. Feuchte innen','CRV.AbsHumidity',12);

        $this->RegisterVariableInteger('Stage','Lüftungsstufe','',20);
        $this->RegisterVariableInteger('Setpoint','Stellwert %','~Intensity.100',21);

        $this->RegisterVariableInteger('LastStage','Letzte Stufe','',90);
        $this->RegisterVariableFloat('LastAbs','Letzte Feuchte','CRV.AbsHumidity',91);
        $this->RegisterVariableInteger('LastSetpoint','Letzter Stellwert','',92);

        $this->RegisterTimer('ControlTimer',300,'IPS_RequestAction($_IPS["TARGET"],"TimerRun",0);');
    }

    public function RequestAction($Ident,$Value)
    {
        if ($Ident==='ManualRun'||$Ident==='TimerRun') $this->Run();
    }

    private function Run()
    {
        $absValues=[];

        for ($i=1;$i<=$this->ReadPropertyInteger('IndoorSensorCount');$i++) {
            $hID=$this->ReadPropertyInteger("IndoorHumidity$i");
            $tID=$this->ReadPropertyInteger("IndoorTemp$i");
            if ($hID>0 && $tID>0 && IPS_VariableExists($hID) && IPS_VariableExists($tID)) {
                $rh=GetValue($hID);
                if ($rh>100) $rh=$rh/2.55; // DPT5
                $t=GetValue($tID);
                $absValues[]=$this->CalcAbsHumidity($t,$rh);
            }
        }

        if (!$absValues) return;

        $avg=array_sum($absValues)/count($absValues);
        $max=max($absValues);

        SetValue($this->GetIDForIdent('IndoorAbs'),$avg);

        $maxID=$this->GetIDForIdent('IndoorAbsMax');
        $minID=$this->GetIDForIdent('IndoorAbsMin');

        if (GetValue($maxID)==0||$avg>GetValue($maxID)) SetValue($maxID,$avg);
        if (GetValue($minID)==0||$avg<GetValue($minID)) SetValue($minID,$avg);

        // Zielstufe
        if ($avg<6.8) $target=1;
        elseif ($avg<7.6) $target=2;
        elseif ($avg<8.8) $target=3;
        elseif ($avg<10.0) $target=4;
        else $target=5;

        $lastStage=GetValue($this->GetIDForIdent('LastStage'));
        if ($target<$lastStage) $target=max($lastStage-1,1);

        SetValue($this->GetIDForIdent('Stage'),$target);
        $percent=self::STAGES[$target];
        SetValue($this->GetIDForIdent('Setpoint'),$percent);

        $out=$this->ReadPropertyInteger('VentilationSetpointID');
        if ($out>0 && IPS_VariableExists($out) && $percent!=GetValueInteger($this->GetIDForIdent('LastSetpoint'))) {
            @RequestAction($out,$percent);
        }

        SetValue($this->GetIDForIdent('LastStage'),$target);
        SetValue($this->GetIDForIdent('LastSetpoint'),$percent);
    }

    private function CalcAbsHumidity($T,$RH)
    {
        $es=6.112*exp((17.62*$T)/(243.12+$T));
        $e=$RH/100*$es;
        return 216.7*$e/($T+273.15);
    }
}
