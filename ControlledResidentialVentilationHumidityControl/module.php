<?php

class ControlledResidentialVentilationHumidityControl extends IPSModule
{
    private const STAGES = [1=>12,2=>24,3=>36,4=>48,5=>60,6=>72,7=>84,8=>96];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('IndoorSensorCount',1);
        for ($i=1;$i<=10;$i++) {
            $this->RegisterPropertyInteger("IndoorHumidity$i",0);
            $this->RegisterPropertyInteger("IndoorTemp$i",0);
        }

        $this->RegisterPropertyBoolean('NightEnable',false);
        $this->RegisterPropertyInteger('NightStartHour',22);
        $this->RegisterPropertyInteger('NightStartMinute',0);
        $this->RegisterPropertyInteger('NightEndHour',6);
        $this->RegisterPropertyInteger('NightEndMinute',0);

        $this->RegisterPropertyInteger('VentilationSetpointID',0);

        if (!IPS_VariableProfileExists('CRV.AbsHumidity')) {
            IPS_CreateVariableProfile('CRV.AbsHumidity', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV.AbsHumidity','',' g/m³');
            IPS_SetVariableProfileDigits('CRV.AbsHumidity',2);
        }

        $this->RegisterVariableFloat('AbsAvg','Absolute Feuchte Ø','CRV.AbsHumidity',10);
        $this->RegisterVariableFloat('AbsMax','Absolute Feuchte Max','CRV.AbsHumidity',11);
        $this->RegisterVariableFloat('AbsMin','Absolute Feuchte Min','CRV.AbsHumidity',12);

        $this->RegisterVariableInteger('Stage','Lüftungsstufe','',20);
        $this->RegisterVariableInteger('Setpoint','Stellwert %','~Intensity.100',21);

        $this->RegisterTimer('ControlTimer',300,'IPS_RequestAction($_IPS["TARGET"],"TimerRun",0);');
    }

    public function RequestAction($Ident,$Value)
    {
        if ($Ident==='ManualRun' || $Ident==='TimerRun') {
            $this->Run();
        }
    }

    private function Run()
    {
        if ($this->IsNightBlocked()) return;

        $abs=[];

        for ($i=1;$i<=$this->ReadPropertyInteger('IndoorSensorCount');$i++) {
            $h=$this->ReadPropertyInteger("IndoorHumidity$i");
            $t=$this->ReadPropertyInteger("IndoorTemp$i");
            if ($h>0 && $t>0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $rh=GetValue($h);
                if ($rh>100) $rh=$rh/2.55;
                $temp=GetValue($t);
                $abs[]=$this->CalcAbsHumidity($temp,$rh);
            }
        }

        if (!$abs) return;

        $avg=array_sum($abs)/count($abs);
        $max=max($abs);
        $min=min($abs);

        SetValue($this->GetIDForIdent('AbsAvg'),$avg);
        SetValue($this->GetIDForIdent('AbsMax'),$max);
        SetValue($this->GetIDForIdent('AbsMin'),$min);

        if ($avg<6.8) $stage=1;
        elseif ($avg<7.6) $stage=2;
        elseif ($avg<8.8) $stage=3;
        elseif ($avg<10) $stage=4;
        else $stage=5;

        SetValue($this->GetIDForIdent('Stage'),$stage);
        $percent=self::STAGES[$stage];
        SetValue($this->GetIDForIdent('Setpoint'),$percent);

        $out=$this->ReadPropertyInteger('VentilationSetpointID');
        if ($out>0 && IPS_VariableExists($out)) {
            @RequestAction($out,$percent);
        }
    }

    private function IsNightBlocked()
    {
        if (!$this->ReadPropertyBoolean('NightEnable')) return false;

        $now=(int)date('H')*60+(int)date('i');
        $start=$this->ReadPropertyInteger('NightStartHour')*60+$this->ReadPropertyInteger('NightStartMinute');
        $end=$this->ReadPropertyInteger('NightEndHour')*60+$this->ReadPropertyInteger('NightEndMinute');

        if ($start<$end) return ($now>=$start && $now<$end);
        return ($now>=$start || $now<$end);
    }

    private function CalcAbsHumidity($T,$RH)
    {
        $es=6.112*exp((17.62*$T)/(243.12+$T));
        $e=$RH/100*$es;
        return 216.7*$e/($T+273.15);
    }
}
