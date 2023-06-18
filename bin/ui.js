var WIM = (function () {

    // Private Variables
    var ui_requestTypeInfo = '';
    var ui_requestTypeTask = '';
    var ui_requestTypeEvent = '';
    var ui_requestTypeWarn = '';
    var ui_wimlocation = { lat: 51.1239082542166, long: 13.585863667297769 };

    var adm_currentOpenedEditor = null;
    var adm_currentOpenedMessage = { isOpen: false, positiveAction: null, negativeAction: null };
    var adm_vehicleTiming = null;

    // Private Functions
    var UiHelper =
    {

        // variables
        ui_hashes: { info: '', task: '', event: '', changed: false },

        // functions
        updateTheme: function () {

            var date = new Date();

            // calculate sun position
            var sun = SunCalc.getTimes(date, ui_wimlocation.lat, ui_wimlocation.long);

            // set dark/light theme
            var useDark = date < sun.sunrise || date > sun.sunsetStart;
            var themeVariableLink = document.getElementById('css-theme-variables');
            if (useDark) { themeVariableLink.href = "res/theme-dark.css"; }
            else { themeVariableLink.href = "res/theme-light.css"; }

            // restart after 1min > like a service
            var t = setTimeout(UiHelper.updateTheme, 60000);

        },

        updateClock: function () {

            // set clock
            var date = new Date();
            let timeTxt = DateHelper.getTimeFraction(date);
            var dateTxt = DateHelper.getDateFraction(date, true, true);
            document.getElementById('ui-clock-time').innerText = timeTxt;
            document.getElementById('ui-clock-date').innerText = dateTxt;

            // restart after 30s > like a service
            var t = setTimeout(UiHelper.updateClock, 30000);

        },

        updateOverflow: function (margin) {

            if (margin < 0) {

                overflowKeyframe.innerHTML = `
                    @keyframes marquee {
                        0%, 10%, 90%, 100% { transform: translateY(0); }
                        40%, 60% { transform: translateY(` + margin + `px); }
                    }
                    
                    #todaylayout-overflow {
                        animation: marquee ` + Math.abs(margin / 3) + `s linear infinite;
                    }`;

            } else {

                overflowKeyframe.innerHTML = "";

            }

        },

        fetchEntriesForType: async function (type) {
            try {
                // fetch
                const response = await fetch('api.php?action=GET-UI&type=' + type, { cache: 'no-store' });
                if (!response.ok) { throw new Error('error while fetching UI (' + type ?? 'type undefined' + ')') }

                // return html
                const htmlCode = await response.text();
                return htmlCode;
            }
            catch (error) {
                console.error(error);
                return null;
            }
        },
        updateEntries: async function () {

            // container
            var listInfo = document.getElementById("list-info");
            var listTask = document.getElementById("list-task");
            var listEvent = document.getElementById("list-event");

            // meta
            var noEntries = true;

            // fetch info-area
            var listWarnHtml = await UiHelper.fetchEntriesForType(ui_requestTypeWarn);
            var listInfoHtml = listWarnHtml + (await UiHelper.fetchEntriesForType(ui_requestTypeInfo));
            if (listInfoHtml) {
                noEntries = false;

                let hash = StringHelper.generateHash(listInfoHtml)
                if (hash != UiHelper.ui_hashes.info) {
                    UiHelper.ui_hashes.info = hash
                    UiHelper.ui_hashes.changed = true
                    listInfo.innerHTML = listInfoHtml
                    FormHelper.domVisibility('group-info', true)
                }
            }
            else {
                listInfo.innerHTML = ''
                FormHelper.domVisibility('group-info', false)
            }

            // fetch task-area
            var listTaskHtml = await UiHelper.fetchEntriesForType(ui_requestTypeTask);
            if (listTaskHtml) {
                noEntries = false;

                let hash = StringHelper.generateHash(listTaskHtml)
                if (hash != UiHelper.ui_hashes.task) {
                    UiHelper.ui_hashes.task = hash
                    UiHelper.ui_hashes.changed = true

                    listTask.innerHTML = listTaskHtml
                    FormHelper.domVisibility('group-task', true)
                }
            }
            else {
                listTask.innerHTML = ''
                FormHelper.domVisibility('group-task', false)
            }

            // show no messages item or hide it
            if (noEntries) {
                FormHelper.domVisibility('group-info', true)
                listInfo.innerHTML = "<li class='check'><div class='title'>Keine Meldungen</div></li>"
            }

            // fetch events
            var listEventHtml = await UiHelper.fetchEntriesForType(ui_requestTypeEvent);
            if (listEventHtml) {
                let hash = StringHelper.generateHash(listEventHtml)
                if (hash != UiHelper.ui_hashes.event) {
                    UiHelper.ui_hashes.event = hash
                    UiHelper.ui_hashes.changed = true

                    listEvent.innerHTML = listEventHtml
                    document.body.classList.remove("no-future");
                }
            }
            else {
                listEvent.innerHTML = ''
                document.body.classList.add("no-future");
            }

            // update overflow container
            if (UiHelper.ui_hashes.changed) {

                var overflowContainer = document.getElementById("todaylayout-overflow");
                var headerDiv = document.getElementById("header");
                var margin = (screen.height - overflowContainer.scrollHeight - headerDiv.offsetHeight);
                UiHelper.updateOverflow(margin);

            }

            // restart updateEntries > like a service
            var t = setTimeout(UiHelper.updateEntries, 60000);

        },

    };

    var FormHelper = {

        /* INPUT */
        inputSetValue: function (htmlId, value) {
            let input = document.getElementById(htmlId);
            if (input) {
                if (input.tagName.toLowerCase() == 'button') { this.domSetInnerText(htmlId, value); return }
                input.value = value ?? '';
            }
        },
        inputGetValue: function (htmlId, defValue = '') {
            let input = document.getElementById(htmlId);
            return input?.value
        },
        inputIsEmpty: function (htmlId) {
            let input = document.getElementById(htmlId);
            if (!input) { return false }
            return StringHelper.stringIsEmptyOrWhitespace(input.value ?? '')
        },

        /* SELECT */
        selectSetByValue: function (htmlId, value) {
            let select = document.getElementById(htmlId)
            let options = select?.options
            if (select && options) {
                for (var i = 0; i < options.length; i++) {
                    let option = options[i]
                    if (option.value == value) {
                        select.selectedIndex = i
                        break;
                    }
                }
            }
        },
        selectGetValue: function (htmlId, defValue = '') {
            let select = document.getElementById(htmlId);
            if (select && select.selectedIndex >= 0) {
                return select.options[select.selectedIndex].value;
            }
            return defValue
        },
        selectGetLabel: function (htmlId, defLabel = '') {
            let select = document.getElementById(htmlId);
            if (select && select.selectedIndex >= 0) {
                return select.options[select.selectedIndex].innerText;
            }
            return defLabel
        },
        selectImportOptions: function (htmlId, options, defaultValue = '') {
            var select = document.getElementById(htmlId)
            if (select) {
                let html = ''
                if (defaultValue.length > 0) { html = html + "<option value=''> " + defaultValue + " </option>" }
                options.forEach(function (option) {
                    html = html + "<option value='" + option.name + "'> " + option.name + " </option>"
                });
                select.innerHTML = html
            }
        },

        /* CHECKBOX */
        checkboxState: function (htmlId, isChecked) {
            let checkbox = document.getElementById(htmlId);
            if (checkbox.type === 'checkbox') { checkbox.checked = isChecked }
        },

        /* FORM */
        formSetToolState: function (htmlId, key, value) {
            let tool = document.getElementById(htmlId);
            if (tool) { tool.setAttribute(key, value.toString()) }
        },
        formGetToolState: function (htmlId, key, defValue = '') {
            let tool = document.getElementById(htmlId);
            if (tool && tool.hasAttribute(key)) { return tool.getAttribute(key) }
            else { return defValue }
        },
        formSetAction: function (htmlId, action) {
            let form = document.getElementById(htmlId)
            if (form) { form.action = action }
        },
        formInvokeSubmit: function (htmlId, action = null) {
            let form = document.getElementById(htmlId)
            if (action != null) { form.action = action }
            form.submit();
        },

        /* PREVIEW */
        previewItem: function (htmlId, payload) {

            let anyMeta = payload.category || payload.location
            let duoMeta = payload.category && payload.location
            let hasVehicle = !StringHelper.stringIsEmptyOrWhitespace(payload.vehicle ?? '')

            // title
            let html = "<div class='group'><ul><li>"
            html += "<div class='title" + (hasVehicle ? " vehicle'>" : "'>")
            html += hasVehicle ? "<span>" + payload.vehicle + "</span>" : ""
            html += payload.title
            html += "</div>"

            // meta
            if (anyMeta)
            {
                html += "<div class='subtext meta'>"
                html += (payload.category ?? '')
                html += duoMeta ? " (" + payload.location + ")" : (payload.location ?? '')
                html += "</div>"
            }

            html += payload.description ? "<div class='subtext description'>" + payload.description + "</div>" : ""
            html += payload.timeinfo ? "<div class='timeinfo'>" + payload.timeinfo + "</div>" : ""

            this.domSetInnerHtml(htmlId, html)

        },

        /* DATETIME */
        dateIsInFuture: function (htmlId) {
            let value = this.inputGetValue(htmlId)
            return value ? DateHelper.isInFuture(value) : false
        },
        dateIsValid: function (htmlId) {
            let value = this.inputGetValue(htmlId)
            return value ? DateHelper.isValidDate(value) : false
        },
        dateEndAfterStart: function (dateStartId, dateEndId) {
            let dateStartValue = this.inputGetValue(dateStartId)
            let dateEndValue = this.inputGetValue(dateEndId)
            return (dateStartValue && dateEndValue) ? DateHelper.isEndAfterStart(dateStartValue, dateEndValue) : false
        },
        datetimeIsInFuture: function (dateId, timeId) {
            let dateValue = this.inputGetValue(dateId)
            let timeValue = this.inputGetValue(timeId)
            return (dateValue && timeValue) ? DateHelper.isInFuture(dateValue, timeValue) : false
        },
        datetimeIsValid: function (dateId, timeId) {
            let dateValue = this.inputGetValue(dateId)
            let timeValue = this.inputGetValue(timeId)
            return (dateValue && timeValue) ? DateHelper.isValidDate(dateValue, timeValue) : false
        },
        datetimeEndAfterStart: function (dateStartId, timeStartId, dateEndId, timeEndId) {
            let dateStartValue = this.inputGetValue(dateStartId)
            let dateEndValue = this.inputGetValue(dateEndId)
            let timeStartValue = this.inputGetValue(timeStartId)
            let timeEndValue = this.inputGetValue(timeEndId)
            return (dateStartValue && dateEndValue && timeStartValue && timeEndValue) ? DateHelper.isEndAfterStart(dateStartValue, dateEndValue, timeStartValue, timeEndValue) : false
        },
        timeIsValid: function (htmlId) {
            let value = this.inputGetValue(htmlId)
            return value ? DateHelper.isValidDate('3000-01-01', value) : false
        },
        timeEndAfterStart: function (timeStartId, timeEndId) {
            let timeStartValue = this.inputGetValue(timeStartId)
            let timeEndValue = this.inputGetValue(timeEndId)
            return (timeStartValue && timeEndValue) ? DateHelper.isEndAfterStart('3000-01-01', '3000-01-01', timeStartValue, timeEndValue) : false
        },

        /* GENERIC */
        domVisibility: function (htmlId, isVisible, mode = 'block') {
            let element = document.getElementById(htmlId);
            if (element) {
                element.style.display = isVisible ? mode : 'none'
                if (isVisible) { element.removeAttribute('disabled') }
                else { element.setAttribute('disabled', '') }
            }
        },
        domEnabled: function (htmlId, isEnabled) {
            let element = document.getElementById(htmlId)
            if (element) { element.disabled = !isEnabled }
        },
        domSetInnerText: function (htmlId, text) {
            let element = document.getElementById(htmlId)
            if (element) { element.innerText = text }
        },
        domSetInnerHtml: function (htmlId, html) {
            let element = document.getElementById(htmlId)
            if (element) { element.innerHTML = html }
        },

    };

    var DateHelper = {

        getTimeFraction: function (dateObj) {
            try {
                let hours = dateObj.getHours().toString().padStart(2, '0');
                let minutes = dateObj.getMinutes().toString().padStart(2, '0');
                return hours + ':' + minutes;
            }
            catch (error) {
                console.error(error);
                return '--:--';
            }
        },
        getDateFraction: function (dateObj, withWeekday, withFullYear) {
            try 
            {

                // create datestring
                let options = { day: '2-digit', month: '2-digit', year: (withFullYear ? 'numeric' : '2-digit') };
                let formattedDate = dateObj.toLocaleDateString('de-DE', options);

                // prefix weekday, if withWeekday
                if (withWeekday) {
                    let weekday = dateObj.toLocaleDateString('de-DE', { weekday: 'short' });
                    formattedDate = weekday + ', ' + formattedDate;
                }

                return formattedDate;
            }
            catch (error) {
                console.error(error);
                return '--.--.--';
            }
        },

        isInFuture: function (date, time = '23:59') {
            let datetime = new Date(date + ' ' + time)
            return datetime instanceof Date && datetime.getTime() > Date.now()
        },
        isValidDate: function (date, time = '23:59') {
            let datetime = new Date(date + ' ' + time)
            return datetime instanceof Date
        },
        isEndAfterStart: function (dateStart, dateEnd, timeStart='23:59', timeEnd='23:59') {
            let datetimeStart = new Date(dateStart + ' ' + timeStart) 
            let datetimeEnd = new Date(dateEnd + ' ' + timeEnd) 
            return datetimeStart instanceof Date &&
                   datetimeEnd instanceof Date &&
                   datetimeEnd > datetimeStart
        }

    };

    var StringHelper = {

        generateHash: function (str) {

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

        stringIsEmptyOrWhitespace: function (str) {
            return str === null || str.trim().length === 0
        },

        escapeHtml: function (input) {
            return new Option(input).innerHTML;
        }

    };

    var CookieHelper = {

        set: function (key, value, daysUntilExpire = 360) {

            const d = new Date(); d.setTime(d.getTime() + (daysUntilExpire * 24 * 60 * 60 * 1000));
            let expires = "expires=" + d.toUTCString();
            document.cookie = key + "=" + value + ";" + expires + ";path=/";
        },

        get: function (key, defValue = '') {
            let name = key + "=";
            let ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    return c.substring(name.length, c.length);
                }
            }
            return defValue;
        }

    };

    var FileHelper = {

        downloadWimCrt: function () {
            let link = document.createElement('a')
            link.href = '/cert'
            link.download = 'wim-zertifikat.crt'
            link.style.display = 'none'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        },

        downloadExport: function () {
            
            let now = new Date()
            let dateOptions = DateHelper.getDateFraction(now, false, false)
            let timeOptions = DateHelper.getTimeFraction(now).replace(':', '')
            let datetimelbl = dateOptions + '-' + timeOptions
            
            let link = document.createElement('a')
            link.href = 'api.php?action=WIM-EXPORT'
            link.download = 'wim-'+datetimelbl+'.json'
            link.style.display = 'none'

            link.addEventListener('error', function() 
            {
                WIM.EDITOR.showMessage({
                    title: 'Fehler beim Export',
                    description: 'Irgendetwas hat nicht geklappt. Informiere bitte den Wachenverantwortlichen.',
                    showWarning: true,
                    mode: 'ok',
                    disableOnAction: false,
                    actionPositive: null,
                    actionNegative: null,
                })
            });

            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)

        }

    };

    // UI-Module
    var UI = {

        init: function (
            infoTag, taskTag, eventTag, warnTag,
            locationLat, locationLong) {
            ui_requestTypeInfo = infoTag
            ui_requestTypeTask = taskTag
            ui_requestTypeEvent = eventTag
            ui_requestTypeWarn = warnTag
            ui_wimlocation = { lat: locationLat, long: locationLong }

            setTimeout(UiHelper.updateClock, 0)
            setTimeout(UiHelper.updateTheme, 0)
            setTimeout(UiHelper.updateEntries, 1500)
        },

    };

    var EDITOR = {

        init: function (vehicleOptions) {
            adm_vehicleTiming = vehicleOptions

            // create heart-beat-listener
            document.addEventListener("visibilitychange", async function() 
            {
                if (document.visibilityState === "visible") 
                {
                    console.log("WIM-ADMIN: user has returned");
                    try 
                    {
                        let respone = await fetch('api.php?action=ACCOUNT-HEARTBEAT', { cache: 'no-store' });
                        if (respone.ok) { return }
                    }
                    catch (error) { console.error(error) }
                    window.location.reload()
                }
            });
        },

        // opening editor-dialogs
        showEditor: function (htmlsuffix) {
            document.getElementById("editorContainer").style.display = "block";
            document.getElementById("editorwindow-" + htmlsuffix).style.display = "block";
            adm_currentOpenedEditor = htmlsuffix;
            EDITOR.calculateEditorPosition();
        },
        closeEditor: function (htmlsuffix) {
            document.getElementById("editorContainer").style.display = "none";
            document.getElementById("editorwindow-" + htmlsuffix).style.display = "none";
            adm_currentOpenedEditor = null;
        },
        calculateEditorPosition: function () {
            if (adm_currentOpenedEditor == null) { return; }
            var dialog = document.getElementById("editorwindow-" + adm_currentOpenedEditor);

            var windowHeight = window.innerHeight;
            var dialogHeight = dialog.offsetHeight;

            if (windowHeight <= (dialogHeight + (0.2 * windowHeight))) { dialog.classList.add("editorWindow-nonFloating"); }
            else { dialog.classList.remove("editorWindow-nonFloating"); }

        },

        // opening messagebox-dialogs
        showMessage: function (message) {

            if (message == null) { return }
            if (message.mode == null || (message.mode != 'yes-no' && message.mode != 'ok')) { return }
            if (message.title == null || message.title.trim().length == 0) { return }
            if (message.description == null || message.description.trim().length == 0) { return }

            // get parameters
            let title = message.title
            let description = message.description
            let showWarning = message.showWarning ?? false
            let disableOnAction = message.disableOnAction ?? false

            // set dialog-elements
            FormHelper.domVisibility('message-icon-warn', showWarning)

            FormHelper.domSetInnerText('message-title', title)
            FormHelper.domSetInnerHtml('message-message', description)

            // set dialog-buttons
            switch (message.mode) {
                case 'yes-no':
                    FormHelper.domVisibility('message-positive-btn', true)
                    FormHelper.domVisibility('message-negative-btn', true)

                    FormHelper.inputSetValue('message-positive-btn', 'Ja')
                    FormHelper.inputSetValue('message-negative-btn', 'Nein')

                    adm_currentOpenedMessage.positiveAction = function () {
                        if (message.actionPositive != null) {
                            if (message.disableOnAction) { FormHelper.domVisibility('messageDisableOverlay', true) }
                            message.actionPositive()
                        }
                        EDITOR.closeMessage()
                    }
                    adm_currentOpenedMessage.negativeAction = function () {
                        if (message.actionNegative != null) {
                            if (message.disableOnAction) { FormHelper.domVisibility('messageDisableOverlay', true) }
                            message.actionNegative()
                        }
                        EDITOR.closeMessage()
                    }

                    break

                case 'ok':
                    FormHelper.domVisibility('message-positive-btn', true)
                    FormHelper.domVisibility('message-negative-btn', false)

                    FormHelper.inputSetValue('message-positive-btn', 'Ok')
                    FormHelper.inputSetValue('message-negative-btn', '')

                    adm_currentOpenedMessage.positiveAction = function () {
                        if (message.actionPositive != null) {
                            if (message.disableOnAction) { FormHelper.domVisibility('messageDisableOverlay', true) }
                            message.actionPositive()
                        }
                        EDITOR.closeMessage()
                    }
                    adm_currentOpenedMessage.negativeAction = null

                    break

            }

            // show container-dialog
            adm_currentOpenedMessage.isOpen = true
            FormHelper.domVisibility('messageContainer', true)
            FormHelper.domVisibility('messagewindow', true)
            EDITOR.calculateMessagePosition()

        },
        closeMessage: function () {
            FormHelper.domVisibility('messageContainer', false)
            FormHelper.domVisibility('messagewindow', false)
            adm_currentOpenedMessage.isOpen = false
        },
        calculateMessagePosition: function () {
            if (!adm_currentOpenedMessage.isOpen) { return; }

            var dialog = document.getElementById("messagewindow");

            var windowHeight = window.innerHeight;
            var dialogHeight = dialog.offsetHeight;

            if (windowHeight <= (dialogHeight + (0.2 * windowHeight))) { dialog.classList.add("editorWindow-nonFloating"); }
            else { dialog.classList.remove("editorWindow-nonFloating"); }
        },
        messagePositiveAction: () => adm_currentOpenedMessage.positiveAction(),
        messageNegativeAction: () => adm_currentOpenedMessage.negativeAction(),

        // groups
        adminGroups: {

            // expanding & collapsing functionality
            expandInit: function (htmlId) {
                let group = document.getElementById(htmlId)
                if (group?.parentElement?.getElementsByTagName('ul')[0]?.children?.length > 0) {
                    let state = CookieHelper.get('group-expanded--' + htmlId) == 'true'
                    this.expandSet(group, state)
                }
            },
            expandToggle: function (group) {
                if (group?.parentElement?.getElementsByTagName('ul')[0]?.children?.length > 0) {
                    let state = FormHelper.formGetToolState(group.id, 'expanded') == 'true'
                    this.expandSet(group, !state)
                }
            },
            expandSet: function (group, isExpanded) {
                if (group?.parentElement?.getElementsByTagName('ul')[0]?.children?.length > 0 &&
                    group?.getElementsByTagName('span')[0]) {

                    FormHelper.domVisibility(group.parentElement.getElementsByTagName('ul')[0].id, isExpanded)
                    group.getElementsByTagName('span')[0].classList = 'arrow ' + (isExpanded ? '' : 'arrow-coll')
                    FormHelper.formSetToolState(group.id, 'expanded', isExpanded)
                    CookieHelper.set('group-expanded--' + group.id, isExpanded ? 'true' : 'false')
                }
            },

        },
        disableUI: function (isDisabled) {
            FormHelper.domVisibility('messageDisableOverlay', isDisabled)
        },

        createPayload: function (title, description, category, location, vehicle) {
            let payload = {}
            payload.key = (vehicle ? vehicle + '#' : '') + (category ? category + '#' : '') + (location ? location + '#' : '') + title
            payload.title = StringHelper.stringIsEmptyOrWhitespace(title) ? 'Information' : StringHelper.escapeHtml(title)
            if (!StringHelper.stringIsEmptyOrWhitespace(description)) { payload.description = StringHelper.escapeHtml(description) }
            if (!StringHelper.stringIsEmptyOrWhitespace(category)) { payload.category = StringHelper.escapeHtml(category) }
            if (!StringHelper.stringIsEmptyOrWhitespace(location)) { payload.location = StringHelper.escapeHtml(location) }
            if (!StringHelper.stringIsEmptyOrWhitespace(vehicle)) { payload.vehicle = StringHelper.escapeHtml(vehicle) }
            return payload
        },

        // account-dialog
        accountEditor:
        {

            create: function () {

                FormHelper.formSetToolState('editor-account-actiontool', 'tool-action', '')
                FormHelper.inputSetValue('editor-account-input-user', '')
                FormHelper.inputSetValue('editor-account-input-oldpass', '')
                FormHelper.inputSetValue('editor-account-input-pass1', '')
                FormHelper.inputSetValue('editor-account-input-pass2', '')

                EDITOR.accountEditor.validate()
                EDITOR.showEditor('account')

            },
            validate: function () {

                let isValid = true

                // tool state
                let showToolUsername = FormHelper.formGetToolState('editor-account-actiontool', 'tool-action', '') == 'user'
                let showToolPassword = FormHelper.formGetToolState('editor-account-actiontool', 'tool-action', '') == 'pass'
                let showToolBack = showToolUsername || showToolPassword
                let showToolMenu = !showToolBack

                // visibility
                FormHelper.domVisibility('editor-account-action-user', showToolMenu)
                FormHelper.domVisibility('editor-account-action-certificate', showToolMenu);
                FormHelper.domVisibility('editor-account-action-password', showToolMenu);
                FormHelper.domVisibility('editor-account-action-logout', showToolMenu);

                FormHelper.domVisibility('editor-account-action-cancelcurrent', showToolBack);

                FormHelper.domVisibility('editor-account-actioncontainer-user', showToolUsername);
                if (showToolUsername) {
                    FormHelper.formSetAction('editor-account-form', 'api.php?action=ACCOUNT-CHANGEUSER')
                    FormHelper.inputSetValue('editor-account-btn-save', 'Ändern')
                    isValid = FormHelper.inputIsEmpty('editor-account-input-user') ? false : isValid
                }

                FormHelper.domVisibility('editor-account-actioncontainer-password', showToolPassword);
                if (showToolPassword) {
                    FormHelper.formSetAction('editor-account-form', 'api.php?action=ACCOUNT-CHANGEPASS')

                    let hasOldPassword = !FormHelper.inputIsEmpty('editor-account-input-oldpass')
                    let hasNewPassword = !(FormHelper.inputIsEmpty('editor-account-input-pass1') || FormHelper.inputIsEmpty('editor-account-input-pass2'))
                    let areNewSame = hasNewPassword && (FormHelper.inputGetValue('editor-account-input-pass1') == FormHelper.inputGetValue('editor-account-input-pass2'))

                    FormHelper.inputSetValue('editor-account-btn-save', 'Ändern');
                    FormHelper.domVisibility('editor-account-input-oldpass-error', (hasNewPassword && !hasOldPassword));
                    FormHelper.domVisibility('editor-account-input-newpass-error', (hasOldPassword && hasNewPassword && !areNewSame));

                    isValid = (hasOldPassword && areNewSame) ? isValid : false
                }

                // setup editor
                FormHelper.domVisibility('editor-account-action-user', false); /* currently not implemented */
                FormHelper.domVisibility('editor-account-btn-save', showToolBack);

                FormHelper.domEnabled('editor-account-btn-save', isValid);

                EDITOR.calculateEditorPosition();

            },

            changeState: function (value) {
                FormHelper.formSetToolState('editor-account-actiontool', 'tool-action', value)
            },

            invokeLogout: function () {
                FormHelper.formInvokeSubmit('editor-account-form', 'api.php?action=ACCOUNT-LOGOUT')
            },
            invokeCrtDownload: function () {
                EDITOR.showMessage({
                    title: 'HTTPS-Zertifikat Herunterladen',
                    description: 'Für den Zugriff auf diese Oberfläche wird ein HTTPS-Zertifikat benötigt, damit keine Fehlermeldungen angezeigt werden. Es wird jetzt heruntergeladen.',
                    showWarning: false,
                    mode: 'ok',
                    disableOnAction: false,
                    actionPositive: () => { FileHelper.downloadWimCrt() },
                    actionNegative: null,
                })
            },

        },

        // user-dialog
        userEditor: {

            create: function () {
                FormHelper.inputSetValue('editor-user-input-loginuser', '')
                FormHelper.checkboxState('editor-user-input-wimadmin', false)

                FormHelper.domVisibility('editor-user-action-passreset', false)
                FormHelper.domVisibility('editor-user-action-deleteuser', false)
                FormHelper.inputSetValue('editor-user-btn-save', 'Hinzufügen')

                FormHelper.inputSetValue('editor-id-user', -1);

                EDITOR.userEditor.validate()
                EDITOR.showEditor('user')
            },
            edit: function (id, username, isAdmin) {
                FormHelper.inputSetValue('editor-user-input-loginuser', username)
                FormHelper.checkboxState('editor-user-input-wimadmin', isAdmin)

                FormHelper.domVisibility('editor-user-action-passreset', true)
                FormHelper.domVisibility('editor-user-action-deleteuser', true)
                FormHelper.inputSetValue('editor-user-btn-save', 'Speichern')

                FormHelper.inputSetValue('editor-id-user', id);

                EDITOR.userEditor.validate()
                EDITOR.showEditor('user')
            },

            validate: function () {
                let isValid = true

                isValid = FormHelper.inputIsEmpty('editor-user-input-loginuser') ? false : isValid
                isValid = /^[a-z]+$/i.test(FormHelper.inputGetValue('editor-user-input-loginuser', '#')) ? isValid : false

                FormHelper.domEnabled('editor-user-btn-save', isValid)
            },

            invokePasswordReset: function () {
                EDITOR.showMessage({
                    title: 'Nutzerpasswort zurücksetzen',
                    description: 'Im Anschluss wird ein neues Passwort erstellt. Du musst dem Nutzer das neue Passwort mitteilen, sonst kann sich dieser nicht mehr anmelden. Soll das Passwort jetzt wirklich zurückgesetzt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-user-form', 'api.php?action=USER-PASSRESET') },
                    actionNegative: null,
                })
            },
            invokeDelete: function () {
                EDITOR.showMessage({
                    title: 'Nutzerprofil löschen',
                    description: 'Dieser Nutzer kann sich nicht mehr anmelden, wenn du sein Profil löscht. Seine Nachrichten werden ebenso entfernt und können nicht wiederhergestellt werden. Soll der Nutzer wirklich jetzt entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-user-form', 'api.php?action=USER-DELETE') },
                    actionNegative: null,
                })
            },

        },

        // settings-dialog
        settingsEditor: {

            create: function (name, resolution, geopos, timing) {

                FormHelper.formSetToolState('editor-settings-actiontool', 'tool-action', '')

                // ui-container
                FormHelper.inputSetValue('editor-settings-input-wachename', name)
                FormHelper.selectSetByValue('editor-settings-select-resolution', resolution)

                FormHelper.inputSetValue('editor-settings-input-lat', geopos?.lat ?? '')
                FormHelper.inputSetValue('editor-settings-input-long', geopos?.long ?? '')

                // timing-container
                FormHelper.inputSetValue('editor-settings-input-deftiming-time-start', timing?.defaultStart ?? '')
                FormHelper.inputSetValue('editor-settings-input-deftiming-time-end', timing?.defaultEnd ?? '')

                let jsonTiming = ''
                try { jsonTiming = JSON.stringify(timing?.vehicles, null, 2) }
                catch (error) { }
                FormHelper.domSetInnerHtml('editor-settings-input-wachekfz', jsonTiming)

                EDITOR.settingsEditor.validate()
                EDITOR.showEditor('settings')
            },
            validate: function () {

                let isValid = true

                // tool state
                let showToolUi = FormHelper.formGetToolState('editor-settings-actiontool', 'tool-action', '') == 'ui'
                let showToolTiming = FormHelper.formGetToolState('editor-settings-actiontool', 'tool-action', '') == 'vehicles'
                let showToolImportExport = FormHelper.formGetToolState('editor-settings-actiontool', 'tool-action', '') == 'importexport'
                let showToolBack = showToolUi || showToolTiming || showToolImportExport
                let showToolMenu = !showToolBack

                // visibility
                FormHelper.domVisibility('editor-settings-action-ui', showToolMenu)
                FormHelper.domVisibility('editor-settings-action-vehicles', showToolMenu);
                FormHelper.domVisibility('editor-settings-action-importexport', showToolMenu)

                FormHelper.domVisibility('editor-settings-action-cancelcurrent', showToolBack);

                FormHelper.domVisibility('editor-settings-actioncontainer-ui', showToolUi);
                if (showToolUi) {
                    FormHelper.formSetAction('editor-settings-form', 'api.php?action=SETTINGS-WIM&m=UI')
                    isValid = FormHelper.inputIsEmpty('editor-settings-input-wachename') ? false : isValid
                    isValid = FormHelper.inputIsEmpty('editor-settings-input-lat') ? false : isValid
                    isValid = FormHelper.inputIsEmpty('editor-settings-input-long') ? false : isValid

                    if (!(/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,9})?))$/).test(FormHelper.inputGetValue('editor-settings-input-lat'))) { isValid = false }
                    if (!(/^(\+|-)?(?:180(?:(?:\.0{1,6})?)|(?:[0-9]|[1-9][0-9]|1[0-7][0-9])(?:(?:\.[0-9]{1,6})?))$/).test(FormHelper.inputGetValue('editor-settings-input-long'))) { isValid = false }

                    if (isValid) {
                        let json = {
                            "lat": FormHelper.inputGetValue('editor-settings-input-lat'),
                            "long": FormHelper.inputGetValue('editor-settings-input-long')
                        }
                        FormHelper.inputSetValue('editor-settings-input-wachelocation', JSON.stringify(json))
                    }
                }

                FormHelper.domVisibility('editor-settings-actioncontainer-vehicles', showToolTiming)
                if (showToolTiming) {
                    FormHelper.formSetAction('editor-settings-form', 'api.php?action=SETTINGS-WIM&m=TIMING')

                    isValid = (document.getElementById('editor-settings-input-deftiming-time-start')?.checkValidity() ?? false) ? isValid : false
                    isValid = (document.getElementById('editor-settings-input-deftiming-time-end')?.checkValidity() ?? false) ? isValid : false

                    try {
                        jsonObject = JSON.parse(FormHelper.inputGetValue('editor-settings-input-wachekfz', ''))
                        isValid =
                            Array.isArray(jsonObject) &&
                                jsonObject.every(function (obj) {
                                    return obj.hasOwnProperty('name') &&
                                        obj.hasOwnProperty('starttime') &&
                                        obj.hasOwnProperty('endtime') &&
                                        obj.name != '' &&
                                        obj.name.length <= 40 &&
                                        (/^([0-9a-z öäü])+$/i).test(obj.name) &&
                                        (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/).test(obj.starttime) &&
                                        (/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/).test(obj.endtime);
                                }) ? isValid : false
                    }
                    catch (error) {
                        isValid = false
                    }

                    if (isValid) {
                        let json = {
                            "defaultStart": FormHelper.inputGetValue('editor-settings-input-deftiming-time-start'),
                            "defaultEnd": FormHelper.inputGetValue('editor-settings-input-deftiming-time-end'),
                            "vehicles": jsonObject
                        }
                        FormHelper.inputSetValue('editor-settings-input-vehicletiming', JSON.stringify(json))
                    }
                }

                // set save-button here, because import/export hides it anyway
                FormHelper.domVisibility('editor-settings-btn-save', showToolBack);
                FormHelper.domEnabled('editor-settings-btn-save', isValid);

                FormHelper.domVisibility('editor-settings-actioncontainer-importexport', showToolImportExport);
                if (showToolImportExport) {
                    

                    FormHelper.domVisibility('editor-settings-btn-save', false);

                }

                EDITOR.calculateEditorPosition();

            },

            changeState: function (value) {
                FormHelper.formSetToolState('editor-settings-actiontool', 'tool-action', value)
            },

            invokeResetVehicles: function () {
                FormHelper.inputSetValue('editor-settings-input-deftiming-time-start', '05:30')
                FormHelper.inputSetValue('editor-settings-input-deftiming-time-end', '19:00')
                FormHelper.inputSetValue('editor-settings-input-wachekfz',
                    `[
    {
        "name": "Alle RTWs",
        "starttime": "05:30",
        "endtime": "19:00"
    },
    {
        "name": "RTW 1",
        "starttime": "05:45",
        "endtime": "17:15"
    },
    {
        "name": "RTW 2",
        "starttime": "06:50",
        "endtime": "19:00"
    }
]`)
                EDITOR.settingsEditor.validate()
            },

            invokeExport: function () {
                EDITOR.showMessage({
                    title: 'Einstellungen exportieren',
                    description: 'Die Datei, die du als nächstes herunterlädst enthält bis auf die Kalendermodule alle Einstellungen &amp; Einträge. In einem anderen WIM, kannst du diese Datei wieder importieren.',
                    showWarning: false,
                    mode: 'ok',
                    disableOnAction: false,
                    actionPositive: () => { FileHelper.downloadExport() },
                    actionNegative: null,
                })
            },
            invokeImport: function () {
                EDITOR.showMessage({
                    title: 'Einstellungen importieren',
                    description: 'Die Datei wird im nächsten Schritt analysiert und dann importiert. Alle Einstellungen &amp; Einträge in diesem WIM werden überschrieben. Willst du wirklich fortfahren?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: false,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-settings-import-form') },
                    actionNegative: null,
                })
                
            },


        },

        // item-info
        infoEditor: {

            create: function () {

                FormHelper.inputSetValue('editor-info-input-title', '')
                FormHelper.inputSetValue('editor-info-input-description', '')
                FormHelper.inputSetValue('editor-info-input-category', '')
                FormHelper.inputSetValue('editor-info-input-location', '')
                FormHelper.selectImportOptions('editor-info-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')

                FormHelper.domVisibility('editor-info-action-delete', false)
                FormHelper.domSetInnerText('editor-info-btn-save', 'Hinzufügen')
                FormHelper.inputSetValue('editor-id-info', -1)

                EDITOR.infoEditor.validate()
                EDITOR.showEditor('info')
            },
            edit: function (id, payload, dateStart, dateEnd) {

                FormHelper.inputSetValue('editor-info-input-title', payload.title)
                FormHelper.inputSetValue('editor-info-input-description', payload.description)
                FormHelper.inputSetValue('editor-info-input-category', payload.category)
                FormHelper.inputSetValue('editor-info-input-location', payload.location)
                FormHelper.selectImportOptions('editor-info-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')
                FormHelper.selectSetByValue('editor-info-select-vehicle', payload.vehicle)

                FormHelper.domVisibility('editor-info-action-delete', true)
                FormHelper.domSetInnerText('editor-info-btn-save', 'Speichern')
                FormHelper.inputSetValue('editor-id-info', id)

                dateStart = dateStart ?? ''
                dateEnd = dateEnd ?? ''

                EDITOR.infoEditor.changeState(dateStart != '' ? 'withdate' : 'nodate')

                FormHelper.inputSetValue('editor-info-datetime-input-start', dateStart)
                FormHelper.inputSetValue('editor-info-datetime-input-end', dateEnd)

                EDITOR.infoEditor.validate()
                EDITOR.showEditor('info')

            },
            validate: function () {

                let isValid = true

                // create payload & preview
                let title = FormHelper.inputGetValue('editor-info-input-title')
                if (StringHelper.stringIsEmptyOrWhitespace(title)) { title = 'Kein Titel'; isValid = false }
                
                let description = FormHelper.inputGetValue('editor-info-input-description')
                let category = FormHelper.inputGetValue('editor-info-input-category')
                let location = FormHelper.inputGetValue('editor-info-input-location')
                let vehicle = FormHelper.selectGetValue('editor-info-select-vehicle')

                let payload = EDITOR.createPayload(title, description, category, location, vehicle)
                FormHelper.previewItem('editor-info-preview', payload)
                FormHelper.inputSetValue('editor-info-input-payload', JSON.stringify(payload))

                // check datetime
                let showToolWithDate = FormHelper.formGetToolState('editor-info-datetime-tool', 'tool-withdate', 'nodate') == 'withdate'
                let showToolNoDate = !showToolWithDate

                FormHelper.domVisibility('editor-info-datetime-tool-withdate', showToolNoDate, 'inline-block')
                FormHelper.domVisibility('editor-info-datetime-tool-nodate', showToolWithDate, 'inline-block')

                FormHelper.domVisibility('editor-info-datetime-header-start', showToolWithDate)
                FormHelper.domVisibility('editor-info-datetime-header-end', showToolWithDate)
                FormHelper.domVisibility('editor-info-datetime-input-start', showToolWithDate)
                FormHelper.domVisibility('editor-info-datetime-input-end', showToolWithDate)

                if (showToolWithDate) {

                    isValid = (document.getElementById('editor-info-datetime-input-start')?.checkValidity() ?? false) ? isValid : false
                    isValid = (document.getElementById('editor-info-datetime-input-end')?.checkValidity() ?? false) ? isValid : false
                    isValid = FormHelper.inputIsEmpty('editor-info-datetime-input-start') ? false : isValid
                    isValid = FormHelper.inputIsEmpty('editor-info-datetime-input-end') ? false : isValid
                    isValid = FormHelper.dateIsInFuture('editor-info-datetime-input-end') ? isValid : false
                }
                else {
                    FormHelper.inputSetValue('editor-info-datetime-input-start', '')
                    FormHelper.inputSetValue('editor-info-datetime-input-end', '')
                }

                // set button
                FormHelper.domEnabled('editor-info-btn-save', isValid)

            },

            changeState: function (state) {
                FormHelper.formSetToolState('editor-info-datetime-tool', 'tool-withdate', state)
            },

            delete: function () {
                EDITOR.showMessage({
                    title: 'Mitteilung löschen',
                    description: 'Du kannst das Löschen nicht rückgängig machen. Soll der Eintrag wirklich entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-form-info', 'api.php?action=ITEM-DELETE') },
                    actionNegative: null,
                })
            }

        },

        // item-event
        eventEditor: {

            create: function () {

                FormHelper.inputSetValue('editor-event-input-title', '')
                FormHelper.inputSetValue('editor-event-input-description', '')
                FormHelper.inputSetValue('editor-event-input-category', '')
                FormHelper.inputSetValue('editor-event-input-location', '')
                FormHelper.selectImportOptions('editor-event-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')

                FormHelper.formSetToolState('editor-event-tools', 'tool-withrange', 'false')
                FormHelper.formSetToolState('editor-event-tools', 'tool-withtime', 'false')

                FormHelper.inputSetValue('editor-event-datetime-date-start', '')
                FormHelper.inputSetValue('editor-event-datetime-date-end', '')
                FormHelper.inputSetValue('editor-event-datetime-time-start', '')
                FormHelper.inputSetValue('editor-event-datetime-time-end', '')

                FormHelper.domVisibility('editor-event-action-delete', false)
                FormHelper.domSetInnerText('editor-event-btn-save', 'Hinzufügen')
                FormHelper.inputSetValue('editor-id-event', -1)

                EDITOR.eventEditor.validate()
                EDITOR.showEditor('event')
            },
            edit: function (id, payload, dateStart, timeStart, dateEnd, timeEnd) {

                FormHelper.inputSetValue('editor-event-input-title', payload.title)
                FormHelper.inputSetValue('editor-event-input-description', payload.description)
                FormHelper.inputSetValue('editor-event-input-category', payload.category)
                FormHelper.inputSetValue('editor-event-input-location', payload.location)
                FormHelper.selectImportOptions('editor-event-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')
                FormHelper.selectSetByValue('editor-event-select-vehicle', payload.vehicle)

                FormHelper.domVisibility('editor-event-action-delete', true)
                FormHelper.domSetInnerText('editor-event-btn-save', 'Speichern')
                FormHelper.inputSetValue('editor-id-event', id)

                dateStart = dateStart ?? ''
                dateEnd = dateEnd ?? ''
                timeStart = timeStart ?? ''
                timeEnd = timeEnd ?? ''

                FormHelper.formSetToolState('editor-event-tools', 'tool-withrange', dateEnd != '' ? 'true' : 'false')
                FormHelper.formSetToolState('editor-event-tools', 'tool-withtime', timeStart != '' ? 'true' : 'false')

                FormHelper.inputSetValue('editor-event-datetime-date-start', dateStart)
                FormHelper.inputSetValue('editor-event-datetime-date-end', dateEnd)
                FormHelper.inputSetValue('editor-event-datetime-time-start', timeStart)
                FormHelper.inputSetValue('editor-event-datetime-time-end', timeEnd)

                EDITOR.eventEditor.validate()
                EDITOR.showEditor('event')

            },
            validate: function () {

                let isValid = true

                // create payload & preview
                let title = FormHelper.inputGetValue('editor-event-input-title')
                if (StringHelper.stringIsEmptyOrWhitespace(title)) { title = 'Kein Titel'; isValid = false }
                
                let description = FormHelper.inputGetValue('editor-event-input-description')
                let category = FormHelper.inputGetValue('editor-event-input-category')
                let location = FormHelper.inputGetValue('editor-event-input-location')
                let vehicle = FormHelper.selectGetValue('editor-event-select-vehicle')

                let payload = EDITOR.createPayload(title, description, category, location, vehicle)
                FormHelper.previewItem('editor-event-preview', payload)
                FormHelper.inputSetValue('editor-event-input-payload', JSON.stringify(payload))

                // check datetime
                let showToolWithRange = FormHelper.formGetToolState('editor-event-tools', 'tool-withrange', 'false') == 'true'
                let showToolNoRange = !showToolWithRange
                let showToolWithTime = FormHelper.formGetToolState('editor-event-tools', 'tool-withtime', 'false') == 'true'
                let showToolNoTime = !showToolWithTime

                FormHelper.domVisibility('editor-event-datetime-tools-withrange', showToolNoRange, 'inline-block')
                FormHelper.domVisibility('editor-event-datetime-tools-norange', showToolWithRange, 'inline-block')
                FormHelper.domVisibility('editor-event-datetime-tools-withtime', showToolNoTime, 'inline-block')
                FormHelper.domVisibility('editor-event-datetime-tools-notime', showToolWithTime, 'inline-block')

                FormHelper.domVisibility('editor-event-datetime-header-end', showToolWithRange)
                FormHelper.domVisibility('editor-event-datetime-date-end', showToolWithRange)
                if (showToolWithRange)
                {
                    if (showToolWithTime)
                    {
                        isValid = FormHelper.datetimeIsValid('editor-event-datetime-date-start', 'editor-event-datetime-time-start') ? isValid : false
                        isValid = FormHelper.datetimeIsInFuture('editor-event-datetime-date-end', 'editor-event-datetime-time-end') ? isValid : false
                        isValid = FormHelper.datetimeEndAfterStart('editor-event-datetime-date-start', 'editor-event-datetime-time-start', 'editor-event-datetime-date-end', 'editor-event-datetime-time-end') ? isValid : false
                    }
                    else
                    {
                        isValid = FormHelper.dateIsValid('editor-event-datetime-date-start') ? isValid : false
                        isValid = FormHelper.dateIsInFuture('editor-event-datetime-date-end') ? isValid : false
                        isValid = FormHelper.dateEndAfterStart('editor-event-datetime-date-start', 'editor-event-datetime-date-end') ? isValid : false
                    }
                }
                else
                {
                    if (showToolWithTime)
                    {
                        isValid = FormHelper.datetimeIsInFuture('editor-event-datetime-date-start', 'editor-event-datetime-time-start') ? isValid : false
                    }
                    else
                    {
                        isValid = FormHelper.dateIsInFuture('editor-event-datetime-date-start') ? isValid : false
                    }
                }

                FormHelper.domVisibility('editor-event-datetime-time-start', showToolWithTime)
                FormHelper.domVisibility('editor-event-datetime-time-end', showToolWithRange && showToolWithTime)
                FormHelper.domSetInnerText('editor-event-datetime-header-start', showToolWithTime ? 'Startdatum / -zeit' : 'Startdatum')
                FormHelper.domSetInnerText('editor-event-datetime-header-end', showToolWithTime ? 'Enddatum / -zeit' : 'Enddatum')
                
                // set button
                EDITOR.calculateEditorPosition()
                FormHelper.domEnabled('editor-event-btn-save', isValid)

            },

            changeStateWithRange: function (withRange) {
                FormHelper.formSetToolState('editor-event-tools', 'tool-withrange', withRange ? 'true' : 'false')
            },
            changeStateWithTime: function (withTime) {
                FormHelper.formSetToolState('editor-event-tools', 'tool-withtime', withTime ? 'true' : 'false')
            },

            delete: function () {
                EDITOR.showMessage({
                    title: 'Termin löschen',
                    description: 'Du kannst das Löschen nicht rückgängig machen. Soll der Eintrag wirklich entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-form-event', 'api.php?action=ITEM-DELETE') },
                    actionNegative: null,
                })
            }

        },

        // item-task
        taskEditor: {

            create: function () {

                FormHelper.inputSetValue('editor-task-input-title', '')
                FormHelper.inputSetValue('editor-task-input-description', '')
                FormHelper.inputSetValue('editor-task-input-category', '')
                FormHelper.inputSetValue('editor-task-input-location', '')
                FormHelper.selectImportOptions('editor-task-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')

                FormHelper.formSetToolState('editor-task-tools', 'tool-withrange', 'false')

                FormHelper.inputSetValue('editor-task-datetime-date-start', '')
                FormHelper.inputSetValue('editor-task-datetime-date-end', '')
                FormHelper.inputSetValue('editor-task-datetime-time-start', '')
                FormHelper.inputSetValue('editor-task-datetime-time-end', '')
                FormHelper.checkboxState('editor-task-datetime-beforeEvent', true)

                FormHelper.domVisibility('editor-task-action-delete', false)
                FormHelper.domSetInnerText('editor-task-btn-save', 'Hinzufügen')
                FormHelper.inputSetValue('editor-id-task', -1)

                EDITOR.taskEditor.validate()
                EDITOR.showEditor('task')
            },
            edit: function (id, payload, dateStart, timeStart, dateEnd, timeEnd, showUpcoming) {

                FormHelper.inputSetValue('editor-task-input-title', payload.title)
                FormHelper.inputSetValue('editor-task-input-description', payload.description)
                FormHelper.inputSetValue('editor-task-input-category', payload.category)
                FormHelper.inputSetValue('editor-task-input-location', payload.location)
                FormHelper.selectImportOptions('editor-task-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')
                FormHelper.selectSetByValue('editor-task-select-vehicle', payload.vehicle)

                FormHelper.domVisibility('editor-task-action-delete', true)
                FormHelper.domSetInnerText('editor-task-btn-save', 'Speichern')
                FormHelper.inputSetValue('editor-id-task', id)

                dateStart = dateStart ?? ''
                dateEnd = dateEnd ?? ''
                timeStart = timeStart ?? ''
                timeEnd = timeEnd ?? ''

                EDITOR.taskEditor.changeStateWithRange(dateStart != '')
                
                FormHelper.inputSetValue('editor-task-datetime-date-start', dateStart)
                FormHelper.inputSetValue('editor-task-datetime-date-end', dateEnd)
                FormHelper.inputSetValue('editor-task-datetime-time-start', timeStart)
                FormHelper.inputSetValue('editor-task-datetime-time-end', timeEnd)
                FormHelper.checkboxState('editor-task-datetime-beforeEvent', showUpcoming)

                EDITOR.taskEditor.validate()
                EDITOR.showEditor('task')

            },
            validate: function () {

                let isValid = true

                // create payload & preview
                let title = FormHelper.inputGetValue('editor-task-input-title')
                if (StringHelper.stringIsEmptyOrWhitespace(title)) { title = 'Kein Titel'; isValid = false }
                
                let description = FormHelper.inputGetValue('editor-task-input-description')
                let category = FormHelper.inputGetValue('editor-task-input-category')
                let location = FormHelper.inputGetValue('editor-task-input-location')
                let vehicle = FormHelper.selectGetValue('editor-task-select-vehicle')

                let payload = EDITOR.createPayload(title, description, category, location, vehicle)
                FormHelper.previewItem('editor-task-preview', payload)
                FormHelper.inputSetValue('editor-task-input-payload', JSON.stringify(payload))

                // check datetime
                let showToolWithRange = FormHelper.formGetToolState('editor-task-tools', 'tool-withrange', 'false') == 'true'
                let showToolNoRange = !showToolWithRange

                FormHelper.domVisibility('editor-task-datetime-tools-startend', showToolNoRange, 'inline-block')
                FormHelper.domVisibility('editor-task-datetime-tools-onlyend', showToolWithRange, 'inline-block')

                FormHelper.domVisibility('editor-task-datetime-header-start', showToolWithRange)
                FormHelper.domVisibility('editor-task-datetime-date-start', showToolWithRange)
                FormHelper.domVisibility('editor-task-datetime-time-start', showToolWithRange)
                FormHelper.domVisibility('editor-task-datetime-beforeEvent-bound', showToolWithRange)
                if (showToolWithRange)
                {
                    isValid = FormHelper.datetimeIsValid('editor-task-datetime-date-start', 'editor-task-datetime-time-start') ? isValid : false
                    isValid = FormHelper.datetimeIsInFuture('editor-task-datetime-date-end', 'editor-task-datetime-time-end') ? isValid : false
                    isValid = FormHelper.datetimeEndAfterStart('editor-task-datetime-date-start', 'editor-task-datetime-time-start', 'editor-task-datetime-date-end', 'editor-task-datetime-time-end') ? isValid : false
                }
                else
                {
                    isValid = FormHelper.datetimeIsInFuture('editor-task-datetime-date-end', 'editor-task-datetime-time-end') ? isValid : false
                }
                
                // set button
                EDITOR.calculateEditorPosition()
                FormHelper.domEnabled('editor-task-btn-save', isValid)

            },

            changeStateWithRange: function (withRange) {
                FormHelper.formSetToolState('editor-task-tools', 'tool-withrange', withRange ? 'true' : 'false')
            },

            delete: function () {
                EDITOR.showMessage({
                    title: 'Einzelne Aufgabe löschen',
                    description: 'Du kannst das Löschen nicht rückgängig machen. Soll der Eintrag wirklich entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-form-task', 'api.php?action=ITEM-DELETE') },
                    actionNegative: null,
                })
            }

        },

        // item-recurring
        recurringEditor: {

            create: function () {

                FormHelper.inputSetValue('editor-recurring-input-title', '')
                FormHelper.inputSetValue('editor-recurring-input-description', '')
                FormHelper.inputSetValue('editor-recurring-input-category', '')
                FormHelper.inputSetValue('editor-recurring-input-location', '')
                FormHelper.selectImportOptions('editor-recurring-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')

                EDITOR.recurringEditor.changeState('daily')

                FormHelper.domVisibility('editor-recurring-action-delete', false)
                FormHelper.domSetInnerText('editor-recurring-btn-save', 'Hinzufügen')
                FormHelper.inputSetValue('editor-id-recurring', -1)

                EDITOR.recurringEditor.validate()
                EDITOR.showEditor('recurring')

            },
            edit: function (id, payload, timeStart, timeEnd, cycleMode, weekday, dom) {

                FormHelper.inputSetValue('editor-recurring-input-title', payload.title)
                FormHelper.inputSetValue('editor-recurring-input-description', payload.description)
                FormHelper.inputSetValue('editor-recurring-input-category', payload.category)
                FormHelper.inputSetValue('editor-recurring-input-location', payload.location)
                FormHelper.selectImportOptions('editor-recurring-select-vehicle', adm_vehicleTiming?.vehicles, 'Besatzung auswählen (Optional)')
                FormHelper.selectSetByValue('editor-recurring-select-vehicle', payload.vehicle)

                switch (cycleMode)
                {
                    case 0:
                        EDITOR.recurringEditor.changeState('daily')
                        break;
                    case 1:
                        EDITOR.recurringEditor.changeState('weekly')
                        FormHelper.selectSetByValue('editor-recurring-cyclemode-weekly-select', weekday ?? 1)
                        break;
                    case 2:
                        EDITOR.recurringEditor.changeState('monthly')
                        FormHelper.selectSetByValue('editor-recurring-cyclemode-monthly-select', dom ?? 1)
                        break;
                    case 3:
                        EDITOR.recurringEditor.changeState('monthly')
                        FormHelper.selectSetByValue('editor-recurring-cyclemode-monthly-select', -1)
                        break;
                    case 4:
                        EDITOR.recurringEditor.changeState('lastday')
                        FormHelper.selectSetByValue('editor-recurring-cyclemode-weekly-select', weekday ?? 1)
                        break;
                }

                FormHelper.domVisibility('editor-recurring-action-delete', true)
                FormHelper.domSetInnerText('editor-recurring-btn-save', 'Speichern')
                FormHelper.inputSetValue('editor-id-recurring', id)

                timeStart = timeStart ?? ''
                timeEnd = timeEnd ?? ''
                FormHelper.inputSetValue('editor-recurring-datetime-time-start', timeStart)
                FormHelper.inputSetValue('editor-recurring-datetime-time-end', timeEnd)

                EDITOR.recurringEditor.validate()
                EDITOR.showEditor('recurring')

            },
            validate: function () {

                let isValid = true

                // create payload & preview
                let title = FormHelper.inputGetValue('editor-recurring-input-title')
                if (StringHelper.stringIsEmptyOrWhitespace(title)) { title = 'Kein Titel'; isValid = false }
                
                let description = FormHelper.inputGetValue('editor-recurring-input-description')
                let category = FormHelper.inputGetValue('editor-recurring-input-category')
                let location = FormHelper.inputGetValue('editor-recurring-input-location')
                let vehicle = FormHelper.selectGetValue('editor-recurring-select-vehicle')

                let payload = EDITOR.createPayload(title, description, category, location, vehicle)
                FormHelper.previewItem('editor-recurring-preview', payload)
                FormHelper.inputSetValue('editor-recurring-input-payload', JSON.stringify(payload))

                // update mode
                let showToolModeDaily = FormHelper.formGetToolState('editor-recurring-tool-cyclemode', 'tool-mode', 'daily') == 'daily'
                let showToolModeWeekly = FormHelper.formGetToolState('editor-recurring-tool-cyclemode', 'tool-mode', 'daily') == 'weekly'
                let showToolModeMonthly = FormHelper.formGetToolState('editor-recurring-tool-cyclemode', 'tool-mode', 'daily') == 'monthly'
                let showToolModeLastday = FormHelper.formGetToolState('editor-recurring-tool-cyclemode', 'tool-mode', 'daily') == 'lastday'
                
                FormHelper.domVisibility('editor-recurring-tool-cyclemode-daily', !showToolModeDaily, 'inline-block')
                FormHelper.domVisibility('editor-recurring-tool-cyclemode-weekly', !showToolModeWeekly, 'inline-block')
                FormHelper.domVisibility('editor-recurring-tool-cyclemode-monthly', !showToolModeMonthly, 'inline-block')
                FormHelper.domVisibility('editor-recurring-tool-cyclemode-lastday', !showToolModeLastday, 'inline-block')
                
                FormHelper.domVisibility('editor-recurring-cyclemode-weekly-select', showToolModeWeekly || showToolModeLastday)
                FormHelper.domVisibility('editor-recurring-cyclemode-monthly-select', showToolModeMonthly)

                let cycleText = 'Täglich. Jeden einzelnen Tag.'
                if (showToolModeDaily) 
                {
                    FormHelper.inputSetValue('editor-recurring-input-cyclemode', '0')
                }
                if (showToolModeWeekly) 
                {
                    cycleText = 'Jede Woche am ' + FormHelper.selectGetLabel('editor-recurring-cyclemode-weekly-select', 'Montag') + '.'
                    FormHelper.inputSetValue('editor-recurring-input-cyclemode', '1')
                }
                if (showToolModeMonthly)
                {
                    cycleText = 'Jeden Monat am ' + FormHelper.selectGetLabel('editor-recurring-cyclemode-monthly-select', '1.')
                    let dom = FormHelper.selectGetValue('editor-recurring-cyclemode-monthly-select')
                    FormHelper.inputSetValue('editor-recurring-input-cyclemode', dom == '-1' ? '3' : '2') /* dom==-1 > last day of month */
                }
                if (showToolModeLastday)
                {
                    cycleText = 'Jeden Letzten ' + FormHelper.selectGetLabel('editor-recurring-cyclemode-weekly-select', 'Montag') + ' im Monat.'
                    FormHelper.inputSetValue('editor-recurring-input-cyclemode', '4')
                }
                FormHelper.domSetInnerText('editor-recurring-cyclemode-header', cycleText)

                // validitation
                isValid = FormHelper.timeIsValid('editor-recurring-datetime-time-start') ? isValid : false
                isValid = FormHelper.timeIsValid('editor-recurring-datetime-time-end') ? isValid : false
                if (!StringHelper.stringIsEmptyOrWhitespace(FormHelper.inputGetValue('editor-recurring-input-title')) && !isValid) { this.changeTiming(); this.validate(); return }
                isValid = FormHelper.timeEndAfterStart('editor-recurring-datetime-time-start', 'editor-recurring-datetime-time-end') ? isValid : false
                

                // set button
                EDITOR.calculateEditorPosition()
                FormHelper.domEnabled('editor-recurring-btn-save', isValid)

            },

            changeState: function (state) {
                FormHelper.formSetToolState('editor-recurring-tool-cyclemode', 'tool-mode', state)
            },
            changeTiming: function () {

                let selected = FormHelper.selectGetValue('editor-recurring-select-vehicle')

                let timeStart = adm_vehicleTiming?.defaultStart ?? ''
                let timeEnd = adm_vehicleTiming?.defaultEnd ?? ''
                
                if (selected != '' && adm_vehicleTiming?.vehicles && adm_vehicleTiming.vehicles.some(e=>e.name == selected))
                {
                    let timing = adm_vehicleTiming.vehicles.find(e=>e.name == selected)
                    timeStart = timing?.starttime ?? timeStart
                    timeEnd = timing?.endtime ?? timeEnd
                }

                let showToolModeDaily = FormHelper.formGetToolState('editor-recurring-tool-cyclemode', 'tool-mode', 'daily') == 'daily'
                if (showToolModeDaily) 
                { 
                    // modify endtime to 3h after timeStart
                    let fracH = timeStart.substring(0,2)
                    let fracM = timeStart.substring(3,5)
                    if (/^\d+$/.test(fracH))
                    {
                        let hours = parseInt(fracH,10) + 3
                        hours = hours >= 24 ? hours - 24 : hours
                        timeEnd = hours.toString().padStart(2, '0') + ':' + fracM
                    }
                }

                FormHelper.inputSetValue('editor-recurring-datetime-time-start', timeStart)
                FormHelper.inputSetValue('editor-recurring-datetime-time-end', timeEnd)

            },

            delete: function () {
                EDITOR.showMessage({
                    title: 'Tagesaufgabe löschen',
                    description: 'Du kannst das Löschen nicht rückgängig machen. Soll der Eintrag wirklich entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-form-recurring', 'api.php?action=ITEM-DELETE') },
                    actionNegative: null,
                })
            }

        },
        hidetaskEditor: {

            create: function () {

                FormHelper.inputSetValue('editor-hidetask-datetime-date', '')

                FormHelper.inputSetValue('editor-hidetask-input-hiddenid', -1)

                FormHelper.domSetInnerHtml('editor-hidetask-searchresult', '')

                FormHelper.inputSetValue('editor-hidetask-btn-save', 'Ausblenden')
                FormHelper.domVisibility('editor-hidetask-action-delete', false)

                EDITOR.hidetaskEditor.validate()
                EDITOR.showEditor('hidetask')

            },
            validate: function () {

                let isValid = true

                let datevalid = FormHelper.dateIsValid('editor-hidetask-datetime-date')
                FormHelper.domEnabled('editor-hidetask-btn-searchtask', datevalid)
                
                let selectedId = parseInt(FormHelper.inputGetValue('editor-hidetask-input-hiddenid'),10)
                let alreadyHidden = FormHelper.formGetToolState('editor-hidetask-tool-mode', 'value') == 'showSelected'

                FormHelper.inputSetValue('editor-hidetask-btn-save', alreadyHidden ? 'Wieder Einblenden' : 'Ausblenden')

                FormHelper.domEnabled('editor-hidetask-btn-save', selectedId >= 0)

            },

            invokeSearch: async function () {

                EDITOR.disableUI(true)

                try
                {
                    let date = FormHelper.inputGetValue('editor-hidetask-datetime-date', '')
                    const response = await fetch('api.php?action=SEARCH-UI&type=ADMIN_RECURRING_SEARCH&date=' + date, { cache: 'no-store' });
                    if (!response.ok) { throw new Error('error while fetching UI (' + date + ')') }
    
                    FormHelper.domSetInnerHtml('editor-hidetask-searchresult', await response.text())
                    FormHelper.inputSetValue('editor-hidetask-input-hiddenid', -1)

                    this.validate()
                }
                catch (error) { console.error(error) }
                EDITOR.disableUI(false)

            },
            invokeSelect: function (sender, id, hidden) {

                // update select-state
                document.querySelectorAll('#editor-hidetask-searchresult button.select').forEach(e=>e.classList.remove('select-active'))
                sender.classList.add('select-active')

                FormHelper.inputSetValue('editor-hidetask-input-hiddenid', id)
                this.changeState(hidden)

                // cancel submit & validate
                this.validate()

            },

            changeState: function (isHiddenSelected) {
                FormHelper.formSetToolState('editor-hidetask-tool-mode', 'value', isHiddenSelected ? 'showSelected' : 'hideSelected')
            },

            delete: function () {
                EDITOR.showMessage({
                    title: 'Tagesaufgabe löschen',
                    description: 'Du kannst das Löschen nicht rückgängig machen. Soll der Eintrag wirklich entfernt werden?',
                    showWarning: false,
                    mode: 'yes-no',
                    disableOnAction: true,
                    actionPositive: () => { FormHelper.formInvokeSubmit('editor-form-recurring', 'api.php?action=ITEM-DELETE') },
                    actionNegative: null,
                })
            }

        },

        // module-abfalleditors
        moduleAbfallEditor: {

            create: function (link, lastUpdate) {
                FormHelper.domSetInnerText('editor-moduleAbfall-meta', 'Bearbeitet: ' + lastUpdate)
                FormHelper.inputSetValue('editor-moduleAbfall-input-abfalllink', link)

                EDITOR.moduleAbfallEditor.validate()
                EDITOR.showEditor('moduleAbfall')
            },
            validate: function () {
                let isValid = true

                isValid = FormHelper.inputIsEmpty('editor-moduleAbfall-input-abfalllink') ? false : isValid
                isValid = (/^https:\/\/www\.zaoe\.de\/kalender\/ical\/([0-9\/\-_]+)$/).test(FormHelper.inputGetValue('editor-moduleAbfall-input-abfalllink')) ? isValid : false

                FormHelper.domEnabled('editor-moduleAbfall-btn-save', isValid)
                EDITOR.calculateEditorPosition()
            },

            invokeRefresh: function () {

            }

        },

        // module-malteser
        moduleMalteserEditor: {

            create: function (endpoint, username) {

                FormHelper.formSetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '')
                FormHelper.inputSetValue('editor-moduleMaltesercloud-input-endpoint', endpoint)
                FormHelper.inputSetValue('editor-moduleMaltesercloud-input-user', username)
                FormHelper.inputSetValue('editor-moduleMaltesercloud-input-pass', '')

                EDITOR.moduleMalteserEditor.validate()
                EDITOR.showEditor('moduleMaltesercloud')

            },
            validate: function () {

                let isValid = true

                // tool state
                let showToolEndpoint = FormHelper.formGetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '') == 'endpoint'
                let showToolCredentials = FormHelper.formGetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', '') == 'credentials'
                let showToolBack = showToolEndpoint || showToolCredentials
                let showToolMenu = !showToolBack

                // visibility
                FormHelper.domVisibility('editor-moduleMaltesercloud-action-endpoint', showToolMenu)
                FormHelper.domVisibility('editor-moduleMaltesercloud-action-credentials', showToolMenu);
                FormHelper.domVisibility('editor-moduleMaltesercloud-action-cancelcurrent', showToolBack);

                FormHelper.domVisibility('editor-moduleMaltesercloud-actioncontainer-endpoint', showToolEndpoint);
                if (showToolEndpoint) {
                    FormHelper.formSetAction('editor-moduleMaltesercloud-form', 'api.php?action=SETTINGS-MODULE&m=MALTESER&a=ENDPOINT')
                    isValid = FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-endpoint') ? false : isValid
                }

                FormHelper.domVisibility('editor-moduleMaltesercloud-actioncontainer-credentials', showToolCredentials);
                if (showToolCredentials) {
                    FormHelper.formSetAction('editor-moduleMaltesercloud-form', 'api.php?action=SETTINGS-MODULE&m=MALTESER&a=CREDENTIALS')

                    isValid = FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-user') ? false : isValid
                    isValid = FormHelper.inputIsEmpty('editor-moduleMaltesercloud-input-pass') ? false : isValid

                    if ((/^([a-z])+(\.)([a-z])+(([a-z])+([-])?)*([a-z])+([0-9])?@malteser\.org$/i).test(FormHelper.inputGetValue('editor-moduleMaltesercloud-input-user', '#'))) {
                        FormHelper.domVisibility('editor-moduleMaltesercloud-input-cred-error', false)
                    }
                    else {
                        isValid = false
                        FormHelper.domVisibility('editor-moduleMaltesercloud-input-cred-error', true)
                    }
                }

                // setup editor
                FormHelper.domVisibility('editor-moduleMaltesercloud-btn-save', showToolBack);
                FormHelper.domEnabled('editor-moduleMaltesercloud-btn-save', isValid);

                EDITOR.calculateEditorPosition();

            },

            changeState: function (value) {
                FormHelper.formSetToolState('editor-moduleMaltesercloud-actiontool', 'tool-action', value)
            },

        },

        // module-nina
        moduleNinaEditor: {

            create: function (ars) {
                FormHelper.inputSetValue('editor-moduleNina-input-ars', ars)

                EDITOR.moduleNinaEditor.validate()
                EDITOR.showEditor('moduleNina')
            },
            validate: function () {
                let isValid = true

                isValid = FormHelper.inputIsEmpty('editor-moduleNina-input-ars') ? false : isValid
                isValid = (/^\d{12}$/).test(FormHelper.inputGetValue('editor-moduleNina-input-ars')) ? isValid : false
                
                FormHelper.domEnabled('editor-moduleNina-btn-save', isValid)
                EDITOR.calculateEditorPosition()
            },

            invokeRefresh: function () {

            }

        },

    };

    // Public interface
    return {
        UI: UI,
        EDITOR: EDITOR
    };

})();

window.WIM = WIM;





//     // TEMPLATE: KFZ
//     editorTemplateKfzCreate: function () {

//         editors.setSelectValue("editor-templatekfz-select-vehicle", 0);
//         editors.setSelectValue("editor-templatekfz-select-reason", 0);

//         FormHelper.inputSetValue("editor-templatekfz-datetime-date", "");

//         editors.editorTemplateKfzValidation();
//         editors.showEditor("templatekfz");

//     },
//     editorTemplateKfzValidation: function () {

//         var isValid = false;
//         if (editors.hasValueEditor("editor-templatekfz-datetime-date")) {
//             if (dateutil.checkDateActual(editors.getValueEditor("editor-templatekfz-datetime-date"))) { isValid = true; }
//         }

//         var subtitle = "Das Fahrzeug auf den Reserve-RTW tauschen und vor die Waschhalle stellen.";

//         var isReserve = editors.getSelectValueEditor("editor-templatekfz-select-vehicle") == "RTW 3";

//         if (isReserve) { subtitle = null; }
//         FormHelper.inputSetValue("editor-templatekfz-typetag", isReserve ? "EVENT" : "UNIQUETASK");

//         FormHelper.inputSetValue("editor-templatekfz-hidden-subtitle", subtitle);

//         if (isValid) {

//             if (isReserve) {

//                 FormHelper.inputSetValue("editor-templatekfz-hidden-title", editors.getSelectValueEditor("editor-templatekfz-select-vehicle") + ': ' + editors.getSelectValueEditor("editor-templatekfz-select-reason"));

//                 FormHelper.inputSetValue("editor-templatekfz-hidden-date-start", editors.getValueEditor("editor-templatekfz-datetime-date"));
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-time-start", "");
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-date-end", "");
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-time-end", "");

//             } else {

//                 FormHelper.inputSetValue("editor-templatekfz-hidden-title", editors.getSelectValueEditor("editor-templatekfz-select-reason"));

//                 var eDate = new Date(editors.getValueEditor("editor-templatekfz-datetime-date") + "T00:00:00");

//                 var tStart = new Date(eDate.getTime());
//                 tStart.setTime(tStart.getTime() - (6 * 60 * 60 * 1000));

//                 var tEnd = new Date(eDate.getTime());
//                 tEnd.setTime(tEnd.getTime() + (6 * 60 * 60 * 1000));

//                 FormHelper.inputSetValue("editor-templatekfz-hidden-date-start", dateutil.convertToInputDate(tStart));
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-time-start", dateutil.convertToInputTime(tStart));
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-date-end", dateutil.convertToInputDate(tEnd));
//                 FormHelper.inputSetValue("editor-templatekfz-hidden-time-end", dateutil.convertToInputTime(tEnd));

//             }
//         }

//         editors.setBtnEnabledEditor("editor-templatekfz-btn-save", isValid);

//     },

//     // TEMPLATE: EVWT
//     editorTemplateEvWTCreate: function () {

//         FormHelper.inputSetValue("editor-templateevwt-input-event", "");
//         FormHelper.inputSetValue("editor-templateevwt-datetime-date", "")
//         FormHelper.inputSetValue("editor-templateevwt-datetime-time", "")

//         FormHelper.inputSetValue("editor-templateevwt-input-task", "")
//         FormHelper.inputSetValue("editor-templateevwt-input-projection", "12");

//         editors.editorTemplateEvWTValidation();
//         editors.showEditor("templateevwt");

//     },
//     editorTemplateEvWTValidation: function () {

//         var isValid = true;

//         // Input
//         if (!editors.hasValueEditor("editor-templateevwt-input-event")) { isValid = false; }
//         if (!editors.hasValueEditor("editor-templateevwt-input-task")) { isValid = false; }
//         if (!(editors.hasValueEditor("editor-templateevwt-input-projection") &&
//             varExt.isInt(editors.getValueEditor("editor-templateevwt-input-projection")) &&
//             editors.getValueEditor("editor-templateevwt-input-projection") > 0)) { isValid = false; }

//         // Event-DateTime
//         if (!dateutil.checkDateTimeActual(
//             editors.getValueEditor("editor-templateevwt-datetime-date"),
//             editors.getValueEditor("editor-templateevwt-datetime-time"))) { isValid = false; }

//         editors.setBtnEnabledEditor("editor-templateevwt-btn-save", isValid);

//     },
//     editorTemplateEvWTInvokeSubmit: function () {

//         FormHelper.domVisibility("messageDisableOverlay", true);

//         // Ereignisse vorbereiten
//         var eventTitle = editors.getValueEditor("editor-templateevwt-input-event");
//         var eventSubtitle = editors.getValueEditor("editor-templateevwt-input-eventsub");
//         var eventDateStart = editors.getValueEditor("editor-templateevwt-datetime-date");
//         var eventTimeStart = editors.getValueEditor("editor-templateevwt-datetime-time");

//         var taskTitle = "Wegen: " + eventTitle;
//         var taskSubtitle = editors.getValueEditor("editor-templateevwt-input-task");
//         var eDate = new Date(eventDateStart + "T" + eventTimeStart + ":00");
//         eDate.setHours(eDate.getHours() - editors.getValueEditor("editor-templateevwt-input-projection"));
//         var taskDateStart = dateutil.convertToInputDate(eDate);
//         var taskTimeStart = dateutil.convertToInputTime(eDate);
//         var taskDateEnd = eventDateStart;
//         var taskTimeEnd = eventTimeStart;

//         // Event erstellen
//         const dataEvent = new URLSearchParams();
//         dataEvent.append('id', -1);
//         dataEvent.append('typetag', 'EVENT');
//         dataEvent.append('title', eventTitle);
//         dataEvent.append('subtitle', eventSubtitle);
//         dataEvent.append('dateStart', eventDateStart);
//         dataEvent.append('timeStart', eventTimeStart);

//         // Task erstellen
//         const dataTask = new URLSearchParams();
//         dataTask.append('id', -1);
//         dataTask.append('typetag', 'UNIQUETASK');
//         dataTask.append('title', taskTitle);
//         dataTask.append('subtitle', taskSubtitle);
//         dataTask.append('vehicle', '');
//         dataTask.append('dateStart', taskDateStart);
//         dataTask.append('timeStart', taskTimeStart);
//         dataTask.append('dateEnd', taskDateEnd);
//         dataTask.append('timeEnd', taskTimeEnd);

//         fetch("api.php?action=ITEM-EDIT",
//             {
//                 method: 'POST',
//                 redirect: 'manual',
//                 body: dataEvent
//             })
//             .then(response => response.text())
//             .then(html => { })
//             .finally(function () {

//                 // UniqueTask erstellen
//                 fetch("api.php?action=ITEM-EDIT",
//                     {
//                         method: 'POST',
//                         redirect: 'manual',
//                         body: dataTask
//                     })
//                     .then(response => response.text())
//                     .then(html => { })
//                     .finally(function () {
//                         editors.submitForm("editor-form-templateevwt");
//                     });

//             });

//     },

//     // TEMPLATE: Busy
//     editorTemplateBusyCreate: function () {

//         FormHelper.inputSetValue("editor-templatebusy-datetime-date", "");

//         editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');
//         FormHelper.inputSetValue('editor-id-templatebusy', -1);

//         document.getElementById("editor-templatebusy-searchresult").innerHTML = "";

//         editors.setInnerHtmlEditor("editor-templatebusy-btn-save", "Hinzufügen");
//         FormHelper.domVisibility("editor-templatebusy-action-delete", false);

//         editors.editorTemplateBusyValidation();
//         editors.showEditor("templatebusy");

//     },
//     editorTemplateBusyEdit: function (id, replaceId, title, subtitle, replaceDate, timeStart, timeEnd) {

//         // Dienst laden & anzeigen
//         FormHelper.domVisibility("messageDisableOverlay", true);
//         editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');

//         FormHelper.inputSetValue('editor-id-templatebusy', id);
//         FormHelper.inputSetValue("editor-templatebusy-input-replacedid", replaceId);

//         FormHelper.inputSetValue("editor-templatebusy-input-title", title);
//         FormHelper.inputSetValue("editor-templatebusy-input-subtitle", subtitle);

//         FormHelper.inputSetValue("editor-templatebusy-datetime-date-start", replaceDate);
//         FormHelper.inputSetValue("editor-templatebusy-datetime-time-start", timeStart);
//         FormHelper.inputSetValue("editor-templatebusy-datetime-time-end", timeEnd);

//         editors.setInnerHtmlEditor("editor-templatebusy-btn-save", "Speichern");
//         FormHelper.domVisibility("editor-templatebusy-action-delete", true);

//         const data = new URLSearchParams();
//         data.append('id', replaceId);

//         fetch("api.php?action=ADMIN-GET-UI-SINGLEID",
//             {
//                 method: 'POST',
//                 redirect: 'manual',
//                 body: data
//             })
//             .then(response => response.text())
//             .then(html => {
//                 document.getElementById("editor-templatebusy-searchresult").innerHTML = html;
//             })
//             .finally(function () {

//                 FormHelper.domVisibility("messageDisableOverlay", false);
//                 editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'edit');

//                 editors.editorTemplateBusyValidation();
//                 editors.showEditor("templatebusy");

//             });



//     },
//     editorTemplateBusyValidation: function () {

//         var isValid = true;

//         var toolMode = document.getElementById("editor-templatebusy-tool-mode").getAttribute("mode");

//         // SearchTask
//         searchValid = true;
//         if (!dateutil.checkDateActual(
//             editors.getValueEditor("editor-templatebusy-datetime-date")
//         )) { searchValid = false; }
//         editors.setBtnEnabledEditor("editor-templatebusy-btn-searchtask", searchValid);

//         // Tool-Mode
//         FormHelper.domVisibility("editor-templatebusy-input-title", toolMode != "none");
//         FormHelper.domVisibility("editor-templatebusy-input-subtitle", toolMode != "none");

//         FormHelper.domVisibility("editor-templatebusy-datetime-header-start", toolMode != "none");
//         FormHelper.domVisibility("editor-templatebusy-datetime-date-start", toolMode != "none");
//         FormHelper.domVisibility("editor-templatebusy-datetime-time-start", toolMode != "none");
//         FormHelper.domVisibility("editor-templatebusy-datetime-time-end", toolMode != "none");

//         FormHelper.domVisibility("editor-templatebusy-hr-resultdiv", toolMode != "none");
//         FormHelper.domVisibility("editor-templatebusy-hr-replace-header", toolMode != "none");

//         FormHelper.domVisibility("editor-templatebusy-hr-search-header", toolMode != "edit");
//         FormHelper.domVisibility("editor-templatebusy-datetime-date", toolMode != "edit", "inline-block");
//         FormHelper.domVisibility("editor-templatebusy-btn-searchtask", toolMode != "edit", "inline-block");

//         switch (toolMode) {
//             case 'none':
//                 isValid = false;

//                 break;

//             case 'edit':
//             case 'selected':

//                 if (!editors.hasValueEditor("editor-templatebusy-input-title")) { isValid = false; }

//                 if (!dateutil.checkDateTime(
//                     editors.getValueEditor("editor-templatebusy-datetime-date-start"),
//                     editors.getValueEditor("editor-templatebusy-datetime-time-start"),
//                     editors.getValueEditor("editor-templatebusy-datetime-date-start"),
//                     editors.getValueEditor("editor-templatebusy-datetime-time-end"))) { isValid = false; }

//                 if (!dateutil.checkDateTimeActual(
//                     editors.getValueEditor("editor-templatebusy-datetime-date-start"),
//                     editors.getValueEditor("editor-templatebusy-datetime-time-end"))) { isValid = false; }

//                 break;

//         }

//         editors.calculateEditorPosition();

//         editors.setBtnEnabledEditor("editor-templatebusy-btn-save", isValid);

//     },
//     editorTemplateBusyInvokeSearch: function () {

//         FormHelper.domVisibility("messageDisableOverlay", true);
//         editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'none');

//         fetch("api.php?action=ADMIN-SEARCH-CYCLEDTASK&date=" + editors.getValueEditor("editor-templatebusy-datetime-date"), { cache: "no-store" })
//             .then(response => response.text())
//             .then(html => {

//                 document.getElementById("editor-templatebusy-searchresult").innerHTML = html;

//             })
//             .finally(function () {

//                 FormHelper.domVisibility("messageDisableOverlay", false);
//                 editors.editorTemplateBusyValidation();

//             });

//     },
//     editorTemplateBusyInvokeSelect: function (sender, id, subtitle, title, timeStart, timeEnd) {

//         [...document.getElementById("editor-templatebusy-searchresult").getElementsByTagName("button")].forEach(item => { item.classList.remove("select-active") });
//         sender.classList.add("select-active");

//         FormHelper.inputSetValue("editor-templatebusy-input-replacedid", id);

//         FormHelper.inputSetValue("editor-templatebusy-input-title", title);
//         FormHelper.inputSetValue("editor-templatebusy-input-subtitle", subtitle);

//         FormHelper.inputSetValue("editor-templatebusy-datetime-date-start", dateutil.convertToInputDate(new Date()));
//         FormHelper.inputSetValue("editor-templatebusy-datetime-time-start", timeStart);
//         FormHelper.inputSetValue("editor-templatebusy-datetime-time-end", timeEnd);

//         editors.setEditorToolArgs('editor-templatebusy-tool-mode', 'mode', 'selected');
//         editors.editorTemplateBusyValidation();

//     },
//     editorTemplateBusyInvokeSubmit: function () {

//         FormHelper.domVisibility("messageDisableOverlay", true);

//         // Ereignisse vorbereiten
//         var replaceId = editors.getValueEditor("editor-templatebusy-input-replacedid");
//         var replaceDate = editors.getValueEditor("editor-templatebusy-datetime-date");

//         var originalId = editors.getValueEditor("editor-id-templatebusy");
//         var taskTitle = editors.getValueEditor("editor-templatebusy-input-title");
//         var taskSubtitle = editors.getValueEditor("editor-templatebusy-input-subtitle");
//         var taskDate = editors.getValueEditor("editor-templatebusy-datetime-date-start");
//         var taskTimeStart = editors.getValueEditor("editor-templatebusy-datetime-time-start");
//         var taskTimeEnd = editors.getValueEditor("editor-templatebusy-datetime-time-end");

//         // Task erstellen
//         const dataTask = new URLSearchParams();
//         dataTask.append('replace_id', replaceId);
//         dataTask.append('replace_date', replaceDate);

//         dataTask.append('orgId', originalId);
//         dataTask.append('title', taskTitle);
//         dataTask.append('subtitle', taskSubtitle);
//         dataTask.append('date', taskDate);
//         dataTask.append('timeStart', taskTimeStart);
//         dataTask.append('timeEnd', taskTimeEnd);

//         fetch("api.php?action=ITEM-REPLACE",
//             {
//                 method: 'POST',
//                 redirect: 'manual',
//                 body: dataTask
//             })
//             .then(response => response.text())
//             .then(html => { })
//             .finally(function () {

//                 editors.submitForm("editor-form-templatebusy");

//             });

//     },
//     editorTemplateBusyInvokeDelete: function () {

//         FormHelper.domVisibility("messageDisableOverlay", true);

//         // Ereignisse vorbereiten
//         var replaceId = editors.getValueEditor("editor-templatebusy-input-replacedid");
//         var originalId = editors.getValueEditor("editor-id-templatebusy");

//         // Task erstellen
//         const dataTask = new URLSearchParams();
//         dataTask.append('replace_id', replaceId);
//         dataTask.append('orgId', originalId);

//         fetch("api.php?action=ITEM-REPLACE-DELETE",
//             {
//                 method: 'POST',
//                 redirect: 'manual',
//                 body: dataTask
//             })
//             .then(response => response.text())
//             .then(html => { })
//             .finally(function () {

//                 editors.submitForm("editor-form-templatebusy");

//             });

//     },

//     // MESSAGES


// ///////////////////////////////////////////////////////////////////////////////////////////////////////////////77

// var dateutil = {

//     getWochentag: function (date) {

//         date = date || new Date();
//         if (date.getDay == null) { date = new Date(); }

//         var wochentag = date.getDay()
//         if (wochentag == 0) return "Sonntag";
//         if (wochentag == 1) return "Montag";
//         if (wochentag == 2) return "Dienstag";
//         if (wochentag == 3) return "Mittwoch";
//         if (wochentag == 4) return "Donnerstag";
//         if (wochentag == 5) return "Freitag";
//         if (wochentag == 6) return "Samstag";

//         return "";

//     },

//     getMonthName: function (date) {

//         date = date || new Date();
//         if (date.getMonth == null) { date = new Date(); }

//         var monat = date.getMonth()
//         if (monat == 0) return "Januar";
//         if (monat == 1) return "Februar";
//         if (monat == 2) return "März";
//         if (monat == 3) return "April";
//         if (monat == 4) return "Mai";
//         if (monat == 5) return "Juni";
//         if (monat == 6) return "Juli";
//         if (monat == 7) return "August";
//         if (monat == 8) return "September";
//         if (monat == 9) return "Oktober";
//         if (monat == 10) return "November";
//         if (monat == 11) return "Dezember";

//         return "";

//     },

//     checkDate: function (startDate, endDate) {
//         return dateutil.checkDateTime(startDate, "00:00", endDate, "00:00");
//     },
//     checkDateTime: function (startDate, startTime, endDate, endTime) {

//         var start = new Date(startDate + " " + startTime);
//         var ende = new Date(endDate + " " + endTime);

//         if (varExt.isDate(start) && varExt.isDate(ende)) { return ende > start; }
//         return false;

//     },
//     checkTime: function (startTime, endTime) {

//         var start = new Date("2000-01-01 " + startTime);
//         var ende = new Date("2000-01-01 " + endTime);

//         if (varExt.isDate(start) && varExt.isDate(ende)) { return ((ende > start) || (startTime == "00:00" && endTime == "00:00")); }
//         return false;

//     },


//     convertToInputTime: function (date) {
//         return String(date.getHours()).padStart(2, '0') + ":" + String(date.getMinutes()).padStart(2, '0');
//     },
//     convertToInputDate: function (date) {
//         return String(date.getFullYear()) + '-' + String(date.getMonth(date) + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
//     }

// }

// var jsext = {

//     stringIsEmptyOrWhitespace: function (str) {
//         return str === null || str.match(/^ *$/) !== null || str.length == 0;
//     },
//     stringGenerateHash: function (str) {

//         var hash = 0;
//         str = str || "";

//         if (str.length == null || str.length == 0) return hash;

//         for (i = 0; i < str.length; i++) {
//             char = str.charCodeAt(i);
//             hash = ((hash << 5) - hash) + char;
//             hash = hash & hash;
//         }

//         return hash;

//     },



// }

// var varExt = {

//     isDate: function (date) {
//         return date instanceof Date && !isNaN(date);
//     },

//     isEmail: function (mail) {
//         var pattern = /(?:[a-z0-9!#$%&'*+/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/;
//         return (typeof mail === "string") && pattern.test(mail);
//     },

//     isInt: function (inttxt) {
//         return Number.isInteger(parseInt(inttxt, 10));
//     },

//     isOnlyAlpha: function (text) {
//         var pattern = /^[a-zA-ZäöüÄÖÜ\s]+$/;
//         return text.match(pattern);
//     }

// }
