# WIM

WachenInformationsModul.
Serveranwendung zur Anzeige von Informationen, Terminen und Aufgaben.

### Bestandteile
- admin.php
  > Administration der hinterlegten Daten. Mit Benutzerverwaltung. 
- ui.php
  > Präsentation der Daten, z.B. für einen angeschlossenen Monitor.

### Konfiguration
Im IncludeOrdner des php-preprocessors muss ein Dokument namens 'wim-config.php' mit folgendem Inhalt erstellt werden:
    <?php
       return [
       'DB_SERVER' => 'localhost',
       'DB_USER' => 'root',
       'DB_PASS' => 'default',
       'CD_SUPERPASS' => 'default'
       ];
    ?>

Diese kann an den Server angepasst werden.