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