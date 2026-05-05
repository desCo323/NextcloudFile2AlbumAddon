(function () {
  "use strict";

  const root = document.getElementById("sakuraalbum-admin-settings");
  if (!root) {
    return;
  }

  const state = JSON.parse(root.dataset.settings || "{}");
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
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel">
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
						<label for="ska-job-interval">Job-Intervall Minuten</label>
						<input id="ska-job-interval" type="number" min="5" title="Vorbereitung fuer spaetere Cron-Laeufe." value="${escapeAttr(numberValue(settings.jobIntervalMinutes, 360))}">
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
					<button id="ska-load-logs" type="button" title="Laedt die neuesten SakuraAlbum-Diagnoseeintraege.">Logs laden</button>
			</div>
			<div id="ska-admin-status" class="sakuraalbum-status"></div>
			<div id="ska-log-output"></div>
		`;
    document.getElementById("ska-save-admin").addEventListener("click", save);
    document
      .getElementById("ska-load-logs")
      .addEventListener("click", loadLogs);
  }

  function collect() {
    return {
      enabled: document.getElementById("ska-enabled").checked,
      defaultIncludePaths: readLines("ska-default-includes"),
      defaultExcludePatterns: readLines("ska-default-excludes"),
      maxScanDepth: fieldNumber("ska-max-depth"),
      maxPreviewFolders: fieldNumber("ska-preview-folders"),
      maxPreviewFiles: fieldNumber("ska-preview-files"),
      maxJobFolders: fieldNumber("ska-job-folders"),
      maxJobFiles: fieldNumber("ska-job-files"),
      maxAlbumsPerRun: fieldNumber("ska-job-albums"),
      allowVideos: document.getElementById("ska-allow-videos").checked,
      jobIntervalMinutes: fieldNumber("ska-job-interval"),
      requireBulkDeleteConfirmation:
        document.getElementById("ska-confirm-delete").checked,
      debugMode: document.getElementById("ska-debug-mode").checked,
      debugRetentionDays: fieldNumber("ska-debug-retention"),
      debugMaxContextLength: fieldNumber("ska-debug-context"),
    };
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
      render(data.settings);
      document.getElementById("ska-admin-status").textContent = "Gespeichert.";
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
        OC.generateUrl("/apps/sakuraalbum/api/v1/admin/logs?limit=50"),
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

  render(state);
})();
