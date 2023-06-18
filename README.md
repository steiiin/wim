# WIM

WachenInformationsModul.
Serveranwendung zur Anzeige von Informationen, Terminen und Aufgaben.

### Bestandteile
- admin.php
  > Administration der hinterlegten Daten. Mit Benutzerverwaltung. 

- ui.php
  > Präsentation der Daten, z.B. für einen angeschlossenen Monitor.

- cron-auto-...php
  > Module, die automatisiert Daten der Datenbank hinzufügen.

### Module
- Interface ModuleWim (in db-entries.php)
```
getName()                 >> Name des Moduls
getAutoTag()              >> AutoTag, dass in der Datenbank verwendet wird
getAdminEntry()           >> Html, dass im Admin angezeigt wird
getAdminSettingsLink()    >> Javascript, dass im Admin aufgerufen wird, wenn auf die Einstellungen geklickt wird
getAdminSettingsHtml()    >> Html für den Editor im Admin
getAdminSettingsScript()  >> Javascript für den Editor im Admin
run($cli = true)          >> Startet das Modul (zum Einträge hinzufügen)
```

- Registrierung in ModulesWim (in db-entries.php)
```
Create()                  >> Modul instanzieren
ApiHookSettings()         >> Api-Aufruf SETTINGS-MODULE wird hierher umgeleitet
ApiHookCron()             >> Api-Aufruf CRON-MODULE wird hierher umgeleitet
```

- cron-auto-abfall.php 
  > Abfallkalender für ZAOE
- cron-auto-malteser.php
  > Sharepoint-Termine aus der MalteserCloud (CloudAuth)
- cron-auto-nina.php
  > Abfrage Warnmeldungen über die NINA API

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