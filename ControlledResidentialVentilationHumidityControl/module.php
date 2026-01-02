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

        $this->RegisterPropertyInteger('VentilationSetpointID',0);
        $this->RegisterPropertyInteger('VentilationActualID',0);

        if (!IPS_VariableProfileExists('CRV.AbsHumidity')) {
            IPS_CreateVariableProfile('CRV.AbsHumidity', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileText('CRV.AbsHumidity','',' g/m³');
            IPS_SetVariableProfileDigits('CRV.AbsHumidity',2);
        }

        $this->RegisterVariableFloat('AbsAvg','Absolute Feuchte Ø','CRV.AbsHumidity',10);
        $this->RegisterVariableFloat('AbsMax24','Max. abs. Feuchte (24h)','CRV.AbsHumidity',11);
        $this->RegisterVariableFloat('AbsMin24','Min. abs. Feuchte (24h)','CRV.AbsHumidity',12);
        $this->RegisterVariableFloat('AbsOutdoor','Absolute Feuchte außen','CRV.AbsHumidity',13);

        $this->RegisterVariableInteger('Stage','Soll-Stufe','',20);
        $this->RegisterVariableInteger('ActualStage','Ist-Stufe','',21);

        $this->RegisterVariableInteger('LastLearnTime','Letzter Lernzeitpunkt','',90);
        $this->RegisterVariableFloat('LastLearnAbs','Lern-Feuchte','CRV.AbsHumidity',91);

        $this->RegisterTimer('ControlTimer',300,'IPS_RequestAction($_IPS["TARGET"],"TimerRun",0);');
    }

    public function RequestAction($Ident,$Value)
    {
        if ($Ident==='ManualRun'||$Ident==='TimerRun') $this->Run();
    }

    private function Run()
    {
        $abs=[];
        for ($i=1;$i<=$this->ReadPropertyInteger('IndoorSensorCount');$i++) {
            $h=$this->ReadPropertyInteger("IndoorHumidity$i");
            $t=$this->ReadPropertyInteger("IndoorTemp$i");
            if ($h>0 && $t>0 && IPS_VariableExists($h) && IPS_VariableExists($t)) {
                $rh=GetValue($h); if ($rh>100) $rh/=2.55;
                $abs[]=$this->CalcAbsHumidity(GetValue($t),$rh);
            }
        }
        if (!$abs) return;

        $avg=array_sum($abs)/count($abs);
        SetValue($this->GetIDForIdent('AbsAvg'),$avg);

        // 24h Max/Min
        $now=time();
        if (!isset($this->data)) $this->data=[];
        $this->data[$now]=$avg;
        foreach ($this->data as $t=>$v) if ($now-$t>86400) unset($this->data[$t]);

        SetValue($this->GetIDForIdent('AbsMax24'),max($this->data));
        SetValue($this->GetIDForIdent('AbsMin24'),min($this->data));

        // Selbstlernen
        $lastTime=GetValue($this->GetIDForIdent('LastLearnTime'));
        if ($lastTime>0 && time()-$lastTime>600) {
            $delta=GetValue($this->GetIDForIdent('LastLearnAbs'))-$avg;
            if ($delta<0.05) $avg+=0.2; // Lüften wirkungslos → konservativer
        }

        SetValue($this->GetIDForIdent('LastLearnTime'),time());
        SetValue($this->GetIDForIdent('LastLearnAbs'),$avg);

        if ($avg<6.8) $stage=1;
        elseif ($avg<7.6) $stage=2;
        elseif ($avg<8.8) $stage=3;
        elseif ($avg<10) $stage=4;
        else $stage=5;

        SetValue($this->GetIDForIdent('Stage'),$stage);
        $percent=self::STAGES[$stage];

        $out=$this->ReadPropertyInteger('VentilationSetpointID');
        if ($out>0 && IPS_VariableExists($out)) {
            @RequestAction($out,$percent);
        }

        // Ist-Stufe prüfen
        $act=$this->ReadPropertyInteger('VentilationActualID');
        if ($act>0 && IPS_VariableExists($act)) {
            $actual=GetValue($act);
            $this->SetValue('ActualStage',$this->PercentToStage($actual));
        }
    }

    private function PercentToStage($p)
    {
        foreach (self::STAGES as $s=>$v) if ($p<=$v) return $s;
        return 8;
    }

    private function CalcAbsHumidity($T,$RH)
    {
        $es=6.112*exp((17.62*$T)/(243.12+$T));
        return 216.7*($RH/100*$es)/($T+273.15);
    }
}
