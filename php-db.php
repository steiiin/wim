<?php

// ######################################################################################
// GLOBALE VARIABLEN

class TypeTag
{

    public const INFO = 'INFO';
    public const EVENT = 'EVENT';
    public const UNIQUETASK = 'UNIQUETASK';
    public const CYCLEDTASK = 'CYCLEDTASK';

}

class RequestType    
{

    public const ALL = 'ALL';

    public const INFO = 'INFO-AREA';
    public const TASK = 'TASK-AREA';
    public const EVENT = 'EVENT-AREA';

    public const ONLYINFO = 'INFO-ONLY';
    public const UNIQUETASK = 'UNIQUETASK-ONLY';
    public const CYCLEDTASK = 'CYCLEDTASK-ONLY';
    public const ONLYEVENTS = 'EVENT-ONLY';

}

class AutoTag
{

    public const ABFALL = 'abfall';
    public const MALTESER_EVENTS = 'maltesercloud-events';

}

// ######################################################################################
// DATENBANK-KLASSEN

class UsersManager {

    private $connection;

    public $isReady = false;

    // ##################################################################################

    public function __construct() {

        if ($this->dbInitTables()) {
            $this->isReady = true; }

    }

    public function __destruct() {

        if ($this->isReady) {
            DatabaseClose($this->connection);
        }

    }

    // ##################################################################################

    public function LoginUser($credUser, $credPass) {
        
        if (!$this->isReady) {return false;}
        $credUser = strtolower($credUser);
        $credPass = trim($credPass);

        $_SESSION['EditedID'] = -1;
        $Config = include('wim-config.php');

        // ADMIN abfangen
        if ($credUser == "" && $credPass == $Config['CD_SUPERPASS']) {

            $_SESSION['UserID'] = -1;
            $_SESSION['LoginUser'] = "admin";
            $_SESSION['FullName'] = "WIM-Verantwortlicher";
            $_SESSION['WimAdmin'] = true;
            $_SESSION['WimFirstAccess'] = false;
            $_SESSION['AllowChanges'] = false;

            return true;

        }

        // Nutzer prüfen & Info abrufen
        $sql = "SELECT `ID`, `LoginUser`, `LoginPass`, `FullName`, `WimAdmin`, `WimFirstAccess` FROM `users` WHERE lower(LoginUser) = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('s', $credUser);
        if ($stm->execute()) {

            $stm->store_result();
            $stm->bind_result($UserID, $LoginUser, $LoginPass, $FullName, $flagWimAdmin, $flagWimFirstAccess);

            if ($stm->num_rows === 1) {

                $stm->fetch();

                if (password_verify($credPass, $LoginPass)) {

                    $_SESSION['UserID'] = $UserID;
                    $_SESSION['LoginUser'] = $LoginUser;
                    $_SESSION['FullName'] = $FullName;
                    $_SESSION['WimAdmin'] = ($flagWimAdmin == 1);
                    $_SESSION['WimFirstAccess'] = ($flagWimFirstAccess == 1);
                    $_SESSION['AllowChanges'] = true;

                    return true;

                }

            }

        }

        return false;
        
    }

    public function SelectCurrentUser() {

        if (!isset($_SESSION['UserID'])) { $this->SelectUser(-1); return false; }

        $this->SelectUser($_SESSION['UserID']);
        return true;

    }
    public function SelectUser($UserID) {

        if (!$this->isReady) {return false;}
        
        $_SESSION['EditedID'] = $UserID;
        return true;

    }

    // ##################################################################################

    public function AddUser($LoginUser, $FullName, $IsWimAdmin) {

        if (!$this->isReady) {return false;}

        // Variablen vorbereiten
        $wimAdmin = $IsWimAdmin ? 1 : 0;

        // SQL vorbereiten
        $sql = "INSERT INTO `users` (`LoginUser`, 
                                     `LoginPass`,
                                     `FullName`, 
                                     `WimAdmin`,
                                     `WimFirstAccess`) VALUES (?,'x',?,?,1)";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('sss', $LoginUser, $FullName, $wimAdmin);

        // Ausführen & Neue ID zurückgeben
        $success = $stm->execute();
        if ($success) {
            return $stm->insert_id;
        } else {
            return false;
        }

    }

    public function DeleteUser() {

        if (!$this->isReady) {return false;}
        if ($_SESSION['EditedID'] === -1) { return false; }

        // USERTAG abfragen
        $USERTAG = false;
        $sql = "SELECT `LoginUser` FROM `users` WHERE `ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('i', $_SESSION['EditedID']);
        if ($stm->execute()) {

            $stm->store_result();
            $stm->bind_result($LoginUser);

            if ($stm->num_rows === 1) {
                $stm->fetch(); $USERTAG = $LoginUser; } } 

        // Tabelle `entries` säubern
        if ($USERTAG !== false) {
            $entriesManager = new EntriesManager();
            if ($entriesManager->isReady) {
                $entriesManager->DeleteEntriesByUsertag($USERTAG); } }

        // Tabelle 'Users' säubern
        $sql = "DELETE FROM `users` WHERE `ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('i', $_SESSION['EditedID']);

        if (!$stm->execute()) { return false; }

        // Tabelle 'entries' säubern TODO


        return true;

    }

    // ##################################################################################

    public function ChangePass($newLoginPass, $firstAccess) {

        if (!$this->isReady) {return false;}
        if ($_SESSION['EditedID'] === -1) { return false; }

        $wimFirstAccess = $firstAccess ? 1 : 0;
        $hashedPass = password_hash(trim($newLoginPass),  PASSWORD_DEFAULT);

        $sql = "UPDATE `users` SET `LoginPass` = ?, `WimFirstAccess` = ? WHERE `ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('sii', $hashedPass, $wimFirstAccess, $_SESSION['EditedID']);

        return $stm->execute();

    }

    public function ChangeUserInfo($LoginUser, $FullName, $IsWimAdmin) {

        if (!$this->isReady) {return false;}
        if ($_SESSION['EditedID'] === -1) { return false; }

        // Variablen vorbereiten für MySQL-Prepare
        $LoginUser = strtolower($LoginUser);
        $FullName = htmlentities($FullName, ENT_QUOTES | ENT_HTML401, 'UTF-8', false);
        $wimAdmin = $IsWimAdmin ? 1 : 0;

        $sql = "UPDATE `users` SET `LoginUser` = ?,
                                   `FullName` = ?, 
                                   `WimAdmin` = ? WHERE `ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('ssii', $LoginUser, $FullName, $wimAdmin, $_SESSION['EditedID']);

        return $stm->execute();

    }

    public function ResetPass() {

        if (!$this->isReady) {return false;}
        if ($_SESSION['EditedID'] === -1) { return false; }

        // Neues Passwort generieren
        $genPassword = $this->dbGenerateRandomPassword();

        if ($this->ChangePass($genPassword, true) === true) {
            return $genPassword;
        } else { return false; }

    }

    // ##################################################################################

    public function GenerateHTML() {

        if (!$this->isReady) {return false;}
        if (!isset($_SESSION['UserID'])) {return false;}

        // UserTabelle abfragen
        $sql = "SELECT `ID`, `LoginUser`, `FullName`, `WimAdmin`, `WimFirstAccess` FROM `users` 
                WHERE `ID` <> ? ORDER BY `WimAdmin` DESC, `LoginUser` ASC";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('i', $_SESSION['UserID']);
        
        // HTML erstellen
        $html = "";

        if ($stm->execute()) {

            $stm->bind_result($UserID, $LoginUser, $FullName, $flagWimAdmin, $flagWimFirstAccess);
            while ($stm->fetch()) {
                
                $infoTxt = "@" . $LoginUser . ($flagWimAdmin ? " (Standort-Administrator)" : "") . ($flagWimFirstAccess ? " - [Passwort noch nicht geändert]" : "");

                $html .= "<li class=\"editable\">";
                $html .= "<button onclick=\"editors.editorUserEdit(&quot;$UserID&quot;, &quot;$LoginUser&quot;, &quot;$FullName&quot;, " . ($flagWimAdmin ? "true": "false") . ");\">&nbsp;</button>";
                $html .= "<div class=\"title\">$FullName</div>";
                $html .= "<div class=\"subtext\">$infoTxt</div>";
                $html .= "<hr>";
                $html .= "</li>";

            }

        }

        // Wenn HTML leer > Keine Nutzer anzeigen
        if ($html === "") {
            return "<li><div class=\"subtext\">Keine anderen Nutzer vorhanden.</div><hr></li>"; }

        return $html;

    }

    // ##################################################################################

    // ##################################################################################

    private function dbInitTables() {

        $conn = DatabaseConnect();
        if ($conn === false) {return false;}

        // Tabelle 'Users' erstellen, wenn nicht vorhanden
        $sql = "CREATE TABLE IF NOT EXISTS users (
                ID int(11) AUTO_INCREMENT PRIMARY KEY,
                LoginUser varchar(50) NOT NULL,
                LoginPass text NOT NULL,
                FullName text NOT NULL,
                WimAdmin tinyint(1) NOT NULL DEFAULT '0',
                WimFirstAccess tinyint(1) NOT NULL DEFAULT '1',
                UNIQUE `UNIQUE` (`LoginUser`))";

        if (!mysqli_query($conn, $sql)) {
            error_log("Die WIM-Benutzertabelle konnte nicht erstellt werden: " . mysqli_error($conn), 0);
            return false;
        }

        $this->connection = $conn;
        return true;

    }

    // ##################################################################################

    private function dbGenerateRandomPassword() {
        $alphabet = 'abcdefghkmnpqrstuvwxyz123456789'; //Ähnliche Zeichen ausschließen (ijlo0)
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 4; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); 
    }

}

class EntriesManager {

    private $connection;

    public $isReady = false;

    // ##################################################################################

    public function __construct() {

        if ($this->dbInitTables()) {
            $this->isReady = true; }

    }

    public function __destruct() {

        if ($this->isReady) {
            DatabaseClose($this->connection);
        }

    }

    // ##################################################################################

    function EditInfo($id, $title, $subtitle, $dateStart, $dateEnd, $autotag = "") {

        if (!$this->isReady) {return false;}
        
        // Variablen für ParamBind (keine Prozeduren erlaubt)
        $typetag = TypeTag::INFO;
        $user = strlen($autotag)>0 ? 'wim-automatik' : $_SESSION['LoginUser'];

        // SQL vorbereiten
        if ($id === -1) {

            // Eintrag erstellen
            $sql = "INSERT INTO `entries` (`TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH`) 
                                   VALUES (current_timestamp(), ?, '$typetag', '$autotag', ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL)";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssss', $user, $title, $subtitle, $dateStart, $dateEnd);

        } else {

            // Vorhandenen Eintrag aktualisieren
            $sql = "UPDATE `entries` SET `TIMETAG`=current_timestamp(), `USERTAG`=?, `TYPETAG`='$typetag', `AUTOTAG`='$autotag', `TITLE`=?, `SUBTITLE`=?, `DTSTART`=?, `DTEND`=?, `DT_HASTIMEVALUE`=NULL, `TASK_VEHICLE`=NULL, `TASK_SHOWINEVENTS`=NULL, `CYCL_WEEKDAY`=NULL, `CYCL_DAYOFMONTH`=NULL WHERE `ID`=?";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssssi', $user, $title, $subtitle, $dateStart, $dateEnd, $id);

        }

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();

    }

    function EditEvent($id, $title, $subtitle, $dateStart, $dateEnd, $hasTime, $autotag = "") {

        if (!$this->isReady) {return false;}
        
        // Variablen für ParamBind (keine Prozeduren erlaubt)
        $typetag = TypeTag::EVENT;
        $user = strlen($autotag)>0 ? 'wim-automatik' : $_SESSION['LoginUser'];

        // SQL vorbereiten
        if ($id === -1) {

            // Eintrag erstellen
            $sql = "INSERT INTO `entries` (`TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH`) 
                    VALUES (current_timestamp(), ?, '$typetag', '$autotag', ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL)";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssssi', $user, $title, $subtitle, $dateStart, $dateEnd, $hasTime);

        } else {

            // Vorhandenen Eintrag aktualisieren
            $sql = "UPDATE `entries` SET `TIMETAG`=current_timestamp(), `USERTAG`=?, `TYPETAG`='$typetag', `AUTOTAG`='$autotag', `TITLE`=?, `SUBTITLE`=?, `DTSTART`=?, `DTEND`=?, `DT_HASTIMEVALUE`=?, `TASK_VEHICLE`=NULL, `TASK_SHOWINEVENTS`=NULL, `CYCL_WEEKDAY`=NULL, `CYCL_DAYOFMONTH`=NULL WHERE `ID`=?";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssssii', $user, $title, $subtitle, $dateStart, $dateEnd, $hasTime, $id);

        }

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();
 

    }

    function EditUniqueTask($id, $title, $subtitle, $vehicle, $dateStart, $dateEnd, $showAsEvent, $autotag = "") {

        if (!$this->isReady) {return false;}
        
        // Variablen für ParamBind (keine Prozeduren erlaubt)
        $typetag = TypeTag::UNIQUETASK;
        $showAsEvent = $showAsEvent ? 1 : 0;
        $user = strlen($autotag)>0 ? 'wim-automatik' : $_SESSION['LoginUser'];

        // SQL vorbereiten
        if ($id === -1) {

            // Eintrag erstellen
            $sql = "INSERT INTO `entries` (`TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH`) 
                    VALUES (current_timestamp(), ?, '$typetag', '$autotag', ?, ?, ?, ?, 1, ?, ?, NULL, NULL)";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('ssssssi', $user, $title, $subtitle, $dateStart, $dateEnd, $vehicle, $showAsEvent);

        } else {

            // Vorhandenen Eintrag aktualisieren
            $sql = "UPDATE `entries` SET `TIMETAG`=current_timestamp(), `USERTAG`=?, `TYPETAG`='$typetag', `AUTOTAG`='$autotag', `TITLE`=?, `SUBTITLE`=?, `DTSTART`=?, `DTEND`=?, `DT_HASTIMEVALUE`=1, `TASK_VEHICLE`=?, `TASK_SHOWINEVENTS`=?, `CYCL_WEEKDAY`=NULL, `CYCL_DAYOFMONTH`=NULL WHERE `ID`=?";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('ssssssii', $user, $title, $subtitle, $dateStart, $dateEnd, $vehicle, $showAsEvent, $id);

        }

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();

    }

    function EditCycledTask($id, $subtitle, $vehicle, $weekday, $dayofmonth, $timeStart, $timeEnd, $autotag = "") {

        if (!$this->isReady) {return false;}
        
        // Variablen für ParamBind (keine Prozeduren erlaubt)
        $typetag = TypeTag::CYCLEDTASK;
        $user = strlen($autotag)>0 ? 'wim-automatik' : $_SESSION['LoginUser'];

        $dateStart = "3000-01-01 $timeStart";
        $dateEnd = "3000-01-01 $timeEnd";

        // SQL vorbereiten
        if ($id === -1) {

            // Eintrag erstellen
            $sql = "INSERT INTO `entries` (`TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH`) 
                                   VALUES (current_timestamp(), ?, '$typetag', '$autotag', ' ', ?, ?, ?, NULL, ?, NULL, ?, ?)";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssssii', $user, $subtitle, $dateStart, $dateEnd, $vehicle, $weekday, $dayofmonth);

        } else {

            // Vorhandenen Eintrag aktualisieren
            $sql = "UPDATE `entries` SET `TIMETAG`=current_timestamp(), `USERTAG`=?, `TYPETAG`='$typetag', `AUTOTAG`='$autotag', `TITLE`=' ', `SUBTITLE`=?, `DTSTART`=?, `DTEND`=?, `DT_HASTIMEVALUE`=NULL, `TASK_VEHICLE`=?, `TASK_SHOWINEVENTS`=NULL, `CYCL_WEEKDAY`=?, `CYCL_DAYOFMONTH`=? WHERE `ID`=?";
            $stm = mysqli_prepare($this->connection, $sql);
            $stm->bind_param('sssssiii', $user, $subtitle, $dateStart, $dateEnd, $vehicle, $weekday, $dayofmonth, $id);

        }

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();

    }

    //##################################################################################

    function DeleteEntry($id) {
        
        if (!$this->isReady) {return false;}

        // SQL vorbereiten
        $sql = "DELETE FROM `entries` WHERE `ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('i', $id);

        // SQL ausführen
        return $stm->execute();

    }

    function DeleteReplacement($id) {

        if (!$this->isReady) {return false;}

        // SQL vorbereiten
        $sql = "DELETE FROM `replacement` WHERE `REPLACE_ID` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('i', $id);

        // SQL ausführen
        return $stm->execute();

    }

    function DeleteEntriesByUsertag($usertag) {
        
        if (!$this->isReady) {return false;}

        // SQL vorbereiten
        $sql = "DELETE FROM `entries` WHERE `USERTAG` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('s', $usertag);

        // SQL ausführen
        return $stm->execute();

    }

    function DeleteEntriesByAutotag($autotag) {
        
        if (!$this->isReady) {return false;}

        // SQL vorbereiten
        $sql = "DELETE FROM `entries` WHERE `AUTOTAG` = ?";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('s', $autotag);

        // SQL ausführen
        return $stm->execute();

    }

    // ##################################################################################

    function ReplaceEntry($replace_id, $replace_date) {

        if (!$this->isReady) {return false;}
        
        // Eintrag erstellen
        $sql = "INSERT INTO `replacement` (`REPLACE_ID`, `REPLACE_DATE`) 
                VALUES (?, ?)";
        $stm = mysqli_prepare($this->connection, $sql);
        $stm->bind_param('is', $replace_id, $replace_date);

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();

    }

    function GetReplaceEntries() {

        if (!$this->isReady) { return array(); }

        $sql = "SELECT `REPLACE_ID`, `REPLACE_DATE` FROM `replacement`";
        $rtn = mysqli_query($this->connection, $sql);
        
        $rows = array();
        while ($row = $rtn->fetch_object()) {
            $stripDate = substr($row->REPLACE_DATE,0,10);
            $rows["$row->REPLACE_ID"]="$stripDate";
            //$rows["$stripDate#$row->REPLACE_ID"]="$stripDate";
        }

        return $rows;
    }

    // ##################################################################################
    
    public function GenerateHTML($requestType, $adminView, $filterId = -1) {

        if (!$this->isReady) { return ""; }

        // Konstanten
        $now = new DateTime();
        $nowDate = $now->format("d.m.y");
        $nowDateC = $now->format("Y-m-d");
        $usertag = isset($_SESSION['LoginUser']) ? $_SESSION['LoginUser'] : "";
        $replaced = $this->GetReplaceEntries();

        // SQL vorbereiten
        if ($filterId >= 0) {
            $sql = "SELECT * FROM `entries` WHERE `ID` = $filterId";
        } else {
            $sql = $this->dbSelect($requestType, $usertag);
        }
        $rtn = mysqli_query($this->connection, $sql);
        if ($rtn === false) {return "";}

        $html = "";
        $isFirst = true;

        // Alle Einträge durchlaufen
        while ($row = $rtn->fetch_object()) {

            if ($filterId < 0) {
                if (array_key_exists($row->ID, $replaced) && $replaced[$row->ID] === $nowDateC) {
                continue; 
                }
            }

            $dateObjStart = ($row->DTSTART != null ? DateTime::createFromFormat("Y-m-d H:i:s", $row->DTSTART) : false);
            $dateObjEnd = ($row->DTEND != null ? DateTime::createFromFormat("Y-m-d H:i:s", $row->DTEND) : false);
            
            $dateCalcStartEndSame = ($dateObjEnd !== false && $dateObjStart !== false) ? ($dateObjStart->format("d.m.y") == $dateObjEnd->format("d.m.y")) : false;
            $dateCalcStartIsToday = $dateObjStart !== false ? ($nowDate == $dateObjStart->format("d.m.y")) : true;
            $dateCalcEndIsToday = $dateObjEnd !== false ? ($nowDate == $dateObjEnd->format("d.m.y")) : $dateCalcStartIsToday;

            switch ($row->TYPETAG) {
                case TypeTag::INFO:

                    $html .= ($adminView ? "<li class=\"editable\">" : "<li>");
                    $html .= ($adminView ? "<button onclick=\"editors.editorInfoEdit($row->ID, &quot;$row->TITLE&quot;, &quot;$row->SUBTITLE&quot;, &quot;$row->DTSTART&quot;, &quot;$row->DTEND&quot;);\">&nbsp;</button>" : "");
                            
                    $html .= "<div class=\"title\">$row->TITLE</div>";
                    $html .= "<div class=\"subtext\">$row->SUBTITLE</div>";

                    if ($adminView && $dateObjStart !== false && $dateObjEnd !== false) {
                        $dateFormat = "d.m.y";
                        $html .= "<div class=\"subtext\">" . 
                                    ($dateObjStart < $now ? "" : "Sichtbar ab: {$dateObjStart->format($dateFormat)}, ") . 
                                    ("Wird nach dem {$dateObjEnd->format($dateFormat)} gelöscht.") . 
                                 "</div>";
                    }

                    $html .= ($adminView && ($row->USERTAG != $_SESSION['LoginUser']) ? "<div class=\"usertag\">von: @$row->USERTAG</div>" : "");
                    $html .= "<hr>";

                    break;

                case TypeTag::EVENT:

                    $hasTime = $row->DT_HASTIMEVALUE == "1" ? 1 : 0;
                    $autoWholeDay = $row->DT_HASTIMEVALUE == "2" ? true : false;

                    $dateFormatStart = $hasTime === 1 ? "d.m.y H:i" : "d.m.y";
                    $dateFormatEnd = $dateCalcStartEndSame && $hasTime === 1 ? "H:i" : $dateFormatStart;

                    $deadline = "";
                    if ($requestType == RequestType::EVENT) {

                        if ($dateCalcStartIsToday && !$adminView) {

                            if ($hasTime === 1) { $dateFormatStart = "H:i"; }
    
                        }

                        $deadline = $dateObjStart->format($dateFormatStart);
                        $deadline .= ($dateObjEnd != null && !$dateCalcStartEndSame ? " - {$dateObjEnd->format($dateFormatEnd)}" : "");

                    }
                    else if ($requestType == RequestType::INFO) {

                        if ($dateCalcEndIsToday && !$adminView) {

                            if ($hasTime === 1) { $dateFormatEnd = "H:i"; }
    
                        }

                        $deadline = (($dateObjEnd != null && !$autoWholeDay) ? " - bis {$dateObjEnd->format($dateFormatEnd)}" : ""); 

                        // kleiner hack: wenn hasTime==2 (nur bei ganztägigen Terminen aus dem Sharepoint), dann schalte diese auf klein, wenn länger als den aktuellen Tag her
                        if (!$dateCalcStartIsToday && $autoWholeDay) {

                            $html .= "<li class=\"inactive\">";
                            $html .= "<div class=\"title\">{$row->TITLE} {$deadline}</div>";
                            $html .= "<hr>";
                            break;

                        }

                    }
                    else {

                        $deadline = $dateObjStart->format($dateFormatStart);
                        $deadline .= ($dateObjEnd != null && !$dateCalcStartEndSame ? " - {$dateObjEnd->format($dateFormatEnd)}" : "");
                        
                    }
                    
                    $title = !$adminView && $requestType == RequestType::INFO ? "{$row->TITLE} {$deadline}" : "$row->TITLE";

                    $html .= ($adminView ? "<li class=\"editable\">" : "<li>");
                    $html .= ($adminView ? "<button onclick=\"editors.editorEventEdit($row->ID, &quot;$row->TITLE&quot;, &quot;$row->SUBTITLE&quot;, &quot;$row->DTSTART&quot;, &quot;$row->DTEND&quot;, $hasTime);\">&nbsp;</button>" : "");
                                
                    $html .= "<div class=\"title\">$title</div>";
                    $html .= "<div class=\"subtext\">$row->SUBTITLE</div>";
    
                    $html .= $adminView || $requestType == RequestType::EVENT ? "<div class=\"subtext\">{$deadline}</div>" : ""; 
                              
                    $html .= ($adminView && ($row->USERTAG != $_SESSION['LoginUser']) ? "<div class=\"usertag\">von: @$row->USERTAG</div>" : "");
                    $html .= "<hr>";
    
                    break;
                        
                case TypeTag::UNIQUETASK:

                    $html .= ($adminView ? "<li class=\"editable\">" : ($requestType == RequestType::EVENT ? "<li>" : "<li class=\"check\">"));
                    
                    if ($row->AUTOTAG === "REPLACE") {

                        $replaceDate = $dateObjStart->format('Y-m-d');
                        $replaceTimeStart = $dateObjStart->format('H:i');
                        $replaceTimeEnd = $dateObjEnd->format('H:i');

                        $orgDate = (DateTime::createFromFormat("Y-m-d", $replaced[$row->TASK_VEHICLE]))->format("d.m.Y");

                        $html .= ($adminView ? "<button onclick=\"editors.editorTemplateBusyEdit($row->ID, $row->TASK_VEHICLE, &quot;$row->TITLE&quot;, &quot;$row->SUBTITLE&quot;, &quot;$replaceDate&quot;, &quot;$replaceTimeStart&quot;, &quot;$replaceTimeEnd&quot;);\">&nbsp;</button>" : "");
                        $html .= "<div class=\"title\">$row->TITLE</div>";
                        $html .= "<div class=\"subtext\">$row->SUBTITLE</div>";

                        $dateFormatStart = "d.m.y H:i";
                        $dateFormatEnd = ($dateObjEnd->format("d.m.y") == $nowDate) ? "H:i" : "d.m.y H:i";
                        $html .= "<div class=\"subtext\">" . 
                                    ($dateObjStart !== false && $dateObjStart > $now && $adminView ? "Sichtbar ab: {$dateObjStart->format($dateFormatStart)}, " : "") . 
                                    ("Zu Erledigen bis: {$dateObjEnd->format($dateFormatEnd)}") . 
                                 "</div>";

                        $html .= "<div class=\"subtext\">Diese Aufgabe ersetzt eine Tagesaufgabe am $orgDate</div>";

                    } else {

                        $html .= ($adminView ? "<button onclick=\"editors.editorUniqueTaskEdit($row->ID, &quot;$row->TITLE&quot;, &quot;$row->SUBTITLE&quot;, &quot;$row->TASK_VEHICLE&quot;, &quot;$row->DTSTART&quot;, &quot;$row->DTEND&quot;, $row->TASK_SHOWINEVENTS, &quot;$row->AUTOTAG&quot;);\">&nbsp;</button>" : "");
                            
                        $title = $row->TASK_VEHICLE !== null && strlen($row->TASK_VEHICLE) > 0 ? "{$row->TASK_VEHICLE}: {$row->TITLE}" : "$row->TITLE";

                        $html .= "<div class=\"title\">$title</div>";
                        $html .= $requestType != RequestType::EVENT || $isFirst ? "<div class=\"subtext\">$row->SUBTITLE</div>" : "";

                        if ($requestType != RequestType::EVENT) {

                            $dateFormatStart = "d.m.y H:i";
                            $dateFormatEnd = ($dateObjEnd->format("d.m.y") == $nowDate) ? "H:i" : "d.m.y H:i";
                            $html .= "<div class=\"subtext\">" . 
                                        ($dateObjStart !== false && $dateObjStart > $now && $adminView ? "Sichtbar ab: {$dateObjStart->format($dateFormatStart)}, " : "") . 
                                        ("Zu Erledigen bis: {$dateObjEnd->format($dateFormatEnd)}") . 
                                    "</div>";

                        } else {
                            $html .= "<div class=\"subtext\">{$dateObjEnd->format('d.m.y H:i')}</div>";
                        }

                    }
                    
                    $html .= ($adminView && ($row->USERTAG != $_SESSION['LoginUser']) ? "<div class=\"usertag\">von: @$row->USERTAG</div>" : "");
                    $html .= "<hr>";

                    break;

                case TypeTag::CYCLEDTASK:

                    $html .= ($adminView ? "<li class=\"editable\">" : "<li class=\"check\">");
                    
                    $timeStart = $dateObjStart !== false ? $dateObjStart->format('H:i') : "00:00";
                    $timeEnd = $dateObjEnd !== false ? $dateObjEnd->format('H:i') : "00:00";

                    $weekday = $row->CYCL_WEEKDAY;
                    $dayof = $row->CYCL_DAYOFMONTH;

                    $html .= ($adminView ? "<button onclick=\"editors.editorCycledTaskEdit($row->ID, &quot;$row->SUBTITLE&quot;, &quot;$row->TASK_VEHICLE&quot;, " . ($weekday==null?"null":$weekday) . ", " . ($dayof==null?"null":$dayof) . ", &quot;$timeStart&quot;, &quot;$timeEnd&quot;);\">&nbsp;</button>" : "");
                            
                    $title = $row->TASK_VEHICLE !== null && strlen($row->TASK_VEHICLE) > 0 ? "{$row->TASK_VEHICLE}: Tagesaufgabe" : "Tagesaufgabe";

                    $html .= "<div class=\"title\">$title</div>";
                    $html .= "<div class=\"subtext\">$row->SUBTITLE</div>";

                    if ($adminView) {
                        $weekdayTxt = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
                        $monthTxt = $dayof;
                        $timeTxt = "(Von $timeStart bis $timeEnd)";

                        if ($monthTxt == -1) {$monthTxt = "Letzten Tag";} else { $monthTxt .= ".";}
                        if ($weekday != null && $weekday >= 0) {$html .= "<div class=\"subtext\">&gt; Jede Woche zum $weekdayTxt[$weekday]. $timeTxt</div>";}
                        if ($weekday != null && $weekday < 0) {$html .= "<div class=\"subtext\">&gt; Täglich. $timeTxt</div>";}
                        if ($dayof != null) {$html .= "<div class=\"subtext\">&gt; Jeden Monat zum $monthTxt $timeTxt</div>";}
                    }
                    
                    $html .= ($adminView && ($row->USERTAG != $_SESSION['LoginUser']) ? "<div class=\"usertag\">von: @$row->USERTAG</div>" : "");
                    $html .= "<hr>";

                    break;

            }

            $html .= "</li>";
            $isFirst = false;
    
        }

        return $html;

    }

    public function GenerateHTMLSingle($requestId) {
        return $this->GenerateHTML(RequestType::ALL, false, $requestId);
    }

    public function GenerateMetaAutoAbfall() {

        if (!$this->isReady) { return ""; }

        // Variablen
        $isOk = false;
        $date = false;
        $timestamp = false;

        // SQL vorbereiten
        $sql = "SELECT `DTSTART`, `TIMETAG`, `AUTOTAG` FROM `entries` WHERE `AUTOTAG` = '".AutoTag::ABFALL."' ORDER BY `DTSTART` DESC LIMIT 1";
        $rtn = mysqli_query($this->connection, $sql);
        if ($rtn !== false) {

            if ($rtn->num_rows === 1) {
                $row = $rtn->fetch_assoc();

                $dateTo = new DateTime($row["DTSTART"]);
                $dateLastFetch = new DateTime($row["TIMETAG"]);
                $dateToString = ($dateTo)->format("d.m.y");
                $dateLastFetchString = ($dateLastFetch)->format("d.m.y H:i");
                $isOk = true;

                if ($dateTo < new DateTime()) {$isOk = false; } // Neuester Eintrag hinter aktuellem Datum
            }

        }

        $html = "";
        $html .= $isOk ? "<li>" : "<li class=\"warn\">";
        $html .= "<div class=\"title\">Abfallkalender</div>";
        $html .= "<div class=\subtext\">" . (($isOk && ($dateTo !== false)) ? "Aktuell bis: $dateToString" : "Kein Kalender hinterlegt bzw. Fehler beim Abruf") . "</div>";
        $html .= ($isOk && ($dateLastFetch !== false)) ? "<div class=\"usertag\">Letzter Abruf: {$dateLastFetchString}</div>" : "";
        $html .= "<hr>";
        $html .= "</li>";

        return $html;

    }

    public function GenerateMetaAutoMaltesercloudEvents() {

        if (!$this->isReady) { return ""; }

        // Variablen
        $isOk = false;
        $date = false;
        $timestamp = false;

        // SQL vorbereiten
        $sql = "SELECT `DTSTART`, `TIMETAG`, `AUTOTAG` FROM `entries` WHERE `AUTOTAG` = '".AutoTag::MALTESER_EVENTS."' ORDER BY `DTSTART` DESC LIMIT 1";
        $rtn = mysqli_query($this->connection, $sql);
        if ($rtn !== false) {

            if ($rtn->num_rows === 1) {
                $row = $rtn->fetch_assoc();

                $dateTo = new DateTime($row["DTSTART"]);
                $dateLastFetch = new DateTime($row["TIMETAG"]);
                $dateToString = ($dateTo)->format("d.m.y");
                $dateLastFetchString = ($dateLastFetch)->format("d.m.y H:i");
                $isOk = true;

                if ($dateTo < new DateTime()) {$isOk = false; } // Neuester Eintrag hinter aktuellem Datum
            }

        }

        $html = "";
        $html .= $isOk ? "<li>" : "<li class=\"warn\">";
        $html .= "<div class=\"title\">MalteserCloud - Kalender</div>";
        $html .= "<div class=\subtext\">" . (($isOk && ($dateTo !== false)) ? "Aktuell bis: $dateToString" : "Kein Zugriff aufs Sharepoint") . "</div>";
        $html .= ($isOk && ($dateLastFetch !== false)) ? "<div class=\"usertag\">Letzter Abruf: {$dateLastFetchString}</div>" : "";
        $html .= "<hr>";
        $html .= "</li>";

        return $html;

    }

    public function GenerateMetaSearchCycledTask($requestDate) {

        if (!$this->isReady) { return ""; }

        // Konstanten
        $now = new DateTime();
        $nowDate = $now->format("d.m.y");
        $usertag = isset($_SESSION['LoginUser']) ? $_SESSION['LoginUser'] : "";

        // SQL vorbereiten
        $sql = $this->dbSelectSearchCycledTask($requestDate);
        $rtn = mysqli_query($this->connection, $sql);
        if ($rtn === false) {return "";}

        $html = "";
        $isFirst = true;

        // Alle Einträge durchlaufen
        while ($row = $rtn->fetch_object()) {

            $dateObjStart = ($row->DTSTART != null ? DateTime::createFromFormat("Y-m-d H:i:s", $row->DTSTART) : false);
            $dateObjEnd = ($row->DTEND != null ? DateTime::createFromFormat("Y-m-d H:i:s", $row->DTEND) : false);
            
            $dateCalcStartEndSame = ($dateObjEnd !== false && $dateObjStart !== false) ? ($dateObjStart->format("d.m.y") == $dateObjEnd->format("d.m.y")) : false;

            switch ($row->TYPETAG) {
                case TypeTag::CYCLEDTASK:

                    $html .= "<li class=\"editable\">";

                    $timeStart = $dateObjStart !== false ? $dateObjStart->format('H:i') : "00:00";
                    $timeEnd = $dateObjEnd !== false ? $dateObjEnd->format('H:i') : "00:00";

                    $weekday = $row->CYCL_WEEKDAY;
                    $dayof = $row->CYCL_DAYOFMONTH;

                    $title = $row->TASK_VEHICLE !== null && strlen($row->TASK_VEHICLE) > 0 ? "{$row->TASK_VEHICLE}: Tagesaufgabe" : "Tagesaufgabe";

                    $html .= "<button type=\"button\" class=\"select\" onclick=\"editors.editorTemplateBusyInvokeSelect(this,$row->ID,&quot;$row->SUBTITLE&quot;,&quot;" . $title . " (verschoben)&quot;,&quot;$timeStart&quot;,&quot;$timeEnd&quot;);\">&nbsp;</button>";
                        
                    $html .= "<div class=\"title\">$title</div>";
                    $html .= "<div class=\"subtext\">$row->SUBTITLE</div>";
                    
                    $html .= "<hr>";

                    break;

            }

            $html .= "</li>";
            $isFirst = false;
    
        }

        if ($isFirst === true) { return "Keine Aufgaben an diesem Tag."; }

        return $html;

    }

    // ##################################################################################

    private function dbInitTables() {

        $conn = DatabaseConnect();
        if ($conn === false) {return false;}

        // Tabelle 'Entries' erstellen, wenn nicht vorhanden
        $sql = "CREATE TABLE IF NOT EXISTS `entries` (
                `ID` int(11) AUTO_INCREMENT PRIMARY KEY,
                `TIMETAG` datetime ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `USERTAG` text NOT NULL,
                `TYPETAG` text NOT NULL,
                `AUTOTAG` text,
                `TITLE` text NOT NULL,
                `SUBTITLE` text,
                `DTSTART` datetime,
                `DTEND` datetime,
                `DT_HASTIMEVALUE` tinyint(1) DEFAULT 0,
                `TASK_VEHICLE` text,
                `TASK_SHOWINEVENTS` tinyint(1) DEFAULT 0,
                `CYCL_WEEKDAY` tinyint(4),
                `CYCL_DAYOFMONTH` tinyint(4))";

        if (!mysqli_query($conn, $sql)) {
            error_log("Die WIM-Infotabelle konnte nicht erstellt werden: " . mysqli_error($conn), 0);
            return false;
        }

        // Tabelle 'Replacement' erstellen, wenn nicht vorhanden
        $sql = "CREATE TABLE IF NOT EXISTS `replacement` (
            `ID` int(11) AUTO_INCREMENT PRIMARY KEY,
            `REPLACE_ID` int(11),
            `REPLACE_DATE` datetime)";

        if (!mysqli_query($conn, $sql)) {
            error_log("Die WIM-Infotabelle konnte nicht erstellt werden: " . mysqli_error($conn), 0);
            return false;
        }

        // Tabelle von alten Einträgen säubern
        $todayNow = date("Y-m-d H:i:s");
        $todayStart = date("Y-m-d")." 00:00";
        $todayEnd = date("Y-m-d")." 23:59";
        $sql = "DELETE FROM `entries`
                WHERE
                    (DTEND IS NULL AND DTSTART < '$todayStart') OR
                    (DT_HASTIMEVALUE = 0 AND DTEND < '$todayStart') OR
                    (DT_HASTIMEVALUE = 2 AND DTEND < '$todayNow')";
        mysqli_query($conn, $sql);

        $sql = "DELETE FROM `replacement`
                WHERE
                    (REPLACE_DATE < '$todayNow')";
        mysqli_query($conn, $sql);

        $this->connection = $conn;
        return true;

    }

    function dbSelectForDate($requestType, $userTag, $autotag, $requestDate, $requestTime) {

        // Verschiedene Zeiten für die SQL-Anweisung vorbereiten
        
        $todayStart = (new DateTime($requestDate . " 00:00:00"))->format("Y-m-d H:i:s"); 
        $todayEnd = (new DateTime($requestDate . " 23:59:59"))->format("Y-m-d H:i:s"); 
        $todayNow = $requestDate . " " . $requestTime;

        $onlytimeStart = "3000-01-01 00:00:00";
        $onlytimeEnd = "3000-01-01 23:59:59";
        $onlytimeNow = "3000-01-01 " . $requestTime;

        $todayWeekday = (new DateTime($requestDate))->format("w");
        $todayDayofMonth = (new DateTime($requestDate))->format("j");
        if ($todayDayofMonth == (new DateTime($requestDate))->format("t")) {$todayDayofMonth = "-1";}

        // Filter
        $filterUserTag = (!isset($_SESSION['WimAdmin']) || $_SESSION['WimAdmin']) ? "" : " AND `USERTAG` = '$userTag'";

        // Je nach RequestType SQL-String zurückgeben
        switch($requestType) {

            case RequestType::INFO:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries`
                        WHERE
                            (`TYPETAG` = '" . TypeTag::INFO . "' AND `DTSTART` IS NULL) OR
                            (`TYPETAG` = '" . TypeTag::INFO . "' AND `DTSTART` <= '$todayStart' AND `DTEND` >= '$todayNow') OR 
                            (`TYPETAG` = '" . TypeTag::EVENT . "' AND `DT_HASTIMEVALUE` = 0 AND `DTSTART` <= '$todayStart') OR
                            (`TYPETAG` = '" . TypeTag::EVENT . "' AND `DT_HASTIMEVALUE` = 1 AND `DTSTART` <= '$todayNow' AND `DTEND` IS NULL) OR
                            (`TYPETAG` = '" . TypeTag::EVENT . "' AND `DT_HASTIMEVALUE` = 1 AND `DTSTART` <= '$todayNow' AND `DTEND` >= '$todayNow') OR
                            (`TYPETAG` = '" . TypeTag::EVENT . "' AND `DT_HASTIMEVALUE` = 2 AND `DTSTART` <= '$todayNow' AND `DTEND` >= '$todayNow')
                        ORDER BY `TYPETAG` DESC, `TIMETAG` DESC";

            case RequestType::TASK:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries`
                        WHERE
                            (
                                `TYPETAG` = '" . TypeTag::UNIQUETASK . "' AND
                                (
                                    ('$todayNow' < `DTEND`) AND
                                    (
                                        (`DTSTART` BETWEEN '$todayStart' AND '$todayNow') OR
                                        (`DTSTART` IS NULL)
                                    )
                                )
                            ) OR
                            (
                                `TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND
                                (
                                    ('$onlytimeNow' BETWEEN `DTSTART` AND `DTEND`) AND
                                    (
                                        (`TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND `CYCL_WEEKDAY` = $todayWeekday) OR
                                        (`TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND `CYCL_WEEKDAY` = -1) OR
                                        (`TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND `CYCL_DAYOFMONTH` = $todayDayofMonth)
                                    )
                                )
                            )
                        ORDER BY
                            `TYPETAG` ASC,
                            `DTEND` ASC";

            case RequestType::EVENT:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries`
                        WHERE
                            (`TYPETAG` = '" . TypeTag::EVENT . "' AND `DTSTART` > '$todayNow') OR
                            (`TYPETAG` = '" . TypeTag::UNIQUETASK . "' AND `DTSTART` > '$todayNow' AND `TASK_SHOWINEVENTS` > 0)
                        ORDER BY `DTSTART` ASC";


            case RequestType::ONLYINFO:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries` WHERE `TYPETAG` = '" . TypeTag::INFO . "' AND (`AUTOTAG` = '$autotag')$filterUserTag ORDER BY `DTSTART` DESC, `TIMETAG` DESC";

            case RequestType::UNIQUETASK:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries` WHERE `TYPETAG` = '" . TypeTag::UNIQUETASK . "' AND (`AUTOTAG` = '$autotag' OR `AUTOTAG` = 'REPLACE')$filterUserTag ORDER BY `DTEND` DESC, `TASK_VEHICLE` ASC";

            case RequestType::CYCLEDTASK:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries` WHERE `TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND (`AUTOTAG` = '$autotag')$filterUserTag ORDER BY `CYCL_DAYOFMONTH` ASC, `CYCL_WEEKDAY` ASC, `TASK_VEHICLE` ASC, `DTSTART` ASC";

            case RequestType::ONLYEVENTS:
                return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries` WHERE `TYPETAG` = '" . TypeTag::EVENT . "' AND (`AUTOTAG` = '$autotag')$filterUserTag ORDER BY `DTSTART` ASC, `DTEND` ASC";
                        
        }

    }
    
    function dbSelect($requestType, $userTag, $autotag = "") {

        return $this->dbSelectForDate($requestType, $userTag, $autotag, date("Y-m-d"), date("H:i:s"));

    }

    function dbSelectSearchCycledTask($requestDate) {

        $todayWeekday = (new DateTime($requestDate))->format("w");
        $todayDayofMonth = (new DateTime($requestDate))->format("j");
        if ($todayDayofMonth == (new DateTime($requestDate))->format("t")) {$todayDayofMonth = "-1";}

        return "SELECT `ID`, `TIMETAG`, `USERTAG`, `TYPETAG`, `AUTOTAG`, `TITLE`, `SUBTITLE`, `DTSTART`, `DTEND`, `DT_HASTIMEVALUE`, `TASK_VEHICLE`, `TASK_SHOWINEVENTS`, `CYCL_WEEKDAY`, `CYCL_DAYOFMONTH` FROM `entries`
                WHERE
                    (
                        `TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND
                        (
                            (
                                (`TYPETAG` = '" . TypeTag::CYCLEDTASK . "' AND `CYCL_WEEKDAY` = $todayWeekday) 
                            )
                        )
                    )
                ORDER BY
                    `TYPETAG` ASC,
                    `DTEND` ASC";

                // OR Tag im Monat / Jeden Tag --> Fällt aber weg, weil diese wahrscheinlich nicht ersetzt werden.

    }

}

class Settings {

    private $connection;

    public $isReady = false;

    // ##################################################################################

    public function __construct() {

        if ($this->dbInitTables()) {
            $this->isReady = true; }

    }

    public function __destruct() {

        if ($this->isReady) {
            DatabaseClose($this->connection);
        }

    }

    // ##################################################################################

    private function GetSetting($key) {

        if (!$this->isReady) {return false; }
        if ($key == "ID") { return false; }
        
        // SQL vorbereiten
        $sql = "SELECT `$key` FROM `settings` WHERE `ID`=1";
        $rtn = mysqli_query($this->connection, $sql);
        if ($rtn === false) { return false; }

        if ($rtn->num_rows === 1) {

            $row = $rtn->fetch_assoc();
            return $row["$key"];

        }

        return false;

    }

    private function SetSetting($key,$value) {

        if (!$this->isReady) {return false; }
        if ($key == "ID") { return false; }
        
        // SQL vorbereiten
        $sql = "UPDATE `settings` SET `LastUpdate`=current_timestamp(), `LastUser`=?, `$key`=? WHERE `ID`=1";
        $stm = mysqli_prepare($this->connection, $sql);
        if ($stm === false) { return false; }

        $stm->bind_param('ss', $_SESSION['LoginUser'], $value);

        // SQL ausführen
        if ($stm === false) { return false; }
        return $stm->execute();

    }

    // ##################################################################################

    public function GetMetaLastUpdate() {
        return (new DateTime($this->GetSetting("LastUpdate")))->format("d.m.y H:i"); }
    public function GetMetaLastUser() {
        return $this->GetSetting("LastUser"); }

    public function GetWacheName() {
        return $this->GetSetting("WacheName"); }
    public function SetWacheName($value) {
        return $this->SetSetting("WacheName", $value); }

    public function GetWacheUiResolution() {
        $def = $this->GetSetting("WacheUI");
        if ($def == null || $def == "") { $def = 'default'; }
        return $def; }
    public function SetWacheUiResolution($value) {
        return $this->SetSetting("WacheUI", $value); }

    public function GetWacheKfz() {
        return $this->GetSetting("WacheKfz"); }
    public function SetWacheKfz($value) {
        return $this->SetSetting("WacheKfz", $value); }
    
    public function GetAutoAbfallLink() {
        return $this->GetSetting("auto-AbfallLink"); }
    public function SetAutoAbfallLink($value) {
        return $this->SetSetting("auto-AbfallLink", $value); }

    public function GetAutoMalteserUser() {
        return $this->GetSetting("auto-MalteserUser"); }
    public function GetAutoMalteserPass() {
        return $this->GetSetting("auto-MalteserPass"); }
    public function SetAutoMalteserUser($value) {
        return $this->SetSetting("auto-MalteserUser", $value); }
    public function SetAutoMalteserPass($value) {
        return $this->SetSetting("auto-MalteserPass", $value); }
    
    // ##################################################################################

    private function dbInitTables() {

        $conn = DatabaseConnect();
        if ($conn === false) {return false;}

        // Tabelle 'settings' erstellen, wenn nicht vorhanden
        $sql = "CREATE TABLE IF NOT EXISTS `settings` (
                `ID` int(11) PRIMARY KEY,
                `LastUpdate` datetime,
                `LastUser` text,
                `WacheName` text,
                `WacheUI` text,
                `WacheKfz` text,
                `auto-AbfallLink` text,
                `auto-MalteserUser` text,
                `auto-MalteserPass` text)";

        if (!mysqli_query($conn, $sql)) {
            error_log("Die WIM-Einstellungstabelle konnte nicht erstellt werden: " . mysqli_error($conn), 0);
            return false;
        }

        // Standard erstellen, wenn keine Einträge
        $sql = "SELECT * FROM `settings`";
        $rtn = mysqli_query($conn, $sql);
        if ($rtn == false || $rtn->num_rows == 0) {

            $sql = "INSERT INTO `settings` (`ID`,`LastUpdate`, `LastUser`, `WacheName`,`WacheKfz`,`WacheUI`,`auto-AbfallLink`)
                    VALUES (1,null,'Standardeinstellungen','Neue Wache','','','')";

            if (!mysqli_query($conn, $sql)) {
                error_log("Die WIM-Einstellungen konnten nicht zurückgesetzt werden: " . mysqli_error($conn), 0);
                return false;
            }

        }
        $this->connection = $conn;
        return true;

    }

}

// ######################################################################################
// ALLGEMEINE FUNKTIONEN

function DatabaseConnect() {

    $Config = include('wim-config.php');

    // Verbinden
    $conn = new mysqli($Config['DB_SERVER'], $Config['DB_USER'], $Config['DB_PASS']);
    if ($conn->connect_error) {
        error_log('Verbindung zur WICO-Datenbank fehlgeschlagen: ' . $conn->connect_error, 0);
        return false;
    }

    //Datenbank erstellen, wenn nicht vorhanden
    $sql = "CREATE DATABASE IF NOT EXISTS wim";
    if ($conn->query($sql) != true) {
        error_log('Die WIM-Datenbank konnte nicht erstellt werden: ' . $conn->error, 0);
        return false;
    }

    $conn = new mysqli($Config['DB_SERVER'], $Config['DB_USER'], $Config['DB_PASS'], 'wim');
    if ($conn->connect_error) {
        error_log('Verbindung zur WIM-Datenbank fehlgeschlagen: ' . $conn->connect_error, 0);
        return false;
    }

    return $conn; }

function DatabaseClose($connection) {
    $connection->close(); }
