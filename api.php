<?php

namespace WIM;

// files ##########################################################################################
require_once dirname(__FILE__) . '/db-settings.php';
require_once dirname(__FILE__) . '/db-entries.php';
require_once dirname(__FILE__) . '/db-users.php';
require_once dirname(__FILE__) . '/db-auth.php';

$paramAction = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? false;
if ($paramAction == false) { Auth::replyErrorBadRequest(); }

///////////////////////////////////////////////////////////////////////////////////////////////////
// UI-ENDPOINT */

    switch ($paramAction)
    {
        case 'GET-UI':

            // get parameters
            $requestType = filter_input(INPUT_GET, "type", FILTER_SANITIZE_STRING) ?? false;
            if ($requestType == false) { Auth::replyErrorBadRequest(); }
            if (!RequestType::IsValidType($requestType)) { Auth::replyErrorBadRequest(); }

            // get entries
            $entries = (new Entries())->LoadEntries($requestType);
            if ($entries === false) { Auth::replyErrorServer(); }

            // filter output if in search
            $hidden = [];
            if ($requestType == RequestType::ADMIN_RECURRING_SEARCH || !RequestType::IsAdminViewType($requestType))
            {
                $today = date('Y-m-d');
                $wholeHidden = (new Settings())->GetHiddenEntries(); 
                if (isset($wholeHidden[$today]) && count($wholeHidden[$today]) > 0) {
                    $hidden = $wholeHidden[$today];
                } 
            }

            // output HTML
            die($html = UserInterface::GenerateHtmlOfEntriesList($entries, $requestType, $hidden));
            break;

        case 'SEARCH-UI':

            // get parameters
            $requestDate = filter_input(INPUT_GET, "date", FILTER_SANITIZE_STRING) ?? false;
            if ($requestDate == false) { Auth::replyErrorBadRequest(); }
            if (!Validation::IsDateValid($requestDate)) { Auth::replyErrorBadRequest(); }
            $requestDate = \DateTime::createFromFormat('Y-m-d', $requestDate);
            if ($requestDate === false) { Auth::replyErrorBadRequest(); }

            $formatedDate = ($requestDate->format('Y-m-d')).' 00:00:00';
            $requestedWeekday = $requestDate->format("w");
            $requestedDoM = $requestDate->format("j");
            $requestedLWeekOM = (new \DateTime('last day of this month'))->modify('-6 days')->format("Y-m-d 00:00:00");
            $isRequestedLastDoM = ($requestDate->format("t") == $requestedDoM) ? 1 : 0;

            // get entries
            $where = "`TYPETAG` = '".TypeTag::RECURRING."' AND 
            (
                (`CYCL_TYPE` = 0) OR 
                (`CYCL_TYPE` = 1 AND `CYCL_WEEKDAY` = $requestedWeekday) OR 
                (`CYCL_TYPE` = 2 AND `CYCL_DOM` = $requestedDoM) OR 
                (`CYCL_TYPE` = 3 AND 1 = $isRequestedLastDoM) OR 
                (`CYCL_TYPE` = 4 AND '$formatedDate' >= '$requestedLWeekOM' AND `CYCL_WEEKDAY` = $requestedWeekday)
            )
            ORDER BY `DATEEND` ASC, `PAYLOAD` ASC";
            $entries = (new Entries())->LoadCustom($where);
            if ($entries === false) { Auth::replyErrorServer(); }

            // get hidden
            $hidden = [];
            $today = date('Y-m-d');
            $wholeHidden = (new Settings())->GetHiddenEntries(); 
            if (isset($wholeHidden[$today]) && count($wholeHidden[$today]) > 0) {
                $hidden = $wholeHidden[$today];
            } 

            // output HTML
            die($html = UserInterface::GenerateHtmlOfEntriesList($entries, RequestType::ADMIN_RECURRING_SEARCH, $hidden));
            break;
    
    }

///////////////////////////////////////////////////////////////////////////////////////////////////
// ACCOUNT-ENDPOINT */

    switch ($paramAction)
    {
        case 'ACCOUNT-HEARTBEAT':
            if (Auth::checkSession()) { Auth::replySuccess(); }
            else { Auth::replyErrorUnauthorized(); }

        case 'ACCOUNT-LOGOUT': 
            session_destroy();
            Auth::redirectToLogin();
            break;

        case 'ACCOUNT-CHANGEUSER': 
            Auth::blockInvalidSession();

            // get parameters
            $newName = \filter_input(INPUT_POST, 'user', FILTER_SANITIZE_STRING);
            if ($newName == false) { Auth::replyErrorBadRequest(); }

            // add username, or give error
            $users = new Users();
            if ($users->ChangeName($newName))
            {
                session_destroy();
                Auth::redirectToLogin();
            }
            else
            {
                Auth::redirectToAdminWithMessage("{
                    title: 'Doppelte Kennung',
                    description: 'Die Benutzerkennung existiert bereits. Bitte wähle eine andere.',
                    showWarning: true,
                    mode: 'ok',
                    actionPositive: null
                }", 'account');
            }
            break;
            
        case 'ACCOUNT-CHANGEPASS':
            Auth::blockInvalidSession();

            // get parameters
            $oldPass = filter_input(INPUT_POST, 'oldpass', FILTER_SANITIZE_STRING);
            $newPass = filter_input(INPUT_POST, 'newpass', FILTER_SANITIZE_STRING);
            if ($oldPass == false) { Auth::replyErrorBadRequest(); }
            if ($newPass == false) { Auth::replyErrorBadRequest(); }

            // update password, if old pass matching, otherwise give error
            $users = new Users();
            if ($users->LoginUser($_SESSION['User'], $oldPass))
            {

                if ($users->ChangePass($newPass))
                {
                    session_destroy();
                    Auth::redirectToLogin();
                }
                
                // on Error
                Auth::redirectToAdminWithMessage("{
                    title: \"Fehler bei der Passwortänderung (@{$_SESSION['User']})\",
                    description: 'Die Änderung konnte leider nicht übernommen werden. Informiere den Standortverantwortlichen, wenn der Fehler weiter bestehen bleibt.',
                    showWarning: true,
                    mode: 'ok',
                    actionPositive: null
                }", 'account');

            }

            // on Error
            Auth::redirectToAdminWithMessage("{
                title: \"Falsches Passwort (@{$_SESSION['User']})\",
                description: 'Das eingegebene Passwort stimmt nicht. Bitte überprüfe deine Eingabe und probiere es nochmal.',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'account');
            break;

    }

///////////////////////////////////////////////////////////////////////////////////////////////////
// USER-ENDPOINT */

    switch ($paramAction)
    {
        case 'USER-EDIT': 
            Auth::blockInvalidSession();
            Auth::blockNoAdminSession();

            // get parameters
            $userId = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
            $userName = strtolower(filter_input(INPUT_POST, 'loginuser', FILTER_SANITIZE_STRING));
            $isAdmin = (filter_input(INPUT_POST, 'wimadmin', FILTER_SANITIZE_STRING) === "on");

            // check parameters
            if ($userId === false || $userName === false) { Auth::replyErrorBadRequest(); }
            $userId = (int)$userId;

            // block forbidden usernames
            if ($userName == 'admin')
            {
                Auth::redirectToAdminWithMessage("{
                    title: 'Reservierter Nutzer (@admin)',
                    description: 'Der Nutzer wurde nicht erstellt. Die Kennung >admin< ist reserviert. Benutze eine andere Kennung.',
                    showWarning: true,
                    mode: 'ok',
                    actionPositive: null
                }", 'users');
            }

            // create new user, or edit existing
            $users = new Users();
            if ($userId === -1)
            {

                $userId = $users->AddUser($userName, $isAdmin);
                if ($userId === false) { Auth::replyErrorBadRequest(); }

                // reset new password & show dialog
                $tmpPassword = $users->ResetPass($userId);
                if ($tmpPassword === false) 
                { 
                    // user created, but passwort could not be resetted
                    Auth::redirectToAdminWithMessage("{
                        title: 'Neuer Benutzer erstellt (@$userName)',
                        description: 'Der Nutzer wurde erstellt. <br>Setze das Kennwort bitte manuell zurück, damit sich der Nutzer anmelden kann zukünftig.',
                        showWarning: true,
                        mode: 'ok',
                        actionPositive: () => { WIM.EDITOR.userEditor.edit($userId, '$userName', ".($isAdmin ? 'true' : 'false').") }
                    }", 'users');
                }

                // successful created user
                Auth::redirectToAdminWithMessage("{
                    title: 'Neuer Benutzer erstellt (@$userName)',
                    description: 'Der Nutzer wurde erstellt. <br>Gib dem Nutzer sein temporäres Passwort: <br><br>$tmpPassword<br><br>Damit kann er sich anmelden.',
                    showWarning: false,
                    mode: 'ok',
                    actionPositive: null
                }", 'users');

            }
            else
            {
                if ($users->ChangeName($userName, $userId) &&
                    $users->ChangeIsAdmin($isAdmin, $userId))
                {
                    Auth::redirectToAdminWithArgs("entries-users-anchor", "");
                }
                else
                {
                    // on Error
                    Auth::redirectToAdminWithMessage("{
                        title: 'Benutzer konnte nicht bearbeitet werden (@$userName)'',
                        description: 'Beim Ändern der Benutzerdaten ist ein Fehler aufgetreten. Probiere es später nochmal oder benachrichte den Standortverantwortlichen, sollte der Fehler weiterhin bestehen.',
                        showWarning: true,
                        mode: 'ok',
                        actionPositive: null
                    }", 'users');
                }
            }
            break;

        case 'USER-PASSRESET':
            Auth::blockInvalidSession();
            Auth::blockNoAdminSession();

            // get parameters
            $userId = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
            $userName = filter_input(INPUT_POST, 'loginuser', FILTER_SANITIZE_STRING);
            if ($userId === false || $userName === false) { Auth::replyErrorBadRequest(); }
            $userId = (int)$userId;

            // reset pass
            $users = new Users();
            $newPass = $users->ResetPass($userId);
            if ($newPass !== false) 
            { 
                
                // successfully resetted password
                Auth::redirectToAdminWithMessage("{
                    title: 'Passwort zurückgesetzt (@$userName)',
                    description: 'Das Passwort wurde zurückgesetzt. <br>Gib dem Nutzer sein temporäres Passwort: <br><br>$newPass<br><br>Damit kann er sich anmelden.',
                    showWarning: false,
                    mode: 'ok',
                    actionPositive: null
                }", 'users');

            }

            // on Error
            Auth::redirectToAdminWithMessage("{
                title: 'Benutzer konnte nicht bearbeitet werden (@$userName)'',
                description: 'Beim Zurücksetzen des Passwortes ist ein Fehler aufgetreten. Probiere es später nochmal oder benachrichte den Standortverantwortlichen, sollte der Fehler weiterhin bestehen.',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'users');
            break;

        case 'USER-DELETE':
            Auth::blockInvalidSession();
            Auth::blockNoAdminSession();

            // get parameters
            $userId = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT); 
            if ($userId === false) { Auth::replyErrorBadRequest(); }

            // delete user
            $users = new Users();
            if ($users->DeleteUser($userId))
            {
                Auth::redirectToAdminWithMessage("{
                    title: 'Benutzer wurde gelöscht',
                    description: 'Der Benutzer wurde entfernt. Die dem Nutzer zugeordneten Einträge bleiben bestehen, bis du diese manuell löschst.',
                    showWarning: false,
                    mode: 'ok',
                    actionPositive: null
                }", 'users');
            }
            else
            {
                // on Error
                Auth::redirectToAdminWithMessage("{
                    title: 'Benutzer konnte nicht gelöscht werden (@$userName)'',
                    description: 'Beim Löschen des Benutzers ist ein Fehler aufgetreten. Probiere es später nochmal oder benachrichte den Standortverantwortlichen, sollte der Fehler weiterhin bestehen.',
                    showWarning: true,
                    mode: 'ok',
                    actionPositive: null
                }", 'users');
            }
            break;

    }

///////////////////////////////////////////////////////////////////////////////////////////////////
// CRONJOB-ENDPOINT */

    if ($paramAction == 'CRON-MODULE')
    {
        ModulesWim::ApiHookCron();
    }
    

///////////////////////////////////////////////////////////////////////////////////////////////////
// SETTINGS-ENDPOINT */

    switch ($paramAction) {
        case 'SETTINGS-WIM':
            Auth::blockInvalidSession();
            Auth::blockNoAdminSession();

            $module = filter_input(INPUT_GET, 'm', FILTER_SANITIZE_STRING);
            switch ($module)
            {
                case 'UI':

                    $stationName = filter_input(INPUT_POST, 'wachename', FILTER_SANITIZE_STRING);
                    $uiRes = filter_input(INPUT_POST, 'ui-res', FILTER_SANITIZE_STRING);
                    $stationLoc = filter_input(INPUT_POST, 'wacheloc', FILTER_UNSAFE_RAW);

                    // prepare connectors
                    $settings = new Settings();
                    $beforeName = $settings->Get(Settings::UiStationName);
                    $beforeRes = $settings->Get(Settings::UiResolution);
                    $beforeLoc = $settings->Get(Settings::UiLocation);

                    if (!$settings->Set(Settings::UiStationName, $stationName) ||
                        !$settings->Set(Settings::UiResolution, $uiRes) ||
                        !$settings->Set(Settings::UiLocation, $stationLoc))
                    {
                        Auth::redirectToAdminWithMessage("{
                            title: 'Fehler beim Speichern',
                            description: 'Die Einstellungen konnten nicht gespeichert werden. Waren z.B. komische Zeichen im Wachennamen?',
                            showWarning: true,
                            mode: 'ok',
                            actionPositive: null
                        }", 'settings');
                    }

                    if ($beforeName != $stationName ||
                        $beforeRes != $uiRes ||
                        $beforeLoc != $stationLoc)
                    {
                        Auth::redirectToAdminWithMessage("{
                            title: 'Neustart erforderlich',
                            description: 'Um die Einstellungen zu übernehmen, muss das WIM neugestartet werden. Aus Sicherheitsgründen funktioniert das aber nicht von der Admin-Oberfläche aus. <br><br>Zieh am Besten kurz den Stecker 😁',
                            showWarning: false,
                            mode: 'ok',
                            actionPositive: null
                        }", 'module');
                    }
                    else
                    {
                        Auth::redirectToAdmin('settings');
                    }

                    break;
                case 'TIMING':

                    $timing = filter_input(INPUT_POST, 'vehicleTiming', FILTER_UNSAFE_RAW);

                    $settings = new Settings();
                    if (!$settings->Set(Settings::UiVehicleTiming, $timing))
                    {
                        Auth::redirectToAdminWithMessage("{
                            title: 'Fehler beim Speichern',
                            description: 'Die Einstellungen konnten nicht gespeichert werden. Waren die Fahrzeugzeiten richtig formatiert?',
                            showWarning: true,
                            mode: 'ok',
                            actionPositive: null
                        }", 'settings');
                    }
                    Auth::redirectToAdmin('settings');

                    break;
            }
            break;
        
        case 'SETTINGS-MODULE':
            Auth::blockInvalidSession();
            Auth::blockNoAdminSession();

            ModulesWim::ApiHookSettings();
            break;


        case 'WIM-EXPORT':

            $withModules = filter_input(INPUT_GET, 'modules', FILTER_SANITIZE_STRING);
            $withModules = $withModules !== null && $withModules !== false && $withModules == 1;

            try
            {

                $entries = new Entries();
                $settings = new Settings();
                $users = new Users();
                $exportEntries = $entries->LoadExport();
                $exportSettings = $settings->LoadExport($withModules);
                $exportUsers = $users->LoadExport();
                if ($exportEntries !== false &&
                    $exportSettings !== false &&
                    $exportUsers !== false)
                {

                    $export = [];
                    $export['entries'] = $exportEntries;
                    $export['settings'] = $exportSettings;
                    $export['users'] = $exportUsers;
                    $exportJson = \json_encode($export);

                    if ($exportJson !== false)
                    {
                        header('Content-Type: application/json');
                        die($exportJson);
                    }
                    
                }

            }
            catch (\Throwable $e)
            { }
            Auth::replyErrorServer();
            break;
        
        case 'WIM-IMPORT':
        
            $reasonMsg = 'Fehler im WIM';
            try
            {

                // check fileupload is ok
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) 
                {
                    
                    // check if only one file is uploaded
                    if (!is_array($_FILES['file']['tmp_name'])) 
                    {
                        
                        $fileType = $_FILES['file']['type'];
                        $fileName = $_FILES['file']['name'];
                        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                
                        // Verify file type as JSON
                        if ($fileType === 'application/json' && $fileExtension === 'json') 
                        {
                            
                            $filepath = $_FILES['file']['tmp_name'];
                            $content = \file_get_contents($filepath);
                            if ($content  !== false)
                            {

                                $jsondata = \json_decode ($content, true);

                                $entries = new Entries();
                                $settings = new Settings();
                                $users = new Users();
                                
                                // import
                                if ($entries->LoadImport($jsondata['entries']) &&
                                    $settings->LoadImport($jsondata['settings']) &&
                                    $users->LoadImport($jsondata['users']))
                                {

                                    Auth::redirectToAdminWithMessage("{
                                        title: 'Neustart empfohlen',
                                        description: 'Um die Einstellungen zu übernehmen, sollte das WIM neugestartet werden (Je nachdem, welche Daten importiert wurden). Aus Sicherheitsgründen funktioniert das aber nicht von der Admin-Oberfläche aus. <br><br>Zieh am Besten kurz den Stecker 😁',
                                        showWarning: false,
                                        mode: 'ok',
                                        actionPositive: null
                                    }", 'settings');

                                } else {
                                    $reasonMsg = 'Abbruch beim Import / Daten unvollständig';
                                }
                                

                            } else {
                                $reasonMsg = 'Datei nicht lesbar';
                            }

                        } else {
                            $reasonMsg = 'Keine JSON-Datei';
                        }
                    } else {
                        $reasonMsg = 'Mehrere Dateien hochgeladen';
                    }
                } else {
                    $reasonMsg = 'Fehler beim Upload';
                }

            }
            catch (\Throwable $e) {  }

            Auth::redirectToAdminWithMessage("{
                title: 'Fehler beim Import',
                description: 'Etwas ist schiefgelaufen: ($reasonMsg). War es die richtige Datei?',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'settings');

            break;
    }

///////////////////////////////////////////////////////////////////////////////////////////////////
// ITEM-EDIT-ENDPOINT

    switch ($paramAction) {
        case 'ITEM-DELETE':
            Auth::blockInvalidSession();

            $itemId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
            $typeTag = filter_input(INPUT_POST, 'typetag', FILTER_SANITIZE_STRING);
            $typeTag = $typeTag === false ? '' : strtolower($typeTag);
            
            if ($itemId !== null && $itemId !== false)
            {
                $entries = new Entries();
                if ($entries->DeleteById($itemId))
                {
                    Auth::redirectToAdmin($typeTag);
                }
            }

            Auth::redirectToAdminWithMessage("{
                title: 'Fehler beim Löschen',
                description: 'Der Eintrag konnte nicht gelöscht werden. Frag bitte beim Standortverantwortlichen nach.',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", $typeTag);
            break;
            break;

        case 'ITEM-EDIT':
            Auth::blockInvalidSession();

            // get item-info
            $typeTag = filter_input(INPUT_POST, 'typetag', FILTER_SANITIZE_STRING);
            $itemId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
            if ($itemId !== null && $itemId !== false && TypeTag::IsValidType($typeTag))
            {
                $itemId = (int)$itemId;

                switch ($typeTag)
                {
                    case TypeTag::INFO:
                        
                        $payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
                        $dateStart = filter_input(INPUT_POST, 'dateStart', FILTER_SANITIZE_STRING);
                        $dateEnd = filter_input(INPUT_POST, 'dateEnd', FILTER_SANITIZE_STRING);
                        if (Validation::IsPayloadValid($payload))
                        {

                            $withDate = Validation::IsDateValid($dateStart) && Validation::IsDateValid($dateEnd);
                            if ($itemId == -1) { $itemId = false; }

                            $entries = new Entries();
                            if ($entries->EditInfo($itemId, $payload, $withDate, $dateStart, $dateEnd))
                            {
                                Auth::redirectToAdmin('info');
                            }

                        }
                        break;

                    case TypeTag::TASK:

                        $payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
                        $dateStart = filter_input(INPUT_POST, 'dateStart', FILTER_SANITIZE_STRING);
                        $dateEnd = filter_input(INPUT_POST, 'dateEnd', FILTER_SANITIZE_STRING);
                        $timeStart = filter_input(INPUT_POST, 'timeStart', FILTER_SANITIZE_STRING);
                        $timeEnd = filter_input(INPUT_POST, 'timeEnd', FILTER_SANITIZE_STRING);
                        $showUpcoming = filter_input(INPUT_POST, 'showAsEvent', FILTER_SANITIZE_STRING) == 'on';

                        if (Validation::IsPayloadValid($payload) && Validation::IsDateValid($dateEnd) && Validation::IsTimeValid($timeEnd))
                        {

                            if ($itemId == -1) { $itemId = false; }

                            $entries = new Entries();
                            if ($entries->EditTask($itemId, $payload, $dateStart, $dateEnd, $timeStart, $timeEnd, $showUpcoming))
                            {
                                Auth::redirectToAdmin('task');
                            }

                        }
                        break;

                    case TypeTag::EVENT:

                        $payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
                        $dateStart = filter_input(INPUT_POST, 'dateStart', FILTER_SANITIZE_STRING);
                        $dateEnd = filter_input(INPUT_POST, 'dateEnd', FILTER_SANITIZE_STRING);
                        $timeStart = filter_input(INPUT_POST, 'timeStart', FILTER_SANITIZE_STRING);
                        $timeEnd = filter_input(INPUT_POST, 'timeEnd', FILTER_SANITIZE_STRING);
                        if (Validation::IsPayloadValid($payload) && Validation::IsDateValid($dateStart))
                        {

                            $withTime = Validation::IsTimeValid($timeStart) || Validation::IsTimeValid($timeEnd);
                            if ($itemId == -1) { $itemId = false; }

                            $entries = new Entries();
                            if ($entries->EditEvent($itemId, $payload, $dateStart, $dateEnd, $timeStart, $timeEnd, $withTime, $withTime))
                            {
                                Auth::redirectToAdmin('task');
                            }

                        }
                        break;

                    case TypeTag::RECURRING:

                        $payload = filter_input(INPUT_POST, 'payload', FILTER_UNSAFE_RAW);
                        $timeStart = filter_input(INPUT_POST, 'timeStart', FILTER_SANITIZE_STRING);
                        $timeEnd = filter_input(INPUT_POST, 'timeEnd', FILTER_SANITIZE_STRING);
                        $cycleMode = filter_input(INPUT_POST, "cyclemode", FILTER_VALIDATE_INT);

                        $weekday = filter_input(INPUT_POST, "weekday", FILTER_VALIDATE_INT);
                        $dom = filter_input(INPUT_POST, "dayofmonth", FILTER_VALIDATE_INT);

                        if (Validation::IsPayloadValid($payload) && Validation::IsTimeValid($timeStart) && Validation::IsTimeValid($timeEnd) && $cycleMode !== false &&
                            ($cycleMode == 0 || ($cycleMode == 1 && $weekday !== null) || ($cycleMode == 2 && $dom !== null) || $cycleMode == 3 || ($cycleMode == 4 && $weekday !== null)))
                        {
                            if ($itemId == -1) { $itemId = false; }

                            $entries = new Entries();
                            if ($entries->EditRecurring($itemId, $payload, $timeStart, $timeEnd, $cycleMode, $weekday, $dom))
                            {
                                Auth::redirectToAdmin('recurring');
                            }
                            
                        }
                        break;

                }

            }

            Auth::redirectToAdminWithMessage("{
                title: 'Fehler beim Hinzufügen',
                description: 'Der Eintrag konnte nicht hinzugefügt werden. Waren z.B. komische Zeichen in den Texten?',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'info');
            break;

        case 'HIDE-ITEM':

            $hiddenId = filter_input(INPUT_POST, "hiddenid", FILTER_VALIDATE_INT);
            $hiddenDate = filter_input(INPUT_POST, "hiddendate", FILTER_SANITIZE_STRING);
            if ($hiddenId == true && Validation::IsDateValid($hiddenDate))
            {
                if ($itemId === -1) { $itemId = false; }

                // get hidden-list
                $settings = new Settings();
                $hidden = $settings->GetHiddenEntries();
                $currentDate = new \DateTime();

                // filter existing keys
                foreach ($hidden as $key => $value) {
                    $date = \DateTime::createFromFormat('Y-m-d', $key);  // Convert the key to DateTime object

                    if ($date < $currentDate) {
                        unset($hidden[$key]);  // Delete the key-value pair
                    }
                }

                // create if date not exists
                if (!isset($hidden[$hiddenDate])) 
                { $hidden[$hiddenDate] = []; }

                // create entry
                if (in_array($hiddenId, $hidden[$hiddenDate]))
                { 
                    $toRemove = \array_search($hiddenId, $hidden[$hiddenDate]);
                    \array_splice($hidden[$hiddenDate], $toRemove); }
                else
                { $hidden[$hiddenDate][] = $hiddenId; }

                // save
                if ($settings->Set(Settings::AdmHiddenEntries, \json_encode($hidden)))
                {
                    Auth::redirectToAdmin('recurring');
                }

            }
            Auth::redirectToAdminWithMessage("{
                title: 'Fehler beim Ausblenden',
                description: 'Der Eintrag konnte nicht den Ausgeblendeten Elementen hinzugefügt werden. Versuch es nochmal, ansonsten gib eine Info an den Wachenverantwortlichen.',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'recurring');
            break;
    
    }

///////////////////////////////////////////////////////////////////////////////////////////////////
// DEFAULT */
Auth::replyErrorBadRequest();

class Validation 
{

    public static function IsPayloadValid(?string $payload)
    {
        try
        {
            if ($payload == null) { return false; }
            $decode = \json_decode($payload);
            return isset($decode->{'title'}) && is_string($decode->title) && $decode->title !== '';
        }
        catch (\Exception $e) { return false; }
    }

    public static function IsDateValid(?string $date)
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
    public static function IsTimeValid(?string $time)
    {
        return is_string($time) && preg_match('/^\d{2}:\d{2}$/', $time);
    }

    public static function IsSqlDateValid($dateTimeString)
    {
        $dateTimeFormat = 'Y-m-d H:i:s';
        $dateTimeObject = \DateTime::createFromFormat($dateTimeFormat, $dateTimeString);
        return $dateTimeObject && $dateTimeObject->format($dateTimeFormat) === $dateTimeString;
    }

}