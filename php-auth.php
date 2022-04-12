<?php

// Status: Alpha
// Todo:   Benutzerauthentifizierung

session_start();

###################################################################################################
# HTTP-Codes & Umleitungen

function redirectToLogin()
{

    // Login aufrufen
    header("Location: admin-login.php");
    die();

}
function redirectToAdmin()
{

    header("Location: admin.php");
    die();

}
function redirectToAdminWithArgs($anchor, $vars)
{
    $args = "";
    if (!is_null($vars)) { $args .= "?$vars"; }
    if (!is_null($anchor)) { $args .= "#$anchor"; }

    header("Location: admin.php$args");
    die();
    
}

function giveErrorUnauthorized()
{

    // Unangemeldeter Aufruf
    header("HTTP/1.1 401 Unauthorized");
    die();

}
function giveErrorBadRequest()
{

    // Ungültiger Aufruf
    header("HTTP/1.1 400 Bad Request");
    die();

}
function giveErrorServer()
{

    // Ungültiger Aufruf
    header("HTTP/1.1 500 Internal Server Error");
    die();

}
function giveSuccess()
{

    // Erfolgreicher API-Aufruf
    header("HTTP/1.1 201 Created");
    die();

}

###################################################################################################
# Authentifizierung prüfen

function checkAdminSession()
{

    // Session prüfen
    if (isset($_SESSION['ident'])) {

        return true;

    }

    return false;

}