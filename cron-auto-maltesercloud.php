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