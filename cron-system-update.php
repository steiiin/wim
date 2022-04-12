<?php

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

        $UPDATE_REMOTE = json_decode($rawRsp, true);

    } else { echo($statusRsp); }

    // Lokal-UPDATE laden
    $UPDATE_LOCAL = json_decode(file_get_contents("UPDATE"), true);

    // Wenn fehlende Datei > Abbrechen
    if ($UPDATE_REMOTE == null || $UPDATE_REMOTE == null) { echo("Fehlende Update-Datei."); return false; }
    unlink("UPDATE");
    file_put_contents("UPDATE", $rawRsp);

    // Update erstellen
    

    return true;

}
return false;