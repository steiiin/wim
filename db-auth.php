<?php

namespace WIM;

session_start();

// HTTP-Codes & Redirects
class Auth {

    // redirect
    public static function redirectToLogin()
    {

        // Login aufrufen
        header("Location: admin-login.php");
        die();

    }
    public static function redirectToAdmin($anchor = '')
    {
        if ($anchor) { $anchor = "#entries-$anchor-anchor"; }
        header("Location: admin.php$anchor");
        die();
    }
    public static function redirectToAdminWithMessage($message, $anchor = '')
    {
        if ($anchor) { $anchor = "#entries-$anchor-anchor"; }
        $_SESSION['MESSAGEDATA'] = $message;
        header("Location: admin.php?msg=1$anchor");
        die();
    }

    // http-headers
    public static function replyErrorUnauthorized()
    {

        // Unangemeldeter Aufruf
        header("HTTP/1.1 401 Unauthorized");
        die();

    }
    public static function replyErrorBadRequest()
    {

        // Ungültiger Aufruf
        header("HTTP/1.1 400 Bad Request");
        die();

    }
    public static function replyErrorServer()
    {

        // Ungültiger Aufruf
        header("HTTP/1.1 500 Internal Server Error");
        die();

    }
    public static function replySuccessCreated()
    {

        // Erfolgreicher API-Aufruf
        header("HTTP/1.1 201 Created");
        die();

    }
    public static function replySuccess()
    {

        // Erfolgreicher API-Aufruf
        header("HTTP/1.1 200 OK");
        die();

    }

    // admin-checks
    public static function checkSession()
    {

        // check session
        if (isset($_SESSION['ident'])) {
            return true;
        }
        return false;

    }
    public static function blockInvalidSession()
    {
        if (!self::checkSession()) { self::redirectToLogin(); }
    }  
    public static function blockNoAdminSession() 
    {
        if ($_SESSION['IsAdmin'] !== true) 
        { 
            self::redirectToAdminWithMessage("{
                title: 'Keine Berechtigung',
                description: 'Für die Bearbeitung der Nutzer fehlen dir die Rechte. Bitte kontaktiere den Standortverantwortlichen.',
                showWarning: true,
                mode: 'ok',
                actionPositive: null
            }", 'users');
        }
    }

}