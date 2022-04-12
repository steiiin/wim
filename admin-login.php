<?php

require_once 'php-auth.php';
if (checkAdminSession()) {redirectToAdmin();}

// Login prüfen
$msg_login = "";
if (isset($_POST['login-user']) &&
    isset($_POST['login-pass'])) {

    require_once 'php-db.php';

    $usersManager = new UsersManager();
    if ($usersManager->isReady) {

        if ($usersManager->LoginUser($_POST['login-user'], $_POST['login-pass'])) {

            $_SESSION['ident'] = session_id();
            redirectToAdmin();

        } else {
            $msg_login = "Falsches Passwort. Oder der Nutzername existiert nicht.";
        }

    } else {
        $msg_login = "Netzwerkfehler.";
    }

}

?>
<!doctype html>
<html lang="de">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <link href="res/theme.css" type="text/css" rel="stylesheet">

    <!-- Theme -->
    <link rel="preload" href="res/theme-light.css" as="style" />
    <link id="css-theme-variables" href="res/theme-light.css" type="text/css" rel="stylesheet">
    <link href="res/theme-admin.css" type="text/css" rel="stylesheet">

    <!-- Meta -->
    <title>WIM-Admin</title>

    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">

    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="assets/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">

    <!-- Startup -->
    <script src='bin/ui.js'></script>

    <script type="text/javascript">

        function startUp() {

            editors.showEditor("login");

        }

        function eventResize() {

            editors.calculateEditorPosition();
            
        }

        window.onload = startUp;
        window.onresize = eventResize; 

    </script>

</head>

<body>

    <!-- LOGIN -->
    <div id="editorContainer" class="editorContainer" style="display:block;">

        <div id="editorwindow-login" class="editorWindow" style="display:block;">

            <form method="post" name="form">

                <h2>Anmeldung</h2>
                <p>Bitte melde dich an, um Änderungen am WIM machen zu können.</p>
                <p style="color:#a00;"><?php echo ($msg_login); ?></p>

                <input name="login-user" placeholder="Nutzerkennung" type="text">
                <input name="login-pass" placeholder="Passwort" type="password">

                <input class="btn btn-input" type="submit" value="Anmelden">

            </form>

        </div>

    </div>

</body>

</html>