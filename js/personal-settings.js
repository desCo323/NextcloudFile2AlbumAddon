(function () {
  "use strict";

  const root = document.getElementById("sakuraalbum-personal-settings");
  if (!root) {
    return;
  }

  let settings = JSON.parse(root.dataset.settings || "{}");
  let effectiveSettings = JSON.parse(root.dataset.effectiveSettings || "{}");
  const adminSettings = JSON.parse(root.dataset.adminSettings || "{}");
  let managedAlbums = [];
  let lastWriteRequest = null;
  let lastDeleteRequest = null;
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

  function number(id) {
    return Number.parseInt(document.getElementById(id).value, 10);
  }

  function render() {
    mount.innerHTML = `
			<div class="sakuraalbum-header">
				<img class="sakuraalbum-mark" src="${OC.imagePath("sakuraalbum", "app.svg")}" alt="">
				<div>
					<h2>SakuraAlbum</h2>
					<p class="sakuraalbum-subtitle">Ordner pruefen, sichere Alben erzeugen und nur SakuraAlbum-verwaltete Alben wieder aufraeumen.</p>
				</div>
			</div>
			${renderEffectiveState()}
			<div class="sakuraalbum-toggle-row">
				<label class="sakuraalbum-toggle">
					<input id="ska-enabled" type="checkbox" title="Aktiviert SakuraAlbum nur fuer dein Konto." ${settings.enabled ? "checked" : ""}>
					Aktiv
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-images" type="checkbox" title="Beruecksichtigt Dateien mit Bild-MIME-Typ." ${settings.includeImages ? "checked" : ""}>
					Bilder
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-videos" type="checkbox" title="Beruecksichtigt Videos, falls der Administrator das erlaubt." ${settings.includeVideos ? "checked" : ""} ${adminSettings.allowVideos ? "" : "disabled"}>
					Videos
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-auto-sync" type="checkbox" title="Aktualisiert deine SakuraAlbum-Alben nach Datei-Aenderungen automatisch, sobald der Administrator diese Funktion erlaubt." ${settings.autoSyncEnabled ? "checked" : ""} ${adminSettings.autoSyncMode === "file_events" ? "" : "disabled"}>
					Automatisch aktuell halten
				</label>
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel">
				<div class="sakuraalbum-field">
					<label for="ska-includes">Ordner</label>
					<textarea id="ska-includes" title="Ein Ordner pro Zeile, relativ zu deinem Dateienbereich.">${escapeText(lines(settings.includePaths))}</textarea>
					<span class="sakuraalbum-field-help">Ein Ordner pro Zeile, zum Beispiel /Photos oder /SakuraAlbumTest. Leer nutzt die Admin-Vorgabe.</span>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-excludes">Ausnahmen</label>
					<textarea id="ska-excludes" title="Ein Ausschluss pro Zeile, zum Beispiel Ordnername, Pfad oder einfaches Muster.">${escapeText(lines(settings.excludePatterns))}</textarea>
					<span class="sakuraalbum-field-help">Ordnernamen, Pfade oder Muster, die nicht in Alben landen sollen. Globale Ausnahmen gelten zusaetzlich.</span>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-template">Albumname</label>
					<select id="ska-template" title="Legt fest, aus welchen Ordnerteilen der Albumname entsteht.">
						<option value="root_relative" ${settings.namingTemplate === "root_relative" ? "selected" : ""}>Hauptordner + Pfad</option>
						<option value="parent_leaf" ${settings.namingTemplate === "parent_leaf" ? "selected" : ""}>Oberordner + Ordner</option>
						<option value="leaf" ${settings.namingTemplate === "leaf" ? "selected" : ""}>Ordner</option>
					</select>
					<span class="sakuraalbum-field-help">Die App speichert die Namensschema-Version mit, damit spaetere Syntaxaenderungen alte Alben nicht ungeplant ueberschreiben.</span>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-separator">Trenner</label>
					<input id="ska-separator" type="text" maxlength="20" title="Trennzeichen zwischen Ordnerteilen im Albumnamen." value="${escapeAttr(settings.separator || " - ")}">
					<span class="sakuraalbum-field-help">Beispiel: Hauptordner - Unterordner. Der Dry-Run zeigt den echten Namen vor dem Schreiben.</span>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-depth">Album-Tiefe</label>
					<input id="ska-depth" type="number" min="0" max="${escapeAttr(adminSettings.maxScanDepth || 20)}" title="Ab dieser Tiefe werden tiefere Unterordner im naechsten Oberalbum gesammelt." value="${escapeAttr(numberValue(settings.albumDepth, 1))}">
					<span class="sakuraalbum-field-help">Hoehere Werte erzeugen feinere Alben. Tiefere Unterordner werden ab der Grenze gesammelt.</span>
				</div>
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-save" class="primary" type="button" title="Speichert deine SakuraAlbum-Einstellungen.">Speichern</button>
				<button id="ska-preview" type="button" title="Zeigt die geplanten Alben ohne Schreibaktion.">Vorschau</button>
				<button id="ska-dry-run" type="button" title="Prueft den echten Schreibplan und erstellt einen Sicherheits-Fingerprint.">Dry-Run</button>
				<button id="ska-write" type="button" title="Schreibt nur den zuletzt sicher geprueften Dry-Run-Plan.">Alben erzeugen</button>
				<button id="ska-runs" type="button" title="Zeigt die letzten SakuraAlbum-Laeufe fuer dein Konto.">Letzte Laeufe</button>
			</div>
			<div class="sakuraalbum-actions sakuraalbum-actions-danger">
				<button id="ska-managed" type="button" title="Listet nur Alben, die SakuraAlbum selbst verwaltet.">Verwaltete Alben</button>
				<button id="ska-delete-preview" type="button" title="Prueft die markierten verwalteten Alben vor dem Loeschen.">Loeschvorschau</button>
				<button id="ska-delete-all-preview" class="sakuraalbum-button-danger" type="button" title="Prueft alle von SakuraAlbum verwalteten Alben innerhalb des Admin-Limits.">Alle verwalteten pruefen</button>
			</div>
			<div id="ska-status" class="sakuraalbum-status"></div>
			<div id="ska-preview-output"></div>
		`;

    document.getElementById("ska-save").addEventListener("click", save);
    document.getElementById("ska-preview").addEventListener("click", preview);
    document.getElementById("ska-dry-run").addEventListener("click", dryRun);
    document.getElementById("ska-write").addEventListener("click", writeAlbums);
    document.getElementById("ska-runs").addEventListener("click", loadRuns);
    document.getElementById("ska-managed").addEventListener("click", loadManagedAlbums);
    document.getElementById("ska-delete-preview").addEventListener("click", deleteDryRunSelected);
    document.getElementById("ska-delete-all-preview").addEventListener("click", deleteDryRunAll);

    if (mount.dataset.planResetBound !== "1") {
      mount.addEventListener("input", resetPreparedPlans);
      mount.addEventListener("change", resetPreparedPlans);
      mount.dataset.planResetBound = "1";
    }
  }

  function renderEffectiveState() {
    const includePaths =
      Array.isArray(effectiveSettings.includePaths) && effectiveSettings.includePaths.length > 0
        ? effectiveSettings.includePaths.join(", ")
        : "(Admin-Vorgabe)";
    return `
			<div class="sakuraalbum-meta-grid">
				<div class="sakuraalbum-meta"><strong>${adminSettings.enabled ? "Freigegeben" : "Zentral gesperrt"}</strong>Admin-Freigabe</div>
				<div class="sakuraalbum-meta"><strong>${settings.enabled ? "Aktiv" : "Aus"}</strong>Dein Konto</div>
				<div class="sakuraalbum-meta"><strong>${autoStateLabel()}</strong>Automatische Aktualisierung</div>
				<div class="sakuraalbum-meta"><strong>${escapeText(includePaths)}</strong>Wirksame Ordner</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>${effectiveSettings.enabled ? "Bereit fuer Vorschau und Dry-Run." : "Noch nicht schreibbereit."}</strong>
				<span>${effectiveStateText()}</span>
			</div>
		`;
  }

  function effectiveStateText() {
    if (!adminSettings.enabled) {
      return "Ein Administrator muss SakuraAlbum zuerst zentral freigeben.";
    }
    if (!settings.enabled) {
      return "Aktiviere SakuraAlbum fuer dein Konto und speichere, bevor Alben geschrieben werden.";
    }
    if (adminSettings.autoSyncMode !== "file_events") {
      return "Automatische Aktualisierung ist zentral auf manuell gestellt; ein Administrator muss sie zuerst freigeben.";
    }
    if (!settings.autoSyncEnabled) {
      return "Automatische Aktualisierung ist vom Administrator erlaubt. Aktiviere den Schalter, wenn deine Alben bei Datei-Aenderungen automatisch nachgezogen werden sollen.";
    }
    return `Deine Alben werden nach Datei-Aenderungen automatisch vorgemerkt und nach mindestens ${escapeText(adminSettings.autoSyncDebounceSeconds || 0)} Sekunden per Hintergrundjob aktualisiert.`;
  }

  function collect() {
    return {
      enabled: document.getElementById("ska-enabled").checked,
      includePaths: readLines("ska-includes"),
      excludePatterns: readLines("ska-excludes"),
      namingTemplate: document.getElementById("ska-template").value,
      separator: document.getElementById("ska-separator").value,
      albumDepth: number("ska-depth"),
      includeImages: document.getElementById("ska-images").checked,
      includeVideos: document.getElementById("ska-videos").checked,
      autoSyncEnabled: document.getElementById("ska-auto-sync").checked,
    };
  }

  async function save() {
    const status = document.getElementById("ska-status");
    status.textContent = "Speichere...";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/user/settings", "PUT", {
        settings: collect(),
      });
      settings = response.settings || settings;
      effectiveSettings = response.effectiveSettings || effectiveSettings;
      lastWriteRequest = null;
      lastDeleteRequest = null;
      render();
      document.getElementById("ska-status").textContent = "Gespeichert.";
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function preview() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Erstelle Vorschau...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/preview", "POST", {
        settings: collect(),
      });
      status.textContent = "Vorschau bereit.";
      renderPreview(response.preview || {});
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function dryRun() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Pruefe geplante Schreibaktion...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/sync/dry-run", "POST", {
        settings: collect(),
      });
      lastWriteRequest =
        response.canWrite === true
          ? { canWrite: true, planFingerprint: response.planFingerprint || "" }
          : null;
      status.textContent =
        response.canWrite === true
          ? "Dry-Run bereit. Dieser exakt gepruefte Plan kann geschrieben werden."
          : "Dry-Run bereit. Schreiben ist aktuell blockiert.";
      renderSyncResult(response);
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function writeAlbums() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    if (!lastWriteRequest || !lastWriteRequest.canWrite || !lastWriteRequest.planFingerprint) {
      status.textContent = "Bitte zuerst einen sicheren Dry-Run erstellen.";
      return;
    }

    const confirmation = window.prompt(
      "Gib CREATE_ALBUMS ein, um genau den zuletzt geprueften Plan zu schreiben.",
    );
    if (confirmation === null) {
      return;
    }

    status.textContent = "Starte Schreibjob...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/sync/write", "POST", {
        confirmation,
        planFingerprint: lastWriteRequest.planFingerprint,
      });
      lastWriteRequest = null;
      status.textContent =
        response.status === "write_completed"
          ? "Schreibjob abgeschlossen."
          : "Schreibjob abgeschlossen, bitte Ergebnis pruefen.";
      renderSyncResult(response);
    } catch (error) {
      status.textContent = `Schreibjob blockiert: ${error.message}`;
    }
  }

  async function loadRuns() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Lade Laeufe...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/sync/runs?limit=10", "GET");
      renderRuns(response.runs || []);
      status.textContent = `${(response.runs || []).length} Laeufe geladen.`;
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function loadManagedAlbums() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Lade verwaltete Alben...";
    output.innerHTML = "";
    lastDeleteRequest = null;
    try {
      const response = await request("/apps/sakuraalbum/api/v1/albums/managed?limit=200", "GET");
      managedAlbums = response.albums || [];
      renderManagedAlbums(response);
      status.textContent = response.truncated
        ? `${managedAlbums.length} von ${response.total || managedAlbums.length} verwalteten Alben geladen.`
        : `${managedAlbums.length} verwaltete Alben geladen.`;
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function deleteDryRunSelected() {
    const ids = selectedManagedIds();
    if (ids.length === 0) {
      document.getElementById("ska-status").textContent =
        "Bitte zuerst verwaltete Alben auswaehlen.";
      return;
    }
    await deleteDryRun(ids, false);
  }

  async function deleteDryRunAll() {
    await deleteDryRun([], true);
  }

  async function deleteDryRun(albumIds, deleteAll) {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Pruefe Loeschaktion...";
    output.innerHTML = "";
    lastDeleteRequest = null;
    try {
      const response = await request("/apps/sakuraalbum/api/v1/albums/delete/dry-run", "POST", {
        albumIds,
        deleteAll,
      });
      lastDeleteRequest = {
        albumIds,
        deleteAll,
        canDelete: response.canDelete === true,
        confirmationText: response.confirmationText || "DELETE_MANAGED_ALBUMS",
        planFingerprint: response.planFingerprint || "",
      };
      status.textContent =
        response.canDelete === true
          ? "Loeschvorschau bereit."
          : "Loeschvorschau bereit. Loeschen ist aktuell blockiert.";
      renderDeleteResult(response);
    } catch (error) {
      status.textContent = `Loeschvorschau blockiert: ${error.message}`;
    }
  }

  async function deleteManagedAlbums() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    if (!lastDeleteRequest || !lastDeleteRequest.canDelete || !lastDeleteRequest.planFingerprint) {
      status.textContent = "Bitte zuerst eine sichere Loeschvorschau erstellen.";
      return;
    }

    const confirmation = window.prompt(
      `Gib ${lastDeleteRequest.confirmationText} ein, um nur diese SakuraAlbum-verwalteten Alben zu loeschen.`,
    );
    if (confirmation === null) {
      return;
    }

    status.textContent = "Starte Loeschjob...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/albums/delete", "POST", {
        albumIds: lastDeleteRequest.albumIds,
        deleteAll: lastDeleteRequest.deleteAll,
        confirmation,
        planFingerprint: lastDeleteRequest.planFingerprint,
      });
      lastDeleteRequest = null;
      status.textContent =
        response.status === "delete_completed"
          ? "Loeschjob abgeschlossen."
          : "Loeschjob abgeschlossen, bitte Ergebnis pruefen.";
      renderDeleteResult(response);
    } catch (error) {
      status.textContent = `Loeschjob blockiert: ${error.message}`;
    }
  }

  function selectedManagedIds() {
    return Array.from(document.querySelectorAll(".ska-managed-check:checked"))
      .map((input) => Number.parseInt(input.value, 10))
      .filter(Number.isFinite);
  }

  function setManagedSelection(selected) {
    Array.from(document.querySelectorAll(".ska-managed-check")).forEach((input) => {
      input.checked = selected;
    });
    updateManagedSelectedCount();
  }

  function updateManagedSelectedCount() {
    const counter = document.getElementById("ska-managed-selected-count");
    if (counter) {
      counter.textContent = String(selectedManagedIds().length);
    }
  }

  function resetPreparedPlans(event) {
    if (!event || !event.target || event.target.classList.contains("ska-managed-check")) {
      return;
    }
    lastWriteRequest = null;
    lastDeleteRequest = null;
  }

  async function request(url, method, body) {
    const options = {
      method,
      headers: {
        "Content-Type": "application/json",
        requesttoken: OC.requestToken,
      },
    };
    if (body !== undefined) {
      options.body = JSON.stringify(body);
    }

    const response = await fetch(OC.generateUrl(url), options);
    if (!response.ok) {
      let message = `HTTP ${response.status}`;
      try {
        const data = await response.json();
        message = data.message || data.error || message;
      } catch (e) {
        // Keep the HTTP status if the response is not JSON.
      }
      throw new Error(message);
    }
    return response.json();
  }

  function renderManagedAlbums(response) {
    const output = document.getElementById("ska-preview-output");
    const albums = response.albums || [];
    if (albums.length === 0) {
      output.innerHTML =
        '<div class="sakuraalbum-empty">Keine von SakuraAlbum verwalteten Alben vorhanden.</div>';
      return;
    }

    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(response.total || albums.length)}</strong><br>Verwaltet</div>
				<div><strong>${escapeText(response.limit || albums.length)}</strong><br>Anzeige-Limit</div>
				<div><strong>${response.truncated ? "Ja" : "Nein"}</strong><br>Gekuerzt</div>
				<div><strong id="ska-managed-selected-count">0</strong><br>Ausgewaehlt</div>
			</div>
			<div class="sakuraalbum-inline-help">Loeschen ist auf SakuraAlbum-verwaltete Alben beschraenkt. Fremde oder umbenannte Alben werden blockiert.</div>
			<div class="sakuraalbum-actions sakuraalbum-managed-actions">
				<button id="ska-managed-select-all" type="button" title="Markiert alle aktuell angezeigten verwalteten Alben.">Alle auswaehlen</button>
				<button id="ska-managed-clear" type="button" title="Hebt die Auswahl auf.">Auswahl aufheben</button>
				<button id="ska-managed-selection-preview" class="sakuraalbum-button-danger" type="button" title="Erstellt eine sichere Loeschvorschau fuer die ausgewaehlten Alben.">Loeschvorschau fuer Auswahl</button>
				<button id="ska-managed-all-preview" class="sakuraalbum-button-danger" type="button" title="Prueft alle verwalteten Alben bis zum Admin-Limit.">Alle verwalteten pruefen</button>
			</div>
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead>
						<tr>
							<th>Auswahl</th>
							<th>Album</th>
							<th>Zielpfad</th>
							<th>Medien</th>
							<th>Status</th>
							<th>Letzter Lauf</th>
						</tr>
					</thead>
					<tbody>
						${albums
              .map(
                (album) => `
							<tr>
								<td><input class="ska-managed-check" type="checkbox" value="${escapeAttr(album.managedId)}"></td>
								<td>${escapeText(album.albumName)}</td>
								<td>${escapeText(album.targetPath)}</td>
								<td>${escapeText(album.mediaCount)}</td>
								<td>${escapeText(album.status)}</td>
								<td>${escapeText(formatTime(album.lastSyncAt))}</td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
    document.getElementById("ska-managed-select-all").addEventListener("click", () => setManagedSelection(true));
    document.getElementById("ska-managed-clear").addEventListener("click", () => setManagedSelection(false));
    document.getElementById("ska-managed-selection-preview").addEventListener("click", deleteDryRunSelected);
    document.getElementById("ska-managed-all-preview").addEventListener("click", deleteDryRunAll);
    Array.from(document.querySelectorAll(".ska-managed-check")).forEach((input) =>
      input.addEventListener("change", updateManagedSelectedCount),
    );
    updateManagedSelectedCount();
  }

  function renderDeleteResult(result) {
    const output = document.getElementById("ska-preview-output");
    const summary = result.summary || {};
    const issues = result.deleteBlockedReasons || [];
    const albums = result.albums || [];
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(summary.plannedAlbums || 0)}</strong><br>Geprueft</div>
				<div><strong>${escapeText(summary.wouldDeletePhotosAlbums || 0)}</strong><br>Photos-Alben</div>
				<div><strong>${escapeText(summary.wouldCleanupTrackingRecords || 0)}</strong><br>Tracking</div>
				<div><strong>${escapeText(summary.blockedAlbums || 0)}</strong><br>Blockiert</div>
				<div><strong>${escapeText(summary.deletedPhotosAlbums || 0)}</strong><br>Geloescht</div>
				<div><strong>${escapeText(summary.cleanedTrackingRecords || 0)}</strong><br>Bereinigt</div>
			</div>
			${issues.map((issue) => `<div class="sakuraalbum-warning">${escapeText(humanDeleteIssueText(issue))}</div>`).join("")}
			${result.canDelete ? renderDeleteConfirm(result) : ""}
			${renderDeleteTable(albums)}
		`;
    const deleteConfirm = document.getElementById("ska-delete-confirm");
    if (deleteConfirm) {
      deleteConfirm.addEventListener("click", deleteManagedAlbums);
    }
  }

  function renderDeleteConfirm(result) {
    return `
			<div class="sakuraalbum-confirm-panel">
				<strong>Bereit zum Loeschen</strong>
				<span>Es werden nur Alben geloescht, die SakuraAlbum eindeutig als verwaltet erkennt. Die Bestaetigung lautet ${escapeText(result.confirmationText || "DELETE_MANAGED_ALBUMS")}.</span>
				<div class="sakuraalbum-actions"><button id="ska-delete-confirm" class="sakuraalbum-button-danger" type="button">Jetzt verwaltete Alben loeschen</button></div>
			</div>
		`;
  }

  function renderDeleteTable(albums) {
    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead>
						<tr>
							<th>Album</th>
							<th>Zielpfad</th>
							<th>Medien</th>
							<th>Aktion</th>
							<th>Grund</th>
						</tr>
					</thead>
					<tbody>
						${albums
              .map(
                (album) => `
							<tr>
								<td>${escapeText(album.albumName)}</td>
								<td>${escapeText(album.targetPath)}</td>
								<td>${escapeText(album.mediaCount)}</td>
								<td><span class="sakuraalbum-action">${escapeText(deleteActionLabel(album.deleteAction))}</span></td>
								<td>${escapeText(blockReasonLabel(album.blockReason))}</td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function renderPreview(preview) {
    const output = document.getElementById("ska-preview-output");
    const summary = preview.summary || {};
    const warnings = preview.warnings || [];
    const albums = preview.albums || [];
    output.innerHTML = `
			${renderPlanSummary(summary)}
			${warnings.map((warning) => `<div class="sakuraalbum-warning">${escapeText(humanWarningText(warning))}</div>`).join("")}
			${renderAlbumTable(albums, (album) => (album.collision ? "Konflikt" : album.aggregated ? "Aggregiert" : "OK"))}
		`;
  }

  function renderSyncResult(result) {
    const output = document.getElementById("ska-preview-output");
    const summary = result.summary || {};
    const issues = result.writeBlockedReasons || [];
    const warnings = result.warnings || [];
    const albums = result.albums || [];
    const isWriteResult = String(result.status || "").startsWith("write_");
    output.innerHTML = `
			${renderPlanSummary(summary)}
			<div class="sakuraalbum-summary">
				<div><strong>${summary.createdAlbums || 0}</strong><br>Erstellt</div>
				<div><strong>${summary.updatedManagedAlbums || 0}</strong><br>Aktualisiert</div>
				<div><strong>${summary.linkedFiles || 0}</strong><br>Verknuepft</div>
				<div><strong>${summary.alreadyLinkedFiles || 0}</strong><br>Bereits drin</div>
				<div><strong>${summary.removedFiles || 0}</strong><br>Entfernt</div>
				<div><strong>${summary.fileErrors || 0}</strong><br>Dateifehler</div>
			</div>
			${isWriteResult ? '<div class="sakuraalbum-success">Schreibjob fertig. Aktualisierte Alben erscheinen in Fotos unter Alben; falls die Ansicht offen war, einmal neu laden.</div>' : ""}
			${issues.map((issue) => `<div class="sakuraalbum-warning">${escapeText(humanIssueText(issue))}</div>`).join("")}
			${warnings.map((warning) => `<div class="sakuraalbum-warning">${escapeText(humanWarningText(warning))}</div>`).join("")}
			${renderAlbumTable(albums, (album) => actionLabel(album.writeAction, result.status))}
		`;
  }

  function renderPlanSummary(summary) {
    return `
			<div class="sakuraalbum-summary">
				<div><strong>${summary.plannedAlbums || 0}</strong><br>Geplante Alben</div>
				<div><strong>${summary.plannedLinks || 0}</strong><br>Geplante Links</div>
				<div><strong>${summary.mediaFiles || 0}</strong><br>Medien</div>
				<div><strong>${summary.foldersScanned || 0}</strong><br>Ordner</div>
				<div><strong>${summary.collisions || 0}</strong><br>Konflikte</div>
				<div><strong>${summary.safetyIssueCount || 0}</strong><br>Blocker</div>
			</div>
		`;
  }

  function renderAlbumTable(albums, action) {
    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead>
						<tr>
							<th>Album</th>
							<th>Zielpfad</th>
							<th>Medien</th>
							<th>Aktion</th>
						</tr>
					</thead>
					<tbody>
						${albums
              .map(
                (album) => `
							<tr>
								<td>${escapeText(album.albumName)}</td>
								<td>${escapeText(album.targetPath)}</td>
								<td>${escapeText(album.mediaCount)}</td>
								<td><span class="sakuraalbum-action">${escapeText(action(album))}</span></td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function renderRuns(runs) {
    const output = document.getElementById("ska-preview-output");
    if (runs.length === 0) {
      output.innerHTML = '<div class="sakuraalbum-empty">Noch keine Laeufe vorhanden.</div>';
      return;
    }
    output.innerHTML = `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead>
						<tr>
							<th>Zeit</th>
							<th>Typ</th>
							<th>Status</th>
							<th>Alben</th>
							<th>Links</th>
							<th>Bereits drin</th>
							<th>Fehler</th>
						</tr>
					</thead>
					<tbody>
						${runs
              .map(
                (run) => `
							<tr>
								<td>${escapeText(formatTime(run.startedAt))}</td>
								<td>${escapeText(run.runType)}</td>
								<td>${escapeText(run.status)}</td>
								<td>${escapeText((run.summary && run.summary.plannedAlbums) || 0)}</td>
								<td>${escapeText((run.summary && run.summary.linkedFiles) || run.summary && run.summary.plannedLinks || 0)}</td>
								<td>${escapeText((run.summary && run.summary.alreadyLinkedFiles) || 0)}</td>
								<td>${escapeText((run.summary && (run.summary.fileErrors || run.summary.albumErrors)) || 0)}</td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function actionLabel(action, status) {
    const writeDone = String(status || "").startsWith("write_");
    const labels = {
      create: writeDone ? "Erstellt" : "Wuerde erstellen",
      update_managed: writeDone ? "Verwaltetes Album aktualisiert" : "Verwaltetes Album aktualisieren",
      blocked_existing_album: "Blockiert: existiert bereits",
      blocked_collision: "Blockiert: Namenskonflikt",
    };
    return labels[action] || "Pruefen";
  }

  function deleteActionLabel(action) {
    const labels = {
      delete_photos_album: "Photos-Album loeschen",
      cleanup_tracking_only: "Tracking bereinigen",
      blocked: "Blockiert",
    };
    return labels[action] || "Pruefen";
  }

  function blockReasonLabel(reason) {
    const labels = {
      no_photos_album_id: "Keine Photos-ID gespeichert",
      photos_album_missing: "Photos-Album nicht mehr vorhanden",
      photos_album_owner_mismatch: "Eigentuemer passt nicht",
      photos_album_name_mismatch: "Album wurde umbenannt",
    };
    return labels[reason] || "";
  }

  function humanIssueText(issue) {
    const code = issue.code || "";
    const warningCode = issue.warningCode || "";
    if (code === "plan_truncated") {
      return "Der Scan wurde durch ein Admin-Limit gestoppt. Es wird nichts geschrieben, bis die Vorschau vollstaendig ist.";
    }
    if (code === "blocking_warning" && warningCode === "max_files_reached") {
      return `Das Dateilimit wurde erreicht${issue.path ? ` bei ${issue.path}` : ""}. Bitte Ordner enger waehlen oder den Administrator um ein hoeheres Job-Dateilimit bitten.`;
    }
    if (code === "blocking_warning" && warningCode === "max_folders_reached") {
      return "Das Ordnerlimit wurde erreicht. Bitte weniger Quellordner waehlen oder das Admin-Limit erhoehen.";
    }
    if (code === "blocking_warning" && warningCode === "missing_include_path") {
      return `Ein Quellordner fehlt${issue.path ? `: ${issue.path}` : ""}.`;
    }
    if (code === "write_disabled") {
      return "SakuraAlbum muss zentral und fuer dieses Konto aktiv sein, bevor Alben geschrieben werden.";
    }
    if (code === "existing_unmanaged_albums") {
      return "Mindestens ein Album mit gleichem Namen existiert bereits und wird nicht von SakuraAlbum verwaltet.";
    }
    if (code === "album_name_collisions") {
      return "Mehrere geplante Alben wuerden denselben Namen bekommen. Bitte Namen, Trenner oder Tiefe aendern.";
    }
    return `${code || "Blockiert"} ${warningCode || issue.message || issue.count || ""}`.trim();
  }

  function humanWarningText(warning) {
    const code = warning.code || "";
    if (code === "max_files_reached") {
      return `Dateilimit erreicht: maximal ${warning.limit || ""} Dateien wurden geprueft. Der Schreibjob bleibt blockiert.`;
    }
    if (code === "max_folders_reached") {
      return `Ordnerlimit erreicht: maximal ${warning.limit || ""} Ordner wurden geprueft.`;
    }
    if (code === "missing_include_path") {
      return `Quellordner nicht gefunden: ${warning.path || ""}`;
    }
    if (code === "include_path_not_folder") {
      return `Quellpfad ist kein Ordner: ${warning.path || ""}`;
    }
    if (code === "media_marker_skip") {
      return `Ordner wegen .nomedia/.noimage uebersprungen: ${warning.path || ""}`;
    }
    if (code === "storage_unavailable") {
      return `Speicher nicht erreichbar: ${warning.path || ""}`;
    }
    return `${code || "Hinweis"} ${warning.path || warning.message || ""}`.trim();
  }

  function humanDeleteIssueText(issue) {
    const code = issue.code || "";
    if (code === "no_deletable_managed_albums") {
      return "Es wurden keine aktiven SakuraAlbum-Alben fuer diese Auswahl gefunden.";
    }
    if (code === "delete_plan_truncated") {
      return `Die Loeschvorschau ist durch das Admin-Albumlimit begrenzt${issue.maxAlbums ? ` (${issue.maxAlbums})` : ""}.`;
    }
    if (code === "managed_album_selection_missing") {
      return `${issue.count || 0} ausgewaehlte Alben sind nicht mehr als verwaltet vorhanden.`;
    }
    if (code === "managed_album_delete_blocked") {
      return `${issue.count || 0} Alben wurden blockiert, weil sie nicht mehr eindeutig zu SakuraAlbum passen.`;
    }
    return `${code || "Blockiert"} ${issue.message || issue.count || ""}`.trim();
  }

  function autoStateLabel() {
    if (adminSettings.autoSyncMode !== "file_events") {
      return "Zentral manuell";
    }
    return settings.autoSyncEnabled ? "Aktiv" : "Aus";
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
    const parsed = Number.parseInt(value, 10);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  render();
})();
