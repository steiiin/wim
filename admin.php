<?php

// Session prüfen
require_once 'php-auth.php';
if (!checkAdminSession()) {redirectToLogin();}
require_once 'php-db.php';

// Klassen instanzieren
$settings = new Settings();
$usersManager = new UsersManager();
$entriesManager = new EntriesManager();
            
if (!($entriesManager->isReady &&
      $usersManager->isReady &&
      $settings->isReady)) { die('Fehler beim Datenabruf. Kontaktiere den WIM-Verantwortlichen.'); }

?>

<!doctype html>
<html lang="de">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <link href="res/theme.css" type="text/css" rel="stylesheet">
    <link href="res/theme-admin.css" type="text/css" rel="stylesheet">
    <link href="ui-resolution.php?res=default" type="text/css;charset=UTF-8" rel="stylesheet">

    <!-- Theme -->
    <link rel="preload" href="res/theme-light.css" as="style" />
    <link id="css-theme-variables" href="res/theme-light.css" type="text/css" rel="stylesheet">

    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">

    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="assets/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">

    <!-- Meta -->
    <title>WIM-Admin</title>

    <!-- Startup -->
    <script src='bin/ui.js'></script>

    <script type="text/javascript">

        function startUp() {

            editors.toggleInit("entries-<?=TypeTag::INFO?>-anchor");
            editors.toggleInit("entries-<?=TypeTag::EVENT?>-anchor");
            editors.toggleInit("entries-<?=TypeTag::UNIQUETASK?>-anchor");
            editors.toggleInit("entries-<?=TypeTag::CYCLEDTASK?>-anchor");

        }

        function eventResize() {

            editors.calculateEditorPosition();
            editors.calculateMessagePosition();

        }

        window.onload = startUp;
        window.onresize = eventResize;

    </script>

</head>
<body id="adminpage">

    <!-- HEADER -->
    <div id="header">
        <h1>WIM|editor [<span>@<?=$_SESSION['LoginUser'].($_SESSION['WimAdmin']?($_SESSION['LoginUser'] === "admin" ? "" : "/Admin"):"");?></span>]</h1>
        <div class="header-info-area admin-header-info-area">
            <a onclick="editors.editorAccountCreate();" class="account">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M3 5v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2H5c-1.11 0-2 .9-2 2zm12 4c0 1.66-1.34 3-3 3s-3-1.34-3-3 1.34-3 3-3 3 1.34 3 3zm-9 8c0-2 4-3.1 6-3.1s6 1.1 6 3.1v1H6v-1z"/></svg>
            </a>
        </div>
    </div>

    <!-- EDITOR -->
    <div id="editorContainer" class="editorContainer">

        <!-- Account -->
        <div id="editorwindow-account" class="editorWindow">
            <form id="editor-account-form" action="api.php" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('account');">×</a>

                <input id="editor-id-account" name="id" type="hidden" value="-1">

                <h2>Mein Profil</h2>
                <h3 style="margin: 0 0 15px 0;">@<?=$_SESSION['LoginUser']?></h3>

                <p <?=($_SESSION['AllowChanges'] ? "" : "style='display:none;'")?>>Hier kannst du deine Daten ändern.</p>

                <div id="editor-account-actiontool" class="tools tools-full" tool-action="" style="margin-bottom: 10px; <?=($_SESSION['AllowChanges'] ? "" : "display:none;")?>" >

                    <button id="editor-account-action-user" type="button"
                        onclick="editors.setEditorToolArgs('editor-account-actiontool', 'tool-action', 'user'); editors.editorAccountValidation();">
                        <img src="res/ic_account_black.svg" style="width:20px;">
                        <span>Nutzerkennung ändern</span>
                    </button>

                    <button id="editor-account-action-password" type="button"
                        onclick="editors.setEditorToolArgs('editor-account-actiontool', 'tool-action', 'pass'); editors.editorAccountValidation();">
                        <img src="res/ic_btn_password.svg" style="width:20px">
                        <span>Passwort ändern</span>
                    </button>

                    <button id="editor-account-action-cancelcurrent" type="button"
                        onclick="editors.setEditorToolArgs('editor-account-actiontool', 'tool-action', ''); editors.editorAccountValidation();">
                        <img src="res/ic_btn_back.svg" style="width:20px">
                        <span>Zurück zum Menü</span>
                    </button>

                </div>

                <div id="editor-account-actioncontainer-user">

                    <input id="editor-account-input-user" name="user" placeholder="Neue Nutzerkennung" type="text"
                        oninput="editors.editorAccountValidation();">
                    <h3 id="editor-account-input-user-error" class="error"></h3>

                </div>
                <div id="editor-account-actioncontainer-password">

                    <input id="editor-account-input-oldpass" name="oldpass" placeholder="Altes Kennwort" type="password"
                        oninput="editors.editorAccountValidation();">

                    <input id="editor-account-input-pass1" name="newpass" placeholder="Neues Kennwort" type="password"
                        oninput="editors.editorAccountValidation();">

                    <input id="editor-account-input-pass2" placeholder="Neues Kennwort wiederholen" type="password"
                        oninput="editors.editorAccountValidation();">

                    <h3 id="editor-account-input-oldpass-error" class="error">Gib bitte das bisherige Passwort ein</h3>
                    <h3 id="editor-account-input-newpass-error" class="error">Die neuen Passwörter stimmen nicht überein</h3>

                </div>

                <div class="tools tools-full">
                    <a id="editor-account-action-certificate" class="link-button"
                        href="bin/wim.crt"
                        onclick="editors.editorAccountInvokeCertificateDownload();">
                        <img src="res/ic_action_download.svg" style="width:20px;">
                        <span>HTTPS-Zertifikat herunterladen</span>
                    </a>
                </div>

                <button id="editor-account-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    >Speichern</button>
                <button id="editor-account-action-logout" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                    onclick="editors.editorAccountInvokeLogout();">Abmelden</button>
            </form>
        </div>

        <!-- Benutzer -->
        <div id="editorwindow-user" class="editorWindow">
            <form id="editor-user-form" action="api.php?action=USER-EDIT" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('user');">×</a>

                <input id="editor-id-user" name="id" type="hidden" value="-1">

                <h2>Nutzer hinzufügen</h2>
                <p>Nutzer können sich im WIM anmelden und Änderungen vornehmen.</p>

                <input id="editor-user-input-loginuser" name="loginuser" placeholder="Nutzerkennung" type="text" 
                    oninput="editors.editorUserValidation();">
                <input id="editor-user-input-fullname" name="fullname" placeholder="Voller Name (Wird im WIM angezeigt)" type="text"
                    oninput="editors.editorUserValidation();">

                <hr/>
        
                <input type="checkbox" id="editor-user-input-wimadmin" name="wimadmin">
                <label class="checkbox-label" for="editor-user-input-wimadmin">Admin (Darf Benutzer erstellen &amp; ändern)</label><br>

                <button id="editor-user-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-user-action-passreset" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorUserInvokePassReset();">Passwort zurücksetzen</button>
                <button id="editor-user-action-deleteuser" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorUserInvokeDelete();">Nutzer entfernen</button>
            </form>
        </div>

        <!-- Einstellungen -->
        <div id="editorwindow-settings" class="editorWindow">
            <form id="editor-settings-form" action="api.php?action=SETTINGS" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('settings');">×</a>

                <h2>WIM-Einstellungen</h2>
                <h3 id="editor-settings-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Hier kannst du die Einstellungen dieses WIM festlegen.</p>

                <h3>Titel auf dem WIM-Bildschirm (z.B. Rettungswache Coswig)</h3>
                <input id="editor-settings-input-wachename" name="wachename" placeholder="Anzeigetitel" type="text" 
                    oninput="editors.editorSettingsValidation();">
                
                <h3>Auflösung des Anzeige-Bildschirms</h3>
                <select id="editor-settings-select-ui" style="width:auto;" name="ui"
                    onchange="editors.editorSettingsValidation();">
                    <?php
                        require_once 'ui-resolution.php';
                        echo GetStyleOptions();
                    ?>
                </select>

                <hr/>

                <h3>Inhalt für die Fahrzeugauswahl</h3>
                <textarea id="editor-settings-input-wachekfz" name="wachekfz" placeholder="Fahrzeuge (HTML)" rows="5" type="text"
                    oninput="editors.editorSettingsValidation();" style="margin-bottom: 0;">
                    <option value="RTW 1"> RTW 1 </option>
                    <option value="RTW 2"> RTW 2 </option>
                    <option value="RTW 3"> RTW 3 (Ersatz) </option>
                </textarea>
                <a href="#" onclick="editors.editorSettingsResetKfz();" style="font-size: 0.7em;color: #000;margin-bottom: 10px;display: block;">Fahrzeugauswahl zurücksetzen</a>

                <button id="editor-settings-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="editors.setVisibleEditor('messageDisableOverlay', true);">Speichern</button>
            </form>
        </div>

        <!-- Module - Einstellungen -->
        <div id="editorwindow-moduleAbfall" class="editorWindow">
            <form id="editor-moduleAbfall-form" action="api.php?action=SETTINGS-MODULE-ABFALL" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('moduleAbfall');">×</a>

                <h2>Abfallkalender - Einstellungen</h2>
                <h3 id="editor-moduleAbfall-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Hier kannst du den Link zum Abfallkalender ändern. Unterstützt wird allerdings nur ein Link der ZAOE.</p>

                <h3>Link zum elektronischen Abfallkalender</h3>
                <input id="editor-moduleAbfall-input-abfalllink" name="auto-abfalllink" placeholder="Url für Abfallkalender (https://www.zaoe.de)" type="text"
                    oninput="editors.editorModuleAbfallValidation();">

                <button id="editor-moduleAbfall-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="editors.setVisibleEditor('messageDisableOverlay', true);">Speichern</button>
            </form>
        </div>
        <div id="editorwindow-moduleMaltesercloud" class="editorWindow">
            <form id="editor-moduleMaltesercloud-form" action="api.php?action=SETTINGS-MODULE-MALTESER" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('moduleMaltesercloud');">×</a>

                <h2>Maltesercloud - Einstellungen</h2>
                <h3 id="editor-moduleMaltesercloud-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Hier kannst du die Zugangsdaten für das Sharepoint ändern.</p>

                <h3>Zugang zur MalteserCloud (Sharepoint)</h3>
                <input id="editor-moduleMaltesercloud-input-user" name="auto-malteseruser" placeholder="Benutzername (@malteser.org)" type="text"
                    oninput="editors.editorModuleMaltesercloudValidation();">
                <input id="editor-moduleMaltesercloud-input-pass" name="auto-malteserpass" placeholder="Neues Passwort" type="password">

                <button id="editor-moduleMaltesercloud-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="editors.setVisibleEditor('messageDisableOverlay', true);">Speichern</button>
            </form>
        </div>


        <!-- Entry: Info -->
        <div id="editorwindow-info" class="editorWindow">
            <form id="editor-form-info" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('info');">×</a>

                <input id="editor-id-info" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::INFO;?>">

                <h2>Mitteilung hinzufügen</h2>
                <p>Mitteilungen werden unter 'Aktuelle Informationen' angezeigt und haben kein Ablaufdatum.</p>
                
                <input id="editor-info-input-title" name="title" placeholder="Titel" type="text" list=entry-columns
                    oninput="editors.editorInfoValidation();">
                <input id="editor-info-input-subtitle" name="subtitle" placeholder="Beschreibung (optional)" type="text">

                <hr/>

                <h3 id="editor-info-datetime-header-start">In der Liste sichtbar ab:</h3>
                <input id="editor-info-datetime-input-start" type="date" style="width:auto;" name="dateStart"
                    oninput="editors.editorInfoValidation();">
                    
                <h3 id="editor-info-datetime-header-end">Wird aus der Liste entfernt nach:</h3>
                <input id="editor-info-datetime-input-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="editors.editorInfoValidation();">

                <div class="tools" id="editor-info-datetime-tool" tool-withdate="false">

                    <button id="editor-info-datetime-tool-withdate" type="button"
                        onclick="editors.setEditorToolArgs('editor-info-datetime-tool', 'tool-withdate', true); editors.editorInfoValidation();">
                        <img src="res/ic_daterange_black.svg" style="width:20px">
                        <span>Zeitraum festlegen</span>
                    </button>

                    <button id="editor-info-datetime-tool-nodate" type="button"
                        onclick="editors.setEditorToolArgs('editor-info-datetime-tool', 'tool-withdate', false); editors.editorInfoValidation();">
                        <img src="res/ic_notime_black.svg" style="width:20px">
                        <span>Dauerhaft Gültig</span>
                    </button>

                </div>

                <button id="editor-info-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-info-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorInvokeDelete('info');">Löschen</button>
            
            </form>
        </div>

        <!-- Entry: Event -->
        <div id="editorwindow-event" class="editorWindow">
            <form id="editor-form-event" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('event');">×</a>

                <input id="editor-id-event" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::EVENT?>">

                <h2>Termin hinzufügen</h2>
                <p>Termine werden rechts in der Terminagenda angezeigt - von Start- bis Enddatum werden diese unter
                    'Aktuelle Informationen' angezeigt.</p>

                <input id="editor-event-input-title" name="title" placeholder="Titel" type="text" list=entry-columns
                    oninput="editors.editorEventValidation();">
                <input id="editor-event-input-subtitle" name="subtitle" placeholder="Beschreibung (optional)" type="text">

                <hr>

                <h3 id="editor-event-datetime-header-start">Startdatum / -zeit</h3>
                <input id="editor-event-datetime-date-start" type="date" style="width:auto;" name="dateStart"
                    oninput="editors.editorEventValidation();">
                <input id="editor-event-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="editors.editorEventValidation();">
                <br>
                <h3 id="editor-event-datetime-header-end">Enddatum / -zeit</h3>
                <input id="editor-event-datetime-date-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="editors.editorEventValidation();">
                <input id="editor-event-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="editors.editorEventValidation();">

                <div class="tools" id="editor-event-tool-datetime" tool-daterange="false" tool-time="false">

                    <button id="editor-event-datetime-tools-startend" type="button"
                        onclick="editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-daterange', true); editors.editorEventValidation();">
                        <img src="res/ic_daterange_black.svg" style="width:20px">
                        <span>Start & Ende</span>
                    </button>

                    <button id="editor-event-datetime-tools-onlystart" type="button"
                        onclick="editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-daterange', false); editors.editorEventValidation();">
                        <img src="res/ic_onlystart_black.svg" style="width:20px">
                        <span>Nur Start</span>
                    </button>

                    <button id="editor-event-datetime-tools-withtime" type="button"
                        onclick="editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-time', true); editors.editorEventValidation();">
                        <img src="res/ic_addtime_black.svg" style="width:26px;margin: -2px 0 0 -1px;">
                        <span>Mit Uhrzeit</span>
                    </button>

                    <button id="editor-event-datetime-tools-notime" type="button"
                        onclick="editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-time', false); editors.editorEventValidation();">
                        <img src="res/ic_notime_black.svg" style="width:20px">
                        <span>Ohne Uhrzeit</span>
                    </button>

                </div>

                <button id="editor-event-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-event-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorInvokeDelete('event');">Löschen</button>

            </form>
        </div>

        <!-- Entry: UniqueTask -->
        <div id="editorwindow-uniquetask" class="editorWindow">
            <form id="editor-form-uniquetask" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('uniquetask');">×</a>

                <input id="editor-id-uniquetask" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::UNIQUETASK;?>">

                <h2>Einzelne Aufgabe hinzufügen</h2>
                <p>Die Aufgaben werden in der "Zu Erledigen"-Liste angezeigt, bis das Ablaufdatum erreicht wurde.
                    Zusätzlich kann ein Datum angegeben werden, ab dem die Aufgabe angezeigt wird.</p>
                
                <input id="editor-uniquetask-input-title" name="title" placeholder="Titel" type="text" list=entry-columns 
                    oninput="editors.editorUniqueTaskValidation();">
                <input id="editor-uniquetask-input-subtitle" name="subtitle" placeholder="Beschreibung (optional)" type="text">

                <select id="editor-uniquetask-select-vehicle" style="width:auto;" name="vehicle"
                    onchange="editors.editorUniqueTaskValidation();">
                    <option value=""> Kein Fahrzeug </option>
                    <?= urldecode($settings->GetWacheKfz()); ?>
                </select>

                <hr>

                <h3 id="editor-uniquetask-datetime-header-start">In der Liste sichtbar ab:</h3>
                <input id="editor-uniquetask-datetime-date-start" type="date" style="width:auto;" name="dateStart"
                    oninput="editors.editorUniqueTaskValidation();">
                <input id="editor-uniquetask-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="editors.editorUniqueTaskValidation();">
                <div id="editor-uniquetask-datetime-beforeEvent-bound">
                    <input id="editor-uniquetask-datetime-beforeEvent" type="checkbox" name="showAsEvent"
                        onchange="editors.editorUniqueTaskValidation();">
                    <label for="editor-uniquetask-datetime-beforeEvent" class="checkbox-label">Vorher als Termin
                        anzeigen</label>
                </div>
                <h3 id="editor-uniquetask-datetime-header-end">Zu Erledigen bis:</h3>
                <input id="editor-uniquetask-datetime-date-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="editors.editorUniqueTaskValidation();">
                <input id="editor-uniquetask-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="editors.editorUniqueTaskValidation();">

                <div class="tools" id="editor-uniquetask-tool-datetime" tool-daterange="false">

                    <button id="editor-uniquetask-datetime-tools-startend" type="button"
                        onclick="editors.setEditorToolArgs('editor-uniquetask-tool-datetime', 'tool-daterange', true); editors.editorUniqueTaskValidation();">
                        <img src="res/ic_daterange_black.svg" style="width:20px">
                        <span>Mit Startdatum</span>
                    </button>

                    <button id="editor-uniquetask-datetime-tools-onlyend" type="button"
                        onclick="editors.setEditorToolArgs('editor-uniquetask-tool-datetime', 'tool-daterange', false); editors.editorUniqueTaskValidation();">
                        <img src="res/ic_onlystart_black.svg" style="width:20px">
                        <span>Ohne Startdatum</span>
                    </button>

                </div>

                <button id="editor-uniquetask-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-uniquetask-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorInvokeDelete('uniquetask');">Löschen</button>
            </form>
        </div>

        <!-- Entry: CycledTask -->
        <div id="editorwindow-cycledtask" class="editorWindow">
            <form id="editor-form-cycledtask" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close"  onclick="editors.closeEditor('cycledtask');">×</a>

                <input id="editor-id-cycledtask" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::CYCLEDTASK?>">

                <h2>Tagesaufgabe hinzufügen</h2>
                <p>Tagesaufgaben werden in der "Zu Erledigen"-Liste angezeigt. Dabei wird kein festes Datum angegeben,
                    sondern nach einem Schema automatisch wiederholt.</p>
 
                <input id="editor-cycledtask-input-subtitle" name="subtitle" placeholder="Beschreibung"
                    oninput="editors.editorCycledTaskValidation();" type="text">
                <select id="editor-cycledtask-select-vehicle" style="width:auto;" name="vehicle"
                    onchange="editors.editorCycledTaskSetVehicleTiming(); editors.editorCycledTaskValidation();">
                    <option value=""> Kein Fahrzeug </option>
                    <?= urldecode($settings->GetWacheKfz()); ?>
                </select>

                <hr />

                <select id="editor-cycledtask-cyclemode-weekly-select" style="width:auto;" name="weekday"
                    onchange="editors.editorCycledTaskValidation();">
                    <option value="1"> Montag </option>
                    <option value="2"> Dienstag </option>
                    <option value="3"> Mittwoch </option>
                    <option value="4"> Donnerstag </option>
                    <option value="5"> Freitag </option>
                    <option value="6"> Samstag </option>
                    <option value="0"> Sonntag </option>
                </select>
                <select id="editor-cycledtask-cyclemode-monthly-select" style="width:auto;" name="dayofmonth"
                    onchange="editors.editorCycledTaskValidation();">
                    <option value="1"> Ersten Tag (1.) </option>
                    <option value="-1"> Letzten Tag </option>
                    <option value="-2"> Letzten Freitag </option>
                    <option value="2"> 2. </option>
                    <option value="3"> 3. </option>
                    <option value="4"> 4. </option>
                    <option value="5"> 5. </option>
                    <option value="6"> 6. </option>
                    <option value="7"> 7. </option>
                    <option value="8"> 8. </option>
                    <option value="9"> 9. </option>
                    <option value="10"> 10. </option>
                    <option value="11"> 11. </option>
                    <option value="12"> 12. </option>
                    <option value="13"> 13. </option>
                    <option value="14"> 14. </option>
                    <option value="15"> 15. </option>
                    <option value="16"> 16. </option>
                    <option value="17"> 17. </option>
                    <option value="18"> 18. </option>
                    <option value="19"> 19. </option>
                    <option value="20"> 20. </option>
                    <option value="21"> 21. </option>
                    <option value="22"> 22. </option>
                    <option value="23"> 23. </option>
                    <option value="24"> 24. </option>
                    <option value="25"> 25. </option>
                    <option value="26"> 26. </option>
                    <option value="27"> 27. </option>
                    <option value="28"> 28. </option>
                    <option value="29"> 29. (fällt sonst aus) </option>
                    <option value="30"> 30. (fällt sonst aus) </option>
                    <option value="31"> 31. (fällt sonst aus) </option>
                </select>

                <input id="editor-cycledtask-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="editors.editorCycledTaskValidation();">
                <input id="editor-cycledtask-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="editors.editorCycledTaskValidation();">

                <h3 id="editor-cycledtask-cyclemode-header" style="margin:3px 0 10px 1px;"></h3>

                <input id="editor-cycledtask-input-cyclemode" name="cyclemode" value="week" type="hidden">
                <div class="tools" id="editor-cycledtask-tool-cyclemode" tool-mode="week" style="border-top:1px solid;">

                    <button id="editor-cycledtask-tool-cyclemode-daily" type="button"
                        onclick="editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'daily'); editors.editorCycledTaskSetVehicleTiming(); editors.editorCycledTaskValidation();">
                        <img src="res/ic_addtime_black.svg" style="width:20px">
                        <span>Täglich</span>
                    </button>

                    <button id="editor-cycledtask-tool-cyclemode-weekly" type="button"
                        onclick="editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'week'); editors.editorCycledTaskSetVehicleTiming(); editors.editorCycledTaskValidation();">
                        <img src="res/ic_daterange_black.svg" style="width:20px">
                        <span>Wöchentlich</span>
                    </button>

                    <button id="editor-cycledtask-tool-cyclemode-monthly" type="button"
                        onclick="editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'month'); editors.editorCycledTaskSetVehicleTiming(); editors.editorCycledTaskValidation();">
                        <img src="res/ic_onlystart_black.svg" style="width:20px">
                        <span>Monatlich</span>
                    </button>

                </div>

                <button id="editor-cycledtask-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-cycledtask-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="editors.editorInvokeDelete('cycledtask');">Löschen</button>

            </form>
        </div>

        <!-- Template: Fahrzeugtausch > UniqueTask | Event -->
        <div id="editorwindow-templatekfz" class="editorWindow">
            <form id="editor-form-templatekfz" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('templatekfz');">×</a>

                <input name="id" type="hidden" value="-1">
                <input id="editor-templatekfz-typetag" name="typetag" type="hidden" value="<?=TypeTag::UNIQUETASK;?>">

                <input id="editor-templatekfz-hidden-title" name="title" value="" type="hidden">
                <input id="editor-templatekfz-hidden-subtitle" name="subtitle" value="" type="hidden">

                <h2>Vorlage: Fahrzeugtausch &amp; MDR</h2>
                <p>Bei einem geplanten Fahrzeugtausch kann hier das Datum angegeben werden - die Aufgabe wird dann automatisch erstellt. Für den Reserve-RTW wird nur ein Termin erzeugt.</p>
                
                <h3>Welches Fahrzeug?</h3>
                <select id="editor-templatekfz-select-vehicle" style="width:auto;" name="vehicle"
                    onchange="editors.editorTemplateKfzValidation();">
                    <?= urldecode($settings->GetWacheKfz()); ?>
                </select>

                <h3>Warum?</h3>
                <select id="editor-templatekfz-select-reason" style="width:auto;"
                    onchange="editors.editorTemplateKfzValidation();">
                    <option value="MDR"> Monatsdesinfektion </option>
                    <option value="Werkstatt"> Werkstatt </option>
                </select>

                <hr>

                <input name="dateStart" id="editor-templatekfz-hidden-date-start" value="" type="hidden">
                <input name="timeStart" id="editor-templatekfz-hidden-time-start" value="" type="hidden">
                <input name="dateEnd" id="editor-templatekfz-hidden-date-end" value="" type="hidden">
                <input name="timeEnd" id="editor-templatekfz-hidden-time-end" value="" type="hidden">

                <input name="showAsEvent" value="on" type="hidden">

                <h3>An welchem Tag?</h3>
                <input id="editor-templatekfz-datetime-date" type="date" style="width:auto;"
                    oninput="editors.editorTemplateKfzValidation();">

                <button id="editor-templatekfz-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
            </form>
        </div>

        <!-- Template: Termin & Vorbereitung > UniqueTask & Event -->
        <div id="editorwindow-templateevwt" class="editorWindow">
            <form id="editor-form-templateevwt" action="admin.php#entries-TEMPLATE-anchor" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('templateevwt');">×</a>

                <h2>Vorlage: Termin &amp; Vorbereitung</h2>
                <p>Für manche Termine muss im Vorfeld eine Aufgabe erledigt werden, z.B. Brandschutzschulung - und davor den Parkplatz räumen. Um nicht zwei Einträge für ein Ereignis zu erstellen, kannst du das hier verbinden. Es wird ein Termin erstellt und eine einzelne Aufgabe vor dem Termin.</p>
                
                <h3>Angaben zum Termin</h3>
                <input id="editor-templateevwt-input-event" placeholder="Termintitel (z.B. Brandschutzschulung)"
                    oninput="editors.editorTemplateEvWTValidation();" type="text">
                <input id="editor-templateevwt-input-eventsub" placeholder="Beschreibung (Optional)"
                    type="text">

                <input id="editor-templateevwt-datetime-date" type="date" style="width:auto;" 
                    oninput="editors.editorTemplateEvWTValidation();">
                <input id="editor-templateevwt-datetime-time" type="time" style="width:auto;" 
                    oninput="editors.editorTemplateEvWTValidation();">

                <hr>

                <h3>Welche Vorbereitung muss vor dem Termin erfolgen?</h3>
                <input id="editor-templateevwt-input-task" placeholder="Aufgabe (z.B. Der Parkplatz ist bis Beginn zu räumen.)"
                    oninput="editors.editorTemplateEvWTValidation();" type="text">

                <h3>Wie viel Stunden vorher soll die Aufgabe angezeigt werden?</h3>
                <input id="editor-templateevwt-input-projection" type="number" style="width:auto;" 
                    oninput="editors.editorTemplateEvWTValidation();">

                <button id="editor-templateevwt-btn-save" class="btn btn-input" type="button" style="margin-top:10px;"
                    onclick="editors.editorTemplateEvWTInvokeSubmit();">Hinzufügen</button>
            </form>
        </div>

        <!-- Template: Tagesaufgabe ersetzen > CycledTask & UniqueTask -->
        <div id="editorwindow-templatebusy" class="editorWindow">
            <form id="editor-form-templatebusy" action="admin.php#entries-TEMPLATE-anchor" method="post" name="form">
                <a class="close" onclick="editors.closeEditor('templatebusy');">×</a>

                <h2>Vorlage: Tagesaufgabe ersetzen</h2>
                <p>Sollte z.B. die Wäsche an einem anderen Tag abgeholt werden, kann hier die Tagesaufgabe eines bestimmten Datums deaktiviert und bei Bedarf mit einem einzelnem Termin ersetzt werden.</p>
                
                <input id="editor-templatebusy-tool-mode" name="mode" value="none" type="hidden">

                <input id="editor-id-templatebusy" name="id" type="hidden" value="-1">
                <input id="editor-templatebusy-input-replacedid" name="replacedId" value="-1" type="hidden">

                <h3 id="editor-templatebusy-hr-search-header">Tag, an dem die Tagesaufgabe ausfällt</h3>
                <input id="editor-templatebusy-datetime-date" type="date" style="width:auto;" 
                    oninput="editors.editorTemplateBusyValidation();">
                <button id="editor-templatebusy-btn-searchtask" class="btn btn-input" type="button" style="width: auto !important; padding: 12px !important; display: inline-block;"
                    onclick="editors.editorTemplateBusyInvokeSearch();">Aufgabe suchen</button>

                <section>
                    <ul id="editor-templatebusy-searchresult" class="group searchresult-group"></ul>
                </section>

                <hr id="editor-templatebusy-hr-resultdiv">

                <h3 id="editor-templatebusy-hr-replace-header" style="margin-top: 10px;">Gewählte Tagesaufgabe ersetzen durch:</h3>

                <input id="editor-templatebusy-input-title" name="title" placeholder="Titel" type="text"
                    oninput="editors.editorTemplateBusyValidation();">
                <input id="editor-templatebusy-input-subtitle" name="subtitle" placeholder="Beschreibung (optional)" type="text">

                <h3 id="editor-templatebusy-datetime-header-start">Tagesaufgabe von / bis:</h3>
                <input id="editor-templatebusy-datetime-date-start" type="date" style="width:auto;" name="dateStart"
                    oninput="editors.editorTemplateBusyValidation();">
                <input id="editor-templatebusy-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="editors.editorTemplateBusyValidation();">
                <input id="editor-templatebusy-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="editors.editorTemplateBusyValidation();">

                <button id="editor-templatebusy-btn-save" class="btn btn-input" type="button" style="margin-top:10px;"
                    onclick="editors.editorTemplateBusyInvokeSubmit();">Hinzufügen</button>
                <button id="editor-templatebusy-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                    onclick="editors.editorTemplateBusyInvokeDelete();">Löschen</button>
            </form>
        </div>

    </div>

    <!-- LAYOUT -->
    <section style="max-width:900px;margin:0 auto;">

        <!-- Vorlagen -->
        <div class="group">
            <h2 id="entries-template-anchor" class="nopointer">Vorlagen</h2>
            <div class="tools tools-expand">
                <button onclick="editors.editorTemplateKfzCreate();">
                    <img src="res/ic_template_kfz.svg">
                    <span>Fahrzeugtausch &amp; MDR</span>
                </button>
                <button onclick="editors.editorTemplateEvWTCreate();">
                    <img src="res/ic_template_evwt.svg">
                    <span>Termin &amp; Vorbereitung</span>
                </button>
                <button onclick="editors.editorTemplateBusyCreate();">
                    <img src="res/ic_template_busy.svg">
                    <span>Tagesaufgabe verschieben</span>
                </button>
            </div>
            <ul style="height:10px;"></ul>
        </div>

        <!-- Einträge -->
        <?php

            $html = "";
            
            // Gruppe: INFO
            $list = $entriesManager->GenerateHTML(RequestType::ONLYINFO, true);

            $html .= "<div class=\"group\">";
            $html .= "  <h2".($list==""?" class=\"nopointer\"":"")." id=\"entries-".TypeTag::INFO."-anchor\"".($list==""?">":" onclick=\"editors.toggleExpand(this);\"><span class=\"arrow\">&nbsp;</span>")."Mitteilungen</h2>";
            $html .= "  <div class=\"tools\">";
            $html .= "    <button onclick=\"editors.editorInfoCreate();\">";
            $html .= "      <img src=\"res/ic_add_white.svg\">";
            $html .= "      <span>Mitteilung</span>";
            $html .= "    </button>";
            $html .= "  </div>";
            $html .= "  <ul id=\"entries-info-list\" style=\"display:none;\">{$list}</ul>";
            $html .= "</div>";

            // Gruppe: EVENT
            $list = $entriesManager->GenerateHTML(RequestType::ONLYEVENTS, true);

            $html .= "<div class=\"group\">";
            $html .= "  <h2".($list==""?" class=\"nopointer\"":"")." id=\"entries-".TypeTag::EVENT."-anchor\"".($list==""?">":" onclick=\"editors.toggleExpand(this);\"><span class=\"arrow\">&nbsp;</span>")."Termine</h2>";
            $html .= "  <div class=\"tools\">";
            $html .= "    <button onclick=\"editors.editorEventCreate();\">";
            $html .= "      <img src=\"res/ic_add_white.svg\">";
            $html .= "      <span>Termin hinzufügen</span>";
            $html .= "    </button>";
            $html .= "  </div>";
            $html .= "  <ul id=\"entries-event-list\" style=\"display:none;\">{$list}</ul>";
            $html .= "</div>";

            // Gruppe: UNIQUETASK
            $list = $entriesManager->GenerateHTML(RequestType::UNIQUETASK, true);

            $html .= "<div class=\"group\">";
            $html .= "  <h2".($list==""?" class=\"nopointer\"":"")." id=\"entries-".TypeTag::UNIQUETASK."-anchor\"".($list==""?">":" onclick=\"editors.toggleExpand(this);\"><span class=\"arrow\">&nbsp;</span>")."Einzelne Aufgabe</h2>";
            $html .= "  <div class=\"tools\">";
            $html .= "    <button onclick=\"editors.editorUniqueTaskCreate();\">";
            $html .= "      <img src=\"res/ic_add_white.svg\">";
            $html .= "      <span>Einzelne Aufgabe</span>";
            $html .= "    </button>";
            $html .= "  </div>";
            $html .= "  <ul id=\"entries-uniquetask-list\" style=\"display:none;\">{$list}</ul>";
            $html .= "</div>";
            
            // Gruppe: CYCLEDTASK
            $list = $entriesManager->GenerateHTML(RequestType::CYCLEDTASK, true);

            $html .= "<div class=\"group\">";
            $html .= "  <h2".($list==""?" class=\"nopointer\"":"")." id=\"entries-".TypeTag::CYCLEDTASK."-anchor\"".($list==""?">":" onclick=\"editors.toggleExpand(this);\"><span class=\"arrow\">&nbsp;</span>")."Tagesaufgaben</h2>";
            $html .= "  <div class=\"tools\">";
            $html .= "    <button onclick=\"editors.editorCycledTaskCreate();\">";
            $html .= "      <img src=\"res/ic_add_white.svg\">";
            $html .= "      <span>Wiederkehrende Aufgabe</span>";
            $html .= "    </button>";
            $html .= "  </div>";
            $html .= "  <ul id=\"entries-cycledtask-list\" style=\"display:none;\">{$list}</ul>";
            $html .= "</div>";
            
            echo $html;

        ?>

        <!-- Benutzer -->
        <?php

            $html = "";
            if ($_SESSION['WimAdmin']) {

                $html .= "<div class=\"group\" style=\"margin-top:20px;\">";
                $html .= "  <h2 id=\"entries-users-anchor\" class=\"nopointer\">WIM - Benutzer &amp; Einstellungen</h2>";
                $html .= "  <div class=\"tools\">";
                $html .= "    <button onclick=\"editors.editorUserCreate();\">";
                $html .= "      <img src=\"res/ic_add_white.svg\">";
                $html .= "      <span>Neuer Benutzer</span>";
                $html .= "    </button>";
                $html .= "    <button onclick=\"editors.editorSettingsEdit('".($settings->GetMetaLastUpdate())."', '".($settings->GetMetaLastUser())."', '".($settings->GetWacheName())."', '".($settings->GetWacheUiResolution())."', '".($settings->GetWacheKfz())."')\">";
                $html .= "      <img src=\"res/ic_settings_white.svg\">";
                $html .= "      <span>Einstellungen</span>";
                $html .= "    </button>";
                $html .= "  </div>";
                $html .= "  <ul id=\"entries-users-list\">".$usersManager->GenerateHTML()."</ul>";
                $html .= "</div>";
                
            }
            echo $html;

        ?>

        <!-- Kalendermodule -->
        <?php

            $html = "";
            if ($_SESSION['WimAdmin']) {

                $html .= "<div class=\"group\">";
                $html .= "  <h2>Module</h2>";
                $html .= "  <div class=\"tools\">";
                $html .= "    <button onclick=\"editors.editorModuleAbfallEdit('".($settings->GetMetaLastUpdate())."', '".($settings->GetMetaLastUser())."', '".($settings->GetAutoAbfallLink())."');\">";
                $html .= "      <img src=\"res/ic_settings_white.svg\">";
                $html .= "      <span>Abfallkalender</span>";
                $html .= "    </button>";
                $html .= "    <button onclick=\"editors.editorModuleMaltesercloudEdit('".($settings->GetMetaLastUpdate())."', '".($settings->GetMetaLastUser())."', '".($settings->GetAutoMalteserUser())."');\">";
                $html .= "      <img src=\"res/ic_settings_white.svg\">";
                $html .= "      <span>MalteserCloud</span>";
                $html .= "    </button>";
                $html .= "  </div>";
                $html .= "  <ul>";
                $html .= $entriesManager->GenerateMetaAutoAbfall(); 
                $html .= "  </ul>";
                $html .= "  <ul>";
                $html .= $entriesManager->GenerateMetaAutoMaltesercloudEvents(); 
                $html .= "  </ul>";
                $html .= "</div>";
                
            }
            echo $html;

        ?>

    </section>

    <!-- MESSAGEBOX -->
    <div id="messageContainer" class="editorContainer">
        <div id="messagewindow" class="editorWindow">
            <form id="message-positive-form" action="#positive" method="post">
                <img id="message-icon-warn" src="res/ic_warn_black.svg" style="height: 60px; display: block; float:right;">
                
                <h2 id="message-title">---</h2>
                <h3 id="message-subtitle" style="margin: 0 0 15px 0;">---</h3>

                <p id="message-message">---</p>

                <button id="message-positive-btn" class="btn btn-input" type="button" style="margin-top:10px;"
                        onclick="editors.messagePositiveAction();">OK</button>
                <button id="message-negative-btn" class="btn btn-input" type="button" style="background-color:#333 !important; margin-top:5px;"
                        onclick="editors.messageNegativeAction();">Abbrechen</button>
            </form>
        </div>
    </div>
    <div id="messageDisableOverlay" class="editorContainer">&nbsp;</div>

</body>

<script type="text/javascript"><?php

    // WimFirstAccess: Zum Passwort-Ändern auffordern
    if ($_SESSION['WimFirstAccess'] && !isset($_SESSION['WimFirstAccessNagged'])) {

        echo "editors.messageOpen('Neues Passwort?', null, 'Dein Passwort wurde zurückgesetzt. Bitte ändere dein Passwort so bald wie möglich.', false, 'yes-no', function() {editors.editorAccountCreate();}, null, false);";
        $_SESSION['WimFirstAccessNagged'] = true;

    }

    // Dialog ausführen
    if (isset($_GET['message']) && (isset($_SESSION['messageArgsTitle']) || isset($_SESSION['messageArgsSubtitle']) || isset($_SESSION['messageArgsBody']))) {

        $title = isset($_SESSION['messageArgsTitle']) ? $_SESSION['messageArgsTitle'] : 'Wichtiger Hinweis';
        $subtitle = isset($_SESSION['messageArgsSubtitle']) ? $_SESSION['messageArgsSubtitle'] : '';
        $body = isset($_SESSION['messageArgsBody']) ? $_SESSION['messageArgsBody'] : '';
        
        $warnMode = ($_GET['message'] === 'warn') ? 'true' : 'false';
        if ($warnMode === 'true' && $body === '') { $body = "Bei der Bearbeitung ist ein Serverfehler aufgetreten. > IT-Verantw. kontaktieren."; }
        
        $_SESSION['messageArgsTitle'] = null;
        $_SESSION['messageArgsSubtitle'] = null;
        $_SESSION['messageArgsBody'] = null;

        // Meldung verarbeiten
        echo "editors.messageOpen('$title', '$subtitle', '$body', $warnMode, 'ok-only', null, null, false);";

    }

?></script>

</html>