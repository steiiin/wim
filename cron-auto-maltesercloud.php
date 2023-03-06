<?php

require_once 'php-auth.php';
require_once 'php-db.php';
require_once 'bin/lib-sharepoint.php';

$settings = new Settings();
if ($settings->isReady) {

    $api = new SharepointApi($settings->GetAutoMalteserUser(), $settings->GetAutoMalteserPass());
    if ($api->Login()) {

        $eventList = $api->GetEvents();
        if ($eventList != false) {

            $entriesManager = new EntriesManager();

            // Alte Einträge löschen
            $entriesManager->DeleteEntriesByAutotag(AutoTag::MALTESER_EVENTS); 
            
            // Für alle Ereignisse einen neuen Eintrag anlegen
            foreach ($eventList as $value) {

                $hasTime = 0;
                $dateStart = null;
                $dateEnd = null;

                if ($value['dateAllDay']) {
                    
                    // Aus dem Kalender nur bis Abend anzeigen
                    $dateStart = $value['dateStart']->format('Y-m-d').' 00:00';
                    $dateEnd = $value['dateEnd']->format('Y-m-d').' 18:00';
                    $hasTime = 2;
                    
                }
                else {
                    
                    $hasTime = 1;
                    $dateStart = $value['dateStart']->format('Y-m-d H:i');
                    $dateEnd = $value['dateEnd']->format('Y-m-d H:i');
                    
                }

                $entriesManager->EditEvent(-1, $value['title'], $value['subtitle'], $dateStart, $dateEnd, $hasTime, AutoTag::MALTESER_EVENTS); 
            
            }
            return true;
        }

    }

}
return false;
