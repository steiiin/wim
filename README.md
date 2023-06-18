# WIM

WachenInformationsModul.
Serveranwendung zur Anzeige von Informationen, Terminen und Aufgaben.

### Bestandteile
- admin.php
  > Administration der hinterlegten Daten. Mit Benutzerverwaltung. 

- ui.php
  > Präsentation der Daten, z.B. für einen angeschlossenen Monitor.

- cron-auto-abfall.php
  cron-auto-maltesercloud.php
  cron-auto-nina.php
  > Module, die automatisiert Daten der Datenbank hinzufügen.
  > Aufruf z.B. crontab -> 0 3 * * 1 curl -k "https://localhost/api.php?action=CRON-MODULE&m=ABFALL"

### Konfiguration
Die Serverkonfiguration wird per include aus der Datei "wim-config.php" geladen. 
```
<?php
  return [
  'DB_SERVER' => 'localhost',
  'DB_USER' => 'root',
  'DB_PASS' => 'default',
  'CD_SUPERPASS' => 'default'
  ];
?>
```
Diese kann zum Beispiel in /usr/share/php erstellt werden.