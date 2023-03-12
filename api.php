<?php

require_once 'php-auth.php';
require_once 'php-db.php';

###################################################################################################

$paramAction = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
if ($paramAction == false) {giveErrorBadRequest();}

###################################################################################################
# Account bearbeiten

switch ($paramAction) {
    case 'ACCOUNT-LOGOUT':
        session_destroy();
        redirectToLogin();
        break;

    case 'ACCOUNT-CHANGEUSER':
        ThrowInvalidSession();
        
        $NewLoginUser = filter_input(INPUT_POST, 'user', FILTER_SANITIZE_STRING);

        $usersManager = new UsersManager();
        if ($usersManager->isReady && ($NewLoginUser !== false)) {
            if ($usersManager->ChangeLoginUser($NewLoginUser) !== false) {
                session_destroy();
                redirectToLogin();
            } else {
                redirectToAdminWithArgs(null, "error=1&msg=" . urlencode("Die Benutzerkennung existiert bereits."));
            }
        }
        break;

    case 'ACCOUNT-CHANGEPASS':
        ThrowInvalidSession();

        $OldLoginPass = filter_input(INPUT_POST, 'oldpass', FILTER_SANITIZE_STRING);
        $NewLoginPass = filter_input(INPUT_POST, 'newpass', FILTER_SANITIZE_STRING);

        $usersManager = new UsersManager();
        if ($usersManager->isReady && ($OldLoginPass !== false) && ($NewLoginPass !== false)) {

            if ($usersManager->LoginUser($_SESSION['LoginUser'], $OldLoginPass) && $usersManager->SelectCurrentUser()) {

                if ($usersManager->ChangePass($NewLoginPass, false)) {
                    session_destroy();
                    redirectToLogin();
                }

            }

            $_SESSION['messageArgsTitle'] = "Falsches Passwort";
            $_SESSION['messageArgsSubtitle'] = "@$_SESSION[LoginUser]";
            $_SESSION['messageArgsBody'] = "Das eingegebene Passwort stimmt nicht. Bitte überprüfe deine Eingabe und probiere es nochmal.";
            redirectToAdminWithArgs(null, "message=error");

        }
        break;

}

###################################################################################################
# User bearbeiten

switch ($paramAction) {
    case 'USER-EDIT':

        ThrowInvalidSession();
        ThrowNoAdminSession();

        $UserID = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
        $LoginUser = strtolower(filter_input(INPUT_POST, 'loginuser', FILTER_SANITIZE_STRING));
        $FullName = filter_input(INPUT_POST, 'fullname', FILTER_SANITIZE_STRING);
        $IsWimAdmin = (filter_input(INPUT_POST, 'wimadmin', FILTER_SANITIZE_STRING) === "on");

        // Wenn POST unvollständig > Abbruch
        if ($UserID === false || $UserID === "" || $LoginUser === false || $FullName === false) {
            giveErrorBadRequest(); }

        $UserID = (int)$UserID;

        // Wenn Nutzer @admin > Abbruch, weil reserviert
        if ($LoginUser == "admin") {

            $_SESSION['messageArgsTitle'] = "Reservierter Nutzer";
            $_SESSION['messageArgsSubtitle'] = "@admin";
            $_SESSION['messageArgsBody'] = "Der Nutzer wurde nicht erstellt. Die Kennung >admin< ist reserviert. Benutze eine andere Kennung.";
            redirectToAdminWithArgs("entries-users-anchor", "message=error"); }

        // User bearbeiten
        $usersManager = new UsersManager();
        if ($usersManager->isReady) {

            if ($UserID === -1) {

                // Neuen Nutzer erstellen & Auswählen
                $rtn = $usersManager->AddUser($LoginUser, $FullName, $IsWimAdmin);
                if ($rtn !== false && $usersManager->SelectUser($rtn)) 
                {
                    $tmpPass = $usersManager->ResetPass();
                    if ($tmpPass !== false) {

                        $_SESSION['messageArgsTitle'] = "Neuer Nutzer";
                        $_SESSION['messageArgsSubtitle'] = "@$LoginUser";
                        $_SESSION['messageArgsBody'] = "Der neue Benutzer wurde erstellt. Damit sich dieser anmelden kann, benötigt er ein Kennwort. Gib bitte folgende Zugangsdaten weiter: <br><br> Nutzername: $LoginUser <br> Passwort: <code>$tmpPass</code><br><a class=\"btn btn-small\" href=\"mailto:?subject=WIM [Neue Zugangsdaten]&body=".rawurlencode("Dein WIM-Zugang wurde zurückgesetzt: \n \n Nutzername: \t $LoginUser \n Passwort: \t $tmpPass")."\">Per E-Mail weiterleiten</a>";
                        redirectToAdminWithArgs("entries-users-anchor", "message=info");

                    }
                } else {

                    $_SESSION['messageArgsTitle'] = "Fehler";
                    $_SESSION['messageArgsSubtitle'] = "@$LoginUser";
                    $_SESSION['messageArgsBody'] = "Der Nutzer <strong>@$LoginUser</strong> konnte nicht erstellt werden. Vermutlich existiert dieser schon. Benutze einen anderen Benutzernamen.";
                    redirectToAdminWithArgs("entries-users-anchor", "message=error");

                }

            } else {

                // Bestehenden Nutzer auswählen & aktualisieren
                if ($usersManager->SelectUser($UserID) &&
                    $usersManager->ChangeUserInfo($LoginUser, $FullName, $IsWimAdmin)) {

                    redirectToAdminWithArgs("entries-users-anchor", null);
                }

            }

        } 
        
        redirectToAdminWithArgs("entries-users-anchor", "message=error");

        break;

    case 'USER-PASSRESET':

        ThrowInvalidSession();
        ThrowNoAdminSession();

        $UserID = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
        $LoginUser = filter_input(INPUT_POST, 'loginuser', FILTER_SANITIZE_STRING);
        if ($UserID === false || $LoginUser === false || $UserID == "") { giveErrorBadRequest(); }

        // User bearbeiten
        $usersManager = new UsersManager();
        if ($usersManager->isReady) {

            if ($usersManager->SelectUser($UserID)) {

                $tmpPass = $usersManager->ResetPass();
                if ($tmpPass !== false) {
                   
                    $_SESSION['messageArgsTitle'] = "Nutzer-Verwaltung";
                    $_SESSION['messageArgsSubtitle'] = "@$LoginUser";
                    $_SESSION['messageArgsBody'] = "Das Passwort wurde zurückgesetzt. Damit sich der Nutzer anmelden kann, benötigt er ein Kennwort. Gib bitte folgende Zugangsdaten weiter: <br><br> Nutzername: $LoginUser <br> Passwort: <code>$tmpPass</code><br>";
                    redirectToAdminWithArgs("entries-users-anchor", "message=info");

                }

            }

        } 
            
        redirectToAdminWithArgs("entries-users-anchor", "message=error");

        break;

    case 'USER-DELETE':

        ThrowInvalidSession();
        ThrowNoAdminSession();

        $UserID = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
        if ($UserID === false || $UserID == "") { giveErrorBadRequest(); }

        // User bearbeiten
        $usersManager = new UsersManager();
        if ($usersManager->isReady) {

            if ($usersManager->SelectUser($UserID) && $usersManager->DeleteUser()) {

                redirectToAdminWithArgs("entries-users-anchor", null);
            }

        } 
            
        redirectToAdminWithArgs("entries-users-anchor", "message=error");

        break;

}

###################################################################################################
# META

switch ($paramAction) {
    case 'SETTINGS':

        ThrowInvalidSession();
        ThrowNoAdminSession();

        $WacheName = filter_input(INPUT_POST, 'wachename', FILTER_SANITIZE_STRING);
        $WacheUI = filter_input(INPUT_POST, 'ui', FILTER_SANITIZE_STRING);
        $WacheKfz = urlencode(filter_input(INPUT_POST, 'wachekfz'));
        
        $settings = new Settings();
        if ($settings->isReady) { 

            $setMessage = null;

            $settings->SetWacheName($WacheName);
            $settings->SetWacheKfz($WacheKfz);

            //Hinweis auf Neustart, wenn Auflösung geändert
            $beforeWacheUI = $settings->GetWacheUiResolution();
            $settings->SetWacheUiResolution($WacheUI);
            if ($beforeWacheUI != $WacheUI) {
                $setMessage = "message=info";
                $_SESSION['messageArgsTitle'] = "Neustart erforderlich";
                $_SESSION['messageArgsBody'] = "Für die Änderung der Auflösung muss die WIM-Box neugestartet werden. <br><br>Aus Sicherheitsgründen funktioniert dies aber nicht von der Admin-Oberfläche aus. <br><br>Am Besten ziehst du kurz den Stecker an der WIM-Box - dann wird die neue Oberfläche geladen. 😁";  
            }

            redirectToAdminWithArgs("entries-users-anchor", $setMessage);

        }

        $_SESSION['messageArgsTitle'] = "Fehler";
        $_SESSION['messageArgsBody'] = "Beim Speichern der Einstellungen ist ein Fehler aufgetreten. > WIM-Verantwortl. kontaktieren.";            
        redirectToAdminWithArgs("entries-users-anchor", "message=error");

        break;

    case 'SETTINGS-MODULE-ABFALL':

        ThrowInvalidSession();
        ThrowNoAdminSession();

        $AutoAbfallLink = filter_input(INPUT_POST, 'auto-abfalllink', FILTER_SANITIZE_STRING);

        $settings = new Settings();
        if ($settings->isReady) { 

            // Abfallkalender aktualisieren, wenn Link geändert.
            $beforeAutoAbfallLink = $settings->GetAutoAbfallLink();
            if ($beforeAutoAbfallLink != $AutoAbfallLink) {
                $settings->SetAutoAbfallLink($AutoAbfallLink); 
                include 'cron-auto-abfall.php'; }

            redirectToAdminWithArgs("entries-modules-anchor", null);

        }

        $_SESSION['messageArgsTitle'] = "Fehler";
        $_SESSION['messageArgsBody'] = "Beim Speichern der Einstellungen ist ein Fehler aufgetreten. > WIM-Verantwortl. kontaktieren.";            
        redirectToAdminWithArgs("entries-modules-anchor", "message=error");

        break;

    case 'SETTINGS-MODULE-MALTESER':

        ThrowInvalidSession();
        ThrowNoAdminSession();
    
        $AutoMalteserUser = filter_input(INPUT_POST, 'auto-malteseruser', FILTER_SANITIZE_STRING);
        $AutoMalteserPass = filter_input(INPUT_POST, 'auto-malteserpass', FILTER_SANITIZE_STRING);
    
        $settings = new Settings();
        if ($settings->isReady) { 
    
            // Zugangsdaten - Passwort nur ändern, wenn gesetzt
            $settings->SetAutoMalteserUser($AutoMalteserUser);
            if ($AutoMalteserPass !== "") { 
                $settings->SetAutoMalteserPass($AutoMalteserPass);
                include 'cron-auto-maltesercloud.php'; }
    
            redirectToAdminWithArgs("entries-modules-anchor", null);
    
        }
    
        $_SESSION['messageArgsTitle'] = "Fehler";
        $_SESSION['messageArgsBody'] = "Beim Speichern der Einstellungen ist ein Fehler aufgetreten. > WIM-Verantwortl. kontaktieren.";            
        redirectToAdminWithArgs("entries-modules-anchor", "message=error");
    
        break;

    case 'GET-UI':

        $requestType = filter_input(INPUT_GET, "type", FILTER_SANITIZE_STRING);
        if ($requestType !== false) {

            $entriesManager = new entriesManager();
            if ($entriesManager->isReady) {
                echo $entriesManager->GenerateHTML($requestType, false); die(); } }

        break;

    case 'ADMIN-SEARCH-CYCLEDTASK':

        ThrowInvalidSession();

        $requestDate = null;
        if (isset($_GET["date"]) && strlen($_GET["date"]) > 0) {$requestDate = "$_GET[date]";}
    
        if ($requestDate !== null) {

            $entriesManager = new entriesManager();
            if ($entriesManager->isReady) {
                echo $entriesManager->GenerateMetaSearchCycledTask($requestDate); die(); } }

        break;
    case 'ADMIN-GET-UI-SINGLEID':

        ThrowInvalidSession();

        $requestId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        if ($requestId !== false) {

            $entriesManager = new entriesManager();
            if ($entriesManager->isReady) {
                echo $entriesManager->GenerateHTMLSingle($requestId); die(); } }

        break;

}

###################################################################################################
# Einträge bearbeiten

switch ($paramAction) {
    case 'ITEM-EDIT':

        ThrowInvalidSession();
        
        // Item abrufen
        $typeTag = filter_input(INPUT_POST, 'typetag', FILTER_SANITIZE_STRING);
        if ($typeTag === false) {giveErrorBadRequest();}

        $ItemId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        if ($ItemId == null || $ItemId === false) {giveErrorBadRequest();}
    
        // Je nach TypeTag
        $entriesManager = new EntriesManager();
        if (!$entriesManager->isReady) {giveErrorServer();}

        switch ($typeTag) {
            case TypeTag::INFO:

                if (isset($_POST["title"]) &&
                    isset($_POST["subtitle"])) {
    
                    $dateStart = null;
                    $dateEnd = null;
                    if (isset($_POST["dateStart"]) && strlen($_POST["dateStart"]) > 0) {$dateStart = "$_POST[dateStart] 00:00:00";}
                    if (isset($_POST["dateEnd"]) && strlen($_POST["dateEnd"]) > 0) {$dateEnd = "$_POST[dateEnd] 23:59:59";}
    
                    if ($entriesManager->EditInfo($ItemId, $_POST["title"], $_POST["subtitle"], $dateStart, $dateEnd)) {
                        redirectToAdminWithArgs("entries-".TypeTag::INFO."-anchor", null); }
    
                }

                break;

            case TypeTag::EVENT:

                if (isset($_POST["title"]) &&
                    isset($_POST["subtitle"]) &&
                    isset($_POST["dateStart"])) {

                    // Zeiten konvertieren
                    $hasTime = false;
                    $dateStart = $_POST["dateStart"];
                    $dateEnd = null;

                    if (isset($_POST["dateEnd"]) && strlen($_POST["dateEnd"]) > 0) {$dateEnd = $_POST["dateEnd"];}
                    if (isset($_POST["timeStart"]) && strlen($_POST["timeStart"]) > 0) {

                        $hasTime = true;
                        $dateStart .= " " . $_POST["timeStart"]; }

                    if ($dateEnd != null && $hasTime && isset($_POST["timeEnd"]) && strlen($_POST["timeEnd"]) > 0) {
                        $dateEnd .= " " . $_POST["timeEnd"]; }

                    $hasTime = $hasTime ? 1 : 0;

                    if ($entriesManager->EditEvent($ItemId, $_POST["title"], $_POST["subtitle"], $dateStart, $dateEnd, $hasTime)) {
                        redirectToAdminWithArgs("entries-".TypeTag::EVENT."-anchor", null); }

                }
                break;

            case TypeTag::UNIQUETASK:

                if (isset($_POST["title"]) &&
                    isset($_POST["subtitle"]) &&
                    isset($_POST["vehicle"]) &&
                    isset($_POST["dateEnd"]) &&
                    isset($_POST["timeEnd"])) {

                    $dateEnd = $_POST["dateEnd"] . " " . $_POST["timeEnd"];
                    $dateStart = null;

                    if (isset($_POST["dateStart"]) && strlen($_POST["dateStart"]) > 0 &&
                        isset($_POST["timeStart"]) && strlen($_POST["timeStart"]) > 0) {
                        $dateStart = $_POST["dateStart"] . " " . $_POST["timeStart"];}

                    $showAsEvent = (isset($_POST["showAsEvent"]) && $_POST["showAsEvent"] == "on");

                    if ($entriesManager->EditUniqueTask($ItemId, $_POST["title"], $_POST["subtitle"], $_POST["vehicle"], $dateStart, $dateEnd, $showAsEvent)) {
                        redirectToAdminWithArgs("entries-".TypeTag::UNIQUETASK."-anchor", null); }

                }
                break;

            case TypeTag::CYCLEDTASK:

                if (isset($_POST["subtitle"]) &&
                    isset($_POST["vehicle"]) &&
                    isset($_POST["cyclemode"]) &&
                    ($_POST["cyclemode"] == "daily" && isset($_POST["timeStart"]) && isset($_POST["timeEnd"])) ||
                    ($_POST["cyclemode"] == "week" && isset($_POST["weekday"])) ||
                    ($_POST["cyclemode"] == "month" && isset($_POST["dayofmonth"]))) {

                    $weekday = null;
                    $dayofmonth = null;

                    switch ($_POST["cyclemode"]) {
                        case "daily":
                            $weekday = -1;
                            break;

                        case "week":
                            $weekday = filter_input(INPUT_POST, "weekday", FILTER_VALIDATE_INT);
                            break;

                        case "month":
                            $dayofmonth = filter_input(INPUT_POST, "dayofmonth", FILTER_VALIDATE_INT);
                            break;}

                    if ($entriesManager->EditCycledTask($ItemId, $_POST["subtitle"], $_POST["vehicle"], $weekday, $dayofmonth, $_POST["timeStart"], $_POST["timeEnd"])) {
                        redirectToAdminWithArgs("entries-".TypeTag::CYCLEDTASK."-anchor", null); }

                }
                break;

        }
        break;

    case 'ITEM-DELETE':

        ThrowInvalidSession();

        // Item abrufen
        $typeTag = filter_input(INPUT_POST, 'typetag', FILTER_SANITIZE_STRING);
        if ($typeTag === false) {giveErrorBadRequest();}

        $ItemId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
        if ($ItemId == null || $ItemId === false) {giveErrorBadRequest();}

        // Item löschen
        $entriesManager = new EntriesManager();
        if (!$entriesManager->isReady) {giveErrorServer();}

        if ($entriesManager->DeleteEntry($ItemId)) {
            redirectToAdminWithArgs("entries-{$typeTag}-anchor", null); }

        break;

    case 'ITEM-REPLACE':

        ThrowInvalidSession();

        $entriesManager = new EntriesManager();
        if (!$entriesManager->isReady) {giveErrorServer();}

        $orgId = filter_input(INPUT_POST, "orgId", FILTER_VALIDATE_INT);
        if ($orgId == null || $orgId === false) {$orgId = -1;}

        $replaceId = filter_input(INPUT_POST, "replace_id", FILTER_VALIDATE_INT);
        if ($replaceId == null || $replaceId === false) {giveErrorBadRequest();}

        if ($orgId == -1) {

            // Replace
            $replaceDate = null;
            if (isset($_POST["replace_date"]) && strlen($_POST["replace_date"]) > 0) { $replaceDate = "$_POST[replace_date] 23:59:59"; }

            if (!$entriesManager->ReplaceEntry($replaceId, $replaceDate)) {
                giveErrorBadRequest(); }

        }

        // UniqueTask
        if (isset($_POST["title"]) &&
            isset($_POST["subtitle"]) &&
            isset($_POST["date"]) &&
            isset($_POST["timeStart"]) &&
            isset($_POST["timeEnd"])) {

            $dateEnd = $_POST["date"] . " " . $_POST["timeEnd"];
            $dateStart = $_POST["date"] . " " . $_POST["timeStart"];
            $showAsEvent = false;

            if ($entriesManager->EditUniqueTask($orgId, $_POST["title"], $_POST["subtitle"], $replaceId, $dateStart, $dateEnd, $showAsEvent, "REPLACE")) {
                giveSuccess(); }

        }

        break;

    case 'ITEM-REPLACE-DELETE':

        ThrowInvalidSession();

        $entriesManager = new EntriesManager();
        if (!$entriesManager->isReady) {giveErrorServer();}

        $orgId = filter_input(INPUT_POST, "orgId", FILTER_VALIDATE_INT);
        if ($orgId == null || $orgId === false) {giveErrorBadRequest();}

        $replaceId = filter_input(INPUT_POST, "replace_id", FILTER_VALIDATE_INT);
        if ($replaceId == null || $replaceId === false) {giveErrorBadRequest();}

        if ($entriesManager->DeleteEntry($orgId) &&
            $entriesManager->DeleteReplacement($replaceId)) {giveSuccess();}

        break;
}

giveErrorBadRequest();

###################################################################################################
# Funktionen

function ThrowInvalidSession()
{
    if (!checkAdminSession()) {redirectToLogin();}
}
function ThrowNoAdminSession() 
{
    if ($_SESSION['WimAdmin'] !== true) { redirectToAdminWithArgs("entries-users-anchor", "error=1&msg=" . urlencode("Für die Bearbeitung der Nutzer fehlen dir die Rechte. > Standortverantw. kontaktieren.")); }
}