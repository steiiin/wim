<?php 

    namespace WIM;

    // check Session (if already logged in)
    require_once dirname(__FILE__) . '/db-auth.php';
    if (Auth::checkSession()) { Auth::redirectToAdmin(); }

    // files ######################################################################################
    require_once dirname(__FILE__) . '/db-entries.php';
    require_once dirname(__FILE__) . '/db-users.php';
    require_once dirname(__FILE__) . '/db-settings.php';
    
    // document ###################################################################################
    $entries = new Entries();
    $users = new Users();
    $settings = new Settings();
    
    // check Login
    $loginMessage = '';

    $userName = filter_input(INPUT_POST, "login-user", FILTER_SANITIZE_STRING);
    $userPass = filter_input(INPUT_POST, "login-pass", FILTER_SANITIZE_STRING);
    if ($userPass)
    {
        if ($users->LoginUser($userName, $userPass))
        {
            // save session_id and redirect
            $_SESSION['ident'] = session_id();
            Auth::redirectToAdmin();
        }
        else
        {
            $loginMessage = 'Der Nutzername oder das Passwort war falsch.';
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
    <script src='ui.js'></script>

    <script type="text/javascript">

        function startUp() {

            WIM.EDITOR.showEditor('login');

            // create heart-beat-listener
            document.addEventListener("visibilitychange", async function() 
            {
                if (document.visibilityState === "visible") 
                {
                    console.log("WIM-ADMIN: user has returned");
                    try 
                    {
                        let respone = await fetch('api.php?action=ACCOUNT-HEARTBEAT', { cache: 'no-store' });
                        if (respone.ok) { window.location.reload() }
                    }
                    catch (error) { console.error(error) }
                }
            });

        }

        function eventResize() {

            WIM.EDITOR.calculateEditorPosition();
            
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
                <p style="color:#a00;"><?=$loginMessage?></p>

                <input name="login-user" placeholder="Nutzerkennung" type="text">
                <input name="login-pass" placeholder="Passwort" type="password">

                <input class="btn btn-input" type="submit" value="Anmelden">

            </form>

        </div>

    </div>

</body>

</html>