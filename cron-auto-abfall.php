<?php

namespace WIM;

// files ##########################################################################################
require_once dirname(__FILE__) . '/bin/lib-ical.php';
require_once dirname(__FILE__) . '/db-settings.php';
require_once dirname(__FILE__) . '/db-entries.php';

// imports ########################################################################################
use WIM\ICal\iCal;
use WIM\ICal\iCal_Event;
use WIM\ICal\iCal_Occurrence;

// interface ######################################################################################
class ModuleAbfall implements ModuleWim {

    private Users $users;
    private Entries $entries;
    private Settings $settings;
    public function __construct($users, $entries, $settings)
    {
        if ($users) { $this->users = $users; }
        if ($entries) { $this->entries = $entries; } 
        if ($settings) { $this->settings = $settings; }
    }

    public function getName() { return 'Abfallkalender'; }
    public function getAutoTag() { return 'abfallkalender-events'; }
    
    public function getAdminEntry() 
    {

        // fetch data
        $lastEvent = $this->entries->LoadAutoInfo($this->getAutoTag(), 'DATESTART', 'DESC');
        $mostRecent = $this->entries->LoadAutoInfo($this->getAutoTag(), 'TIMETAG', 'ASC');

        $lastEvent = $lastEvent !== false ? new \DateTime($lastEvent) : false;
        $mostRecent = $mostRecent !== false ? new \DateTime($mostRecent) : false;

        $linkExisting = $this->settings->Get(self::Link) !== false;
        
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

        if (!$linkExisting)
        {
            $isOk = false;
            $message = 'Kein Link hinterlegt.';
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
        $mostRecent = $this->entries->LoadAutoInfo($this->getAutoTag(), 'TIMETAG', 'ASC');
        $mostRecent = $mostRecent !== false ? (new \DateTime($mostRecent))->format('d.m.y') : 'Noch nie.';
        $link = $this->settings->Get(ModuleAbfall::Link);

        return "WIM.EDITOR.moduleAbfallEditor.create('$link', '$mostRecent')";
    }
    
    public function run($cli = true) 
    {

        // get parameters from database
        $abfallEndpoint = $this->settings->Get(ModuleAbfall::Link);

        // open ical-converter
        $ical = new iCal($abfallEndpoint);
        $ical = $ical->events;
        if (count($ical) == 0) { $this->setErrorTag(true); if ($cli) { die('AbfallList is empty.'); } else { return false; } }

        // open entries, delete old items & import new fetched ones
        $this->entries->DeleteByAutotag($this->getAutoTag());
        foreach ($ical as $task)
        {

            // validate event
            if (strpos($task->summary, "Restabfall") === false && 
                strpos($task->summary, "Gelbe Tonne") === false && 
                strpos($task->summary, "Pappe") === false) { echo($task->summary . ': invalid summary. Skipping.'); continue; }

            // modify times (12h before -> 18h duration)
            $modStart = new \DateTime($task->dateStart);
            $modStart = $modStart->sub(new \DateInterval("PT12H"));
            $modEnd = clone $modStart;
            $modEnd->add(new \DateInterval("PT18H"));

            // create task
            $payload = UserInterface::GetPayloadFromAbfallEvent($task);
            $dateStart = $modStart->format('Y-m-d');
            $timeStart = $modStart->format('H:i');
            $dateEnd = $modEnd->format('Y-m-d');
            $timeEnd = $modEnd->format('H:i');

            $this->entries->EditTask(false, $payload, $dateStart, $dateEnd, $timeStart, $timeEnd, false, $this->getAutoTag());

        }

        $this->setErrorTag(false);
        if ($cli) { die(count($ical) . ' events was added.'); } else { return true; }

    }
    private function setErrorTag($isErrored)
    {
        $this->settings->Set(self::ErrorTag, $isErrored ? 'true' : 'false', true);
    }

    const Link = "ABFALL_LINK";
    const ErrorTag = "ABFALL_ERRORED";

}