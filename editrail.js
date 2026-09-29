// Editrail extension, https://github.com/pfadfinder26/yellow-editrail
// Based on Datenstrom Yellow, https://datenstrom.se/yellow/
// The rail at the side of the window: it takes the buttons of the edit extension, remembers
// whether it is open, acts on the buttons of a page, and manages the files of the website.
(function () {
    "use strict";

    // the rail and its sections stay open or closed the way the editor left them
    function setupEditRailToggle() {
        remember(document.getElementById("editrail-toggle"), "pfadi-editrail");
        remember(document.getElementById("editrail-media-toggle"), "pfadi-editrail-media");
        remember(document.getElementById("editrail-shared-toggle"), "pfadi-editrail-shared");
    }

    // a page is loaded again after every change, a section that was open opens again
    function remember(toggle, key) {
        if (!toggle) return;
        try {
            toggle.checked = window.localStorage.getItem(key)=="open";
        } catch (e) {}
        toggle.addEventListener("change", function () {
            try {
                window.localStorage.setItem(key, toggle.checked ? "open" : "closed");
            } catch (e) {}
        });
    }

    // the edit extension builds its bar at the top of the page, it goes into the rail
    function setupEditRail() {
        var rail = document.getElementById("editrail");
        var bar = document.getElementById("yellow-bar");
        if (!rail || !bar) return;
        rail.querySelector(".editrail-actions").appendChild(bar);
        setLabel("yellow-pane-create-bar", rail.getAttribute("data-label-create"));
        setLabel("yellow-pane-delete-bar", rail.getAttribute("data-label-delete"));
        if (!window.yellow || !window.yellow.edit) return;
        keepPaneOpen();
        bindTools(rail);
        setupMedia(rail);
        markEditing();
        processAction(window.location.hash.indexOf("#pfadi-")===0 ?
            window.location.hash.substring(7) : "", true);
    }

    // the bar says "+" and "-", in the rail the buttons say what they do
    function setLabel(id, text) {
        var element = document.getElementById(id);
        if (element && text) element.textContent = text;
    }

    // clicking next to the window that edits a page should not throw the text away
    function keepPaneOpen() {
        var edit = window.yellow.edit;
        var click = edit.click;
        edit.click = function (e) {
            var modal = this.paneId=="yellow-pane-edit" || this.paneId=="yellow-pane-create" ||
                this.paneId=="yellow-pane-delete";
            if (!modal) return click.call(this, e);
            if (this.popupId && !document.getElementById(this.popupId).contains(e.target)) {
                this.hidePopup(this.popupId, true);
            }
        };
    }

    // while a page is being edited the rail offers to insert a file into it
    function markEditing() {
        var edit = window.yellow.edit;
        var show = edit.showPane, hide = edit.hidePane;
        edit.showPane = function (paneId, paneAction, paneStatus, paneModal) {
            show.call(this, paneId, paneAction, paneStatus, paneModal);
            document.body.classList.toggle("editrail-editing", this.paneId=="yellow-pane-edit");
        };
        edit.hidePane = function (paneId, fadeout) {
            hide.call(this, paneId, fadeout);
            document.body.classList.toggle("editrail-editing", this.paneId=="yellow-pane-edit");
        };
    }

    // a button for the page that is open does not lead anywhere, it acts right away
    function bindTools(rail) {
        rail.querySelectorAll(".editrail-tool").forEach(function (tool) {
            tool.addEventListener("click", function (e) {
                var url = new URL(tool.href, window.location.href);
                if (url.pathname!=window.location.pathname) return;
                e.preventDefault();
                processAction(url.hash.substring(7), false);
            });
        });
    }

    // a button of the page tree asks for this, in the fragment or right here
    function processAction(action, fromHash) {
        if (!action) return;
        if (fromHash) window.history.replaceState(null, "", window.location.pathname);
        if (action=="status") {
            toggleStatus();
        } else if (action=="edit" || action=="create" || action=="delete") {
            window.yellow.edit.processAction(action, "none");
        }
    }

    // shows or hides a page, the same way the edit extension saves a page
    function toggleStatus() {
        var page = window.yellow.page;
        var raw = page.rawDataSource;
        if (!raw) return;
        var lines = raw.split(/\r?\n/);
        var end = 0;
        for (var i = 1; i<lines.length; i++) {
            if (lines[i].trim()=="---") { end = i; break; }
        }
        if (lines[0].trim()!="---" || !end) return;
        var found = -1;
        for (var j = 1; j<end; j++) {
            if (/^status\s*:/i.test(lines[j])) found = j;
        }
        if (found!==-1) {
            lines.splice(found, 1);
        } else {
            lines.splice(end, 0, "Status: unlisted");
        }
        window.yellow.toolbox.submitForm({
            "action": "edit",
            "yellowcsrftoken": window.yellow.edit.getCookie("yellowcsrftoken"),
            "rawdatasource": raw,
            "rawdataedit": lines.join(page.rawDataEndOfLine=="crlf" ? "\r\n" : "\n"),
            "rawdataendofline": page.rawDataEndOfLine
        });
    }

    // the files of the website: insert one into the page that is open, delete one, add new ones
    function setupMedia(rail) {
        var token = getCookie("yellowcsrftoken");
        rail.querySelectorAll(".editrail-tool-insert").forEach(function (button) {
            button.addEventListener("click", function () {
                insertText("![](" + button.getAttribute("data-media") + ")");
            });
        });
        rail.querySelectorAll(".editrail-tool-delete[data-media]").forEach(function (button) {
            button.addEventListener("click", function () {
                var name = button.getAttribute("data-media");
                if (!window.confirm(window.yellow.language.editrailDeleteFileAsk.replace("@file", name))) return;
                submit({"editrail-media-delete": name, "yellowcsrftoken": token});
            });
        });
        var upload = rail.querySelector(".editrail-upload input");
        if (upload) {
            upload.addEventListener("change", function () {
                uploadFiles(Array.prototype.slice.call(upload.files), token);
            });
        }
    }

    // the markdown of a file goes where the cursor is, in the window that edits a page
    function insertText(text) {
        var area = document.getElementById("yellow-pane-edit-text");
        if (!area) return;
        var start = area.selectionStart, end = area.selectionEnd;
        area.value = area.value.substring(0, start) + text + area.value.substring(end);
        area.selectionStart = area.selectionEnd = start + text.length;
        area.focus();
        area.dispatchEvent(new Event("input", {bubbles: true}));
    }

    // a file goes to the cloud of this website through the edit extension, which knows the rules
    function uploadFiles(files, token) {
        if (files.length===0) return;
        var pending = files.length;
        files.forEach(function (file) {
            var data = new FormData();
            data.append("action", "upload");
            data.append("yellowcsrftoken", token);
            data.append("file", file);
            window.fetch(window.location.pathname, {method: "POST", body: data, credentials: "same-origin"})
                .then(function (response) { return response.json(); })
                .catch(function () { return {error: "failed"}; })
                .then(function (result) {
                    if (result && result.error) window.alert(result.error);
                    if (--pending===0) window.location.reload();
                });
        });
    }

    // a form is the plainest way to ask the server for something that changes files
    function submit(values) {
        var form = document.createElement("form");
        form.method = "post";
        form.action = window.location.pathname;
        Object.keys(values).forEach(function (key) {
            var input = document.createElement("input");
            input.type = "hidden";
            input.name = key;
            input.value = values[key];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    function getCookie(key) {
        var parts = document.cookie.split(";");
        for (var number = 0; number<parts.length; number++) {
            var pair = parts[number].split("=");
            if (pair[0].trim()===key) return decodeURIComponent(pair.slice(1).join("="));
        }
        return "";
    }

    document.addEventListener("DOMContentLoaded", setupEditRailToggle);
    window.addEventListener("load", setupEditRail);
})();
