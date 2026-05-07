(function () {
  "use strict";

  const root = document.getElementById("sakuraalbum-admin-settings");
  if (!root) {
    return;
  }

  const state = JSON.parse(root.dataset.settings || "{}");
  let availableGroups = [];
  const mount = root.querySelector(".sakuraalbum-settings");

  const autoStatusRoutes = [
    "/apps/sakuraalbum/api/v1/admin/auto_status",
    "/apps/sakuraalbum/api/v1/admin/auto_status/",
    "/apps/sakuraalbum/api/v1/admin/auto-status",
    "/apps/sakuraalbum/api/v1/admin/auto-status/",
    "/apps/sakuraalbum/api/v1/admin/auto-sync/status",
    "/apps/sakuraalbum/api/v1/admin/auto-sync/status/",
    "/apps/sakuraalbum/api/v1/admin/autosync/status",
    "/apps/sakuraalbum/api/v1/admin/autosync/status/",
    "/apps/sakuraalbum/api/v1/admin/auto_sync/status",
    "/apps/sakuraalbum/api/v1/admin/auto_sync/status/",
  ];
  const autoRunRoutes = [
    "/apps/sakuraalbum/api/v1/admin/auto-sync/process-due",
    "/apps/sakuraalbum/api/v1/admin/autosync/process-due",
    "/apps/sakuraalbum/api/v1/admin/auto_sync/process-due",
    "/apps/sakuraalbum/api/v1/admin/auto-sync/process-due/",
    "/apps/sakuraalbum/api/v1/admin/autosync/process-due/",
    "/apps/sakuraalbum/api/v1/admin/auto_sync/process-due/",
    "/apps/sakuraalbum/api/v1/admin/auto-sync/trigger",
    "/apps/sakuraalbum/api/v1/admin/auto-sync/trigger/",
  ];

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

  async function adminRouteGetJson(candidates) {
    return requestAdminRoute("GET", candidates, {
      headers: {requesttoken: OC.requestToken},
      query: {limit: 12},
    });
  }

  async function adminRoutePostJson(candidates, body = {}) {
    return requestAdminRoute("POST", candidates, {
      headers: {
        "Content-Type": "application/json",
        requesttoken: OC.requestToken,
      },
      body,
    });
  }

  async function requestAdminRoute(method, candidates, options = {}) {
    const paths = Array.isArray(candidates) ? candidates : [candidates];
    const queryString = buildQueryString(options.query || null);
    let responseError = null;

    for (let i = 0; i < paths.length; i++) {
      const path = paths[i];
      const route = queryString ? `${path}?${queryString}` : path;
      const response = await fetch(OC.generateUrl(route), {
        method,
        headers: Object.assign({}, options.headers || {}, {
          requesttoken: OC.requestToken,
        }),
        body: options.body && Object.keys(options.body || {}).length !== 0
          ? JSON.stringify(options.body)
          : undefined,
      });

      let responseDetail = "";
      try {
        const contentType = response.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
          const data = await response.clone().json();
          if (data && typeof data === "object" && data.error) {
            responseDetail = `: ${data.error}`;
          }
        } else {
          const text = (await response.clone().text()).trim();
          if (text) {
            responseDetail = `: ${text.slice(0, 120)}`;
          }
        }
      } catch {
        // Keep probing usable even when Nextcloud returns an HTML error page.
      }

      const candidateError = `HTTP ${response.status} on ${path}${responseDetail}`;

      if (response.status === 404 && i + 1 < paths.length) {
        responseError = candidateError;
        continue;
      }

      if (!response.ok) {
        responseError = formatAdminRouteError(response.status, path, candidateError);
        throw new Error(responseError);
      }

      return response.json();
    }

    throw new Error(responseError || "Admin-Route ist nicht verfuegbar.");
  }

  function buildQueryString(query) {
    if (!query || typeof query !== "object") {
      return "";
    }

    const params = [];
    Object.keys(query).forEach((key) => {
      const value = query[key];
      if (value === undefined || value === null || value === "") {
        return;
      }
      params.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`);
    });

    return params.join("&");
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
				<strong>Automatik folgt der zentralen Vorgabe.</strong>
				<span>Wenn hier Bei Dateiaenderungen aktiv ist, reicht es fuer Benutzer, SakuraAlbum fuer ihr Konto zu aktivieren. Datei-Events werden dann automatisch im Hintergrund verarbeitet.</span>
			</div>
			<div class="sakuraalbum-note">
				<strong>Rollout und Quoten schuetzen Produktivsysteme.</strong>
				<span>Gruppen begrenzen, wer SakuraAlbum ueberhaupt nutzen darf. Benutzerquoten blockieren Schreibjobs, bevor ein Konto mehr verwaltete Alben oder Medienlinks erzeugen wuerde als erlaubt.</span>
			</div>
			${renderAdminControlGuide(settings)}
			${renderAdminAutomationCard(settings)}
			<div class="sakuraalbum-load-profiles">
				<span>Lastprofil</span>
				<button type="button" data-profile="gentle" title="Sehr vorsichtige Werte fuer produktive Server oder schwache Hardware.">Schonend</button>
				<button type="button" data-profile="balanced" title="Ausgewogene Werte fuer normale Server.">Normal</button>
				<button type="button" data-profile="fast" title="Hoehere Werte fuer kurze Testfenster oder starke Server.">Schnell</button>
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel" id="ska-admin-limits">
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
						<label for="ska-download-files">Download: Dateilimit</label>
						<input id="ska-download-files" type="number" min="1" max="100000" title="Maximale Dateien in einem direkten Album-ZIP. Groessere Alben werden blockiert." value="${escapeAttr(numberValue(settings.maxDownloadFiles, 1000))}">
						<span class="sakuraalbum-field-help">Schuetzt CPU, Speicher und lange HTTP-Verbindungen.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-download-bytes">Download: Bytelimit</label>
						<input id="ska-download-bytes" type="number" min="1048576" max="2147483647" title="Maximale Gesamtgroesse eines direkten Album-ZIPs in Bytes." value="${escapeAttr(numberValue(settings.maxDownloadBytes, 2147483647))}">
						<span class="sakuraalbum-field-help">Diese Limits gelten nur fuer direkte ZIP-Streams. Grosse Album-Downloads laufen in der Benutzeroberflaeche als Hintergrund-Export mit Teil-ZIP-Dateien.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-export-files">Export: Dateilimit</label>
						<input id="ska-export-files" type="number" min="1" max="1000000" title="Maximale Dateien in einem Hintergrund-Albumexport." value="${escapeAttr(numberValue(settings.maxExportFiles, 100000))}">
						<span class="sakuraalbum-field-help">Schuetzt Hintergrundjobs gegen riesige oder missbraeuchlich grosse Album-Exports.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-export-bytes">Export: Bytelimit</label>
						<input id="ska-export-bytes" type="number" min="1048576" max="10995116277760" title="Maximale lesbare Gesamtgroesse eines Hintergrund-Albumexports in Bytes." value="${escapeAttr(numberValue(settings.maxExportBytes, 1099511627776))}">
						<span class="sakuraalbum-field-help">Standard ist 1 TiB. Grosse erlaubte Exporte werden weiter in Teil-ZIP-Dateien ab 1 GiB geschrieben.</span>
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
						<span class="sakuraalbum-field-help">Bei Dateiaenderungen ist die empfohlene Standardeinstellung: Benutzer aktivieren nur SakuraAlbum, die Aktualisierung laeuft dann automatisch im Hintergrund.</span>
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
					<button id="ska-admin-diagnostic-csv" type="button" title="Laedt die Diagnose- und Logdaten als CSV herunter und speichert eine Kopie serverseitig im SakuraAlbum-AppData.">CSV herunterladen</button>
			</div>
			<div id="ska-admin-status" class="sakuraalbum-status" role="status" aria-live="polite" aria-atomic="true"></div>
			<div id="ska-log-output" role="region" aria-label="SakuraAlbum Admin-Ausgabe" tabindex="-1"></div>
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
    document
      .getElementById("ska-admin-diagnostic-csv")
      .addEventListener("click", downloadDiagnosticCsv);
    Array.from(root.querySelectorAll("[data-profile]")).forEach((button) => {
      button.addEventListener("click", () => applyLoadProfile(button.dataset.profile));
    });
    bindGroupPicker();
  }

  function collect() {
    const autoMode = normalizedAutoSyncMode(document.getElementById("ska-auto-mode").value);
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
      maxDownloadFiles: fieldNumber("ska-download-files"),
      maxDownloadBytes: fieldNumber("ska-download-bytes"),
      maxExportFiles: fieldNumber("ska-export-files"),
      maxExportBytes: fieldNumber("ska-export-bytes"),
      allowVideos: document.getElementById("ska-allow-videos").checked,
      jobIntervalMinutes: fieldNumber("ska-job-interval"),
      autoSyncMode: autoMode,
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
    const enabled = settings.enabled && normalizedAutoSyncMode(settings.autoSyncMode) === "file_events";
    const mode = enabled ? "Automatik serverweit erlaubt" : settings.enabled ? "Manuell gespeichert" : "Zentral gesperrt";
    const next = enabled
      ? "Benutzer muessen nur SakuraAlbum aktivieren; Datei-Events werden vorgemerkt und spaeter per Hintergrundjob verarbeitet."
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
					<div><span>Exportlimit</span><strong>${escapeText(settings.maxExportFiles || 0)} Dateien</strong></div>
					<div><span>Fenster</span><strong>${escapeText(windowLabel(settings))}</strong></div>
					<div><span>Rollout</span><strong>${escapeText(groupLabel(settings.allowedGroups || []))}</strong></div>
				</div>
			</div>
		`;
  }

  function renderAdminControlGuide(settings) {
    const debugActive = settings.debugMode ? "Debug aktiv" : "Debug aus";
    const autoMode = normalizedAutoSyncMode(settings.autoSyncMode) === "file_events" ? "Datei-Events" : "Manuell";
    const rollout = groupLabel(settings.allowedGroups || []);
    const quota =
      Number(settings.maxManagedAlbumsPerUser || 0) || Number(settings.maxManagedFilesPerUser || 0)
        ? "Quoten aktiv"
        : "Keine Benutzerquote";

    return `
			<div class="sakuraalbum-admin-guide" aria-label="SakuraAlbum Admin-Ueberblick">
				<div class="sakuraalbum-panel-head">
					<div>
						<h3>Admin-Cockpit</h3>
						<p>Diese vier Bereiche reichen fuer den normalen Betrieb: Freigabe, Lastgrenzen, Automatik und Diagnose. Erst danach sind Detailfelder relevant.</p>
					</div>
				</div>
				<div class="sakuraalbum-status-lanes">
					<div>
						<span>1 Freigabe</span>
						<strong>${escapeText(settings.enabled ? "Global aktiv" : "Global aus")}</strong>
						<small>${escapeText(rollout)}</small>
					</div>
					<div>
						<span>2 Lastschutz</span>
						<strong>${escapeText(quota)}</strong>
						<small>${escapeText(settings.maxJobFiles || 0)} Dateien pro Job, ${escapeText(settings.maxAlbumsPerRun || 0)} Alben pro Lauf</small>
					</div>
					<div>
						<span>3 Automatik</span>
						<strong>${escapeText(autoMode)}</strong>
						<small>${escapeText(settings.autoSyncMaxUsersPerRun || 0)} Benutzer, ${escapeText(settings.autoSyncMaxRuntimeSeconds || 0)} Sekunden pro Lauf</small>
					</div>
					<div>
						<span>4 Diagnose</span>
						<strong>${escapeText(debugActive)}</strong>
						<small>${escapeText(settings.debugRetentionDays || 0)} Tage Aufbewahrung, CSV fuer Support</small>
					</div>
				</div>
				<div class="sakuraalbum-inline-help">
					Empfohlener Betriebsablauf: erst speichern, dann Auto-Status laden, danach nur bei Bedarf Logs oder Diagnosebericht oeffnen. "Faellige Jobs jetzt verarbeiten" ist fuer kontrollierte Tests und Fehleranalyse gedacht.
				</div>
			</div>
		`;
  }

  function normalizedAutoSyncMode(value) {
    return value === "file_events" ? "file_events" : "manual";
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
      output.focus({preventScroll: true});
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
      output.focus({preventScroll: true});
      status.textContent = "Diagnosebericht vorbereitet. Versand per Mail wird spaeter angebunden.";
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  function downloadDiagnosticCsv() {
    const status = document.getElementById("ska-admin-status");
    const url = `${OC.generateUrl("/apps/sakuraalbum/api/v1/admin/diagnostics/logs.csv")}?limit=200`;
    status.textContent =
      "CSV-Export gestartet. SakuraAlbum speichert zusaetzlich eine Kopie im AppData-Diagnoseordner.";
    window.location.href = url;
  }

  async function loadAutoStatus() {
    const status = document.getElementById("ska-admin-status");
    const output = document.getElementById("ska-log-output");
    status.textContent = "Lade Auto-Status...";
    output.innerHTML = "";
    try {
      const data = await adminRouteGetJson(autoStatusRoutes);
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
      const data = await adminRoutePostJson(autoRunRoutes, {});
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
        maxExportFiles: 10000,
        maxExportBytes: 1099511627776,
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
        maxExportFiles: 50000,
        maxExportBytes: 1099511627776,
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
        maxExportFiles: 100000,
        maxExportBytes: 2199023255552,
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
    document.getElementById("ska-export-files").value = selected.maxExportFiles;
    document.getElementById("ska-export-bytes").value = selected.maxExportBytes;
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
    const job = autoStatus.job || {};
    const counts = autoStatus.counts || {};
    const samples = autoStatus.samples || [];
    const automationBlockingReason = autoStatus.automationBlockingReason || "";
    const autoModeText = (autoStatus.globalEnabled ?? true) && normalizedAutoSyncMode(autoStatus.mode || "manual") === "file_events"
      ? "Datei-Events"
      : "Manuell";
    output.innerHTML = `
			<div class="sakuraalbum-admin-guide">
				<div class="sakuraalbum-panel-head">
					<div>
						<h3>Auto-Sync Betriebszustand</h3>
						<p>Gruen ist nicht noetig: entscheidend ist, ob Arbeit wartet, ob Cron faellig ist und ob Fehler oder blockierende Gruende sichtbar sind.</p>
					</div>
				</div>
				<div class="sakuraalbum-status-lanes">
					<div>
						<span>Queue</span>
						<strong>${escapeText(counts.pending || 0)} wartend</strong>
						<small>${escapeText(counts.processing || 0)} in Arbeit, ${escapeText(counts.failed || 0)} fehlerhaft</small>
					</div>
					<div>
						<span>Cron</span>
						<strong>${escapeText(autoNextStartText(autoStatus))}</strong>
						<small>${escapeText(renderBackgroundJobsHealth(autoStatus))}</small>
					</div>
					<div>
						<span>Fenster</span>
						<strong>${escapeText(autoStatus.processingEnabled ? "Offen" : "Wartet")}</strong>
						<small>${escapeText(windowStatusLabel(autoStatus))}</small>
					</div>
					<div>
						<span>Limits</span>
						<strong>${escapeText(autoStatus.maxUsersPerRun || 0)} Benutzer</strong>
						<small>${escapeText(autoStatus.maxEventsPerRun || 0)} Events, ${escapeText(autoStatus.maxRuntimeSeconds || 0)} Sekunden</small>
					</div>
				</div>
			</div>
			<div class="sakuraalbum-inline-help">${escapeText(renderAutoSyncJobHealth(job))}</div>
			<div class="sakuraalbum-inline-help">${escapeText(renderBackgroundJobsHealth(autoStatus))}</div>
			${automationBlockingReason ? `<div class="sakuraalbum-warning">${escapeText(autoAutomationBlockingHint(autoStatus, automationBlockingReason))}</div>` : ""}
			${job && job.stale ? `<div class="sakuraalbum-warning">${escapeText(autoSyncJobDiagnostic(job))}</div>` : ""}
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(autoStatus.enabled ? "Ein" : "Aus")}</strong><br>Automatik</div>
				<div><strong>${escapeText(autoModeText)}</strong><br>Modus</div>
				<div><strong>${escapeText(autoStatus.processingEnabled ? "Offen" : "Wartet")}</strong><br>Fenster</div>
				<div><strong>${escapeText(autoStatus.dueUsers || 0)}</strong><br>Faellige Benutzer</div>
				<div><strong>${escapeText(autoStatus.dueUsersWaitingForWindow || 0)}</strong><br>Warten auf Fenster</div>
				<div><strong>${escapeText(counts.pending || 0)}</strong><br>Wartend</div>
				<div><strong>${escapeText(counts.processing || 0)}</strong><br>In Arbeit</div>
				<div><strong>${escapeText(counts.failed || 0)}</strong><br>Fehler</div>
				<div><strong>${escapeText(autoNextStartText(autoStatus))}</strong><br>Naechster Start</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>Aktive Grenzen:</strong>
				<span>${escapeText(autoStatus.maxUsersPerRun || 0)} Benutzer, ${escapeText(autoStatus.maxEventsPerRun || 0)} Events und ${escapeText(autoStatus.maxRuntimeSeconds || 0)} Sekunden pro Hintergrundlauf; Entprellzeit ${escapeText(autoStatus.debounceSeconds || 0)} Sekunden; Wartungsfenster ${escapeText(windowStatusLabel(autoStatus))}.</span>
			</div>
			${autoStatus.skippedReasons ? `<div class="sakuraalbum-note">${escapeText(autoSkipTextSummary(autoStatus.skippedReasons))}</div>` : ""}
			<div class="sakuraalbum-inline-help">Neue, geaenderte, verschobene, geloeschte oder umbenannte Dateien/Ordner erzeugen nur einen Warteschlangeneintrag fuer den betroffenen Quellordner. Der eigentliche Albumabgleich passiert erst, wenn Cron faellige Eintraege verarbeitet.</div>
			${samples.length === 0 ? '<div class="sakuraalbum-empty">Keine Auto-Sync-Events in der Warteschlange.</div>' : renderAutoQueueTable(samples)}
		`;
    output.focus({preventScroll: true});
  }

  function autoAutomationBlockingHint(autoStatus, reason) {
    const hints = {
      global_disabled: "Automatik ist global deaktiviert. Bitte 'Global aktiv' einschalten und speichern.",
      manual_mode: "Automatik steht auf Manuell. Bitte auf 'Bei Dateiaenderungen' umstellen und speichern.",
      outside_window: "Automatik wartet auf das konfigurierte Wartungsfenster.",
      missing_job_record: "Auto-Sync-Hintergrundjob fehlt in der Job-Tabelle. Nach dem Speichern kurz warten und erneut laden.",
      cron_not_recorded: "Cron-Modus ist aktiv, aber Nextcloud hat noch keinen Cron-Lauf registriert.",
      cron_stale: `Cron lief zuletzt vor etwa ${formatAgeSeconds(autoStatus.backgroundJobsCronAgeSeconds)}. Bitte Host-Cron pruefen.`,
    };

    return hints[reason] || "Automatik ist aktuell blockiert. Siehe Hintergrundjob-Details.";
  }

  function formatAgeSeconds(seconds) {
    if (seconds === null || seconds === undefined || Number.isNaN(seconds)) {
      return "unbekannter Zeit";
    }
    const total = Math.max(0, Math.floor(Number(seconds)));
    const minutes = Math.floor(total / 60);
    if (minutes < 60) {
      return `${minutes} Min`;
    }
    const hours = Math.floor(minutes / 60);
    return `${hours} h ${minutes % 60} min`;
  }

  function formatAdminRouteError(status, path, candidateError) {
    if (status === 401) {
      return `Keine gueltige Admin-Session fuer ${path}. Bitte erneut als Admin anmelden.`;
    }
    if (status === 403) {
      return `Kein Zugriff auf ${path}. Der angemeldete Benutzer hat nicht die noetigen Admin-Rechte.`;
    }
    if (status === 404) {
      return `Auto-Status-Route nicht erreichbar: ${candidateError}. Bitte App-Code, Cache und aktivierte SakuraAlbum-Version pruefen.`;
    }

    return candidateError;
  }

  function renderBackgroundJobsHealth(autoStatus) {
    const mode = autoStatus.backgroundJobsMode || "unknown";
    if (mode === "cron") {
      const ageSeconds = autoStatus.backgroundJobsCronAgeSeconds;
      const last = autoStatus.backgroundJobsLastCronAt;
      const healthy = autoStatus.backgroundJobsCronHealthy === false ? false : true;
      const reason = autoStatus.backgroundJobsCronReason || "";
      if (!healthy) {
        const lastText = last ? ` letzte Ausfuehrung ${formatTime(last)}` : " keine letzte Ausfuehrung";
        if (reason === "cron_not_recorded") {
          return `Hintergrundjobs laufen im Modus ${mode}, aber lastcron ist nicht vorhanden. Bitte System-Cron im Host aktivieren und fuer SakuraAlbum neu starten.`;
        }
        const ageText = ageSeconds !== null ? `${Math.max(0, Math.floor(ageSeconds / 60))} Min` : "unbekannt";
        return `Hintergrundjob-Status ist nicht gesund: letzte Cron-Ausfuehrung ${ageText} her. Bitte Cron/Nextcloud Hintergrundauftraege pruefen.`;
      }
      if (last) {
        const ageText = ageSeconds !== null ? `${Math.max(0, Math.floor(ageSeconds / 60))} Min` : "unbekannt";
        return `Hintergrundjob-Modus ${escapeText(mode)} ist aktiv; letzter Cron vor ${escapeText(ageText)}.`;
      }
      return `Hintergrundjob-Modus ${escapeText(mode)} ist aktiv; cron-Laufzeitdaten sind noch nicht vollstaendig.`;
    }

    return `Hintergrundjobs-Modus: ${escapeText(mode)}. Automatische Ausfuehrung findet im gewaehlten Trigger-Modus statt.`;
  }

  function autoSyncJobDiagnostic(job) {
    if (!job.exists) {
      return "Kein Auto-Sync-Hintergrundjob in oc_jobs gefunden. Das System kann nicht automatisch triggern, bis der Cron-Worker den App-Job registriert hat.";
    }
    if (job.stale) {
      const lastSeen = job.lastCheckedAt || job.lastRunAt;
      const age = lastSeen !== null ? `${Math.max(0, Math.floor((job.now - lastSeen) / 60))} Min` : "unbekannt";
      return `Der Auto-Sync-Hintergrundjob reagiert nicht. Letzte bekannte Aktivitaet vor etwa ${age}. Bitte Cron/Background-Jobs auf dem Server pruefen.`;
    }
    if (job.status === "scheduled") {
      return `Auto-Sync-Hintergrundjob ist geplant. Nächster Check in ${Math.max(0, job.runDueInSeconds || 0)} Sekunden.`;
    }
    if (job.status === "ready_or_overdue") {
      return "Auto-Sync-Hintergrundjob ist fällig. Er sollte beim nächsten Cron-/AJAX-Lauf verarbeitet werden.";
    }
    if (job.status === "running") {
      return "Auto-Sync-Hintergrundjob ist gerade aktiv.";
    }
    if (job.status === "bootstrapped") {
      return "Auto-Sync-Hintergrundjob ist registriert, aber noch nicht regelmaessig ausgefuehrt.";
    }
    return "Auto-Sync-Hintergrundjob-Status ist unklar.";
  }

  function renderAutoSyncJobHealth(job) {
    if (!job.exists) {
      return "Hintergrundjob: nicht gefunden (id: -)";
    }

    const scheduled = job.nextScheduledAt ? ` naechste Pruefung ${formatTime(job.nextScheduledAt)}` : "";
    const lastRun = job.lastRunAt ? `letzter Lauf ${formatTime(job.lastRunAt)}` : "kein Lauf registriert";
    const lastCheck = job.lastCheckedAt ? `letzte Pruefung ${formatTime(job.lastCheckedAt)}` : "keine Pruefung registriert";
    const age = job.jobAgeSeconds !== null ? `${Math.max(0, Math.floor(job.jobAgeSeconds / 60))} Min seit Lauf` : "keine Laufzeit";

    return `Auto-Sync Job ${escapeText(job.jobId || "-")} | ${lastRun} | ${lastCheck}${scheduled} | ${escapeText(age)}.`;
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
			${summary.skippedReasons ? `<div class="sakuraalbum-warning">${escapeText(autoSkipTextSummary(summary.skippedReasons))}</div>` : ""}
			${summary.stoppedReason ? `<div class="sakuraalbum-warning">${escapeText(autoStopText(summary.stoppedReason))}</div>` : ""}
			<div class="sakuraalbum-note">
				<strong>Warteschlange nach dem Lauf</strong>
				<span>${escapeText(((autoStatus.counts || {}).pending) || 0)} wartend, ${escapeText(((autoStatus.counts || {}).processing) || 0)} in Arbeit, ${escapeText(((autoStatus.counts || {}).failed) || 0)} fehlerhaft.</span>
			</div>
			${(autoStatus.samples || []).length === 0 ? '<div class="sakuraalbum-empty">Keine Auto-Sync-Events in der Warteschlange.</div>' : renderAutoQueueTable(autoStatus.samples || [])}
		`;
    output.focus({preventScroll: true});
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
      auto_sync_disabled: "Automatik ist zentral ausgeschaltet.",
      admin_auto_sync_disabled_global: "Automatik ist global ausgeschaltet.",
      admin_auto_sync_disabled: "Automatik ist zentral auf manuell gestellt.",
      admin_auto_sync_mode_manual: "Automatik ist zentral auf manuell gestellt.",
      user_auto_sync_disabled: "SakuraAlbum ist fuer diesen Nutzer nicht aktiv.",
      outside_auto_sync_window: "Der Lauf wartet auf das konfigurierte Wartungsfenster.",
      auto_sync_user_locked: "Ein Nutzer wurde auf Grund einer konkurrierenden Sperre uebersprungen.",
      runtime_limit: "Der Lauf wurde durch das Laufzeitlimit gestoppt.",
    };
    return labels[reason] || reason || "";
  }

  function autoSkipTextSummary(skippedReasons) {
    if (!skippedReasons || typeof skippedReasons !== "object") {
      return "";
    }
    const entries = Object.keys(skippedReasons)
      .filter((reason) => skippedReasons[reason] > 0)
      .map((reason) => {
        const count = skippedReasons[reason];
        const label = autoSkipText(reason);
        return label ? `${label} (${count})` : `${reason} (${count})`;
      });
    return entries.length === 0 ? "" : `Aktuelle Ausloesgrundlage: ${entries.join(", ")}`;
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
    const health = report.health || {};
    const healthSummary = health.summary || {};
    const json = JSON.stringify(report, null, 2);
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(meta.scope || "")}</strong><br>Bereich</div>
				<div><strong>${escapeText(formatTime(meta.createdAt))}</strong><br>Erstellt</div>
				<div><strong>${escapeText(logs.length)}</strong><br>Logs</div>
				<div><strong>${escapeText(counts.pending || 0)}</strong><br>Wartend</div>
				<div><strong>${escapeText(health.status || "unbekannt")}</strong><br>Betrieb</div>
				<div><strong>${escapeText(healthSummary.issueCount || 0)}</strong><br>Befunde</div>
				<div><strong>${report.sendMailReady ? "Ja" : "Nein"}</strong><br>Mail aktiv</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>Redigierter Diagnosebericht</strong>
				<span>Bekannte Geheimnisse werden vor dem Speichern von Log-Kontext redigiert. Der Bereich Betrieb bewertet Queue, Cron, laufende Jobs, Exporte, Cursor und Fehlerlogs automatisch.</span>
			</div>
			${renderHealthIssues(health)}
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

  function renderHealthIssues(health) {
    const issues = (health && health.issues) || [];
    if (!issues.length) {
      return '<div class="sakuraalbum-success"><strong>Betriebsdiagnose unauffaellig</strong><span>Es wurden keine haengenden SakuraAlbum-Jobs, stale Locks, fehlgeschlagenen Exporte oder kritischen Fehlerlogs erkannt.</span></div>';
    }
    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead><tr><th>Schwere</th><th>Befund</th><th>Details</th></tr></thead>
					<tbody>
						${issues
              .map(
                (issue) => `
							<tr>
								<td><span class="sakuraalbum-level sakuraalbum-level-${healthLevelClass(issue.severity || "warning")}">${escapeText(issue.severity || "warning")}</span></td>
								<td>${escapeText(healthIssueText(issue.code || "", issue.message || ""))}</td>
								<td><pre class="sakuraalbum-log-context">${escapeText(JSON.stringify(issue.context || {}, null, 2))}</pre></td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function healthIssueText(code, fallback) {
    const labels = {
      debug_disabled: "Debug-Logging ist ausgeschaltet; fuer Fehleranalyse fehlen Details.",
      auto_sync_global_disabled: "Automatische Albumaktualisierung ist global deaktiviert.",
      auto_sync_manual_mode: "Automatische Albumaktualisierung steht auf manuell.",
      auto_sync_window_closed: "Automatik wartet auf das Wartungsfenster.",
      failed_auto_sync_queue_entries: "Auto-Sync hat fehlgeschlagene Warteschlangeneintraege.",
      stale_auto_sync_processing_lock: "Ein Auto-Sync-Lock haengt zu lange in Verarbeitung.",
      overdue_auto_sync_queue: "Auto-Sync-Arbeit wartet laenger als erwartet.",
      stale_running_sync_run: "Ein SakuraAlbum-Lauf steht zu lange auf laufend.",
      recent_failed_sync_runs: "In den letzten 24 Stunden sind SakuraAlbum-Laeufe fehlgeschlagen.",
      failed_sync_cursors: "Hintergrund-Fortsetzungscursor sind fehlgeschlagen.",
      stale_pending_sync_cursor: "Ein Hintergrundcursor macht laenger keinen Fortschritt.",
      recent_failed_album_exports: "Album-Export-Jobs sind fehlgeschlagen.",
      stale_album_export_job: "Ein Album-Export wartet oder laeuft zu lange.",
      recent_error_logs: "SakuraAlbum hat Fehlerlogs in den letzten 24 Stunden geschrieben.",
      recent_warning_logs: "SakuraAlbum hat Warnlogs in den letzten 24 Stunden geschrieben.",
      missing_managed_photos_albums: "Verwaltete SakuraAlbum-Alben fehlen in Photos und muessen neu aufgebaut werden.",
      nextcloud_cron_not_recorded: "Nextcloud hat keinen Cron-Lauf protokolliert.",
      nextcloud_cron_stale: "Nextcloud Cron ist zu alt; Hintergrundjobs koennen haengen.",
    };
    return labels[code] || fallback || code || "";
  }

  function healthLevelClass(severity) {
    return severity === "critical" ? "error" : levelClass(severity || "warning");
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

  function autoNextStartText(autoStatus) {
    const dueUsers = Number.parseInt((autoStatus && autoStatus.dueUsers) || 0, 10);
    const nextDueAt = autoStatus && autoStatus.nextDueAt ? Number.parseInt(autoStatus.nextDueAt, 10) : 0;
    const job = (autoStatus && autoStatus.job) || {};
    if (dueUsers > 0) {
      return job.nextScheduledAt
        ? `Faellig, Cron ${formatTime(job.nextScheduledAt)}`
        : "Faellig";
    }
    return nextDueAt ? formatTime(nextDueAt) : "";
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
