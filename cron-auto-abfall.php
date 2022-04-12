<?php

require_once 'php-auth.php';
require_once 'php-db.php';

// Einstellung abrufen
$settings = new Settings();
if ($settings->isReady) {

    $url = $settings->GetAutoAbfallLink();
    if (preg_match("/^https:\/\/www\.zaoe\.de\/ical\/([0-9\/\-_]+)$/", $url)) {

        require 'bin/lib-ical.php';
        $entriesManager = new EntriesManager();

        // ICAL abrufen
        $iCal = new iCal($url);
        $events = $iCal->eventsByDateSince('today');

        // Alte Einträge löschen
        if (count($events) > 0) {
            $entriesManager->DeleteEntriesByAutotag(AutoTag::ABFALL); } 
        else { return false; }

        // Neue Einträge importieren
        foreach ($events as $date => $dateEvents) {
            foreach ($dateEvents as $event) {
    
                // Namen anpassen
                $title = "";
                if (strpos($event->summary, "Restabfall") !== false) {$title = "Restabfalltonne (Schwarz) an die Straße stellen";} else if (strpos($event->summary, "Gelbe Tonne") !== false) {$title = "Gelbe Tonne an die Straße stellen";} else if (strpos($event->summary, "Pappe") != false) {$title = "Papiertonne (Blau) an die Straße stellen";}
    
                // Zeiten anpassen (12h vorher -> 18h Länge)
                $start = new DateTime($event->dateStart);
                $start = $start->sub(new DateInterval("PT12H"));
                $end = clone $start;
                $end->add(new DateInterval("PT18H"));
    
                // Aufgabe erstellen
                $entriesManager->EditUniqueTask(-1, "Abfallkalender", $title, "", $start->format("Y-m-d H:i"), $end->format("Y-m-d H:i"), false, AutoTag::ABFALL);
    
            }
        }

        return true;
        
    }

}

return false;