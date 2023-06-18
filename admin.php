<?php 

    namespace WIM;

    // check Session
    require_once dirname(__FILE__) . '/db-auth.php';
    Auth::blockInvalidSession();

    // files ######################################################################################
    require_once dirname(__FILE__) . '/db-entries.php';
    require_once dirname(__FILE__) . '/db-users.php';
    require_once dirname(__FILE__) . '/db-settings.php';
    require_once dirname(__FILE__) . '/ui-resolution.php';
    
    // document ###################################################################################
    $users = new Users();
    $entries = new Entries();
    $settings = new Settings();
    $modules = ModulesWim::Create($users, $entries, $settings);
    
    
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
    <script src='ui.js'></script>

    <script type="text/javascript">

        function init() {

            WIM.EDITOR.adminGroups.expandInit('entries-info-anchor')
            WIM.EDITOR.adminGroups.expandInit('entries-task-anchor')
            WIM.EDITOR.adminGroups.expandInit('entries-event-anchor')
            WIM.EDITOR.adminGroups.expandInit('entries-recurring-anchor')

            WIM.EDITOR.adminGroups.expandInit('entries-users-anchor')
            WIM.EDITOR.adminGroups.expandInit('entries-modules-anchor')

            <?php 
                $encodedTiming = $settings->GetVehicleTiming();
                echo "WIM.EDITOR.init($encodedTiming)";
            ?>

        }

        function eventResize() {

            WIM.EDITOR.calculateEditorPosition();
            WIM.EDITOR.calculateMessagePosition();

        }

        window.onload = init;
        window.onresize = eventResize;

    </script>

</head>
<body id="adminpage">

    <!-- HEADER -->
    <div id="header">
        <h1>WIM|editor [<span>@<?=$_SESSION['User'].($_SESSION['IsAdmin']?($_SESSION['User'] === 'admin' ? '' : '/Admin'):'');?></span>]</h1>
        <div class="header-info-area admin-header-info-area">
            <a onclick="WIM.EDITOR.accountEditor.create()" class="account">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M3 5v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2H5c-1.11 0-2 .9-2 2zm12 4c0 1.66-1.34 3-3 3s-3-1.34-3-3 1.34-3 3-3 3 1.34 3 3zm-9 8c0-2 4-3.1 6-3.1s6 1.1 6 3.1v1H6v-1z"/></svg>
            </a>
        </div>
    </div>

    <!-- EDITOR -->
    <div id="editorContainer" class="editorContainer">

        <!-- account -->
        <div id="editorwindow-account" class="editorWindow">
            <form id="editor-account-form" action="api.php" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('account');">×</a>

                <input id="editor-id-account" name="id" type="hidden" value="-1">

                <h2>Mein Profil</h2>
                <h3 style="margin: 0 0 15px 0;">@<?=$_SESSION['User']?></h3>

                <p <?=($_SESSION['AllowChanges'] ? '' : "style='display:none;'")?>>Hier kannst du deine Daten ändern.</p>

                <div id="editor-account-actiontool" class="tools tools-full" tool-action="" style="margin-bottom: 10px; <?=($_SESSION['AllowChanges'] ? "" : "display:none;")?>" >

                    <button id="editor-account-action-user" type="button"
                        onclick="WIM.EDITOR.accountEditor.changeState('user'); WIM.EDITOR.accountEditor.validate()">
                        <img src="res/ic_account.svg" style="width:20px;">
                        <span>Nutzerkennung ändern</span>
                    </button>

                    <button id="editor-account-action-password" type="button"
                        onclick="WIM.EDITOR.accountEditor.changeState('pass'); WIM.EDITOR.accountEditor.validate()">
                        <img src="res/ic_btn_password.svg" style="width:20px">
                        <span>Passwort ändern</span>
                    </button>

                    <button id="editor-account-action-cancelcurrent" type="button"
                        onclick="WIM.EDITOR.accountEditor.changeState(''); WIM.EDITOR.accountEditor.validate()">
                        <img src="res/ic_btn_back.svg" style="width:20px">
                        <span>Zurück zum Menü</span>
                    </button>

                </div>

                <div id="editor-account-actioncontainer-user">

                    <input id="editor-account-input-user" name="user" placeholder="Neue Nutzerkennung" type="text"
                        oninput="WIM.EDITOR.accountEditor.validate()">
                    <h3 id="editor-account-input-user-error" class="error"></h3>

                </div>
                <div id="editor-account-actioncontainer-password">

                    <input id="editor-account-input-oldpass" name="oldpass" placeholder="Altes Kennwort" type="password"
                        oninput="WIM.EDITOR.accountEditor.validate()">

                    <input id="editor-account-input-pass1" name="newpass" placeholder="Neues Kennwort" type="password"
                        oninput="WIM.EDITOR.accountEditor.validate()">

                    <input id="editor-account-input-pass2" placeholder="Neues Kennwort wiederholen" type="password"
                        oninput="WIM.EDITOR.accountEditor.validate()">

                    <h3 id="editor-account-input-oldpass-error" class="error">Gib bitte das bisherige Passwort ein</h3>
                    <h3 id="editor-account-input-newpass-error" class="error">Die neuen Passwörter stimmen nicht überein</h3>

                </div>

                <div class="tools tools-full">
                    <a id="editor-account-action-certificate" class="link-button"
                        onclick="WIM.EDITOR.accountEditor.invokeCrtDownload()">
                        <img src="res/ic_btn_download.svg" style="width:20px;">
                        <span>HTTPS-Zertifikat herunterladen</span>
                    </a>
                </div>

                <button id="editor-account-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    >Speichern</button>
                <button id="editor-account-action-logout" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                    onclick="WIM.EDITOR.accountEditor.invokeLogout()">Abmelden</button>
            </form>
        </div>

        <!-- users -->
        <div id="editorwindow-user" class="editorWindow">
            <form id="editor-user-form" action="api.php?action=USER-EDIT" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('user');">×</a>

                <input id="editor-id-user" name="id" type="hidden" value="-1">

                <h2>Nutzer hinzufügen</h2>
                <p>Nutzer können sich im WIM anmelden und Änderungen vornehmen.</p>

                <input id="editor-user-input-loginuser" name="loginuser" placeholder="Nutzerkennung" type="text" 
                    oninput="WIM.EDITOR.userEditor.validate()">

                <hr/>
        
                <input type="checkbox" id="editor-user-input-wimadmin" name="wimadmin">
                <label class="checkbox-label" for="editor-user-input-wimadmin">Admin (Darf Benutzer erstellen &amp; ändern)</label><br>

                <button id="editor-user-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-user-action-passreset" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.userEditor.invokePasswordReset()">Passwort zurücksetzen</button>
                <button id="editor-user-action-deleteuser" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.userEditor.invokeDelete()">Nutzer entfernen</button>
            </form>
        </div>

        <!-- settings -->
        <div id="editorwindow-settings" class="editorWindow">
            <form id="editor-settings-form" action="api.php?action=SETTINGS" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('settings');">×</a>

                <h2>WIM-Einstellungen</h2>
                <h3 id="editor-settings-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Hier kannst du die Einstellungen dieses WIM festlegen.</p>

                <div id="editor-settings-actiontool" class="tools tools-full" tool-action="" style="margin-bottom: 10px;" >

                    <button id="editor-settings-action-ui" type="button"
                        onclick="WIM.EDITOR.settingsEditor.changeState('ui'); WIM.EDITOR.settingsEditor.validate()">
                        <img src="res/ic_btn_edit.svg" style="width:20px;">
                        <span>Bildschirm einrichten</span>
                    </button>

                    <button id="editor-settings-action-vehicles" type="button"
                        onclick="WIM.EDITOR.settingsEditor.changeState('vehicles'); WIM.EDITOR.settingsEditor.validate()">
                        <img src="res/ic_btn_vehicles.svg" style="width:20px">
                        <span>Zeiten &amp; Fahrzeuge ändern</span>
                    </button>

                    <button id="editor-settings-action-importexport" type="button"
                        onclick="WIM.EDITOR.settingsEditor.changeState('importexport'); WIM.EDITOR.settingsEditor.validate()">
                        <img src="res/ic_btn_importexport.svg" style="width:20px">
                        <span>Importieren/Exportieren</span>
                    </button>

                    <button id="editor-settings-action-cancelcurrent" type="button"
                        onclick="WIM.EDITOR.settingsEditor.changeState(''); WIM.EDITOR.settingsEditor.validate()">
                        <img src="res/ic_btn_back.svg" style="width:20px">
                        <span>Zurück zum Menü</span>
                    </button>

                </div>

                <div id="editor-settings-actioncontainer-ui">

                    <h3>Titel auf dem WIM-Bildschirm (z.B. Rettungswache Dresden)</h3>
                    <input id="editor-settings-input-wachename" name="wachename" placeholder="Wachenname" type="text" 
                        oninput="WIM.EDITOR.settingsEditor.validate()">

                    <h3>Auflösung des Anzeige-Bildschirms</h3>
                    <select id="editor-settings-select-resolution" style="width:auto;" name="ui-res"
                        onchange="WIM.EDITOR.settingsEditor.validate()">
                        <?php
                            $html = '';
                            $resolutions = UiResolution::GetAvailable();
                            foreach($resolutions as $key)
                            {
                                $html .= "<option value='$key'>$key</option>";
                            }
                            echo $html;
                        ?>
                    </select>

                    <hr/>

                    <h3>Wachenkoordinaten (für Nachtmodus)</h3>
                    <input id="editor-settings-input-wachelocation" name="wacheloc" type="hidden">
                    <input id="editor-settings-input-lat" placeholder="Breitengrad (z.B. 51.123456)" type="text" 
                        oninput="WIM.EDITOR.settingsEditor.validate()">
                    <input id="editor-settings-input-long" placeholder="Längengrad (z.B. 12.123456)" type="text" 
                        oninput="WIM.EDITOR.settingsEditor.validate()">
                    <a class="link" href="https://www.latlong.net/" target="_blank">Koordinaten finden</a>

                </div>

                <div id="editor-settings-actioncontainer-vehicles">

                    <input id="editor-settings-input-vehicletiming" name="vehicleTiming" type="hidden">

                    <h3>Standardzeiten der Wache</h3>
                    <input id="editor-settings-input-deftiming-time-start" type="time" style="width: auto; display: inline-block;" oninput="WIM.EDITOR.settingsEditor.validate()">
                    <input id="editor-settings-input-deftiming-time-end" type="time" style="width: auto; display: inline-block;" oninput="WIM.EDITOR.settingsEditor.validate()">

                    <h3>Inhalt für die Fahrzeugauswahl</h3>
                    <textarea id="editor-settings-input-wachekfz" placeholder="Fahrzeuge (HTML)" rows="15" type="text"
                        oninput="WIM.EDITOR.settingsEditor.validate()">
                    </textarea>
                    <a class="link" href="#" onclick="WIM.EDITOR.settingsEditor.invokeResetVehicles()">Zeiten &amp; Fahrzeuge zurücksetzen</a>
                    
                </div>

                <button id="editor-settings-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="WIM.EDITOR.disableUI(true);">Speichern</button>
            </form>
            <div id="editor-settings-actioncontainer-importexport" style="margin-top:-10px">
                <div>
                    <input id="editor-settings-export-withmodules" type="checkbox">
                    <label for="editor-settings-export-withmodules" class="checkbox-label">Moduleinstellungen exportieren (Achtung: inklusive Passwörter)</label>
                </div>
                <button id="editor-settings-btn-export" class="btn btn-input" type="button" style="margin-top:10px;"
                    onclick="WIM.EDITOR.settingsEditor.invokeExport();">Exportiere Einträge &amp; Einstellungen</button>
                <form id="editor-settings-import-form" action="api.php?action=WIM-IMPORT" method="post" enctype="multipart/form-data">
                    <label for="editor-settings-btn-import" class="btn btn-input" style="margin-top:10px;text-align:center">
                        <input id="editor-settings-btn-import" name="file" type="file" style="display:none"
                            onchange="WIM.EDITOR.settingsEditor.invokeImport();">Importiere Einträge &amp; Einstellungen</label>
                    </form>
            </div>
        </div>

        <!-- include modulesettings -->
        <?php

            foreach ($modules as $module) 
            {
                echo $module->getAdminSettingsHtml();
            }

        ?>

        <!-- Entry: Info -->
        <div id="editorwindow-info" class="editorWindow">
            <form id="editor-form-info" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('info');">×</a>

                <input id="editor-id-info" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::INFO;?>">

                <h2>Mitteilung hinzufügen</h2>
                <p>Mitteilungen werden unter 'Aktuelle Informationen' angezeigt und haben kein Ablaufdatum.</p>
                
                <input id="editor-info-input-payload" name="payload" type="hidden">
                <input id="editor-info-input-title" placeholder="Titel" type="text" list=entry-columns
                    oninput="WIM.EDITOR.infoEditor.validate()">
                <input id="editor-info-input-description" placeholder="Beschreibung (optional)" type="text"
                    oninput="WIM.EDITOR.infoEditor.validate()">
                <input id="editor-info-input-category" placeholder="Kategorie (optional)" type="text"
                    oninput="WIM.EDITOR.infoEditor.validate()">
                <input id="editor-info-input-location" placeholder="Ort (optional)" type="text"
                    oninput="WIM.EDITOR.infoEditor.validate()">
                <select id="editor-info-select-vehicle" style="width:auto;"
                    onchange="WIM.EDITOR.infoEditor.validate()">
                </select>

                <hr/>
                
                <section id="editor-info-preview" class="preview"></section>

                <hr/>

                <h3 id="editor-info-datetime-header-start">In der Liste sichtbar ab:</h3>
                <input id="editor-info-datetime-input-start" type="date" style="width:auto;" name="dateStart"
                    oninput="WIM.EDITOR.infoEditor.validate()">
                    
                <h3 id="editor-info-datetime-header-end">Wird aus der Liste entfernt nach:</h3>
                <input id="editor-info-datetime-input-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="WIM.EDITOR.infoEditor.validate()">

                <div class="tools" id="editor-info-datetime-tool" tool-withdate="false">

                    <button id="editor-info-datetime-tool-withdate" type="button"
                        onclick="WIM.EDITOR.infoEditor.changeState('withdate'); WIM.EDITOR.infoEditor.validate()">
                        <img src="res/ic_btn_daterange.svg" style="width:20px">
                        <span>Zeitraum festlegen</span>
                    </button>

                    <button id="editor-info-datetime-tool-nodate" type="button"
                        onclick="WIM.EDITOR.infoEditor.changeState('nodate');; WIM.EDITOR.infoEditor.validate()">
                        <img src="res/ic_btn_notime.svg" style="width:20px">
                        <span>Dauerhaft Gültig</span>
                    </button>

                </div>

                <button id="editor-info-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-info-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.infoEditor.delete()">Löschen</button>
            
            </form>
        </div>

        <!-- Entry: Event -->
        <div id="editorwindow-event" class="editorWindow">
            <form id="editor-form-event" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('event');">×</a>

                <input id="editor-id-event" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::EVENT?>">

                <h2>Termin hinzufügen</h2>
                <p>Termine werden rechts in der Terminagenda angezeigt - von Start- bis Enddatum werden diese unter
                    'Aktuelle Informationen' angezeigt.</p>

                <input id="editor-event-input-payload" name="payload" type="hidden">
                <input id="editor-event-input-title" placeholder="Titel" type="text" list=entry-columns
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <input id="editor-event-input-description" placeholder="Beschreibung (optional)" type="text"
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <input id="editor-event-input-category" placeholder="Kategorie (optional)" type="text"
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <input id="editor-event-input-location" placeholder="Ort (optional)" type="text"
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <select id="editor-event-select-vehicle" style="width:auto;"
                    onchange="WIM.EDITOR.eventEditor.validate()">
                </select>

                <hr/>
                
                <section id="editor-event-preview" class="preview"></section>

                <hr/>

                <h3 id="editor-event-datetime-header-start">Startdatum / -zeit</h3>
                <input id="editor-event-datetime-date-start" type="date" style="width:auto;" name="dateStart"
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <input id="editor-event-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="WIM.EDITOR.eventEditor.validate()">

                <br>

                <h3 id="editor-event-datetime-header-end">Enddatum / -zeit</h3>
                <input id="editor-event-datetime-date-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="WIM.EDITOR.eventEditor.validate()">
                <input id="editor-event-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="WIM.EDITOR.eventEditor.validate()">

                <div class="tools" id="editor-event-tools" tool-withrange="false" tool-withtime="false">

                    <button id="editor-event-datetime-tools-withrange" type="button"
                        onclick="WIM.EDITOR.eventEditor.changeStateWithRange(true); WIM.EDITOR.eventEditor.validate()">
                        <img src="res/ic_btn_daterange.svg" style="width:20px">
                        <span>Start & Ende</span>
                    </button>

                    <button id="editor-event-datetime-tools-norange" type="button"
                        onclick="WIM.EDITOR.eventEditor.changeStateWithRange(false); WIM.EDITOR.eventEditor.validate()">
                        <img src="res/ic_btn_onlystart.svg" style="width:20px">
                        <span>Nur Start</span>
                    </button>

                    <button id="editor-event-datetime-tools-withtime" type="button"
                        onclick="WIM.EDITOR.eventEditor.changeStateWithTime(true); WIM.EDITOR.eventEditor.validate()">
                        <img src="res/ic_action_addtime.svg" style="width:26px;margin: -2px 0 0 -1px;">
                        <span>Mit Uhrzeit</span>
                    </button>

                    <button id="editor-event-datetime-tools-notime" type="button"
                        onclick="WIM.EDITOR.eventEditor.changeStateWithTime(false); WIM.EDITOR.eventEditor.validate()">
                        <img src="res/ic_btn_notime.svg" style="width:20px">
                        <span>Ohne Uhrzeit</span>
                    </button>

                </div>

                <button id="editor-event-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-event-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.eventEditor.delete()">Löschen</button>

            </form>
        </div>

        <!-- Entry: Task -->
        <div id="editorwindow-task" class="editorWindow">
            <form id="editor-form-task" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('task');">×</a>

                <input id="editor-id-task" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::TASK;?>">

                <h2>Einzelne Aufgabe hinzufügen</h2>
                <p>Die Aufgaben werden in der "Zu Erledigen"-Liste angezeigt, bis das Ablaufdatum erreicht wurde.
                    Zusätzlich kann ein Datum angegeben werden, ab dem die Aufgabe angezeigt wird.</p>
                
                <input id="editor-task-input-payload" name="payload" type="hidden">
                <input id="editor-task-input-title" placeholder="Titel" type="text" list=entry-columns
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <input id="editor-task-input-description" placeholder="Beschreibung (optional)" type="text"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <input id="editor-task-input-category" placeholder="Kategorie (optional)" type="text"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <input id="editor-task-input-location" placeholder="Ort (optional)" type="text"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <select id="editor-task-select-vehicle" style="width:auto;"
                    onchange="WIM.EDITOR.taskEditor.validate()">
                </select>

                <hr/>
                
                <section id="editor-task-preview" class="preview"></section>

                <hr/>

                <h3 id="editor-task-datetime-header-start">In der Liste sichtbar ab:</h3>
                <input id="editor-task-datetime-date-start" type="date" style="width:auto;" name="dateStart"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <input id="editor-task-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <div id="editor-task-datetime-beforeEvent-bound">
                    <input id="editor-task-datetime-beforeEvent" type="checkbox" name="showAsEvent"
                        onchange="WIM.EDITOR.taskEditor.validate()">
                    <label for="editor-task-datetime-beforeEvent" class="checkbox-label">Vorher als Termin
                        anzeigen</label>
                </div>
                <h3 id="editor-task-datetime-header-end">Zu Erledigen bis:</h3>
                <input id="editor-task-datetime-date-end" type="date" style="width:auto;" name="dateEnd"
                    oninput="WIM.EDITOR.taskEditor.validate()">
                <input id="editor-task-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="WIM.EDITOR.taskEditor.validate()">

                <div class="tools" id="editor-task-tools" tool-withrange="false">

                    <button id="editor-task-datetime-tools-startend" type="button"
                        onclick="WIM.EDITOR.taskEditor.changeStateWithRange(true); WIM.EDITOR.taskEditor.validate()">
                        <img src="res/ic_btn_daterange.svg" style="width:20px">
                        <span>Mit Startdatum</span>
                    </button>

                    <button id="editor-task-datetime-tools-onlyend" type="button"
                        onclick="WIM.EDITOR.taskEditor.changeStateWithRange(false); WIM.EDITOR.taskEditor.validate()">
                        <img src="res/ic_btn_onlystart.svg" style="width:20px">
                        <span>Ohne Startdatum</span>
                    </button>

                </div>

                <button id="editor-task-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-task-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.taskEditor.delete()">Löschen</button>
            </form>
        </div>

        <!-- Entry: Recurring -->
        <div id="editorwindow-recurring" class="editorWindow">
            <form id="editor-form-recurring" action="api.php?action=ITEM-EDIT" method="post" name="form">
                <a class="close"  onclick="WIM.EDITOR.closeEditor('recurring')">×</a>

                <input id="editor-id-recurring" name="id" type="hidden" value="-1">
                <input name="typetag" type="hidden" value="<?=TypeTag::RECURRING?>">

                <h2>Tagesaufgabe hinzufügen</h2>
                <p>Tagesaufgaben werden in der "Zu Erledigen"-Liste angezeigt. Dabei wird kein festes Datum angegeben,
                    sondern nach einem Schema automatisch wiederholt.</p>
 
                <input id="editor-recurring-input-payload" name="payload" type="hidden">
                <input id="editor-recurring-input-title" placeholder="Titel" type="text" list=entry-columns
                    oninput="WIM.EDITOR.recurringEditor.validate()">
                <input id="editor-recurring-input-description" placeholder="Beschreibung (optional)" type="text"
                    oninput="WIM.EDITOR.recurringEditor.validate()">
                <input id="editor-recurring-input-category" placeholder="Kategorie (optional)" type="text"
                    oninput="WIM.EDITOR.recurringEditor.validate()">
                <input id="editor-recurring-input-location" placeholder="Ort (optional)" type="text"
                    oninput="WIM.EDITOR.recurringEditor.validate()">
                <select id="editor-recurring-select-vehicle" style="width:auto;"
                    onchange="WIM.EDITOR.recurringEditor.changeTiming(); WIM.EDITOR.recurringEditor.validate()">
                </select>

                <hr/>
                
                <section id="editor-recurring-preview" class="preview"></section>

                <hr/>

                <select id="editor-recurring-cyclemode-weekly-select" style="width:auto;" name="weekday"
                    onchange="WIM.EDITOR.recurringEditor.validate()">
                    <option value="1"> Montag </option>
                    <option value="2"> Dienstag </option>
                    <option value="3"> Mittwoch </option>
                    <option value="4"> Donnerstag </option>
                    <option value="5"> Freitag </option>
                    <option value="6"> Samstag </option>
                    <option value="0"> Sonntag </option>
                </select>
                <select id="editor-recurring-cyclemode-monthly-select" style="width:auto;" name="dayofmonth"
                    onchange="WIM.EDITOR.recurringEditor.validate()">
                    <option value="1"> Ersten Tag (1.) </option>
                    <option value="-1"> Letzten Tag </option>
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

                <input id="editor-recurring-datetime-time-start" type="time" style="width:auto;" name="timeStart"
                    oninput="WIM.EDITOR.recurringEditor.validate()">
                <input id="editor-recurring-datetime-time-end" type="time" style="width:auto;" name="timeEnd"
                    oninput="WIM.EDITOR.recurringEditor.validate()">

                <h3 id="editor-recurring-cyclemode-header" style="margin:3px 0 10px 1px;"></h3>

                <input id="editor-recurring-input-cyclemode" name="cyclemode" value="week" type="hidden">
                <div class="tools" id="editor-recurring-tool-cyclemode" tool-mode="week" style="border-top:1px solid;">

                    <button id="editor-recurring-tool-cyclemode-daily" type="button"
                        onclick="WIM.EDITOR.recurringEditor.changeState('daily'); WIM.EDITOR.recurringEditor.validate()">
                        <img src="res/ic_action_addtime.svg" style="width:20px">
                        <span>Täglich</span>
                    </button>

                    <button id="editor-recurring-tool-cyclemode-weekly" type="button"
                        onclick="WIM.EDITOR.recurringEditor.changeState('weekly'); WIM.EDITOR.recurringEditor.validate()">
                        <img src="res/ic_btn_daterange.svg" style="width:20px">
                        <span>Wöchentlich</span>
                    </button>

                    <button id="editor-recurring-tool-cyclemode-monthly" type="button"
                        onclick="WIM.EDITOR.recurringEditor.changeState('monthly'); WIM.EDITOR.recurringEditor.validate()">
                        <img src="res/ic_btn_onlystart.svg" style="width:20px">
                        <span>Monatlich</span>
                    </button>

                    <button id="editor-recurring-tool-cyclemode-lastday" type="button"
                        onclick="WIM.EDITOR.recurringEditor.changeState('lastday'); WIM.EDITOR.recurringEditor.validate()">
                        <img src="res/ic_btn_onlystart.svg" style="width:20px">
                        <span>Letzter Wochentag</span>
                    </button>

                </div>

                <button id="editor-recurring-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Hinzufügen</button>
                <button id="editor-recurring-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.recurringEditor.delete()">Löschen</button>

            </form>
        </div>
        <div id="editorwindow-hidetask" class="editorWindow">
            <form id="editor-form-hidetask" action="api.php?action=HIDE-ITEM" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('hidetask');">×</a>

                <h2>Tagesaufgabe ausblenden</h2>
                <p>Sollte z.B. die Wäsche an einem anderen Tag abgeholt werden, kann hier die Tagesaufgabe eines bestimmten Datums deaktiviert werden.</p>
                
                <input id="editor-hidetask-tool-mode" name="mode" value="none" type="hidden">
                <input id="editor-hidetask-input-hiddenid" name="hiddenid" value="-1" type="hidden">

                <h3 id="editor-hidetask-hr-search-header">Tag, an dem die Tagesaufgabe ausfällt</h3>
                <input id="editor-hidetask-datetime-date" type="date" style="width:auto;" name="hiddendate"
                    oninput="WIM.EDITOR.hidetaskEditor.validate()">
                <button id="editor-hidetask-btn-searchtask" class="btn btn-input" type="button" style="width: auto !important;padding: 11px !important;display: inline-block;line-height: 22px !important;vertical-align: top;"
                    onclick="WIM.EDITOR.hidetaskEditor.invokeSearch()">Aufgabe suchen</button>

                <section>
                    <ul id="editor-hidetask-searchresult" class="group searchresult-group"></ul>
                </section>

                <hr id="editor-hidetask-hr-resultdiv">

                <button id="editor-hidetask-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;">Ausblenden</button>
                <button id="editor-hidetask-action-delete" class="btn btn-input" type="button" style="background-color:#a00 !important; margin-top:5px;"
                    onclick="editors.editorTemplateBusyInvokeDelete();">Wieder Einblenden</button>
            </form>
        </div>

    </div>

    <!-- LAYOUT -->
    <section style="max-width:900px;margin:0 auto;">

        <!-- Dependencies -->
        <?php

            $extensionsAvailable = true;
            $hasCurl = extension_loaded('curl');
            $hasIntl = extension_loaded('intl');
            $hasXml = extension_loaded('xml');
            
            $unfulfilled = "";
            if (!$hasCurl)
            {
                $unfulfilled .= "<p>- php-curl fehlt.</p>";
                $extensionsAvailable = false;
            }
            if (!$hasIntl)
            {
                $unfulfilled .= "<p>- php-intl fehlt.</p>";
                $extensionsAvailable = false;
            }
            if (!$hasXml)
            {
                $unfulfilled .= "<p>- php-xml fehlt.</p>";
                $extensionsAvailable = false;
            }
            if (!$extensionsAvailable)
            {
                $html = "<div id='nodata'>";
                $html .= "<h2>Fehlerhafte Installation</h2>";
                $html .= "<p>Leider fehlt mindestens eine Erweiterung:</p>";
                $html .= $unfulfilled;
                $html .= "<p>Bitte installiere die nötigen Erweiterungen und versuche es erneut.</p>";
                $html .= "</div>";
                die($html); 
            }

        ?>

        <!-- Entries -->
        <?php

            $infoList = $entries->LoadEntries(RequestType::ADMIN_INFO);
            $taskList = $entries->LoadEntries(RequestType::ADMIN_TASK);
            $eventList = $entries->LoadEntries(RequestType::ADMIN_EVENT);
            $recurringList = $entries->LoadEntries(RequestType::ADMIN_RECURRING);

            if ($infoList === false ||
                $taskList === false ||
                $eventList === false ||
                $recurringList === false) 
            { 
                $html = "<div id='nodata'>";
                $html .= "<h2>Keine Verbindung zum WIM</h2>";
                $html .= "<p>Leider konnte ein Teil der Daten nicht abgerufen werden:</p>";
                $html .= $infoList === false ? "<p>- Mitteilungen (INFO)</p>" : "";
                $html .= $taskList === false ? "<p>- Aufgaben (TASK)</p>" : "";
                $html .= $eventList === false ? "<p>- Termine (EVENT)</p>" : "";
                $html .= $recurringList === false ? "<p>- Tagesaufgaben (RECURRING)</p>" : "";
                $html .= "<p>Bei der Aktualisierung des WIM kann es immerwieder zu kleinen Fehlern kommen. Melde dich beim Verantwortlichen, damit das Problem behoben werden kann.</p>";
                $html .= "</div>";
                die($html); 
            }

            $html = '';
            
            // Group: INFO
            $listHtml = UserInterface::GenerateHtmlOfEntriesList($infoList, RequestType::ADMIN_INFO);
            $html .= UserInterface::GenerateHtmlOfGroup(TypeTag::INFO, 'Mitteilungen', $listHtml == '', $listHtml, 
                [[ 'title' => 'Mitteilung', 'onclick' => 'WIM.EDITOR.infoEditor.create()']]);
            
            // Group: EVENT
            $listHtml = UserInterface::GenerateHtmlOfEntriesList($eventList, RequestType::ADMIN_EVENT);
            $html .= UserInterface::GenerateHtmlOfGroup(TypeTag::EVENT, 'Termine', $listHtml == '', $listHtml, 
                [[ 'title' => 'Termin', 'onclick' => 'WIM.EDITOR.eventEditor.create()']]);

            // Group: TASK
            $listHtml = UserInterface::GenerateHtmlOfEntriesList($taskList, RequestType::ADMIN_TASK);
            $html .= UserInterface::GenerateHtmlOfGroup(TypeTag::TASK, 'Einzelne Aufgaben', $listHtml == '', $listHtml, 
                [[ 'title' => 'Einzelne Aufgabe', 'onclick' => 'WIM.EDITOR.taskEditor.create()']]);
            
            // Group: RECURRING
            $listHtml = UserInterface::GenerateHtmlOfEntriesList($recurringList, RequestType::ADMIN_RECURRING);
            $html .= UserInterface::GenerateHtmlOfGroup(TypeTag::RECURRING, 'Tagesaufgaben', $listHtml == '', $listHtml, 
                [
                    [ 'title' => 'Ausblenden', 'onclick' => 'WIM.EDITOR.hidetaskEditor.create()' , 'icon' => 'ic_action_hide.svg' ],
                    [ 'title' => 'Neue Tagesaufgabe', 'onclick' => 'WIM.EDITOR.recurringEditor.create()' ]
                ]);

            echo $html;

        ?>
        <br />

        <!-- Users -->
        <?php

            $usersList = $users->LoadUsers();
            if ($usersList === false) { $usersList = []; }

            $encodedLocation = \htmlentities($settings->GetLocation());
            $encodedTiming = \htmlentities($settings->GetVehicleTiming());
            if ($encodedLocation == '') { $encodedLocation = 'null'; }
            if ($encodedTiming == '') { $encodedTiming = 'null'; }

            $usersOptions = [];
            if ($_SESSION['IsAdmin']) 
            { 
                $usersOptions[] = [ 'title' => 'Neuer Benutzer', 'onclick' => 'WIM.EDITOR.userEditor.create()' ]; 
                $usersOptions[] = [ 'title' => 'Einstellungen', 'onclick' => "WIM.EDITOR.settingsEditor.create('{$settings->GetStationName()}','{$settings->GetUiResolution()}', $encodedLocation, $encodedTiming)", 'icon' => 'ic_action_settings.svg' ]; 
            }

            $listHtml = UserInterface::GenerateHtmlOfUsersList($usersList);
            $html = UserInterface::GenerateHtmlOfGroup('users', 'Benutzer &amp; Einstellungen', $listHtml == '', $listHtml, $usersOptions);
            echo $html;

        ?>
        <br />

        <!-- Auto-Modules -->
        <?php

            // create module-html
            $moduleHtml = "";
            foreach ($modules as $module)
            {
                $moduleHtml .= $module->getAdminEntry();
            }

            // create module-options
            $moduleOptions = [];
            if ($_SESSION['IsAdmin']) 
            { 
                foreach ($modules as $module)
                {
                    $moduleOptions[] = [ 'title' => $module->getName(),
                                         'onclick' => $module->getAdminSettingsLink(),
                                         'icon' => 'ic_action_settings.svg' ];
                }
            }

            // generate group for modules
            $html = UserInterface::GenerateHtmlOfGroup('modules', 'Automatische Module', false, $moduleHtml, $moduleOptions);
            $html = str_replace("class='tools'", "class='tools full-width'", $html);
            echo $html;

        ?>

    </section>

    <!-- MESSAGEBOX -->
    <div id="messageContainer" class="editorContainer">
        <div id="messagewindow" class="editorWindow">
            <form id="message-positive-form" action="#positive" method="post">
                <img id="message-icon-warn" src="res/ic_warn_black.svg" style="height: 60px; display: block; float:right;">
                
                <h2 id="message-title">---</h2>
                <p id="message-message">---</p>

                <button id="message-positive-btn" class="btn btn-input" type="button" style="margin-top:10px;"
                        onclick="WIM.EDITOR.messagePositiveAction()">OK</button>
                <button id="message-negative-btn" class="btn btn-input" type="button" style="background-color:#333 !important; margin-top:5px;"
                        onclick="WIM.EDITOR.messageNegativeAction()">Abbrechen</button>
            </form>
        </div>
    </div>
    <div id="messageDisableOverlay" class="editorContainer">&nbsp;</div>

</body>

<script type="text/javascript"><?php

    // import module-scripts
    foreach ($modules as $module)
    {
        echo $module->getAdminSettingsScript();
    }

    // show password-nag if IsFirstAccess set
    if (isset($_SESSION['IsFirstAccess']) && $_SESSION['IsFirstAccess'] && !isset($_SESSION['WimFirstAccessNagged'])) {

        echo "WIM.EDITOR.showMessage({
            title: 'Neues Passwort?',
            description: 'Dein Passwort wurde zurückgesetzt. Bitte ändere dein Passwort so bald wie möglich.',
            showWarning: false,
            mode: 'yes-no',
            actionPositive: () => { WIM.EDITOR.accountEditor.create() },
            actionNegative: null,
        });";
        $_SESSION['WimFirstAccessNagged'] = true;

    }

    // show messagebox
    if (isset($_GET['msg']) && isset($_SESSION['MESSAGEDATA']))
    {
        // inject message-code
        echo("WIM.EDITOR.showMessage({$_SESSION['MESSAGEDATA']});");

        // removes msg-GET
        echo("let baseUrl = window.location.href.split('?')[0];");
        echo("window.history.pushState(null, '', baseUrl);");
    }

?></script>

</html>