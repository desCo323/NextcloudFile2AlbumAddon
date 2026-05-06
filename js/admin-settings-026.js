(function () {
  "use strict";

  const root = document.getElementById("sakuraalbum-admin-settings");
  if (!root) {
    return;
  }

  const state = JSON.parse(root.dataset.settings || "{}");
  let availableGroups = [];
  const mount = root.querySelector(".sakuraalbum-settings");

  function lines(value) {
    return Array.isArray(value) ? value.join("\n") : "";
  }

  function readLines(id) {
    return document
      .getElementById(id)
      .value.split(/\r?\n/)
      .map((line) => line.trim())
      .filter(Boolean);
  }

  function fieldNumber(id) {
    return Number.parseInt(document.getElementById(id).value, 10);
  }

  function render(settings) {
    mount.innerHTML = `
			<div class="sakuraalbum-header">
				<img class="sakuraalbum-mark" src="${OC.imagePath("sakuraalbum", "app.svg")}" alt="">
				<div>
					<h2>SakuraAlbum</h2>
					<p class="sakuraalbum-subtitle">Zentrale Freigabe, Lastgrenzen und Diagnose fuer automatisch gepflegte Fotos-Alben.</p>
				</div>
			</div>
			<div class="sakuraalbum-toggle-row">
				<label class="sakuraalbum-toggle">
						<input id="ska-enabled" type="checkbox" title="Gibt SakuraAlbum serverweit frei; Benutzer muessen zusaetzlich selbst aktivieren." ${settings.enabled ? "checked" : ""}>
					Global aktiv
				</label>
				<label class="sakuraalbum-toggle">
						<input id="ska-allow-videos" type="checkbox" title="Erlaubt Benutzern, Videos in Alben aufzunehmen." ${settings.allowVideos ? "checked" : ""}>
					Videos erlauben
					</label>
					<label class="sakuraalbum-toggle">
						<input id="ska-confirm-delete" type="checkbox" title="Strikte Textbestaetigung ist fuer Loeschaktionen immer aktiv." checked disabled>
						Loeschbestaetigung erzwingen
					</label>
				<label class="sakuraalbum-toggle">
						<input id="ska-debug-mode" type="checkbox" title="Speichert ausfuehrlichere SakuraAlbum-Diagnosedaten ohne bekannte Geheimnisse." ${settings.debugMode ? "checked" : ""}>
					Debug-Logging
				</label>
				<label class="sakuraalbum-toggle">
						<input id="ska-remove-missing" type="checkbox" title="Entfernt Dateien aus SakuraAlbum-verwalteten Alben, wenn sie nicht mehr im geplanten Quellordner liegen." ${settings.syncRemoveMissingFiles ? "checked" : ""}>
					Veraltete Dateien entfernen
				</label>
				<label class="sakuraalbum-toggle">
						<input id="ska-delete-missing" type="checkbox" title="Loescht SakuraAlbum-verwaltete Alben, wenn sie mit exakt derselben Konfiguration nicht mehr geplant sind. Standardmaessig aus." ${settings.syncDeleteMissingManagedAlbums ? "checked" : ""}>
					Fehlende verwaltete Alben loeschen
				</label>
			</div>
			<div class="sakuraalbum-note">
				<strong>Auto-Sync schreibt nie direkt im Datei-Event.</strong>
				<span>Dateiaenderungen werden gesammelt, entprellt und erst im Hintergrund verarbeitet. Die Limits unten begrenzen, wie viele Benutzer, Events, Ordner, Dateien und Alben ein Lauf maximal beruehrt.</span>
			</div>
			<div class="sakuraalbum-note">
				<strong>Automatik braucht zwei Freigaben.</strong>
				<span>Der Administrator erlaubt hier Bei Dateiaenderungen. Danach muss jeder Benutzer in den SakuraAlbum-Benutzereinstellungen selbst Automatisch aktuell halten aktivieren.</span>
			</div>
			<div class="sakuraalbum-note">
				<strong>Rollout und Quoten schuetzen Produktivsysteme.</strong>
				<span>Gruppen begrenzen, wer SakuraAlbum ueberhaupt nutzen darf. Benutzerquoten blockieren Schreibjobs, bevor ein Konto mehr verwaltete Alben oder Medienlinks erzeugen wuerde als erlaubt.</span>
			</div>
			${renderAdminAutomationCard(settings)}
			<div class="sakuraalbum-load-profiles">
				<span>Lastprofil</span>
				<button type="button" data-profile="gentle" title="Sehr vorsichtige Werte fuer produktive Server oder schwache Hardware.">Schonend</button>
				<button type="button" data-profile="balanced" title="Ausgewogene Werte fuer normale Server.">Normal</button>
				<button type="button" data-profile="fast" title="Hoehere Werte fuer kurze Testfenster oder starke Server.">Schnell</button>
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel">
				<div class="sakuraalbum-field sakuraalbum-field-wide">
					<label for="ska-allowed-groups">Rollout: erlaubte Gruppen</label>
						<textarea id="ska-allowed-groups" title="Leer bedeutet: alle Benutzer duerfen SakuraAlbum aktivieren. Sonst muss ein Benutzer Mitglied in mindestens einer dieser Gruppen sein.">${escapeText(lines(settings.allowedGroups))}</textarea>
						<span class="sakuraalbum-field-help">Eine Gruppen-ID pro Zeile. Nicht berechtigte Benutzer koennen Vorschau und Schreibjobs nicht aktivieren; bereits wartende Auto-Sync-Eintraege werden uebersprungen.</span>
						${renderGroupPicker(settings.allowedGroups || [])}
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-default-includes">Standard-Ordner</label>
						<textarea id="ska-default-includes" title="Standardordner fuer Benutzer ohne eigene Ordnerauswahl.">${escapeText(lines(settings.defaultIncludePaths))}</textarea>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-default-excludes">Standard-Ausnahmen</label>
						<textarea id="ska-default-excludes" title="Globale Ausschluesse, die zu Benutzer-Ausnahmen addiert werden.">${escapeText(lines(settings.defaultExcludePatterns))}</textarea>
				</div>
					<div class="sakuraalbum-field">
						<label for="ska-max-depth">Max. Tiefe</label>
						<input id="ska-max-depth" type="number" min="0" max="20" title="Begrenzt die Rekursion in Benutzerordnern." value="${escapeAttr(numberValue(settings.maxScanDepth, 8))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-preview-folders">Preview: Ordnerlimit</label>
						<input id="ska-preview-folders" type="number" min="10" title="Maximal gepruefte Ordner pro Vorschau." value="${escapeAttr(numberValue(settings.maxPreviewFolders, 500))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-preview-files">Preview: Dateilimit</label>
						<input id="ska-preview-files" type="number" min="10" title="Maximal gepruefte Dateien pro Vorschau." value="${escapeAttr(numberValue(settings.maxPreviewFiles, 5000))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-job-folders">Job: Ordnerlimit</label>
						<input id="ska-job-folders" type="number" min="10" title="Maximal gepruefte Ordner pro Schreibjob." value="${escapeAttr(numberValue(settings.maxJobFolders, 5000))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-job-files">Job: Dateilimit</label>
						<input id="ska-job-files" type="number" min="10" title="Maximal gepruefte Dateien pro Schreibjob." value="${escapeAttr(numberValue(settings.maxJobFiles, 50000))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-job-albums">Job: Albumlimit</label>
						<input id="ska-job-albums" type="number" min="1" max="5000" title="Maximal erstellte oder geloeschte Alben pro Lauf." value="${escapeAttr(numberValue(settings.maxAlbumsPerRun, 500))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-user-album-quota">Benutzerquote: Alben</label>
						<input id="ska-user-album-quota" type="number" min="0" max="100000" title="Maximal aktive SakuraAlbum-verwaltete Alben pro Benutzer. 0 bedeutet unbegrenzt." value="${escapeAttr(numberValue(settings.maxManagedAlbumsPerUser, 0))}">
						<span class="sakuraalbum-field-help">0 = keine Gesamtquote. Schreibjobs werden blockiert, bevor diese Grenze ueberschritten wird.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-user-file-quota">Benutzerquote: Medienlinks</label>
						<input id="ska-user-file-quota" type="number" min="0" max="5000000" title="Maximal geplante Medienlinks in SakuraAlbum-verwalteten Alben pro Benutzer. 0 bedeutet unbegrenzt." value="${escapeAttr(numberValue(settings.maxManagedFilesPerUser, 0))}">
						<span class="sakuraalbum-field-help">Die Quote zaehlt die von SakuraAlbum geplanten Medien pro verwaltetem Album, nicht den Speicherplatz der Originaldateien.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-job-interval">Job-Intervall Minuten</label>
						<input id="ska-job-interval" type="number" min="5" title="Mindestabstand zwischen SakuraAlbum-Hintergrundlaeufen." value="${escapeAttr(numberValue(settings.jobIntervalMinutes, 360))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-mode">Automatik</label>
						<select id="ska-auto-mode" title="Manuell reagiert nur auf Benutzeraktionen. Dateiaenderungen werden gesammelt und spaeter per Cron verarbeitet.">
							<option value="manual" ${settings.autoSyncMode === "manual" ? "selected" : ""}>Manuell</option>
							<option value="file_events" ${settings.autoSyncMode === "file_events" ? "selected" : ""}>Bei Dateiaenderungen</option>
						</select>
						<span class="sakuraalbum-field-help">Bei Dateiaenderungen erlaubt automatische Updates serverweit; Benutzer muessen sie trotzdem selbst einschalten.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-debounce">Auto: Wartezeit Sekunden</label>
						<input id="ska-auto-debounce" type="number" min="30" title="Sammelt schnelle Dateioperationen, bevor ein Benutzer synchronisiert wird." value="${escapeAttr(numberValue(settings.autoSyncDebounceSeconds, 300))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-users">Auto: Benutzer pro Lauf</label>
						<input id="ska-auto-users" type="number" min="1" max="1000" title="Maximale Anzahl Benutzer, die ein Cron-Lauf automatisch synchronisiert." value="${escapeAttr(numberValue(settings.autoSyncMaxUsersPerRun, 3))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-runtime">Auto: Laufzeit Sekunden</label>
						<input id="ska-auto-runtime" type="number" min="5" max="3600" title="Harter Zeitrahmen fuer einen automatischen SakuraAlbum-Lauf." value="${escapeAttr(numberValue(settings.autoSyncMaxRuntimeSeconds, 30))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-events">Auto: Events pro Benutzer</label>
						<input id="ska-auto-events" type="number" min="1" max="100000" title="Maximal zusammengefasste Dateiaenderungen pro Benutzer und Cron-Lauf." value="${escapeAttr(numberValue(settings.autoSyncMaxEventsPerRun, 200))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-window-start">Auto: Wartungsfenster Start</label>
						<input id="ska-auto-window-start" type="time" title="Leer zusammen mit Ende bedeutet: Auto-Sync darf jederzeit laufen. Server-Zeitzone wird verwendet." value="${escapeAttr(settings.autoSyncWindowStart || "")}">
						<span class="sakuraalbum-field-help">Optionales Low-Load-Fenster in Serverzeit.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-auto-window-end">Auto: Wartungsfenster Ende</label>
						<input id="ska-auto-window-end" type="time" title="Liegt das Ende vor dem Start, gilt das Fenster ueber Mitternacht." value="${escapeAttr(settings.autoSyncWindowEnd || "")}">
						<span class="sakuraalbum-field-help">Beispiel 22:00 bis 06:00 laeuft ueber Nacht.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-debug-retention">Debug-Aufbewahrung Tage</label>
						<input id="ska-debug-retention" type="number" min="1" max="365" title="Anzahl Tage, die Debug-Logs behalten werden sollen." value="${escapeAttr(numberValue(settings.debugRetentionDays, 14))}">
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-debug-context">Max. Kontextlaenge</label>
						<input id="ska-debug-context" type="number" min="1000" max="100000" title="Maximale gespeicherte Kontextgroesse pro Logeintrag." value="${escapeAttr(numberValue(settings.debugMaxContextLength, 8000))}">
					</div>
			</div>
			<div class="sakuraalbum-actions">
					<button id="ska-save-admin" class="primary" type="button" title="Speichert zentrale SakuraAlbum-Vorgaben und Limits.">Speichern</button>
					<button id="ska-load-groups" type="button" title="Laedt Nextcloud-Gruppen fuer die Rollout-Auswahl.">Gruppen laden</button>
					<button id="ska-enable-auto-mode" type="button" title="Setzt Global aktiv und Automatik auf Bei Dateiaenderungen. Danach speichern.">Automatik vorbereiten</button>
					<button id="ska-load-auto-status" type="button" title="Zeigt, ob Auto-Sync-Events warten, verarbeitet werden oder fehlgeschlagen sind.">Auto-Status laden</button>
					<button id="ska-run-auto-now" type="button" title="Verarbeitet jetzt faellige Auto-Sync-Eintraege mit den gespeicherten Admin-Limits.">Faellige Jobs jetzt verarbeiten</button>
					<button id="ska-load-logs" type="button" title="Laedt die neuesten SakuraAlbum-Diagnoseeintraege.">Logs laden</button>
					<button id="ska-admin-diagnostic-report" type="button" title="Bereitet einen redigierten Diagnosebericht fuer Admins vor.">Diagnosebericht</button>
			</div>
			<div id="ska-admin-status" class="sakuraalbum-status"></div>
			<div id="ska-log-output"></div>
		`;
    document.getElementById("ska-save-admin").addEventListener("click", save);
    document
      .getElementById("ska-load-groups")
      .addEventListener("click", loadGroups);
    document
      .getElementById("ska-enable-auto-mode")
      .addEventListener("click", prepareAutomation);
    document
      .getElementById("ska-load-auto-status")
      .addEventListener("click", loadAutoStatus);
    document
      .getElementById("ska-run-auto-now")
      .addEventListener("click", runAutoSyncNow);
    document
      .getElementById("ska-load-logs")
      .addEventListener("click", loadLogs);
    document
      .getElementById("ska-admin-diagnostic-report")
      .addEventListener("click", loadDiagnosticReport);
    Array.from(root.querySelectorAll("[data-profile]")).forEach((button) => {
      button.addEventListener("click", () => applyLoadProfile(button.dataset.profile));
    });
    bindGroupPicker();
  }

  function collect() {
    return {
      enabled: document.getElementById("ska-enabled").checked,
      allowedGroups: readLines("ska-allowed-groups"),
      defaultIncludePaths: readLines("ska-default-includes"),
      defaultExcludePatterns: readLines("ska-default-excludes"),
      maxScanDepth: fieldNumber("ska-max-depth"),
      maxPreviewFolders: fieldNumber("ska-preview-folders"),
      maxPreviewFiles: fieldNumber("ska-preview-files"),
      maxJobFolders: fieldNumber("ska-job-folders"),
      maxJobFiles: fieldNumber("ska-job-files"),
      maxAlbumsPerRun: fieldNumber("ska-job-albums"),
      maxManagedAlbumsPerUser: fieldNumber("ska-user-album-quota"),
      maxManagedFilesPerUser: fieldNumber("ska-user-file-quota"),
      allowVideos: document.getElementById("ska-allow-videos").checked,
      jobIntervalMinutes: fieldNumber("ska-job-interval"),
      autoSyncMode: document.getElementById("ska-auto-mode").value,
      autoSyncDebounceSeconds: fieldNumber("ska-auto-debounce"),
      autoSyncMaxUsersPerRun: fieldNumber("ska-auto-users"),
      autoSyncMaxRuntimeSeconds: fieldNumber("ska-auto-runtime"),
      autoSyncMaxEventsPerRun: fieldNumber("ska-auto-events"),
      autoSyncWindowStart: document.getElementById("ska-auto-window-start").value,
      autoSyncWindowEnd: document.getElementById("ska-auto-window-end").value,
      syncRemoveMissingFiles: document.getElementById("ska-remove-missing")
        .checked,
      syncDeleteMissingManagedAlbums:
        document.getElementById("ska-delete-missing").checked,
      requireBulkDeleteConfirmation:
        document.getElementById("ska-confirm-delete").checked,
      debugMode: document.getElementById("ska-debug-mode").checked,
      debugRetentionDays: fieldNumber("ska-debug-retention"),
      debugMaxContextLength: fieldNumber("ska-debug-context"),
    };
  }

  function renderGroupPicker(selectedGroups) {
    const selected = new Set(Array.isArray(selectedGroups) ? selectedGroups : []);
    if (!availableGroups.length) {
      return '<div class="sakuraalbum-field-help">Gruppenliste noch nicht geladen. Freitext funktioniert trotzdem; Gruppen laden zeigt eine Auswahl.</div>';
    }

    return `
			<div class="sakuraalbum-group-picker">
				${availableGroups
          .map(
            (group) => `
					<label>
						<input type="checkbox" data-group-id="${escapeAttr(group.id)}" ${selected.has(group.id) ? "checked" : ""}>
						<span>${escapeText(group.displayName || group.id)}</span>
						<small>${escapeText(group.id)}</small>
					</label>
				`,
          )
          .join("")}
			</div>
		`;
  }

  function bindGroupPicker() {
    Array.from(root.querySelectorAll("[data-group-id]")).forEach((checkbox) => {
      checkbox.addEventListener("change", () => {
        const selected = new Set(readLines("ska-allowed-groups"));
        const groupId = checkbox.dataset.groupId || "";
        if (!groupId) {
          return;
        }
        if (checkbox.checked) {
          selected.add(groupId);
        } else {
          selected.delete(groupId);
        }
        document.getElementById("ska-allowed-groups").value = Array.from(selected).join("\n");
      });
    });
  }

  function renderAdminAutomationCard(settings) {
    const enabled = settings.enabled && settings.autoSyncMode === "file_events";
    const mode = enabled ? "Automatik serverweit erlaubt" : settings.enabled ? "Manuell gespeichert" : "Zentral gesperrt";
    const next = enabled
      ? "Benutzer koennen Automatisch aktuell halten aktivieren; Datei-Events werden nur vorgemerkt und spaeter per Hintergrundjob verarbeitet."
      : "Setze Global aktiv und Automatik auf Bei Dateiaenderungen, speichere danach und pruefe den Auto-Status.";
    return `
			<div class="sakuraalbum-automation-card">
				<div>
					<strong>${escapeText(mode)}</strong>
					<span>${escapeText(next)}</span>
				</div>
				<div class="sakuraalbum-mini-table">
					<div><span>Entprellung</span><strong>${escapeText(settings.autoSyncDebounceSeconds || 0)} s</strong></div>
					<div><span>Benutzer/Lauf</span><strong>${escapeText(settings.autoSyncMaxUsersPerRun || 0)}</strong></div>
					<div><span>Laufzeit</span><strong>${escapeText(settings.autoSyncMaxRuntimeSeconds || 0)} s</strong></div>
					<div><span>Dateien/Chunk</span><strong>${escapeText(settings.maxJobFiles || 0)}</strong></div>
					<div><span>Fenster</span><strong>${escapeText(windowLabel(settings))}</strong></div>
					<div><span>Rollout</span><strong>${escapeText(groupLabel(settings.allowedGroups || []))}</strong></div>
				</div>
			</div>
		`;
  }

  async function loadGroups() {
    const status = document.getElementById("ska-admin-status");
    status.textContent = "Lade Gruppen...";
    try {
      const current = collect();
      const response = await fetch(
        `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/groups")}?limit=200`,
        {
          method: "GET",
          headers: {
            requesttoken: OC.requestToken,
          },
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      availableGroups = Array.isArray(data.groups) ? data.groups : [];
      render(current);
      document.getElementById("ska-admin-status").textContent =
        `${availableGroups.length} Gruppen geladen. Zum Uebernehmen speichern.`;
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  function prepareAutomation() {
    document.getElementById("ska-enabled").checked = true;
    document.getElementById("ska-auto-mode").value = "file_events";
    document.getElementById("ska-admin-status").textContent =
      "Automatik vorbereitet. Speichern uebernimmt die zentrale Freigabe.";
  }

  async function save() {
    const status = document.getElementById("ska-admin-status");
    status.textContent = "Speichere...";
    try {
      const response = await fetch(
        OC.generateUrl("/apps/sakuraalbum/api/v1/admin/settings"),
        {
          method: "PUT",
          headers: {
            "Content-Type": "application/json",
            requesttoken: OC.requestToken,
          },
          body: JSON.stringify({ settings: collect() }),
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      const saved = data.settings || collect();
      render(saved);
      document.getElementById("ska-admin-status").textContent =
        `Gespeichert. Automatik: ${autoModeText(saved.autoSyncMode)}.`;
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function loadLogs() {
    const status = document.getElementById("ska-admin-status");
    const output = document.getElementById("ska-log-output");
    status.textContent = "Lade Logs...";
    output.innerHTML = "";
    try {
      const response = await fetch(
        `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/logs")}?limit=50`,
        {
          method: "GET",
          headers: {
            requesttoken: OC.requestToken,
          },
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      renderLogs(data.logs || []);
      status.textContent = `${(data.logs || []).length} Logeintraege geladen.`;
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function loadDiagnosticReport() {
    const status = document.getElementById("ska-admin-status");
    const output = document.getElementById("ska-log-output");
    status.textContent = "Bereite Diagnosebericht vor...";
    output.innerHTML = "";
    try {
      const response = await fetch(
        `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/diagnostics/report")}?limit=80`,
        {
          method: "GET",
          headers: {
            requesttoken: OC.requestToken,
          },
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      renderDiagnosticReport(data.report || {});
      status.textContent = "Diagnosebericht vorbereitet. Versand per Mail wird spaeter angebunden.";
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function loadAutoStatus() {
    const status = document.getElementById("ska-admin-status");
    const output = document.getElementById("ska-log-output");
    status.textContent = "Lade Auto-Status...";
    output.innerHTML = "";
    try {
      const response = await fetch(
        `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/auto-sync/status")}?limit=12`,
        {
          method: "GET",
          headers: {
            requesttoken: OC.requestToken,
          },
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      renderAutoStatus(data.status || {});
      status.textContent = "Auto-Status geladen.";
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function runAutoSyncNow() {
    const status = document.getElementById("ska-admin-status");
    const output = document.getElementById("ska-log-output");
    status.textContent = "Verarbeite faellige Auto-Sync-Jobs...";
    output.innerHTML = "";
    try {
      const response = await fetch(
        `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/auto-sync/process-due")}?limit=12`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            requesttoken: OC.requestToken,
          },
          body: JSON.stringify({}),
        },
      );
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      renderAutoProcessResult(data.summary || {}, data.status || {});
      status.textContent = "Faellige Auto-Sync-Jobs verarbeitet.";
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  function applyLoadProfile(profile) {
    const profiles = {
      gentle: {
        maxJobFolders: 250,
        maxJobFiles: 2000,
        maxAlbumsPerRun: 50,
        jobIntervalMinutes: 30,
        autoSyncDebounceSeconds: 600,
        autoSyncMaxUsersPerRun: 1,
        autoSyncMaxRuntimeSeconds: 10,
        autoSyncMaxEventsPerRun: 50,
      },
      balanced: {
        maxJobFolders: 1000,
        maxJobFiles: 10000,
        maxAlbumsPerRun: 150,
        jobIntervalMinutes: 15,
        autoSyncDebounceSeconds: 300,
        autoSyncMaxUsersPerRun: 3,
        autoSyncMaxRuntimeSeconds: 30,
        autoSyncMaxEventsPerRun: 200,
      },
      fast: {
        maxJobFolders: 5000,
        maxJobFiles: 50000,
        maxAlbumsPerRun: 500,
        jobIntervalMinutes: 5,
        autoSyncDebounceSeconds: 60,
        autoSyncMaxUsersPerRun: 10,
        autoSyncMaxRuntimeSeconds: 120,
        autoSyncMaxEventsPerRun: 2000,
      },
    };
    const selected = profiles[profile];
    if (!selected) {
      return;
    }

    document.getElementById("ska-job-folders").value = selected.maxJobFolders;
    document.getElementById("ska-job-files").value = selected.maxJobFiles;
    document.getElementById("ska-job-albums").value = selected.maxAlbumsPerRun;
    document.getElementById("ska-job-interval").value = selected.jobIntervalMinutes;
    document.getElementById("ska-auto-debounce").value = selected.autoSyncDebounceSeconds;
    document.getElementById("ska-auto-users").value = selected.autoSyncMaxUsersPerRun;
    document.getElementById("ska-auto-runtime").value = selected.autoSyncMaxRuntimeSeconds;
    document.getElementById("ska-auto-events").value = selected.autoSyncMaxEventsPerRun;
    document.getElementById("ska-admin-status").textContent =
      "Lastprofil gesetzt. Zum Uebernehmen speichern.";
  }

  function renderAutoStatus(autoStatus) {
    const output = document.getElementById("ska-log-output");
    const counts = autoStatus.counts || {};
    const samples = autoStatus.samples || [];
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(autoStatus.enabled ? "Ein" : "Aus")}</strong><br>Automatik</div>
				<div><strong>${escapeText(autoStatus.processingEnabled ? "Offen" : "Wartet")}</strong><br>Fenster</div>
				<div><strong>${escapeText(autoStatus.dueUsers || 0)}</strong><br>Faellige Benutzer</div>
				<div><strong>${escapeText(autoStatus.dueUsersWaitingForWindow || 0)}</strong><br>Warten auf Fenster</div>
				<div><strong>${escapeText(counts.pending || 0)}</strong><br>Wartend</div>
				<div><strong>${escapeText(counts.processing || 0)}</strong><br>In Arbeit</div>
				<div><strong>${escapeText(counts.failed || 0)}</strong><br>Fehler</div>
				<div><strong>${escapeText(formatTime(autoStatus.nextDueAt))}</strong><br>Naechster Start</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>Aktive Grenzen:</strong>
				<span>${escapeText(autoStatus.maxUsersPerRun || 0)} Benutzer, ${escapeText(autoStatus.maxEventsPerRun || 0)} Events und ${escapeText(autoStatus.maxRuntimeSeconds || 0)} Sekunden pro Hintergrundlauf; Entprellzeit ${escapeText(autoStatus.debounceSeconds || 0)} Sekunden; Wartungsfenster ${escapeText(windowStatusLabel(autoStatus))}.</span>
			</div>
			<div class="sakuraalbum-inline-help">Neue, geaenderte, verschobene, geloeschte oder umbenannte Dateien/Ordner erzeugen nur einen Warteschlangeneintrag fuer den betroffenen Quellordner. Der eigentliche Albumabgleich passiert erst, wenn Cron faellige Eintraege verarbeitet.</div>
			${samples.length === 0 ? '<div class="sakuraalbum-empty">Keine Auto-Sync-Events in der Warteschlange.</div>' : renderAutoQueueTable(samples)}
		`;
  }

  function renderAutoProcessResult(summary, autoStatus) {
    const output = document.getElementById("ska-log-output");
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(summary.processedUsers || 0)}</strong><br>Benutzer verarbeitet</div>
				<div><strong>${escapeText(summary.succeededUsers || 0)}</strong><br>Erfolgreich</div>
				<div><strong>${escapeText(summary.failedUsers || 0)}</strong><br>Fehlerhaft</div>
				<div><strong>${escapeText(summary.continuedUsers || 0)}</strong><br>Fortsetzungen</div>
				<div><strong>${escapeText(summary.lockedEvents || 0)}</strong><br>Events reserviert</div>
				<div><strong>${escapeText(summary.recoveredStaleLocks || 0)}</strong><br>Locks repariert</div>
			</div>
			${summary.skipped ? `<div class="sakuraalbum-warning">${escapeText(autoSkipText(summary.skipped))}</div>` : ""}
			${summary.stoppedReason ? `<div class="sakuraalbum-warning">${escapeText(autoStopText(summary.stoppedReason))}</div>` : ""}
			<div class="sakuraalbum-note">
				<strong>Warteschlange nach dem Lauf</strong>
				<span>${escapeText(((autoStatus.counts || {}).pending) || 0)} wartend, ${escapeText(((autoStatus.counts || {}).processing) || 0)} in Arbeit, ${escapeText(((autoStatus.counts || {}).failed) || 0)} fehlerhaft.</span>
			</div>
			${(autoStatus.samples || []).length === 0 ? '<div class="sakuraalbum-empty">Keine Auto-Sync-Events in der Warteschlange.</div>' : renderAutoQueueTable(autoStatus.samples || [])}
		`;
  }

  function renderAutoQueueTable(samples) {
    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead>
						<tr>
							<th>Benutzer</th>
							<th>Status</th>
							<th>Pfad</th>
							<th>Events</th>
							<th>Letzte Aenderung</th>
							<th>Fehler</th>
						</tr>
					</thead>
					<tbody>
						${samples
              .map(
                (item) => `
							<tr>
								<td>${escapeText(item.userId)}</td>
								<td>${escapeText(autoQueueStatusText(item.status))}</td>
								<td>${escapeText(item.path)}</td>
								<td>${escapeText(item.changeCount || 0)} ${escapeText(autoEventText(item.eventType || ""))}</td>
								<td>${escapeText(formatTime(item.lastSeenAt))}</td>
								<td>${escapeText(item.lastError || "")}</td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function autoEventText(eventType) {
    const labels = {
      chunk_continue: "Fortsetzung",
      manual_refresh: "manuell vorgemerkt",
      settings_update: "Einstellungen",
      created: "erstellt",
      written: "geaendert",
      deleted: "geloescht",
      renamed_source: "umbenannt/verschoben Quelle",
      renamed_target: "umbenannt/verschoben Ziel",
    };
    return labels[eventType] || eventType || "";
  }

  function autoQueueStatusText(status) {
    const labels = {
      pending: "wartend",
      processing: "in Arbeit",
      failed: "fehlerhaft",
    };
    return labels[status] || status || "";
  }

  function autoSkipText(reason) {
    const labels = {
      auto_sync_disabled: "Automatik ist zentral auf manuell gestellt.",
      outside_auto_sync_window: "Der Lauf wartet auf das konfigurierte Wartungsfenster.",
    };
    return labels[reason] || reason || "";
  }

  function autoStopText(reason) {
    const labels = {
      runtime_limit: "Der Lauf wurde durch das Laufzeitlimit beendet. Offene Eintraege bleiben in der Warteschlange.",
    };
    return labels[reason] || reason || "";
  }

  function renderLogs(logs) {
    const output = document.getElementById("ska-log-output");
    if (logs.length === 0) {
      output.innerHTML =
        '<div class="sakuraalbum-empty">Keine Logeintraege gefunden.</div>';
      return;
    }

    output.innerHTML = `
			<table class="sakuraalbum-preview-table sakuraalbum-log-table">
				<thead>
					<tr>
						<th>Zeit</th>
						<th>Level</th>
						<th>Ereignis</th>
						<th>Benutzer</th>
						<th>Nachricht</th>
					</tr>
				</thead>
				<tbody>
					${logs
            .map(
              (log) => `
						<tr>
							<td>${escapeText(formatTime(log.createdAt))}</td>
								<td><span class="sakuraalbum-level sakuraalbum-level-${levelClass(log.level)}">${escapeText(log.level)}</span></td>
							<td>${escapeText(log.event)}</td>
							<td>${escapeText(log.userId || "")}</td>
							<td>
								${escapeText(log.message || "")}
								${log.context ? `<pre>${escapeText(JSON.stringify(log.context, null, 2))}</pre>` : ""}
							</td>
						</tr>
					`,
            )
            .join("")}
				</tbody>
			</table>
		`;
  }

  function renderDiagnosticReport(report) {
    const output = document.getElementById("ska-log-output");
    const meta = report.meta || {};
    const logs = report.logs || [];
    const autoSync = report.autoSync || {};
    const counts = autoSync.counts || {};
    const json = JSON.stringify(report, null, 2);
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(meta.scope || "")}</strong><br>Bereich</div>
				<div><strong>${escapeText(formatTime(meta.createdAt))}</strong><br>Erstellt</div>
				<div><strong>${escapeText(logs.length)}</strong><br>Logs</div>
				<div><strong>${escapeText(counts.pending || 0)}</strong><br>Wartend</div>
				<div><strong>${escapeText(counts.failed || 0)}</strong><br>Fehler</div>
				<div><strong>${report.sendMailReady ? "Ja" : "Nein"}</strong><br>Mail aktiv</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>Redigierter Diagnosebericht</strong>
				<span>Bekannte Geheimnisse werden vor dem Speichern von Log-Kontext redigiert. Der direkte Mailversand ist noch nicht aktiv.</span>
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-copy-admin-diagnostic-report" type="button" title="Kopiert den sichtbaren JSON-Bericht in die Zwischenablage.">Bericht kopieren</button>
			</div>
			<pre class="sakuraalbum-report-json">${escapeText(json)}</pre>
		`;
    const copy = document.getElementById("ska-copy-admin-diagnostic-report");
    if (copy) {
      copy.addEventListener("click", () => copyDiagnosticReport(json));
    }
  }

  async function copyDiagnosticReport(json) {
    const status = document.getElementById("ska-admin-status");
    try {
      if (!navigator.clipboard || !navigator.clipboard.writeText) {
        throw new Error("Zwischenablage nicht verfuegbar");
      }
      await navigator.clipboard.writeText(json);
      status.textContent = "Diagnosebericht kopiert.";
    } catch (error) {
      status.textContent = `Kopieren nicht moeglich: ${error.message}`;
    }
  }

  function formatTime(timestamp) {
    if (!timestamp) {
      return "";
    }
    return new Date(timestamp * 1000).toLocaleString();
  }

  function escapeText(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function escapeAttr(value) {
    return escapeText(value).replaceAll("'", "&#039;");
  }

  function numberValue(value, fallback) {
    const number = Number.parseInt(value, 10);
    return Number.isFinite(number) ? number : fallback;
  }

  function levelClass(level) {
    return ["debug", "info", "success", "warning", "error"].includes(level)
      ? level
      : "info";
  }

  function autoModeText(mode) {
    return mode === "file_events" ? "Bei Dateiaenderungen" : "Manuell";
  }

  function groupLabel(groups) {
    return Array.isArray(groups) && groups.length > 0 ? `${groups.length} Gruppen` : "Alle";
  }

  function windowLabel(settings) {
    const start = settings.autoSyncWindowStart || "";
    const end = settings.autoSyncWindowEnd || "";
    return start && end ? `${start}-${end}` : "Immer";
  }

  function windowStatusLabel(status) {
    const start = status.windowStart || "";
    const end = status.windowEnd || "";
    if (!start || !end) {
      return "immer offen";
    }
    if (status.windowActive) {
      return `${start}-${end} offen`;
    }
    const next = status.nextWindowAt ? `, naechster Start ${formatTime(status.nextWindowAt)}` : "";
    return `${start}-${end} geschlossen${next}`;
  }

  render(state);
})();
