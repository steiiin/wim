<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'php-db.php';

$settings = new Settings();
if ($settings->isReady) {

    // Remote-UPDATE laden
    $handle = curl_init("https://raw.githubusercontent.com/steiiin/wim/main/UPDATE");
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_FRESH_CONNECT, true);
    $rawRsp = curl_exec($handle);
    $statusRsp = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    $UPDATE_REMOTE = null;
    if ($statusRsp === 200) {

        $UPDATE_REMOTE = json_decode($rawRsp);

    } else { echo($statusRsp); }

    // Lokal-UPDATE laden
    $UPDATE_LOCAL = json_decode(file_get_contents("UPDATE"));

    // Wenn fehlende Datei, oder Lokal neuer als Remote > Abbrechen
    if ($UPDATE_REMOTE == null || $UPDATE_REMOTE == null) { 
        echo("Fehlende Update-Datei."); return false; }

    $DATE_LOKAL = GetLocalDateTimeFromString($UPDATE_LOCAL->timestamp);
    $DATE_REMOTE = GetLocalDateTimeFromString($UPDATE_REMOTE->timestamp);
    if ($DATE_LOKAL >= $DATE_REMOTE) {
        echo("Aktuelle Version."); return false; }

    // Update erstellen
    foreach ($UPDATE_LOCAL->workdir as $item) 
    {

        $hasFound = false;
        foreach ($UPDATE_REMOTE->workdir as $remoteItem) 
        {

            // Wenn Übereinstimmung, dann Fetch vom Server, wenn auf Server neuer
            if ($item->path === $remoteItem->path) 
            {
                $lokalChanged = GetLocalDateTimeFromString($item->changed);
                $remoteChanged = GetLocalDateTimeFromString($remoteItem->changed);

                if ($lokalChanged < $remoteChanged) 
                {

                    FetchUpdate($item->path);

                }

                $hasFound = true;
            }

        }
        if ($hasFound) { continue; }

        // Wenn keine Übereinstimmung gefunden, dann löschen
        unlink($item->path);

    }

    // Remote-Updatedatei in den Aktuellen Arbeitsordner schreiben
    file_put_contents("UPDATE", $rawRsp);

    return true;

}
return false;

// ################################################################################################

function GetLocalDateTimeFromString($datetimeString) {
    return DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $datetimeString, new DateTimeZone('UTC'))->setTimezone(new DateTimeZone('Europe/Berlin'));
}

function FetchUpdate($path) {

    $url = "https://raw.githubusercontent.com/steiiin/wim/main/$path";
    $downloadedFileContents = file_get_contents($url);

    if($downloadedFileContents === false){
        echo "Update fehlgeschlagen: $path";
        return false; }

    $save = file_put_contents($path, $downloadedFileContents);
    if($save === false){
        echo "Schreiben fehlgeschlagen: $path";
        return false; }

}