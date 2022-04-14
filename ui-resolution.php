<?php

// Stylesheets definieren
$uiResolution = array(
    "1080p" => "

        :root {

            --spec-header-size: 60px;
            --spec-header-padd: 30px;
        
            --spec-today-group-size: 30px;
            --spec-today-group-padd: 10px;
        
            --spec-today-content-size: 20px;
        
            --spec-today-group-list-padd: 30px;

            --spec-events-size-head: 18px;
            --spec-events-size-content: 16px;

            --spec-icon-size: 24px;

        }

    ",
    "720p" => "

        :root {

            --spec-header-size: 50px;
            --spec-header-padd: 20px;
        
            --spec-today-group-size: 25px;
            --spec-today-group-padd: 10px;
        
            --spec-today-content-size: 18px;
        
            --spec-today-group-list-padd: 25px;

            --spec-events-size-head: 14px;
            --spec-events-size-content: 12px;
        
            --spec-icon-size: 24px;

        }

    "
);

// Stylesheet zurückgeben
$paramRes = filter_input(INPUT_GET, 'res', FILTER_SANITIZE_STRING);
if ($paramRes === 'default') { $paramRes = array_key_first($uiResolution); }

foreach ($uiResolution as $res => $sheet) {
    if ($res === $paramRes) {
        header('Content-Type: text/css');
        die($sheet); } }

// ################################################################################################

function GetStyleOptions() {

    global $uiResolution;

    $html = "";
    foreach ($uiResolution as $style => $sheet) {
        $html .= "<option value=\"{$style}\"> {$style} </option>"; }
    return $html;

}