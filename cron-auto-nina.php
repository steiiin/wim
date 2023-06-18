<?php

namespace WIM;

// files ##########################################################################################
require_once dirname(__FILE__) . '/bin/lib-nina.php';
require_once dirname(__FILE__) . '/db-settings.php';
require_once dirname(__FILE__) . '/db-entries.php';

// imports ########################################################################################
use WIM\NinaApi\Client;

// interface ######################################################################################
class ModuleNina implements ModuleWim {

    private Users $users;
    private Entries $entries;
    private Settings $settings;
    public function __construct($users, $entries, $settings)
    {
        if ($users) { $this->users = $users; }
        if ($entries) { $this->entries = $entries; } 
        if ($settings) { $this->settings = $settings; }
    }

    public function getName() { return 'NINA-Warnportal'; }
    public function getAutoTag() { return 'nina-events'; }

    public function getAdminEntry() 
    {

        // fetch data       
        $existing = $this->settings->Get(self::ARS) !== false;

        // check the data state
        $isOk = true;
        $message = 'OK.';
        
        $errorTagged = $this->settings->Get(self::ErrorTag, 'false') === 'true';
        if ($errorTagged)
        {
            $isOk = false;
            $message = 'Letzter Abruf fehlgeschlagen.';
        }

        if (!$existing)
        {
            $isOk = false;
            $message = 'Kein Amtlicher Regionalschlüssel hinterlegt.';
        }

        // generate html
        $html = '';
        $html .= $isOk ? '<li>' : "<li class='warn'>";
        $html .= "<div class='title'>{$this->getName()}</div>";
        $html .= "<div class='subtext category'>Status: $message</div>";
        $html .= "<hr>";
        $html .= "</li>";
        return $html;

    }
    public function getAdminSettingsLink() 
    {
        $ars = $this->settings->Get(self::ARS);
        return "WIM.EDITOR.moduleNinaEditor.create('$ars')";
    }
    public function getAdminSettingsHtml()
    {
        return <<<EOT

        <div id="editorwindow-moduleNina" class="editorWindow">
            <form id="editor-moduleNina-form" action="api.php?action=SETTINGS-MODULE&m=NINA" method="post" name="form">
                <a class="close" onclick="WIM.EDITOR.closeEditor('moduleNina');">×</a>

                <h2>NINA - Einstellungen</h2>
                <h3 id="editor-moduleNina-meta" style="margin: 0 0 15px 0;"></h3>

                <p>Für das NINA-Warnportal muss ein Regionalschlüssel angegeben werden.</p>

                <h3>Amtlicher Regionalschlüssel</h3>
                <input id="editor-moduleNina-input-ars" name="auto-ars" placeholder="Amtlicher Regionalschlüssel z.B. 146270000000" type="text"
                    oninput="this.value=this.value.trim();WIM.EDITOR.moduleNinaEditor.validate()">
                <a class="link" href="https://www.xrepository.de/api/xrepository/urn:de:bund:destatis:bevoelkerungsstatistik:schluessel:rs_2021-07-31/download/Regionalschl_ssel_2021-07-31.json" target="_blank">Schlüssel finden (letzte 7 Stellen nullen)</a>

                <button id="editor-moduleNina-btn-save" class="btn btn-input" type="submit" style="margin-top:10px;"
                    onclick="WIM.EDITOR.disableUI(true)">Speichern</button>
            </form>
        </div>

        EOT;
    }
    public function getAdminSettingsScript()
    {
        return <<<EOT

        WIM.EDITOR.moduleNinaEditor = 
        {

            create: function (ars) {
                WIM.FUNC.FormHelper.inputSetValue('editor-moduleNina-input-ars', ars)

                WIM.EDITOR.moduleNinaEditor.validate()
                WIM.EDITOR.showEditor('moduleNina')
            },
            validate: function () {
                let isValid = true

                let sanitizedValue = WIM.FUNC.FormHelper.inputGetValue('editor-moduleNina-input-ars').replace(/\D/g, '') // Remove non-digit characters
                WIM.FUNC.FormHelper.inputSetValue('editor-moduleNina-input-ars', sanitizedValue)

                isValid = WIM.FUNC.FormHelper.inputIsEmpty('editor-moduleNina-input-ars') ? false : isValid
                isValid = (/^\d{12}$/).test(WIM.FUNC.FormHelper.inputGetValue('editor-moduleNina-input-ars')) ? isValid : false

                WIM.FUNC.FormHelper.domEnabled('editor-moduleNina-btn-save', isValid)
                WIM.EDITOR.calculateEditorPosition()
            },

            invokeRefresh: function () {

            }

        };

        EOT;
    }
    
    public function run($cli = true) 
    {

        // get parameters from database
        $ars = $this->settings->Get(self::ARS);
        if ($ars === false || $ars == "") { if ($cli) { die('No ARS, cancel.'); } else { return false; } } 

        // open client
        $client = new Client($ars);
        $warnings = $client->GetWarnings();

        if ($warnings === false) { $this->setErrorTag(true); if ($cli) { die('Error while fetching warnings.'); } else { return false; } }
        $this->entries->DeleteByAutotag($this->getAutoTag());

        foreach ($warnings as $warning)
        {

            $payload = UserInterface::GetPayloadFromWarningInfo($warning);

            $dateStart = $warning->DateSent->format('Y-m-d');
            $timeStart = $warning->DateSent->format('H:i');
            $dateEnd = $warning->DateExpires->format('Y-m-d');
            $timeEnd = $warning->DateExpires->format('H:i');

            $this->entries->EditWarn(false, $payload, $dateStart, $dateEnd, $timeStart, $timeEnd, $this->getAutoTag());

        }

        $this->setErrorTag(false);
        if ($cli) { die(count($warnings) . ' warnings was added.'); } else { return true; }

    }
    private function setErrorTag($isErrored)
    {
        $this->settings->Set(self::ErrorTag, $isErrored ? 'true' : 'false', true);
    }

    const ARS = "NINA_ARS";
    const ErrorTag = "NINA_LASTERRORED";

}