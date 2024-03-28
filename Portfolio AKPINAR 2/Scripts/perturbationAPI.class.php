<?php

class PerturbationAPI {
    private $unPDO;
    private $IDMessage = "";
    private $dateDebutM = "";
    private $dateFinM = "";
    private $raisonCourte = "";
    private $raisonLongue = "";
    private $arrets = array();
    private $requetePt = "";
    private $requeteCr = "";

    public function __construct($server,$bdd,$user,$password){
        $this->unPDO = null;
        try {
            $this->unPDO = new PDO("mysql:host=".$server.";dbname=".$bdd, $user, $password);
        }
    
        catch (PDOExeption $exp){
            echo "Impossible de se connecter au serveur<br/>";
            echo $exp -> getMessage();
        }
    }    

    public function constructPerturbation(){
        $chaineCol = "";
        $chaineVal = "";
        $this->requetePt = "insert into Perturbation "; // insert perturbation
        
        $chaineCol .= "(IdPt,";
        if ($this->raisonCourte != ""){ 
            $chaineCol .= "raisonCourte,";
        }
        $chaineCol .= "raisonLongue,dateDebMessage,dateFinMessage) ";

        $chaineVal .= '("'.$this->IDMessage.'",';
        if ($this->raisonCourte != ""){
            $chaineVal .= '"'.$this->raisonCourte.'",';
        }
        $chaineVal .= '"'.$this->raisonLongue.'","'.$this->dateDebutM.'","'.$this->dateFinM.'")';

        $this->requetePt .= $chaineCol."values ".$chaineVal.";";
        //var_dump($this->requetePt);
    }    
    public function constructConcerner(){
        if ($this->transporteur == 'RATP') { 
            for($i = 0; $i+1 <= sizeof($this->arrets); $i++){ // arrets = ligne dans ce cas precis
                $chaineProc = "";
                $chaineProc = 'call insertPtByTp("'.$this->arrets[$i].'","'.$this->IDMessage.'");';
                $this->requeteCr .= $chaineProc;
            }
        }else {
            $this->requeteCr = "insert into Concerner values "; // insert perturber
            for($i = 0; $i+1 <= sizeof($this->arrets); $i++){
                $chaineVal = "";
                $chaineVal = '("'.$this->arrets[$i].'","'.$this->IDMessage.'")';
                $this->requeteCr .= $chaineVal;
                if ($i+1 != sizeof($this->arrets)){
                    $this->requeteCr .= ",";
                } else {
                    $this->requeteCr .= ";";
                }
            }
        }
        //var_dump($this->requeteCr);
    }
    public function insertAll(){
        $insert = $this->unPDO->prepare($this->requetePt);
        echo "<br>REQUETE Perturbation: ".$this->requetePt."<br>";
        $insert->execute();
        echo "INSERE Perturbation ";
        $insert = $this->unPDO->prepare($this->requeteCr);
        echo "REQUETE Concerner: ".$this->requeteCr."<br>";
        $insert->execute();
        echo "INSERE Concerner ";
    }
    
    public function setDates($dd,$df){
        $this->dateDebutM = $dd;
        $this->dateFinM = $df;
    }
    public function setMessages($tab){
        foreach($tab as $key => $value){
            if ($key == 'TEXT_ONLY'){
                $this->raisonLongue = $value;
            } elseif ($key == 'SHORT_MESSAGE'){
                $this->raisonCourte = $value;
            }
        }
    }
    public function setTransporteur($T){
        $this->transporteur = $T;
    }
    public function setIDMessage($idm){
        $this->IDMessage = $idm;
    }
    public function setArrets($tab){
        $this->arrets = $tab;
    }
}

?>