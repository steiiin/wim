var ui = {

    /* CLOCK */
    updateDateTimeInfo: function() {

        var date = new Date();
        // var timeTxt = String(date.getHours()).padStart(2, '0') + ":" + String(date.getMinutes()).padStart(2, '0');
        var dateTxt = dateutil.getWochentag(date) + ", " + String(date.getDate()).padStart(2, '0') + ". " + dateutil.getMonthName(date) + " " + date.getFullYear();

        document.getElementById('ui-clock-time').innerText = dateutil.convertToInputTime(date);
        document.getElementById('ui-clock-date').innerText = dateTxt;

    },
    startDateTimeInfoService: function () {

        ui.updateDateTimeInfo();
        var t = setTimeout(function () { ui.startDateTimeInfoService() }, 30000);

    },

    /* THEME */
    updateTheme: function(isDark) {

        var themeVariableLink = document.getElementById('css-theme-variables');

        if (isDark) {
            themeVariableLink.href = "res/theme-dark.css";
        } else {
            themeVariableLink.href = "res/theme-light.css";
        }

    },
    startThemeService: function() {

        var date = new Date();

        // Sonnenstand errechnen
        var sonnenstand = SunCalc.getTimes(date, 51.1239082542166, 13.585863667297769);
        var sonneAufgang = sonnenstand.sunrise;
        var sonneUntergang = sonnenstand.sunsetStart;

        // DarkMode, wenn zwischen Untergang und Aufgang
        var useDarkMode = date < sonneAufgang || date > sonneUntergang;
        ui.updateTheme(useDarkMode);

        // Timeout berechnen & Timer starten
        var t = setTimeout(() => { ui.startThemeService(); }, 60000);

    },
    updateOverflow: function(margin) {

        if (margin < 0) {

            overflowKeyframe.innerHTML = `
                @keyframes marquee {
                    0%, 10%, 90%, 100% { transform: translateY(0); }
                    40%, 60% { transform: translateY(` + margin + `px); }
                }
                
                #todaylayout-overflow {
                    animation: marquee ` + Math.abs(margin/3) + `s linear infinite;
                }`;
            
        } else {

            overflowKeyframe.innerHTML = "";

        }
        
            
    },

    /* DATA */
    updateEntries: function () {

        var listInfo = document.getElementById("list-info");
        var listTask = document.getElementById("list-task");
        var listEvent = document.getElementById("list-event");

        var noMessages = true;
        var noInfos = false;
        var changed = false;

        // Sectionen füllen
        fetch("api.php?action=GET-UI&type=" + window.RequestType["INFO"], {cache: "no-store"})
        .then(response => response.text())
        .then(html => {
            
            if (html.length > 5) { noMessages = false; }
            else { noInfos = true; }

            var hash = jsext.stringGenerateHash(html);
            if (window.hashListInfo == null || (window.hashListInfo != null && window.hashListInfo != hash)) {

                changed = true;

                if (html.length > 5) { listInfo.innerHTML = html; } 
                else { listInfo.innerHTML = ""; }

            }
            window.hashListInfo = hash;

        })
        .finally(function () {

            fetch("api.php?action=GET-UI&type=" + window.RequestType["TASK"], {cache: "no-store"})
            .then(response => response.text())
            .then(html => {
                
                if (html.length > 5) { noMessages = false;  }

                var hash = jsext.stringGenerateHash(html);
                if (window.hashListTask == null || (window.hashListTask != null && window.hashListTask != hash)) {

                    changed = true;

                    if (html.length > 5) { listTask.innerHTML = html; editors.setVisibleEditor("group-task", true); } 
                    else { listTask.innerHTML = ""; editors.setVisibleEditor("group-task", false); }

                }
                window.hashListTask = hash;

            })
            .finally(function () {

                if (noMessages) {

                    editors.setVisibleEditor("group-info", true);
                    listInfo.innerHTML = "<li class='check'><div class='title'>Keine Meldungen</div></li>";

                } else {

		    editors.setVisibleEditor("group-info", !(noInfos));

                }

                fetch("api.php?action=GET-UI&type=" + window.RequestType["EVENT"], {cache: "no-store"})
                .then(response => response.text())
                .then(html => {
                    
                    var hash = jsext.stringGenerateHash(html);
                    if (window.hashListEvent == null || (window.hashListEvent != null && window.hashListEvent != hash)) {

                        if (html.length > 5) { 
                            
                            listEvent.innerHTML = html; 
                            document.body.classList.remove("no-future");
                        
                        } 
                        else 
                        { 
                            listEvent.innerHTML = ""; 
                            document.body.classList.add("no-future");

                        }

                    }
                    window.hashListEvent = hash;

                })
                .finally(function () {

                    // ScrollAnimation vorbereiten
                    if (changed) {

                        var overflowContainer = document.getElementById("todaylayout-overflow");
                        var headerDiv = document.getElementById("header");

                        var margin = (screen.height - overflowContainer.scrollHeight - headerDiv.offsetHeight);
                        ui.updateOverflow(margin);

                    }

                });

            });

        });

    },
    startEntriesService: function() {

        ui.updateEntries(); 
        var t = setTimeout(function () { ui.startEntriesService(); }, 60000);

    }

}

var editors = {

    /* Editordialog öffnen / schließen */
    showEditor: function (htmlsuffix) {
        document.getElementById("editorContainer").style.display = "block";
        document.getElementById("editorwindow-" + htmlsuffix).style.display = "block";
        window.CurrentEditorSuffix = htmlsuffix;
        editors.calculateEditorPosition();
    },
    closeEditor: function (htmlsuffix) {
        document.getElementById("editorContainer").style.display = "none";
        document.getElementById("editorwindow-" + htmlsuffix).style.display = "none";
        window.CurrentEditorSuffix = null;
    },
    calculateEditorPosition: function() {
        if (window.CurrentEditorSuffix == null) { return; }
        var dialog = document.getElementById("editorwindow-" + window.CurrentEditorSuffix);
        
        var windowHeight = window.innerHeight;
        var dialogHeight = dialog.offsetHeight;
        
        if (windowHeight <= (dialogHeight + (0.2*windowHeight))) { dialog.classList.add("editorWindow-nonFloating"); }
        else { dialog.classList.remove("editorWindow-nonFloating"); }

    },

    showMessage: function (htmlsuffix) {
        document.getElementById("messageContainer").style.display = "block";
        document.getElementById("messagewindow").style.display = "block";
        window.CurrentMessageSuffix = true;
        editors.calculateMessagePosition();
    },
    closeMessage: function () {
        document.getElementById("messageContainer").style.display = "none";
        document.getElementById("messagewindow").style.display = "none";
        window.CurrentMessageSuffix = null;
    },
    calculateMessagePosition: function() {
        if (window.CurrentMessageSuffix == null) { return; }
        var dialog = document.getElementById("messagewindow");
        
        var windowHeight = window.innerHeight;
        var dialogHeight = dialog.offsetHeight;
        
        if (windowHeight <= (dialogHeight + (0.2*windowHeight))) { dialog.classList.add("editorWindow-nonFloating"); }
        else { dialog.classList.remove("editorWindow-nonFloating"); }

    },

    /* Input-Schnelleingabe */
    setValueEditor: function (htmlId, value) {
        var input = document.getElementById(htmlId);
        input.value = value;
    },
    setSelectIndex: function (htmlId, index) {
        var select = document.getElementById(htmlId);
        select.selectedIndex = index;
    },
    setSelectValue: function (htmlId, value) {
        var select = document.getElementById(htmlId);
        for (var i = 0, j = select.options.length; i < j; ++i) {
            if (select.options[i].value == value) {
                select.selectedIndex = i;
                break;
            }
        }
    },
    setCheckedEditor: function (htmlId, checked) {
        var input = document.getElementById(htmlId);
        input.checked = checked;
    },
    setVisibleEditor: function (htmlId, visible, displayMode) {
        displayMode = displayMode || "block";
        var input = document.getElementById(htmlId);
        input.style.display = visible ? displayMode : "none";
        if (visible) { input.removeAttribute("disabled"); }
        else { input.setAttribute("disabled", ""); }
    },
    setInnerTextEditor: function (htmlId, text) {
        var input = document.getElementById(htmlId);
        input.innerText = text;
    },
    setInnerHtmlEditor: function (htmlId, text) {
        var input = document.getElementById(htmlId);
        input.innerHTML = text;
    },
    setBtnEnabledEditor: function (htmlId, enabled) {
        var input = document.getElementById(htmlId);
        input.disabled = !enabled;
    },
    
    /* Input-Schnellabfrage */
    hasValueEditor: function (htmlId) {
        return !jsext.stringIsEmptyOrWhitespace(editors.getValueEditor(htmlId));
    },
    getValueEditor: function (htmlId) {
        var element = document.getElementById(htmlId);
        return element.value;
    },
    getSelectTextEditor: function (htmlId) {
        var select = document.getElementById(htmlId);
        return select.options[select.selectedIndex].innerHTML;
    },
    getSelectValueEditor: function (htmlId) {
        var select = document.getElementById(htmlId);
        if (select.selectedIndex == -1) { return null; }
        return select.options[select.selectedIndex].value;
    },

    /* Toolleiste-Einstellungen */
    setEditorToolArgs: function (toolId, toolArgName, toolArgValue) {
        var toolGroup = document.getElementById(toolId);
        toolGroup.setAttribute(toolArgName, toolArgValue.toString());
    },
    getEditorToolArgs: function (toolId, toolArgName, defaultValue='') {
        var toolGroup = document.getElementById(toolId);
        if (toolGroup.hasAttribute(toolArgName)) { return toolGroup.getAttribute(toolArgName); }
        return defaultValue;
    },

    /* Dynamisches Formular */
    setFormAction: function (formId, action) {
        var accountForm = document.getElementById(formId);
        accountForm.action = action;
    },
    submitForm: function (formId, action) {
        var accountForm = document.getElementById(formId);
        if (action != null) {accountForm.action = action;}
        accountForm.submit();
    },

    toggleInit: function(htmlId){
        var sender = document.getElementById(htmlId);

        if (sender.parentElement.getElementsByTagName('ul')[0].children.length > 0) {
            var saved = jsext.getCookie("expand--"+htmlId) == "true";
            editors.toggleSetter(sender, saved); }
    },
    toggleExpand: function(sender) {

        if (sender.parentElement.getElementsByTagName('ul')[0].children.length > 0) {
            var collapsed = editors.getEditorToolArgs(sender.id, "collapsed", "false") == "true";
            editors.toggleSetter(sender, collapsed);
        }

    },
    toggleSetter: function(sender, expand) {

        editors.setVisibleEditor(sender.parentElement.getElementsByTagName('ul')[0].id, expand);
        sender.getElementsByTagName('span')[0].classList = "arrow "+(expand ? "" : "arrow-coll");
        editors.setEditorToolArgs(sender.id, "collapsed", !expand);
        jsext.setCookie("expand--"+sender.id, expand ? "true" : "false", 360);

    },

    // EDITOR: ACCOUNT
    editorAccountCreate: function () {

        editors.setEditorToolArgs("editor-account-actiontool", "tool-action", "");

        editors.setValueEditor("editor-account-input-user", "");
        editors.setValueEditor("editor-account-input-oldpass", "");
        editors.setValueEditor("editor-account-input-pass1", "");
        editors.setValueEditor("editor-account-input-pass2", "");

        editors.editorAccountValidation();
        editors.showEditor("account");

    },
    editorAccountValidation: function () {

        var isValid = true;

        var toolActionUser = editors.getEditorToolArgs("editor-account-actiontool", "tool-action", "") == "user";
        var toolActionPass = editors.getEditorToolArgs("editor-account-actiontool", "tool-action", "") == "pass";
        var toolActionBack = toolActionUser || toolActionPass;

        editors.setVisibleEditor("editor-account-action-user", !toolActionBack);
        editors.setVisibleEditor("editor-account-action-certificate", !toolActionBack);
        editors.setVisibleEditor("editor-account-action-password", !toolActionBack);
        editors.setVisibleEditor("editor-account-action-cancelcurrent", toolActionBack);
        editors.setVisibleEditor("editor-account-action-logout", !toolActionBack);

        editors.setVisibleEditor("editor-account-actioncontainer-user", toolActionUser);
        editors.setVisibleEditor("editor-account-actioncontainer-password", toolActionPass);

        if (toolActionUser) {

            editors.setFormAction("editor-account-form", "api.php?action=ACCOUNT-CHANGEUSER");

            editors.setValueEditor("editor-account-btn-save", "Ändern");
            if (!editors.hasValueEditor("editor-account-input-user")) { isValid = false; }

        }
        if (toolActionPass) {

            editors.setFormAction("editor-account-form", "api.php?action=ACCOUNT-CHANGEPASS");

            var hasOld = editors.hasValueEditor("editor-account-input-oldpass");
            var hasNew = editors.hasValueEditor("editor-account-input-pass1") || editors.hasValueEditor("editor-account-input-pass2");
            var isSame = hasNew && (editors.getValueEditor("editor-account-input-pass1") == editors.getValueEditor("editor-account-input-pass2"));
        
            editors.setValueEditor("editor-account-btn-save", "Ändern");
            editors.setVisibleEditor("editor-account-input-oldpass-error", (hasNew && !hasOld));
            editors.setVisibleEditor("editor-account-input-newpass-error", (hasOld && hasNew && !isSame));
            if (!(hasOld && isSame)) { isValid = false; }
        }

        editors.setVisibleEditor("editor-account-action-user", false); // Keine Lust das zu beheben

        editors.setVisibleEditor("editor-account-btn-save", toolActionBack);
        editors.setBtnEnabledEditor("editor-account-btn-save", isValid);

        editors.calculateEditorPosition();

    },
    editorAccountInvokeLogout: function () {

        editors.submitForm("editor-account-form", "api.php?action=ACCOUNT-LOGOUT");

    },
    editorAccountInvokeCertificateDownload: function () {

        editors.messageOpen('Zertifikat','HTTPS-Zertifikat Herunterladen',
                            'Für den Zugriff auf diese Oberfläche wird ein HTTPS-Zertifikat benötigt, damit keine Fehlermeldungen angezeigt werden. Es wird jetzt heruntergeladen.', 
                            false, 'ok-only', 
        null, null, true);

    },

    // EDITOR: USER
    editorUserCreate: function () {

        editors.setValueEditor("editor-user-input-loginuser", "");
        editors.setValueEditor("editor-user-input-fullname", "");
        editors.setCheckedEditor("editor-user-input-wimadmin", false);

        editors.setVisibleEditor("editor-user-action-passreset", false);
        editors.setVisibleEditor("editor-user-action-deleteuser", false);
        editors.setInnerTextEditor("editor-user-btn-save", "Hinzufügen");

        editors.setValueEditor("editor-id-user", -1);

        editors.editorUserValidation();
        editors.showEditor("user");

    },
    editorUserEdit: function (sqlId, loginuser, fullname, wimadmin) {

        editors.setValueEditor("editor-user-input-loginuser", loginuser);
        editors.setValueEditor("editor-user-input-fullname", fullname);
        editors.setCheckedEditor("editor-user-input-wimadmin", wimadmin);

        editors.setVisibleEditor("editor-user-action-passreset", true);
        editors.setVisibleEditor("editor-user-action-deleteuser", true);
        editors.setInnerTextEditor("editor-user-btn-save", "Speichern");

        editors.setValueEditor("editor-id-user", sqlId);

        editors.editorUserValidation();
        editors.showEditor("user");

    },
    editorUserValidation: function () {

        var isValid = true;

        if (!(editors.hasValueEditor("editor-user-input-loginuser") &&
              varExt.isOnlyAlpha(editors.getValueEditor("editor-user-input-loginuser")))) { isValid = false; }
        if (!editors.hasValueEditor("editor-user-input-fullname")) { isValid = false; }

        editors.setBtnEnabledEditor("editor-user-btn-save", isValid);

    },
    editorUserInvokePassReset: function () {

        editors.messageOpen('Nutzer-Profil','Passwort zurücksetzen',
                            'Im Anschluss wird ein neues Passwort erstellt. Du musst dem Nutzer das neue Passwort mitteilen, sonst kann sich dieser nicht mehr anmelden. Soll das Passwort jetzt wirklich zurückgesetzt werden?', 
                            false, 'yes-no', 
        function() { /* Positive Aktion */

            editors.submitForm("editor-user-form", "api.php?action=USER-PASSRESET");

        }, null, true);

    },
    editorUserInvokeDelete: function () {

        editors.messageOpen('Nutzer-Profil','Profil löschen',
                            'Dieser Nutzer kann sich nicht mehr anmelden, wenn du sein Profil löscht. Seine Nachrichten werden ebenso entfernt und können nicht wiederhergestellt werden. Soll der Nutzer wirklich jetzt entfernt werden?', 
                            false, 'yes-no', 
        function() { /* Positive Aktion */

            editors.submitForm("editor-user-form", "api.php?action=USER-DELETE");
            
        }, null, true);

    },

    // EDITOR: SETTINGS
    editorSettingsEdit: function (lastUpdate, lastUser, wachename, wacheui, wachekfz, autoabfalllink, automalteseruser) {

        editors.setInnerTextEditor("editor-settings-meta", "Bearbeitet: " + lastUpdate + ", von @" + lastUser);

        editors.setValueEditor("editor-settings-input-wachename", wachename);
        editors.setSelectValue("editor-settings-select-ui", wacheui);
        editors.setValueEditor("editor-settings-input-wachekfz", decodeURIComponent(wachekfz.replaceAll("+", " ")));

        editors.setValueEditor("editor-settings-input-autoabfalllink", autoabfalllink);

        editors.setValueEditor("editor-settings-input-automalteseruser", automalteseruser);

        editors.editorSettingsValidation();
        editors.showEditor("settings");

    },
    editorSettingsValidation: function () {

        var isValid = true;

        if (!editors.hasValueEditor("editor-settings-input-wachename")) { isValid = false; }
        if (!editors.hasValueEditor("editor-settings-input-autoabfalllink")) { isValid = false; }
        
        if (editors.getValueEditor("editor-settings-input-wachekfz").match(/^(<option value="[A-Za-z 0-9]*">[A-Za-z 0-9\(\)]*<\/option>[\r\n]?)+$/i) === null) {
            isValid = false; }

        if (editors.getValueEditor("editor-settings-input-autoabfalllink").match(/^https:\/\/www\.zaoe\.de\/kalender\/ical\/([0-9\/\-_]+)$/) === null) {
            isValid = false; }

        if (editors.getValueEditor("editor-settings-input-automalteseruser").match(/[a-zA-Z]+\.[a-zA-Z]+@malteser\.org/i) === null) {
            isValid = false; }

        editors.setBtnEnabledEditor("editor-settings-btn-save", isValid);
        editors.calculateEditorPosition();

    },
    editorSettingsResetKfz: function () {

        editors.setValueEditor("editor-settings-input-wachekfz", "<option value=\"RTW 1\"> RTW 1 </option>\n<option value=\"RTW 2\"> RTW 2 </option>\n<option value=\"RTW 3\"> RTW 3 (Ersatz) </option>");
        editors.editorSettingsValidation();

    },

    // EDITOR: Allgemein
    editorInvokeDelete: function(htmlsuffix) {

        editors.messageOpen('Eintrag löschen',null,
                            'Willst du diesen Eintrag wirklich entfernen?', 
                            false, 'yes-no', 
        function() { /* Positive Aktion */

            editors.submitForm("editor-form-" + htmlsuffix, "api.php?action=ITEM-DELETE");

        }, null, true);

    },

    // EDITOR: INFO
    editorInfoCreate: function () {

        editors.setValueEditor("editor-info-input-title", "");
        editors.setValueEditor("editor-info-input-subtitle", "");

        editors.setVisibleEditor("editor-info-action-delete", false);
        editors.setInnerTextEditor("editor-info-btn-save", "Hinzufügen");

        editors.setValueEditor("editor-id-info", -1);

        editors.editorInfoValidation();
        editors.showEditor("info");

    },
    editorInfoEdit: function (sqlId, title, subtitle, dateStart, dateEnd) {

        dateStart = dateStart.substring(0, 10);
        dateEnd = dateEnd.substring(0, 10);
        var toolWithDate = (dateStart != "");

        editors.setEditorToolArgs('editor-info-datetime-tool', 'tool-withdate', toolWithDate);

        editors.setValueEditor("editor-info-datetime-input-start", dateStart);
        editors.setValueEditor("editor-info-datetime-input-end", dateEnd);

        editors.setValueEditor("editor-info-input-title", title);
        editors.setValueEditor("editor-info-input-subtitle", subtitle);

        editors.setVisibleEditor("editor-info-action-delete", true);
        editors.setInnerTextEditor("editor-info-btn-save", "Speichern");

        editors.setValueEditor("editor-id-info", sqlId);

        editors.editorInfoValidation();
        editors.showEditor("info");

    },
    editorInfoValidation: function () {

        var isValid = true;

        var toolDateTime = document.getElementById("editor-info-datetime-tool");
        var toolWithDate = toolDateTime.getAttribute("tool-withdate") == "true";

        editors.setVisibleEditor("editor-info-datetime-tool-withdate", !toolWithDate, "inline-block");
        editors.setVisibleEditor("editor-info-datetime-tool-nodate", toolWithDate, "inline-block");

        editors.setVisibleEditor("editor-info-datetime-input-start", toolWithDate);
        editors.setVisibleEditor("editor-info-datetime-input-end", toolWithDate);
        editors.setVisibleEditor("editor-info-datetime-header-start", toolWithDate);
        editors.setVisibleEditor("editor-info-datetime-header-end", toolWithDate);

        if (toolWithDate) {

            if (!dateutil.checkDateTime(
                editors.getValueEditor("editor-info-datetime-input-start"), "00:00",
                editors.getValueEditor("editor-info-datetime-input-end"), "01:00")) { isValid = false; }

            if (!editors.hasValueEditor("editor-info-datetime-input-start", "date")) { isValid = false; }
            if (!editors.hasValueEditor("editor-info-datetime-input-end", "date")) { isValid = false; } 

            if (!dateutil.checkDateActual(
                editors.getValueEditor("editor-info-datetime-input-end"))) { isValid = false; }

        } else {

            editors.setValueEditor("editor-info-datetime-input-start", "");
            editors.setValueEditor("editor-info-datetime-input-end", "");

        }

        if (!editors.hasValueEditor("editor-info-input-title")) { isValid = false; }
        editors.setBtnEnabledEditor("editor-info-btn-save", isValid);

    },

    // EDITOR: EVENT
    editorEventCreate: function () {

        editors.setValueEditor("editor-event-input-title", "");
        editors.setValueEditor("editor-event-input-subtitle", "");
    
        editors.setValueEditor("editor-event-datetime-date-start", "");
        editors.setValueEditor("editor-event-datetime-date-end", "");
        editors.setValueEditor("editor-event-datetime-time-start", "");
        editors.setValueEditor("editor-event-datetime-time-end", "");
    
        editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-daterange', false);
        editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-time', false);
    
        editors.setVisibleEditor("editor-event-action-delete", false);
        editors.setInnerTextEditor("editor-event-btn-save", "Hinzufügen");
    
        editors.setValueEditor("editor-id-event", -1);
    
        editors.editorEventValidation();
        editors.showEditor("event");
    
    },
    editorEventEdit: function (sqlId, title, subtitle, dateStart, dateEnd, hasTime) {
            
        // TimeStart & TimeEnd Split
        var timeStart = "";
        var timeEnd = "";
        hasTime = (hasTime == "1");
    
        if (hasTime) {
            timeStart = dateStart.substring(11, 16);
            timeEnd = dateEnd.substring(11, 16); }
    
        var toolDaterange = (dateEnd != "");
    
        dateStart = dateStart.substring(0, 10);
        dateEnd = dateEnd.substring(0, 10);
    
        editors.setValueEditor("editor-event-input-title", title);
        editors.setValueEditor("editor-event-input-subtitle", subtitle);
    
        editors.setValueEditor("editor-event-datetime-date-start", dateStart);
        editors.setValueEditor("editor-event-datetime-date-end", dateEnd);
        editors.setValueEditor("editor-event-datetime-time-start", timeStart);
        editors.setValueEditor("editor-event-datetime-time-end", timeEnd);
    
        editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-daterange', toolDaterange);
        editors.setEditorToolArgs('editor-event-tool-datetime', 'tool-time', hasTime);
    
        editors.setVisibleEditor("editor-event-action-delete", true);
        editors.setInnerTextEditor("editor-event-btn-save", "Speichern");
    
        editors.setValueEditor("editor-id-event", sqlId);
    
        editors.editorEventValidation();
        editors.showEditor("event");
    
    },
    editorEventValidation: function () {
    
        var isValid = true;
    
        // Start/Endzeit
        var toolDatetime = document.getElementById("editor-event-tool-datetime");
        var toolDatetimeIsRange = toolDatetime.getAttribute("tool-daterange") == "true";
        var toolDatetimeIsTime = toolDatetime.getAttribute("tool-time") == "true";
    
        editors.setVisibleEditor("editor-event-datetime-tools-startend", !toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-tools-onlystart", toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-tools-withtime", !toolDatetimeIsTime, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-tools-notime", toolDatetimeIsTime, "inline-block");
    
        editors.setVisibleEditor("editor-event-datetime-time-start", toolDatetimeIsTime, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-date-end", toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-time-end", toolDatetimeIsRange && toolDatetimeIsTime, "inline-block");
        editors.setVisibleEditor("editor-event-datetime-header-end", toolDatetimeIsRange);
    
        editors.setInnerTextEditor("editor-event-datetime-header-start", toolDatetimeIsRange ? (toolDatetimeIsTime ? "Startdatum / -zeit" : "Startdatum") : (toolDatetimeIsTime ? "Datum & Uhrzeit" : "Datum"));
        editors.setInnerTextEditor("editor-event-datetime-header-end", toolDatetimeIsTime ? "Enddatum / -zeit" : "Enddatum");
    
        // Validität
        if (!editors.hasValueEditor("editor-event-input-title")) { isValid = false; }
        if (!editors.hasValueEditor("editor-event-datetime-date-start", "date")) { isValid = false; }
    
        if (toolDatetimeIsRange) {
    
            if (!editors.hasValueEditor("editor-event-datetime-date-end", "date")) { isValid = false; }
            if (toolDatetimeIsTime) {
    
                if (!dateutil.checkDateTime(
                    editors.getValueEditor("editor-event-datetime-date-start"),
                    editors.getValueEditor("editor-event-datetime-time-start"),
                    editors.getValueEditor("editor-event-datetime-date-end"),
                    editors.getValueEditor("editor-event-datetime-time-end"))) { isValid = false; }
    
                if (!editors.hasValueEditor("editor-event-datetime-time-end", "time")) { isValid = false; }
    
            } else {
    
                if (!dateutil.checkDate(
                    editors.getValueEditor("editor-event-datetime-date-start"),
                    editors.getValueEditor("editor-event-datetime-date-end"))) { isValid = false; }
    
                editors.setValueEditor("editor-event-datetime-time-start", "");
    
            }
    
        } else {
    
            editors.setValueEditor("editor-event-datetime-date-end", "");
            editors.setValueEditor("editor-event-datetime-time-end", "");
    
        }
    
        if (toolDatetimeIsTime) {
    
            if (!editors.hasValueEditor("editor-event-datetime-time-start", "time")) { isValid = false; }
    
        } else {
    
            editors.setValueEditor("editor-event-datetime-time-start", "");
    
        }
    
        if (toolDatetimeIsRange) {
    
            if (toolDatetimeIsTime) {
                if (!dateutil.checkDateTimeActual(
                    editors.getValueEditor("editor-event-datetime-date-end"),
                    editors.getValueEditor("editor-event-datetime-time-end"))) { isValid = false; } } 
            else {
                if (!dateutil.checkDateActual(
                    editors.getValueEditor("editor-event-datetime-date-end"))) { isValid = false; } }
    
        } else {
    
            if (toolDatetimeIsTime) {
                if (!dateutil.checkDateTimeActual(
                    editors.getValueEditor("editor-event-datetime-date-start"),
                    editors.getValueEditor("editor-event-datetime-time-start"))) { isValid = false; } } 
            else {
                if (!dateutil.checkDateActual(
                    editors.getValueEditor("editor-event-datetime-date-start"))) { isValid = false; } }
    
        }
    
        editors.setBtnEnabledEditor("editor-event-btn-save", isValid);
    
    },

    // EDITOR: UNIQUETASK
    editorUniqueTaskCreate: function () {

        editors.setValueEditor("editor-uniquetask-input-title", "");
        editors.setValueEditor("editor-uniquetask-input-subtitle", "");

        editors.setSelectIndex("editor-uniquetask-select-vehicle", 0);
        editors.setVisibleEditor("editor-uniquetask-action-delete", false);
        editors.setInnerTextEditor("editor-uniquetask-btn-save", "Hinzufügen");

        editors.setValueEditor("editor-uniquetask-datetime-date-start", "");
        editors.setValueEditor("editor-uniquetask-datetime-date-end", "");
        editors.setValueEditor("editor-uniquetask-datetime-time-start", "");
        editors.setValueEditor("editor-uniquetask-datetime-time-end", "");
        editors.setCheckedEditor("editor-uniquetask-datetime-beforeEvent", false);

        editors.setEditorToolArgs('editor-uniquetask-tool-datetime', 'tool-daterange', false);

        editors.setValueEditor("editor-id-uniquetask", -1);

        editors.editorUniqueTaskValidation();
        editors.showEditor("uniquetask");

    },
    editorUniqueTaskEdit: function (sqlId, title, subtitle, vehicle, dateStart, dateEnd, showInEvents, autotag) {

        var timeStart = dateStart.substring(11, 16);
        var timeEnd = dateEnd.substring(11, 16);
        dateStart = dateStart.substring(0, 10);
        dateEnd = dateEnd.substring(0, 10);
        var toolDaterange = (dateStart != "");

        editors.setValueEditor("editor-uniquetask-input-title", title);
        editors.setValueEditor("editor-uniquetask-input-subtitle", subtitle);
        editors.setSelectValue("editor-uniquetask-select-vehicle", vehicle);
        editors.setVisibleEditor("editor-uniquetask-action-delete", true);
        editors.setInnerTextEditor("editor-uniquetask-btn-save", "Speichern");

        editors.setValueEditor("editor-uniquetask-datetime-date-start", dateStart);
        editors.setValueEditor("editor-uniquetask-datetime-date-end", dateEnd);
        editors.setValueEditor("editor-uniquetask-datetime-time-start", timeStart);
        editors.setValueEditor("editor-uniquetask-datetime-time-end", timeEnd);
        editors.setCheckedEditor("editor-uniquetask-datetime-beforeEvent", showInEvents);

        editors.setEditorToolArgs('editor-uniquetask-tool-datetime', 'tool-daterange', toolDaterange);

        editors.setValueEditor("editor-id-uniquetask", sqlId);

        editors.editorUniqueTaskValidation();
        editors.showEditor("uniquetask");

    },
    editorUniqueTaskValidation: function () {

        var isValid = true;

        // Start/Endzeit
        var toolDatetime = document.getElementById("editor-uniquetask-tool-datetime");
        var toolDatetimeIsRange = toolDatetime.getAttribute("tool-daterange") == "true";

        editors.setVisibleEditor("editor-uniquetask-datetime-header-start", toolDatetimeIsRange);
        editors.setVisibleEditor("editor-uniquetask-datetime-date-start", toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-uniquetask-datetime-time-start", toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-uniquetask-datetime-beforeEvent-bound", toolDatetimeIsRange);

        editors.setVisibleEditor("editor-uniquetask-datetime-tools-startend", !toolDatetimeIsRange, "inline-block");
        editors.setVisibleEditor("editor-uniquetask-datetime-tools-onlyend", toolDatetimeIsRange, "inline-block");

        // Validierung
        if (!editors.hasValueEditor("editor-uniquetask-input-title")) { isValid = false; }
        if (!editors.hasValueEditor("editor-uniquetask-datetime-date-end", "date")) { isValid = false; }
        if (!editors.hasValueEditor("editor-uniquetask-datetime-time-end", "date")) { isValid = false; }

        if (toolDatetimeIsRange) {

            if (!dateutil.checkDateTime(
                editors.getValueEditor("editor-uniquetask-datetime-date-start"),
                editors.getValueEditor("editor-uniquetask-datetime-time-start"),
                editors.getValueEditor("editor-uniquetask-datetime-date-end"),
                editors.getValueEditor("editor-uniquetask-datetime-time-end"))) { isValid = false; }

            if (!editors.hasValueEditor("editor-uniquetask-datetime-date-start", "date")) { isValid = false; }
            if (!editors.hasValueEditor("editor-uniquetask-datetime-time-start", "date")) { isValid = false; } 

        } else {

            editors.setValueEditor("editor-uniquetask-datetime-date-start", "");
            editors.setValueEditor("editor-uniquetask-datetime-time-start", "");

        }

        if (!dateutil.checkDateTimeActual(
            editors.getValueEditor("editor-uniquetask-datetime-date-end"),
            editors.getValueEditor("editor-uniquetask-datetime-time-end"))) { isValid = false; }

        editors.setBtnEnabledEditor("editor-uniquetask-btn-save", isValid);

    },

    // EDITOR: CYCLEDTASK
    editorCycledTaskCreate: function () {

        editors.setValueEditor("editor-cycledtask-input-subtitle", "");
        editors.setSelectIndex("editor-cycledtask-select-vehicle", 0);

        editors.setSelectIndex("editor-cycledtask-cyclemode-weekly-select", 0);
        editors.setSelectIndex("editor-cycledtask-cyclemode-monthly-select", 0);
        editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'week');

        editors.setVisibleEditor("editor-cycledtask-action-delete", false);
        editors.setInnerTextEditor("editor-cycledtask-btn-save", "Hinzufügen");

        editors.setValueEditor("editor-id-cycledtask", -1);

        editors.editorCycledTaskSetVehicleTiming();

        editors.editorCycledTaskValidation();
        editors.showEditor("cycledtask");

    },
    editorCycledTaskEdit: function (sqlId, subtitle, vehicle, weekDay, dayOfMonth, timeStart, timeEnd) {

        editors.setValueEditor("editor-cycledtask-input-subtitle", subtitle);
        editors.setSelectValue("editor-cycledtask-select-vehicle", vehicle);

        // CycleMode einstellen
        if (weekDay == null && String(dayOfMonth).length > 0) {

            editors.setSelectValue("editor-cycledtask-cyclemode-monthly-select", dayOfMonth);
            editors.setSelectIndex("editor-cycledtask-cyclemode-weekly-select", 0);
            editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'month');

        }
        if (dayOfMonth == null && String(weekDay).length > 0) {

            if (weekDay >= 0) {

                editors.setSelectValue("editor-cycledtask-cyclemode-weekly-select", weekDay);
                editors.setSelectIndex("editor-cycledtask-cyclemode-monthly-select", 0);
                editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'week');

            } 
            else if (weekDay == -1) {

                editors.setSelectValue("editor-cycledtask-cyclemode-weekly-select", 0);
                editors.setSelectIndex("editor-cycledtask-cyclemode-monthly-select", 0);
                editors.setEditorToolArgs('editor-cycledtask-tool-cyclemode', 'tool-mode', 'daily');

            }

        }

        editors.setValueEditor("editor-cycledtask-datetime-time-start", timeStart);
        editors.setValueEditor("editor-cycledtask-datetime-time-end", timeEnd);

        editors.setVisibleEditor("editor-cycledtask-action-delete", true);
        editors.setInnerTextEditor("editor-cycledtask-btn-save", "Speichern");

        editors.setValueEditor("editor-id-cycledtask", sqlId);

        editors.editorCycledTaskValidation();
        editors.showEditor("cycledtask");

    },
    editorCycledTaskValidation: function () {

        var isValid = true;

        var toolCycleMode = document.getElementById("editor-cycledtask-tool-cyclemode");
        var toolModeIsWeekly = toolCycleMode.getAttribute("tool-mode") == "week";
        var toolModeIsMonthly = toolCycleMode.getAttribute("tool-mode") == "month";
        var toolModeIsDaily = toolCycleMode.getAttribute("tool-mode") == "daily";

        // Modus-Buttons
        editors.setVisibleEditor("editor-cycledtask-tool-cyclemode-monthly", !toolModeIsMonthly, "inline-block");
        editors.setVisibleEditor("editor-cycledtask-tool-cyclemode-weekly", !toolModeIsWeekly, "inline-block");
        editors.setVisibleEditor("editor-cycledtask-tool-cyclemode-daily", !toolModeIsDaily, "inline-block");

        // Zyklusbeschreibung
        var cycleText = "";
        if (toolModeIsDaily) { cycleText = "Täglich. Jeden einzelnen Tag."; }
        else if (toolModeIsWeekly) { cycleText = "Jede Woche am " + editors.getSelectTextEditor("editor-cycledtask-cyclemode-weekly-select"); }
        else if (toolModeIsMonthly) { cycleText = "Jeden Monat am " + editors.getSelectTextEditor("editor-cycledtask-cyclemode-monthly-select"); }

        if (editors.hasValueEditor("editor-cycledtask-datetime-time-start") &&
            editors.hasValueEditor("editor-cycledtask-datetime-time-end")) {

            cycleText += " (Von " + editors.getValueEditor("editor-cycledtask-datetime-time-start") + " bis " + 
                        editors.getValueEditor("editor-cycledtask-datetime-time-end") + ")";

        }

        editors.setInnerTextEditor("editor-cycledtask-cyclemode-header", cycleText);
        editors.setEditorToolArgs("editor-cycledtask-input-cyclemode", "value", toolCycleMode.getAttribute("tool-mode"));

        editors.setVisibleEditor("editor-cycledtask-cyclemode-weekly-select", toolModeIsWeekly);
        editors.setVisibleEditor("editor-cycledtask-cyclemode-monthly-select", toolModeIsMonthly);

        // Validität
        if (!editors.hasValueEditor("editor-cycledtask-input-subtitle")) { isValid = false; }
        if (!dateutil.checkTime(
            editors.getValueEditor("editor-cycledtask-datetime-time-start"),
            editors.getValueEditor("editor-cycledtask-datetime-time-end"))) { isValid = false; }

        editors.setBtnEnabledEditor("editor-cycledtask-btn-save", isValid);

    },
    editorCycledTaskSetVehicleTiming: function() {

        var vehicle = editors.getValueEditor("editor-cycledtask-select-vehicle");

        var start = "05:00";
        var end = "19:00";

        var toolCycledTask = document.getElementById("editor-cycledtask-tool-cyclemode");
        var toolIsDaily = toolCycledTask.getAttribute("tool-mode") == "daily";

        switch (vehicle) {
            case "RTW 1":
                start = "05:30";
                end = "17:30";
                break;

            case "RTW 2":
                start = "06:45";
                end = "19:00"; 
                break; }

        if (toolIsDaily) { end = "12:00"; }

        editors.setValueEditor("editor-cycledtask-datetime-time-start", start);
        editors.setValueEditor("editor-cycledtask-datetime-time-end", end);

    },

    // EDITOR: AUTOTASK-Abfall
    editorAutoTaskAbfallCreate: function (url) {

        editors.setValueEditor("editor-input-link-autotask-abfall", url);

        editors.editorAutoTaskAbfallValidation();
        editors.showEditor("autotask-abfall");

    },
    editorAutoTaskAbfallValidation: function () {
        editors.setBtnEnabledEditor("editor-btn-save-autotask-abfall", editors.hasValueEditor("editor-input-link-autotask-abfall"));
    },

    // TEMPLATE: KFZ
    editorTemplateKfzCreate: function () {

        editors.setSelectValue("editor-templatekfz-select-vehicle", 0);
        editors.setSelectValue("editor-templatekfz-select-reason", 0);

        editors.setValueEditor("editor-templatekfz-datetime-date", "");

        editors.editorTemplateKfzValidation();
        editors.showEditor("templatekfz");

    },
    editorTemplateKfzValidation: function () {

        var isValid = false;
        if (editors.hasValueEditor("editor-templatekfz-datetime-date")) {
            if (dateutil.checkDateActual(editors.getValueEditor("editor-templatekfz-datetime-date"))) { isValid = true; } }

        var subtitle = "Das Fahrzeug auf den Reserve-RTW tauschen und vor die Waschhalle stellen.";
        
        var isReserve = editors.getSelectValueEditor("editor-templatekfz-select-vehicle") == "RTW 3";
        
        if (isReserve) { subtitle = null; }
        editors.setValueEditor("editor-templatekfz-typetag", isReserve ? "EVENT" : "UNIQUETASK");
        
        editors.setValueEditor("editor-templatekfz-hidden-subtitle", subtitle);

        if (isValid) {

            if (isReserve) {

                editors.setValueEditor("editor-templatekfz-hidden-title", editors.getSelectValueEditor("editor-templatekfz-select-vehicle") + ': ' + editors.getSelectValueEditor("editor-templatekfz-select-reason"));

                editors.setValueEditor("editor-templatekfz-hidden-date-start", editors.getValueEditor("editor-templatekfz-datetime-date"));
                editors.setValueEditor("editor-templatekfz-hidden-time-start", "");
                editors.setValueEditor("editor-templatekfz-hidden-date-end", "");
                editors.setValueEditor("editor-templatekfz-hidden-time-end", "");

            } else {

                editors.setValueEditor("editor-templatekfz-hidden-title", editors.getSelectValueEditor("editor-templatekfz-select-reason"));

                var eDate = new Date(editors.getValueEditor("editor-templatekfz-datetime-date")+"T00:00:00");
                
                var tStart = new Date(eDate.getTime()); 
                tStart.setTime(tStart.getTime() - (6 * 60 * 60 * 1000));

                var tEnd = new Date(eDate.getTime()); 
                tEnd.setTime(tEnd.getTime() + (6 * 60 * 60 * 1000));

                editors.setValueEditor("editor-templatekfz-hidden-date-start", dateutil.convertToInputDate(tStart));
                editors.setValueEditor("editor-templatekfz-hidden-time-start", dateutil.convertToInputTime(tStart));
                editors.setValueEditor("editor-templatekfz-hidden-date-end", dateutil.convertToInputDate(tEnd));
                editors.setValueEditor("editor-templatekfz-hidden-time-end", dateutil.convertToInputTime(tEnd));

            }
        }

        editors.setBtnEnabledEditor("editor-templatekfz-btn-save", isValid);

    },

    // TEMPLATE: EVWT
    editorTemplateEvWTCreate: function () {

        editors.setValueEditor("editor-templateevwt-input-event", "");
        editors.setValueEditor("editor-templateevwt-datetime-date", "")
        editors.setValueEditor("editor-templateevwt-datetime-time", "")
        
        editors.setValueEditor("editor-templateevwt-input-task", "")
        editors.setValueEditor("editor-templateevwt-input-projection", "12");

        editors.editorTemplateEvWTValidation();
        editors.showEditor("templateevwt");

    },
    editorTemplateEvWTValidation: function () {

        var isValid = true;

        // Input
        if (!editors.hasValueEditor("editor-templateevwt-input-event")) { isValid = false; }
        if (!editors.hasValueEditor("editor-templateevwt-input-task")) { isValid = false; }
        if (!(editors.hasValueEditor("editor-templateevwt-input-projection") &&
              varExt.isInt(editors.getValueEditor("editor-templateevwt-input-projection")) &&
              editors.getValueEditor("editor-templateevwt-input-projection") > 0)) { isValid = false; }

        // Event-DateTime
        if (!dateutil.checkDateTimeActual(
                editors.getValueEditor("editor-templateevwt-datetime-date"),
                editors.getValueEditor("editor-templateevwt-datetime-time"))) { isValid = false; }
        
        editors.setBtnEnabledEditor("editor-templateevwt-btn-save", isValid);

    },
    editorTemplateEvWTInvokeSubmit: function() {

        editors.setVisibleEditor("messageDisableOverlay", true);

        // Ereignisse vorbereiten
        var eventTitle = editors.getValueEditor("editor-templateevwt-input-event");
        var eventSubtitle = editors.getValueEditor("editor-templateevwt-input-eventsub");
        var eventDateStart = editors.getValueEditor("editor-templateevwt-datetime-date");
        var eventTimeStart = editors.getValueEditor("editor-templateevwt-datetime-time");

        var taskTitle = "Wegen: " + eventTitle;
        var taskSubtitle = editors.getValueEditor("editor-templateevwt-input-task");
        var eDate = new Date(eventDateStart+"T"+eventTimeStart+":00");
        eDate.setHours(eDate.getHours() - editors.getValueEditor("editor-templateevwt-input-projection"));
        var taskDateStart = dateutil.convertToInputDate(eDate);
        var taskTimeStart = dateutil.convertToInputTime(eDate);
        var taskDateEnd = eventDateStart;
        var taskTimeEnd = eventTimeStart;

        // Event erstellen
        const dataEvent = new URLSearchParams();
        dataEvent.append('id', -1);
        dataEvent.append('typetag', 'EVENT');
        dataEvent.append('title', eventTitle);
        dataEvent.append('subtitle',eventSubtitle);
        dataEvent.append('dateStart', eventDateStart);
        dataEvent.append('timeStart', eventTimeStart);

        // Task erstellen
        const dataTask = new URLSearchParams();
        dataTask.append('id', -1);
        dataTask.append('typetag', 'UNIQUETASK');
        dataTask.append('title', taskTitle);
        dataTask.append('subtitle',taskSubtitle);
        dataTask.append('vehicle', '');
        dataTask.append('dateStart', taskDateStart);
        dataTask.append('timeStart', taskTimeStart);
        dataTask.append('dateEnd', taskDateEnd);
        dataTask.append('timeEnd', taskTimeEnd);

        fetch("api.php?action=ITEM-EDIT",
        {
            method: 'POST',
            redirect: 'manual',
            body: dataEvent
        })
        .then(response => response.text())
        .then(html => { })
        .finally(function () {

            // UniqueTask erstellen
            fetch("api.php?action=ITEM-EDIT",
            {
                method: 'POST',
                redirect: 'manual',
                body: dataTask
            })
            .then(response => response.text())
            .then(html => { })
            .finally(function () {
                editors.submitForm("editor-form-templateevwt");
            });

        });

    },

    // TEMPLATE: Busy
    editorTemplateBusyCreate: function () {

        editors.setValueEditor("editor-templatebusy-datetime-date", "");

        editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');
        editors.setValueEditor('editor-id-templatebusy', -1);

        document.getElementById("editor-templatebusy-searchresult").innerHTML = "";

        editors.setInnerHtmlEditor("editor-templatebusy-btn-save", "Hinzufügen");
        editors.setVisibleEditor("editor-templatebusy-action-delete", false);

        editors.editorTemplateBusyValidation();
        editors.showEditor("templatebusy");

    },
    editorTemplateBusyEdit: function (id, replaceId, title, subtitle, replaceDate, timeStart, timeEnd) {

        // Dienst laden & anzeigen
        editors.setVisibleEditor("messageDisableOverlay", true);
        editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');

        editors.setValueEditor('editor-id-templatebusy', id);
        editors.setValueEditor("editor-templatebusy-input-replacedid", replaceId);

        editors.setValueEditor("editor-templatebusy-input-title", title);
        editors.setValueEditor("editor-templatebusy-input-subtitle", subtitle);

        editors.setValueEditor("editor-templatebusy-datetime-date-start", replaceDate);
        editors.setValueEditor("editor-templatebusy-datetime-time-start", timeStart);
        editors.setValueEditor("editor-templatebusy-datetime-time-end", timeEnd);

        editors.setInnerHtmlEditor("editor-templatebusy-btn-save", "Speichern");
        editors.setVisibleEditor("editor-templatebusy-action-delete", true);

        const data = new URLSearchParams();
        data.append('id', replaceId);

        fetch("api.php?action=ADMIN-GET-UI-SINGLEID",
        {
            method: 'POST',
            redirect: 'manual',
            body: data
        })
        .then(response => response.text())
        .then(html => { 
            document.getElementById("editor-templatebusy-searchresult").innerHTML = html;
        })
        .finally(function () {

            editors.setVisibleEditor("messageDisableOverlay", false);
            editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'edit');

            editors.editorTemplateBusyValidation();
            editors.showEditor("templatebusy");

        });

        

    },
    editorTemplateBusyValidation: function () {

        var isValid = true;

        var toolMode = document.getElementById("editor-templatebusy-tool-mode").getAttribute("mode");

        // SearchTask
        searchValid = true;
        if (!dateutil.checkDateActual(
            editors.getValueEditor("editor-templatebusy-datetime-date")
        )) { searchValid = false; }
        editors.setBtnEnabledEditor("editor-templatebusy-btn-searchtask", searchValid);

        // Tool-Mode
        editors.setVisibleEditor("editor-templatebusy-input-title", toolMode != "none");
        editors.setVisibleEditor("editor-templatebusy-input-subtitle", toolMode != "none");
                
        editors.setVisibleEditor("editor-templatebusy-datetime-header-start", toolMode != "none");
        editors.setVisibleEditor("editor-templatebusy-datetime-date-start", toolMode != "none");
        editors.setVisibleEditor("editor-templatebusy-datetime-time-start", toolMode != "none");
        editors.setVisibleEditor("editor-templatebusy-datetime-time-end", toolMode != "none");

        editors.setVisibleEditor("editor-templatebusy-hr-resultdiv", toolMode != "none");
        editors.setVisibleEditor("editor-templatebusy-hr-replace-header", toolMode != "none");

        editors.setVisibleEditor("editor-templatebusy-hr-search-header", toolMode != "edit");
        editors.setVisibleEditor("editor-templatebusy-datetime-date", toolMode != "edit", "inline-block");
        editors.setVisibleEditor("editor-templatebusy-btn-searchtask", toolMode != "edit", "inline-block");

        switch(toolMode) {
            case 'none':
                isValid = false;

                break;

            case 'edit':
            case 'selected':
                
                if (!editors.hasValueEditor("editor-templatebusy-input-title")) { isValid = false; }

                if (!dateutil.checkDateTime(
                    editors.getValueEditor("editor-templatebusy-datetime-date-start"),
                    editors.getValueEditor("editor-templatebusy-datetime-time-start"),
                    editors.getValueEditor("editor-templatebusy-datetime-date-start"),
                    editors.getValueEditor("editor-templatebusy-datetime-time-end"))) { isValid = false; }

                if (!dateutil.checkDateTimeActual(
                    editors.getValueEditor("editor-templatebusy-datetime-date-start"),
                    editors.getValueEditor("editor-templatebusy-datetime-time-end"))) { isValid = false; }

                break;

        } 

        editors.calculateEditorPosition();

        editors.setBtnEnabledEditor("editor-templatebusy-btn-save", isValid);

    },
    editorTemplateBusyInvokeSearch: function() {

        editors.setVisibleEditor("messageDisableOverlay", true);
        editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');

        fetch("api.php?action=ADMIN-SEARCH-CYCLEDTASK&date=" + editors.getValueEditor("editor-templatebusy-datetime-date"), {cache: "no-store"})
        .then(response => response.text())
        .then(html => {
                    
            document.getElementById("editor-templatebusy-searchresult").innerHTML = html;

        })
        .finally(function () {

            editors.setVisibleEditor("messageDisableOverlay", false);
            editors.editorTemplateBusyValidation();

        });

    },
    editorTemplateBusyInvokeSelect: function(sender, id, subtitle, title, timeStart, timeEnd) {

        [...document.getElementById("editor-templatebusy-searchresult").getElementsByTagName("button")].forEach(item=>{item.classList.remove("select-active")});
        sender.classList.add("select-active");

        editors.setValueEditor("editor-templatebusy-input-replacedid", id);

        editors.setValueEditor("editor-templatebusy-input-title", title);
        editors.setValueEditor("editor-templatebusy-input-subtitle", subtitle);

        editors.setValueEditor("editor-templatebusy-datetime-date-start", dateutil.convertToInputDate(new Date()));
        editors.setValueEditor("editor-templatebusy-datetime-time-start", timeStart);
        editors.setValueEditor("editor-templatebusy-datetime-time-end", timeEnd);

        editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'selected');
        editors.editorTemplateBusyValidation();

    },
    editorTemplateBusyInvokeSubmit: function() {

        editors.setVisibleEditor("messageDisableOverlay", true);

        // Ereignisse vorbereiten
        var replaceId = editors.getValueEditor("editor-templatebusy-input-replacedid");
        var replaceDate = editors.getValueEditor("editor-templatebusy-datetime-date");

        var originalId = editors.getValueEditor("editor-id-templatebusy");
        var taskTitle = editors.getValueEditor("editor-templatebusy-input-title");
        var taskSubtitle = editors.getValueEditor("editor-templatebusy-input-subtitle");
        var taskDate = editors.getValueEditor("editor-templatebusy-datetime-date-start");
        var taskTimeStart = editors.getValueEditor("editor-templatebusy-datetime-time-start");
        var taskTimeEnd = editors.getValueEditor("editor-templatebusy-datetime-time-end");

        // Task erstellen
        const dataTask = new URLSearchParams();
        dataTask.append('replace_id', replaceId);
        dataTask.append('replace_date', replaceDate);
        
        dataTask.append('orgId', originalId);
        dataTask.append('title', taskTitle);
        dataTask.append('subtitle',taskSubtitle);
        dataTask.append('date', taskDate);
        dataTask.append('timeStart', taskTimeStart);
        dataTask.append('timeEnd', taskTimeEnd);

        fetch("api.php?action=ITEM-REPLACE",
        {
            method: 'POST',
            redirect: 'manual',
            body: dataTask
        })
        .then(response => response.text())
        .then(html => { })
        .finally(function () {

            editors.submitForm("editor-form-templatebusy");

        });

    },
    editorTemplateBusyInvokeDelete: function() {

        editors.setVisibleEditor("messageDisableOverlay", true);

        // Ereignisse vorbereiten
        var replaceId = editors.getValueEditor("editor-templatebusy-input-replacedid");
        var originalId = editors.getValueEditor("editor-id-templatebusy");

        // Task erstellen
        const dataTask = new URLSearchParams();
        dataTask.append('replace_id', replaceId);
        dataTask.append('orgId', originalId);

        fetch("api.php?action=ITEM-REPLACE-DELETE",
        {
            method: 'POST',
            redirect: 'manual',
            body: dataTask
        })
        .then(response => response.text())
        .then(html => { })
        .finally(function () {

            editors.submitForm("editor-form-templatebusy");

        });

    },

    // MESSAGES
    messageOpen: function(title, subtitle, message, warnMode, btnMode, positiveFunc, negativeFunc, waitOnAction) {

        editors.setVisibleEditor("message-icon-warn", warnMode);

        switch(btnMode) {
            case 'ok-only':
                
                editors.setVisibleEditor("message-positive-btn", true);
                editors.setVisibleEditor("message-negative-btn", false);
                
                editors.setInnerTextEditor("message-positive-btn", "OK");
                editors.setInnerTextEditor("message-negative-btn", "Abbrechen");

              break;

            case 'yes-no':
             
                editors.setVisibleEditor("message-positive-btn", true);
                editors.setVisibleEditor("message-negative-btn", true);

                editors.setInnerTextEditor("message-positive-btn", "Ja");
                editors.setInnerTextEditor("message-negative-btn", "Nein");

              break;
            default:
              return;
        }
        
        editors.setInnerTextEditor("message-title", title);
        editors.setInnerTextEditor("message-subtitle", subtitle);
        editors.setVisibleEditor("message-subtitle", subtitle != null);
        editors.setInnerHtmlEditor("message-message", message);

        editors.messagePositiveAction = function() { 
            if (positiveFunc != null) { positiveFunc(); if (waitOnAction) { editors.setVisibleEditor("messageDisableOverlay", true); } } 
            editors.closeMessage(); } 
        editors.messageNegativeAction = function() { 
            if (negativeFunc != null) { negativeFunc(); if (waitOnAction) { editors.setVisibleEditor("messageDisableOverlay", true); } } 
            editors.closeMessage(); } 

        editors.showMessage("error");

    },
    messagePositiveAction: function() {},
    messageNegativeAction: function() {}

}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////77

var dateutil = {

    getWochentag: function (date) {

        date = date || new Date();
        if (date.getDay == null) { date = new Date(); }

        var wochentag = date.getDay()
        if (wochentag == 0) return "Sonntag";
        if (wochentag == 1) return "Montag";
        if (wochentag == 2) return "Dienstag";
        if (wochentag == 3) return "Mittwoch";
        if (wochentag == 4) return "Donnerstag";
        if (wochentag == 5) return "Freitag";
        if (wochentag == 6) return "Samstag";

        return "";

    },

    getMonthName: function (date) {

        date = date || new Date();
        if (date.getMonth == null) { date = new Date(); }

        var monat = date.getMonth()
        if (monat == 0) return "Januar";
        if (monat == 1) return "Februar";
        if (monat == 2) return "März";
        if (monat == 3) return "April";
        if (monat == 4) return "Mai";
        if (monat == 5) return "Juni";
        if (monat == 6) return "Juli";
        if (monat == 7) return "August";
        if (monat == 8) return "September";
        if (monat == 9) return "Oktober";
        if (monat == 10) return "November";
        if (monat == 11) return "Dezember";

        return "";

    },

    checkDate: function (startDate, endDate) {
        return dateutil.checkDateTime(startDate, "00:00", endDate, "00:00");
    },
    checkDateTime: function (startDate, startTime, endDate, endTime) {

        var start = new Date(startDate + " " + startTime);
        var ende = new Date(endDate + " " + endTime);

        if (varExt.isDate(start) && varExt.isDate(ende)) { return ende > start; }
        return false;

    },
    checkTime: function (startTime, endTime) {

        var start = new Date("2000-01-01 " + startTime);
        var ende = new Date("2000-01-01 " + endTime);

        if (varExt.isDate(start) && varExt.isDate(ende)) { return ((ende > start) || (startTime == "00:00" && endTime == "00:00")); }
        return false;

    },
    checkDateActual: function (date) {
        return dateutil.checkDateTimeActual(date, "23:59");
    },
    checkDateTimeActual: function (date, time) {
        var check = new Date(date + " " + time);

        if (varExt.isDate(check)) { return (check >= new Date()); }
        return false;
    },

    convertToInputTime: function (date) {
        return String(date.getHours()).padStart(2, '0') + ":" + String(date.getMinutes()).padStart(2, '0');
    },
    convertToInputDate: function (date) {
        return String(date.getFullYear()) + '-' + String(date.getMonth(date)+1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

}

var jsext = {

    stringIsEmptyOrWhitespace: function (str) {
        return str === null || str.match(/^ *$/) !== null || str.length == 0;
    },
    stringGenerateHash: function (str) {
     
        var hash = 0;
        str = str || "";  
        
        if (str.length == null || str.length == 0) return hash;
              
        for (i = 0; i < str.length; i++) {
            char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
              
        return hash;

    },

    setCookie: function(cname, cvalue, exdays) {
        const d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        let expires = "expires="+d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    },
      
    getCookie: function(cname) {
        let name = cname + "=";
        let ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
          let c = ca[i];
          while (c.charAt(0) == ' ') {
            c = c.substring(1);
          }
          if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
          }
        }
        return "";
    }

}

var varExt = {

    isDate: function (date) {
        return date instanceof Date && !isNaN(date);
    },

    isEmail: function (mail) {
        var pattern = /(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/;
        return (typeof mail === "string") && pattern.test(mail);
    },

    isInt: function (inttxt) {
        return Number.isInteger(parseInt(inttxt, 10));
    },

    isOnlyAlpha: function (text) {
        var pattern = /^[a-zA-ZäöüÄÖÜ\s]+$/;
        return text.match(pattern);
    }

}
