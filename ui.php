<?php 

    namespace WIM;

    // files ######################################################################################
    require_once dirname(__FILE__) . '/db-entries.php';
    require_once dirname(__FILE__) . '/db-users.php';
    require_once dirname(__FILE__) . '/db-settings.php';
    
    // document ###################################################################################
    $entries = new Entries();
    $users = new Users();
    $settings = new Settings();
    
?>
<!doctype html>
<html lang="de">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="google" content="notranslate">

    <link href="res/theme.css" type="text/css;charset=UTF-8" rel="stylesheet">
    <link href="ui-resolution.php?res=<?=$settings->GetUiResolution(); ?>" type="text/css;charset=UTF-8" rel="stylesheet">

    <!-- Light/Dark-Theme -->
    <link rel="preload" href="res/theme-dark.css" as="style" type="text/css" />
    <link rel="preload" href="res/theme-light.css" as="style" type="text/css" />
    <link id="css-theme-variables" href="res/theme-light.css" type="text/css" rel="stylesheet">

    <!-- Meta -->
    <title>WIM - Oberfläche</title>

    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="assets/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">

    <!-- Startup -->
    <script src="ui.js"></script>
    <script src="bin/lib-suncalc.js"></script>

    <script type="text/javascript">

        const overflowKeyframe = document.createElement("style");

        function startUp() {

            // setup overflow container
            var overflowContainer = document.getElementById("todaylayout-overflow");
            overflowContainer.appendChild(overflowKeyframe);

            // convert location-info
            let location = <?php

                $location = $settings->GetLocation();
                echo $location === false ? 'null' : $location;

            ?>

            // init wim-module
            WIM.UI.init(
                '<?=RequestType::INFO?>', '<?=RequestType::TASK?>', '<?=RequestType::EVENT?>', '<?=RequestType::WARN?>',
                location?.lat ?? 51, location?.long ?? 13
            );

        }

        window.onload = startUp;

    </script>

</head>

<body class="<?=(isset($_GET['nokiosk']) ? '' : 'kiosk')?> no-future">

    <!-- HEADER -->
    <div id="header">
        <h1><?php

            $name = $settings->GetStationName();
            echo $name === false ? 'Wachenmonitor' : $name;

        ?></h1>
        <div class="header-info-area">
            <div id="ui-clock-time" class="clock">--:--</div>
            <div id="ui-clock-date" class="date">---</div>
        </div>
    </div>

    <!-- TWINLAYOUT -->
    <section id="todaylayout">
        <div id="todaylayout-overflow">
            <div class="group" id="group-info">
                <h2>Aktuelle Informationen</h2>
                <ul id="list-info">
                    <div id="init-load">Daten werden geladen ...</div>
                </ul>
            </div>
            <div class="group" id="group-task" style="display: none;">
                <h2>Zu Erledigen</h2>
                <ul id="list-task"></ul>
            </div>
        </div>
    </section>
    <section id="futurelayout">
        <div class="group" id="group-event">
            <h2>Kommende Termine</h2>
            <ul id="list-event"></ul>
        </div>
    </section>
    <div id="eos-balken">&nbsp;</div>

</body>

</html>
