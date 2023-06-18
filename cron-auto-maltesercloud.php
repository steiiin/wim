<?php

namespace WIM;

// files ##########################################################################################
require_once dirname(__FILE__) . '/bin/lib-sharepoint.php';
require_once dirname(__FILE__) . '/db-settings.php';
require_once dirname(__FILE__) . '/db-entries.php';

// imports ########################################################################################
use WIM\SharepointApi\Client;

// interface ######################################################################################
class ModuleMalteser implements ModuleWim {

    private Users $users;
    private Entries $entries;
    private Settings $settings;
    public function __construct($users, $entries, $settings)
    {
        if ($users) { $this->users = $users; }
        if ($entries) { $this->entries = $entries; } 
        if ($settings) { $this->settings = $settings; }
    }

    public function getName() { return 'MalteserCloud'; }
    public function getAutoTag() { return 'maltesercloud-events'; }

    public function getAdminEntry() 
    {

        // fetch data
        $lastEvent = $this->entries->LoadAutoInfo($this->getAutoTag(), 'DATESTART', 'DESC');
        $mostRecent = $this->entries->LoadAutoInfo($this->getAutoTag(), 'TIMETAG', 'ASC');

        $lastEvent = $lastEvent !== false ? new \DateTime($lastEvent) : false;
        $mostRecent = $mostRecent !== false ? new \DateTime($mostRecent) : false;
        
        $mcExisting = $this->settings->Get(self::Username) !== false &&
                      $this->settings->Get(self::Password) !== false &&
                      $this->settings->Get(self::EndPoint) !== false;

        // check the data state
        $isOk = true;
        $message = 'OK';
        if ($lastEvent === false || $mostRecent === false)
        {
            $isOk = false;
            $message = 'Keine Daten.';
        }
        else
        {

            $futureDateTime = new \DateTime();  
            $futureDateTime->add(new \DateInterval('P7D'));

            $pastDateTime = new \DateTime();
            $pastDateTime->sub(new \DateInterval('P31D'));

            if ($lastEvent < $futureDateTime || $mostRecent < $pastDateTime) 
            {
                $isOk = false;
                $message = 'Fehler beim Abruf.';
            }
        }

        $errorTagged = $this->settings->Get(self::ErrorTag, 'false') === 'true';
        if ($errorTagged)
        {
            $isOk = false;
            $message = 'Letzter Abruf fehlgeschlagen.';
        }

        if (!$mcExisting)
        {
            $isOk = false;
            $message = 'Zugangsdaten nicht hinterlegt.';
        }

        // generate html
        $html = '';
        $html .= $isOk ? '<li>' : "<li class='warn'>";
        $html .= "<div class='title'>{$this->getName()}</div>";
        $html .= "<div class='subtext category'>Status: $message</div>";
        if ($lastEvent !== false) { $html .= "<div class='subtext module'>Aktuell bis: {$lastEvent->format('d.m.y')}</div>"; }
        if ($mostRecent !== false) { $html .= "<div class='subtext module'>Letzter Abruf: {$mostRecent->format('d.m.y')}</div>"; }
        if ($mostRecent === false && $lastEvent === false) { $html .= "<div class='subtext'>Keine Daten vorhanden.</div>"; }
        $html .= "<hr>";
        $html .= "</li>";
        return $html;

    }
    public function getAdminSettingsLink() 
    {
        $username = $this->settings->Get(self::Username);
        $endpoint = $this->settings->Get(self::EndPoint);
        
        return "WIM.EDITOR.moduleMalteserEditor.create(&quot;" . str_replace("'", '&#39;', $endpoint) . "&quot;, '$username')";
    }
    public function getAdminSettingsHtml()
    {
        return <<<EOT

        <div id="editorwindow-moduleMaltesercloud" class="editorWindow">
            <form id="editor-moduleMaltesercloud-form" action="api.php?action=SETTINGS-MODULE-MALTESER" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('moduleMaltesercloud');">×</a>

                <h2>Maltesercloud - Einstellungen</h2>
                <h3 id="editor-moduleMaltesercloud-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Hier kannst du die Daten für das Sharepoint ändern.</p>

                <div id="editor-moduleMaltesercloud-actiontool" class="tools tools-full" tool-action="" style="margin-bottom: 10px;" >

                    <button id="editor-moduleMaltesercloud-action-endpoint" type="button"
                        onclick="WIM.EDITOR.moduleMalteserEditor.changeState('endpoint'); WIM.EDITOR.moduleMalteserEditor.validate()">
                        <img src="res/ic_btn_onlystart.svg" style="width:20px;">
                        <span>Terminkalender ändern</span>
                    </button>

                    <button id="editor-moduleMaltesercloud-action-credentials" type="button"
                        onclick="WIM.EDITOR.moduleMalteserEditor.changeState('credentials'); WIM.EDITOR.moduleMalteserEditor.validate()">
                        <img src="res/ic_btn_password.svg" style="width:20px">
                        <span>Zugangsdaten ändern</span>
                    </button>

                    <button id="editor-moduleMaltesercloud-action-cancelcurrent" type="button"
                        onclick="WIM.EDITOR.moduleMalteserEditor.changeState(''); WIM.EDITOR.moduleMalteserEditor.validate()">
                        <img src="res/ic_btn_back.svg" style="width:20px">
                        <span>Zurück zum Menü</span>
                    </button>

                </div>

                <div id="editor-moduleMaltesercloud-actioncontainer-endpoint">

                    <input id="editor-moduleMaltesercloud-input-endpoint" name="auto-malteser-endpoint" placeholder="https://maltesercloud.sharepoint.com/sites/[...]/_api/lists(guid'[...]')" type="text"
                        oninput="WIM.EDITOR.moduleMalteserEditor.validate()">

                </div>
                <div id="editor-moduleMaltesercloud-actioncontainer-credentials">

                    <input id="editor-moduleMaltesercloud-input-user" name="auto-malteser-user" placeholder="Benutzername (vorname.nachname@malteser.org)" type="text"
                        oninput="WIM.EDITOR.moduleMalteserEditor.validate()">
                    <input id="editor-moduleMaltesercloud-input-pass" name="auto-malteser-pass" placeholder="Neues Passwort" type="password"
                        oninput="WIM.EDITOR.moduleMalteserEditor.validate()">
                        <h3 id="editor-moduleMaltesercloud-input-cred-error" class="error">Der Benutzername ist im falschen Format.</h3>

                </div>

                <button id="editor-moduleMaltesercloud-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="WIM.EDITOR.disableUI(true)">Speichern</button>
            </form>
        </div>

        EOT;
    }
    public function getAdminSettingsScript()
    {
        return <<<EOT

        WIM.EDITOR.moduleMalteserEditor = 
        {

            create: function (endpoint, username) {

                WIM.FUNC.FormHelper.formSetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '')
                WIM.FUNC.FormHelper.inputSetValue('editor-moduleMaltesercloud-input-endpoint', endpoint)
                WIM.FUNC.FormHelper.inputSetValue('editor-moduleMaltesercloud-input-user', username)
                WIM.FUNC.FormHelper.inputSetValue('editor-moduleMaltesercloud-input-pass', '')

                WIM.EDITOR.moduleMalteserEditor.validate()
                WIM.EDITOR.showEditor('moduleMaltesercloud')

            },
            validate: function () {

                let isValid = true

                // tool state
                let showToolEndpoint = WIM.FUNC.FormHelper.formGetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '') == 'endpoint'
                let showToolCredentials = WIM.FUNC.FormHelper.formGetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '') == 'credentials'
                let showToolBack = showToolEndpoint || showToolCredentials
                let showToolMenu = !showToolBack

                // visibility
                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-action-endpoint', showToolMenu)
                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-action-credentials', showToolMenu);
                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-action-cancelcurrent', showToolBack);

                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-actioncontainer-endpoint', showToolEndpoint);
                if (showToolEndpoint) {
                    WIM.FUNC.FormHelper.formSetAction('editor-moduleMaltesercloud-form', 'api.php?action=SETTINGS-MODULE&m=MALTESER&a=ENDPOINT')
                    isValid = WIM.FUNC.FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-endpoint') ? false : isValid
                }

                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-actioncontainer-credentials', showToolCredentials);
                if (showToolCredentials) {
                    WIM.FUNC.FormHelper.formSetAction('editor-moduleMaltesercloud-form', 'api.php?action=SETTINGS-MODULE&m=MALTESER&a=CREDENTIALS')

                    isValid = WIM.FUNC.FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-user') ? false : isValid
                    isValid = WIM.FUNC.FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-pass') ? false : isValid

                    if ((/^([a-z])+(\.)([a-z])+(([a-z])+([-])?)*([a-z])+([0-9])?@malteser\.org$/i).test(WIM.FUNC.FormHelper.inputGetValue('editor-moduleMaltesercloud-input-user', '#'))) {
                        WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-input-cred-error', false)
                    }
                    else {
                        isValid = false
                        WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-input-cred-error', true)
                    }
                }

                // setup editor
                WIM.FUNC.FormHelper.domVisibility('editor-moduleMaltesercloud-btn-save', showToolBack);
                WIM.FUNC.FormHelper.domEnabled('editor-moduleMaltesercloud-btn-save', isValid);

                WIM.EDITOR.calculateEditorPosition();

            },

            changeState: function (value) {
                WIM.FUNC.FormHelper.formSetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', value)
            },

        };

        EOT;
    }

    public function run($cli = true) 
    {

        // get parameters from database
        $username = $this->settings->Get(self::Username);
        $password = $this->settings->Get(self::Password);
        $endpoint = $this->settings->Get(self::EndPoint);
        $endpoint = str_replace('&#39;', "'", $endpoint);

        // open client
        $client = new Client($username, $password);
        if (!$client->Login()) { $this->setErrorTag(true); if ($cli) { die('Login failed.'); } else { return false; } }

        // get events
        $events = $client->GetEvents($endpoint);
        if ($events === false) { $this->setErrorTag(true); if ($cli) { die('EventsList is empty.'); } else { return false; } }

        // open entries, delete old items & import new fetched ones
        $this->entries->DeleteByAutotag($this->getAutoTag());
        foreach ($events as $event)
        {

            $payload = UserInterface::GetPayloadFromMalteserCloudEvent($event);
            $isAllDay = $event->getIsAllDay();
            if ($isAllDay)
            {
                $dateStart = $event->getEventStart()->format('Y-m-d');
                $timeStart = "00:00";
                $dateEnd = $event->getEventEnd()->format('Y-m-d');
                $timeEnd = "16:00";
            }
            else
            {
                $dateStart = $event->getEventStart()->format('Y-m-d');
                $timeStart = $event->getEventStart()->format('H:i');
                $dateEnd = $event->getEventEnd()->format('Y-m-d');
                $timeEnd = $event->getEventEnd()->format('H:i');
            }

            $this->entries->EditEvent(false, $payload, $dateStart, $dateEnd, $timeStart, $timeEnd, true, !$isAllDay, $this->getAutoTag());

        }

        $this->setErrorTag(false);
        if ($cli) { die(count($events) . ' events was added.'); } else { return true; }

    }
    private function setErrorTag($isErrored)
    {
        $this->settings->Set(self::ErrorTag, $isErrored ? 'true' : 'false', true);
    }

    const Username = "MC_USERNAME";
    const Password = "MC_PASSWORD";
    const EndPoint = "MC_ENDPOINT";
    const ErrorTag = "MC_LASTERRORED";

}