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

        $this->RegisterPropertyInteger('OutdoorHumidity',0);
        $this->RegisterPropertyInteger('OutdoorTemp',0);

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

        if (!IPS_VariableProfileExists('CRV.Status')) {
            IPS_CreateVariableProfile('CRV.Status', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('CRV.Status',0,'Aus','',0xAAAAAA);
            IPS_SetVariableProfileAssociation('CRV.Status',1,'Normal','',0x00FF00);
            IPS_SetVariableProfileAssociation('CRV.Status',2,'Feuchtesprung','',0xFFFF00);
            IPS_SetVariableProfileAssociation('CRV.Status',3,'Nacht','',0x0000FF);
            IPS_SetVariableProfileAssociation('CRV.Status',4,'Fehler','',0xFF0000);
        }

        $this->RegisterVariableFloat('AbsAvg','Absolute Feuchte Ø','CRV.AbsHumidity',10);
        $this->RegisterVariableFloat('AbsMax','Absolute Feuchte Max','CRV.AbsHumidity',11);
        $this->RegisterVariableFloat('AbsMin','Absolute Feuchte Min','CRV.AbsHumidity',12);

        $this->RegisterVariableFloat('AbsOutdoor','Absolute Feuchte außen','CRV.AbsHumidity',13);

        $this->RegisterVariableInteger('Stage','Lüftungsstufe','',20);
        $this->RegisterVariableInteger('Setpoint','Stellwert %','~Intensity.100',21);

        $this->RegisterVariableInteger('Status','Status','CRV.Status',30);
        $this->RegisterVariableString('JumpUntil','Feuchtesprung bis','',31);

        $this->RegisterTimer('ControlTimer',300,'IPS_RequestAction($_IPS["TARGET"],"TimerRun",0);');
    }

    public function RequestAction($Ident,$Value)
    {
        if ($Ident==='ManualRun'||$Ident==='TimerRun') $this->Run();
    }

    private function Run()
    {
        if ($this->IsNightBlocked()) {
            SetValue($this->GetIDForIdent('Status'),3);
            return;
        }

        $abs=[];
        for ($i=1;$i<=$this->ReadPropertyInteger('IndoorSensorCount');$i++) {
            $h=$this->ReadPropertyInteger("IndoorHumidity$i");
            $t=$this->ReadPropertyInteger("IndoorTemp$i");
            if ($h>0 && $t>0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $rh=GetValue($h);
                if ($rh>100) $rh=$rh/2.55;
                $abs[]=$this->CalcAbsHumidity(GetValue($t),$rh);
            }
        }
        if (!$abs) return;

        $avg=array_sum($abs)/count($abs);
        $max=max($abs);
        $min=min($abs);

        SetValue($this->GetIDForIdent('AbsAvg'),$avg);
        SetValue($this->GetIDForIdent('AbsMax'),$max);
        SetValue($this->GetIDForIdent('AbsMin'),$min);

        // Außenvergleich
        $outH=$this->ReadPropertyInteger('OutdoorHumidity');
        $outT=$this->ReadPropertyInteger('OutdoorTemp');
        $absOut=null;
        if ($outH>0 && $outT>0 && IPS_VariableExists($outH) && IPS_VariableExists($outT)) {
            $rh=GetValue($outH); if ($rh>100) $rh=$rh/2.55;
            $absOut=$this->CalcAbsHumidity(GetValue($outT),$rh);
            SetValue($this->GetIDForIdent('AbsOutdoor'),$absOut);
        }

        if ($absOut!==null && $absOut >= $avg) {
            SetValue($this->GetIDForIdent('Status'),1);
            return;
        }

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

        SetValue($this->GetIDForIdent('Status'),1);
    }

    private function IsNightBlocked()
    {
        if (!$this->ReadPropertyBoolean('NightEnable')) return false;
        $now=(int)date('H')*60+(int)date('i');
        $s=$this->ReadPropertyInteger('NightStartHour')*60+$this->ReadPropertyInteger('NightStartMinute');
        $e=$this->ReadPropertyInteger('NightEndHour')*60+$this->ReadPropertyInteger('NightEndMinute');
        return ($s<$e)?($now>=$s&&$now<$e):($now>=$s||$now<$e);
    }

    private function CalcAbsHumidity($T,$RH)
    {
        $es=6.112*exp((17.62*$T)/(243.12+$T));
        return 216.7*($RH/100*$es)/($T+273.15);
    }
}
