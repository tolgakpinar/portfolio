<?php
//Configuration de l'affichage des erreurs (PRODUCTION)
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(-1);
print_r ("DEBUT\n");

require_once("bdd_config.php");
require_once("perturbationAPI.class.php");


//SUPPRESSION des anciennes données Perturbation et Concerner

try{
    $unPDO = new PDO("mysql:host=".$server.";dbname=".$bdd, $user, $password);
}catch (PDOExeption $exp){
    print_r("Impossible de se connecter au serveur<br/>");
    echo $exp -> getMessage();
}
$requete = "delete from Concerner;";
$delete = $unPDO->prepare($requete);
$delete->execute();
$requete = "delete from Perturbation;";
$delete = $unPDO->prepare($requete);
$delete->execute();

//APPEL API

try{
    $curl = curl_init();
    var_dump($curl);
    // Check if initialization had gone wrong*    
    if ($curl === false) {
        throw new Exception('failed to initialize');
    } elseif (!isset($curl)){
        throw new Exception('Curl extension not working');
    }

    curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://prim.iledefrance-mobilites.fr/marketplace/general-message?LineRef=all&InfoChannelRef=Perturbation',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 30,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array($apiKey),
    ));

    $response = curl_exec($curl);

    // Check the return value of curl_exec() too,
    if ($response === false) {
        throw new Exception(curl_error($curl), curl_errno($curl));
    }
} catch (Exception $e){
    trigger_error(sprintf(
        'Curl failed with error #%d: %s',
        $e->getCode(), $e->getMessage()),
        E_USER_ERROR);
} finally {
    if (is_resource($curl)) {
        curl_close($curl);
    }
}
//Transformation de la reponse en Array PHP

$parsed_json = json_decode($response);

//Importation de la blacklist

$blacklist = array();
$blacklist = explode("\n", file_get_contents('/Scripts/blacklist.txt'));
var_dump($blacklist);
echo "</br>" ;

//Premier tri

$InfoMessages = $parsed_json->{'Siri'}->{'ServiceDelivery'}->{'GeneralMessageDelivery'}[0]->{'InfoMessage'};

//Formatage des variables

if (preg_match("/StopPointRef/",$response) || preg_match("/LineRef/",$response))
{
    $messageF = "Message";
    $stationF = "StopPointRef";
    $lineF = "LineRef";
    $messageTypeF = "MessageType";
    $messageTextF = "MessageText";

    echo "MAJ</br>";
} else {
    $messageF = "message";
    $stationF = "stopPointRef";
    $lineF = "lineRef";
    $messageTypeF = "messageType";
    $messageTextF = "messageText";
    echo "MIN</br>";
}

//Extraction, tri, et mise en forme des données en fonction du transporteur (RATP renseigne le code ligne, SNCF renseigne les codes stations du tronçon perturbé)

$listeMessages = array();

var_dump($listeMessages);
echo "<br>";


$c = 0;

foreach ($InfoMessages as $InfoMessage){ // pour chaque message (SQL => ligne dans la table PERTURBATION)

    echo "<hr>";
    $listeMessages[$c]= new PerturbationAPI($server,$bdd,$user,$password); // nouvel objet Perturbation

    $IdMessage = $InfoMessage->{'InfoMessageIdentifier'}->{'value'}; // = IdP

    $DateDebutMessage = $InfoMessage->{'RecordedAtTime'};
    $DateDebutMessage = preg_replace("/T/"," ",$DateDebutMessage);
    $DateDebutMessage = preg_replace("/Z/"," ",$DateDebutMessage);
    $DateDebutMessage = preg_replace("/\.\d+/"," ",$DateDebutMessage); // = DateDebAlerte

    $DateFinMessage = $InfoMessage->{'ValidUntilTime'};
    $DateFinMessage = preg_replace("/T/"," ",$DateFinMessage);
    $DateFinMessage = preg_replace("/Z/"," ",$DateFinMessage);
    $DateFinMessage = preg_replace("/\.\d+/"," ",$DateFinMessage); //DateFinAlerte

    $validite = 1;

    echo "ID message: ".$IdMessage;
    echo "</br>";
    echo "Date enregistrement du message: ".$DateDebutMessage;
    echo "</br>";
    echo "Date de fin de validité du message: ".$DateFinMessage;
    echo "</br>";

    if (preg_match("/SNCF_ACCES_CLOUD/i",$IdMessage) == 1){ // SI le message vient de la SNCF
        $transporteur = "SNCF"; // = transporteur
        $i = 0;
        $codes = array();
        foreach ($InfoMessage->{'Content'}->{$stationF} as $station){ // POUR chaque code station
            $codes[$i]= $station->{'value'};
            $codes[$i] = preg_replace("/:/","",$codes[$i]);
            $codes[$i] = preg_replace("/STIFStopPointQ/","",$codes[$i]);
            $i++;
            // effectuer un/des insert/s dans la table perturbationSNCF (IdPt,IdSt) avec une boucle pour chaque station indexée dans la liste
        }
        echo "Liste des stations : </br>";
        var_dump($codes);
        echo "</br>";
        $messages = array();
        foreach ($InfoMessage->{'Content'}->{$messageF} as $message){ // pour chaque message
            $type = $message->{$messageTypeF};
            $text = $message->{$messageTextF}->{'value'};
            $messages[$type]=$text;
            // seulement deux types de messages (TEXT_ONLY et SHORT)
            foreach($blacklist as $banword){ // BLACKLIST
                if (preg_match("/".$banword."/",$text) == 1){

                    echo "BANNED WORD ERROR : ".$banword."</br>";
                    $validite = 0; //SI banword dans le message alors message invalide
                }
            }
        }
        echo "Liste des messages: </br>";
        var_dump($messages);
        echo "</br>";
        echo "</br>";

    } elseif(preg_match("/RATP/i",$IdMessage) == 1){ //SI le message vient de la RATP
        $transporteur = "RATP"; // = transporteur
        $i = 0;
        $codes = array();
        if(! preg_match("/StopPointRef/",serialize($InfoMessage->{'Content'}))){ // SI la liste des lignes existe
            foreach ($InfoMessage->{'Content'}->{$lineF} as $ligne){ // ALORS POUR chaque code ligne
                $codes[$i]= $ligne->{'value'};
                $codes[$i] = preg_replace("/:/","",$codes[$i]);
                $codes[$i] = preg_replace("/STIFLine/","",$codes[$i]); 
                $i++;
            }
        } else {
            $validite = 0; //SINON message invalide
        }
        echo "Liste des lignes : </br>";
        var_dump($codes);
        //echo serialize($InfoMessage->{'Content'});
        echo "</br>";
        $messages = array();
        foreach ($InfoMessage->{'Content'}->{$messageF} as $message){ // pour chaque message
            $type = $message->{$messageTypeF};
            $text = $message->{$messageTextF}->{'value'};
            $messages[$type]=$text;
            foreach($blacklist as $banword){ // BLACKLIST
                if (preg_match("/".$banword."/",$text) == 1){
                    echo "BANNED WORD ERROR :".$banword."</br>";
                    var_dump($banword);
                    $validite = 0; //SI banword dans le message alors message invalide
                }
            }
        }
        echo "Liste des messages: </br>";
        var_dump($messages);
        echo "</br>";
        echo "</br>";
    } else {
        echo "</br> ID INCONNU </br> </br>"; //SI le message vient d'un autre transporteur
        echo "<hr>";
        $validite = 0; //ALORS Message invalide
    }

    //INSERTION dans l'objet

    if ($validite == 1){ 
        //SI pas d'erreur durant le traitement, ALORS creation d'un nouvel objet PerturbationAPI
        //dans la liste d'objets Perturbation et insertion des valeurs de la boucle dans celui-ci
        $listeMessages[$c]->setIDMessage($IdMessage);
        $listeMessages[$c]->setDates($DateDebutMessage,$DateFinMessage);
        $listeMessages[$c]->setTransporteur($transporteur);
        $listeMessages[$c]->setMessages($messages);
        $listeMessages[$c]->setArrets($codes);
        $listeMessages[$c]->constructPerturbation();
        $listeMessages[$c]->constructConcerner();
        echo "INSERTION OBJ OK <br>";
        // INSERTION dans la BDD
        $listeMessages[$c]->insertAll();
        echo "INSERTION BDD OK <br>";
    }
    $c++;
}
echo "</br>";
echo "FIN";
?>
