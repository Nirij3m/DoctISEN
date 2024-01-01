<?php
require_once "utils.php";
require_once "src/dao/DaoUser.php";
require_once "src/dao/DaoSpeciality.php";
require_once "src/dao/DaoMeeting.php";

class cntrlApp {
    public function getAccueil() {
        $alerts = [];

        require PATH_VIEW . "vaccueil.php";
    }
    public function getRendezVous() {

        if(isset($_SESSION["user"])){
            require PATH_VIEW . "vrendezvous.php";
        }
        require PATH_VIEW . "vconnection.php";
    }
    public function getDocPage(){
        $DaoTimeslot = new DaoTime(DBHOST, DBNAME, PORT, USER, PASS);
        $DaoMeeting = new DaoMeeting(DBHOST, DBNAME, PORT, USER, PASS);
        $weekArray = $DaoTimeslot->getFutureWeeks();
        if(isset($_POST["selectedWeek"]) && $_POST["selectedWeek"] != -1){
            $currentWeek = $weekArray[$_POST["selectedWeek"]];
        }
        elseif(isset($_POST["persistWeek"]) && $_POST["persistWeek"] != -1){
            $currentWeek = $weekArray[$_POST["persistWeek"]];
            $_POST["selectedWeek"] = $_POST["persistWeek"];
        }
        else $currentWeek = $weekArray[0];
        $meetings = $DaoMeeting->getMeetings($_SESSION["user"]);

        if(!isset($utils)){
            $utils = new Utils();
        }
        require PATH_VIEW . "vmedecin.php";
    }
    public function createMeeting(){
        if(!isset($_SESSION)){
            session_start();
        }
        $DaoMeeting = new DaoMeeting(DBHOST, DBNAME, PORT, USER, PASS);
        $utils = new Utils();
        $date = $_POST["date"];
        $ts = $_POST["timeStart"];
        $te = $_POST["timeEnd"];
        if(empty($ts) || empty($te)){
            $utils->echoWarning("Vous devez spécifier un horaire");
            $this->getDocPage();
            return;
        }

        $beg = DateTime::createFromFormat('D. d/m/Y H:i', $date. " ".$ts);
        $end = DateTime::createFromFormat('D. d/m/Y H:i', $date. " ".$te);
        if(!$beg || !$end){
            $utils->echoError("Erreur lors de la création du rendez-vous");
        }
        $isSuccess = $DaoMeeting->insertMeeting($beg, $end, $_SESSION["user"]);
        if($isSuccess){
            $utils->echoSuccess("Rendez-vous enregistré avec succès");
        }
        else {
            $utils->echoError("Cet horaire existe déjà");
        }
        $this->getDocPage();

    }
    public function deleteMeeting(){
        $DaoMeeting = new DaoMeeting(DBHOST, DBNAME, PORT, USER, PASS);
        $idDoc = $_POST["idDoc"];
        $tbeg = $_POST["tbeg"];
        $DaoMeeting->deleteMeeting($tbeg, $idDoc);
        $this->getDocPage();
    }

    public function getMedecin(){
        $DaoUser = new DaoUser(DBHOST, DBNAME, PORT, USER, PASS);
        $utils = new Utils();
        $specialite = $_POST["specialite"];
        $nom = $_POST["nom"];

        if( !empty($nom) && !($utils->isSanitize($nom))){
            $utils->echoWarning("Le champ de recherche ne peut contenir ni caractères spéciaux ni accents");
            require PATH_VIEW . "vrendezvous.php";
            return;
        }

        if(!empty($nom)){
            $nom = explode(" ", $_POST["nom"]); //Array that separates the name from the surname. [0] => surname, [1] => name
            if(isset($nom[1])){ //The user inputed a name and a surname
                $users = $DaoUser->getByUserSpe($nom[0], $nom[1], $specialite);
            }
            else{ //Tests each name and surname in cas the user inputed only one thing
                $u1 = $DaoUser->getByUserSpe(" ", $nom[0], $specialite);
                $u2 = $DaoUser->getByUserSpe($nom[0], " ", $specialite);
                if(!empty($u1)){
                    $users = $u1;
                }
                else if (!empty($u2)){
                    $users = $u2;
                }
            }
        }
        else {
            if(empty($nom)) {
            $users = $DaoUser->getByUserSpe(" ", " ", $specialite);
            }
            if(empty($users)) {
                    $utils->echoInfo("Aucun practicien trouvé");
            }
        }

        require PATH_VIEW . "vrendezvous.php";
    }

    public function dispoMedecin() {
        $user = $_SESSION['user'];
        $alerts = [];
        $daoUser = new DaoUser(DBHOST, DBNAME, PORT, USER, PASS);
        $daoMeeting = new DaoMeeting(DBHOST, DBNAME, PORT, USER, PASS);

        $idMedecin = $_POST['idMedecin'];

        $medecin = $daoUser->getFullById($idMedecin);
        $meetings = $daoMeeting->getMeetings($medecin);
        $orderedMeetings = [];
        foreach ($meetings as $meeting) {
            $day = $meeting->get_beginning()->format('d/m/Y');
            if (!isset($orderedMeetings[$day])) $orderedMeetings[$day] = [];
            array_push($orderedMeetings[$day], $meeting);
        }
        $medecin->set_meetings($orderedMeetings);

        require PATH_VIEW . "vhorairesMedecin.php";
    }

    public function userReservation() {
        $alerts = [];
        $user = $_SESSION['user'];
        $idMeeting = $_POST['idMeeting'];

        $daoMeeting = new DaoMeeting(DBHOST, DBNAME, PORT, USER, PASS);
        $utils      = new Utils();

        $meeting = $daoMeeting->getMeetingById($idMeeting);

        if ($meeting->get_user() == null) {
            $daoMeeting->setUserOfMeeting($meeting, $user);
            $utils->echoSuccess("Votre rendez-vous a bien été ajouté");
        }
        else {
            $utils->echoError("Votre rendez-vous n'a pas pu être réservé");
        }

        require PATH_VIEW . "vrendezvous.php";
    }
}