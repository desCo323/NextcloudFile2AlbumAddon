(function () {
  "use strict";

  const root = document.getElementById("sakuraalbum-personal-settings");
  if (!root) {
    return;
  }

  let settings = JSON.parse(root.dataset.settings || "{}");
  let effectiveSettings = JSON.parse(root.dataset.effectiveSettings || "{}");
  let adminSettings = JSON.parse(root.dataset.adminSettings || "{}");
  let sourceFolders = normalizeSourceFolders(
    settings.sourceFolders,
    settings.includePaths,
    effectiveSettings.sourceFolders,
  );
  let managedAlbums = [];
  let folderBrowser = null;
  let folderBrowserOpen = false;
  let folderBrowserPath = "/";
  let syncStatus = null;
  let lastWriteRequest = null;
  let lastDeleteRequest = null;
  let lastResetRequest = null;
  let statusInterval = null;
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
					<p class="sakuraalbum-subtitle">Ordner auswaehlen, Alben automatisch aktuell halten und SakuraAlbum-verwaltete Alben sicher aufraeumen.</p>
				</div>
			</div>
			${renderEffectiveState()}
			${renderAutoSetupPanel()}
			<div id="ska-live-status-wrap">${renderLiveStatus()}</div>
			<div class="sakuraalbum-toggle-row">
				<label class="sakuraalbum-toggle">
					<input id="ska-enabled" type="checkbox" title="Aktiviert SakuraAlbum nur fuer dein Konto." ${settings.enabled ? "checked" : ""}>
					SakuraAlbum verwenden
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
			<div class="sakuraalbum-panel">
				<div class="sakuraalbum-panel-head">
					<div>
						<h3>Standard fuer Quellordner</h3>
						<p>Diese Regeln gelten fuer alle Quellordner, ausser ein Ordner hat unten eine eigene Regel.</p>
					</div>
				</div>
				<div class="sakuraalbum-grid">
					<div class="sakuraalbum-field">
						<label for="ska-template">Albumname</label>
						<select id="ska-template" title="Legt fest, aus welchen Ordnerteilen der Albumname entsteht.">
							<option value="root_relative" ${settings.namingTemplate === "root_relative" ? "selected" : ""}>Hauptordner + Pfad</option>
							<option value="parent_leaf" ${settings.namingTemplate === "parent_leaf" ? "selected" : ""}>Oberordner + Ordner</option>
							<option value="leaf" ${settings.namingTemplate === "leaf" ? "selected" : ""}>Ordner</option>
						</select>
						<span class="sakuraalbum-field-help">Die App speichert die Namensschema-Version, damit spaetere Syntaxaenderungen alte Alben nicht ungeplant ueberschreiben.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-separator">Trenner</label>
						<input id="ska-separator" type="text" maxlength="20" title="Trennzeichen zwischen Ordnerteilen im Albumnamen." value="${escapeAttr(settings.separator || " - ")}">
						<span class="sakuraalbum-field-help">Der Dry-Run zeigt den echten Albumnamen vor dem Schreiben.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-depth">Album-Tiefe</label>
						<input id="ska-depth" type="number" min="0" max="${escapeAttr(adminSettings.maxScanDepth || 20)}" title="Ab dieser Tiefe werden tiefere Unterordner im naechsten Oberalbum gesammelt." value="${escapeAttr(numberValue(settings.albumDepth, 1))}">
						<span class="sakuraalbum-field-help">0 bedeutet: alle Unterordner eines Quellordners landen in einem Album.</span>
					</div>
					<div class="sakuraalbum-field">
						<label for="ska-excludes">Ausnahmen</label>
						<textarea id="ska-excludes" title="Ein Ausschluss pro Zeile, zum Beispiel Ordnername, Pfad oder einfaches Muster.">${escapeText(lines(settings.excludePatterns))}</textarea>
						<span class="sakuraalbum-field-help">Ordnernamen, Pfade oder Muster, die nie in Alben landen sollen. Globale Ausnahmen gelten zusaetzlich.</span>
					</div>
				</div>
			</div>
			<div class="sakuraalbum-panel sakuraalbum-source-panel">
				<div class="sakuraalbum-panel-head">
					<div>
						<h3>Quellordner</h3>
						<p>Mehrere Ordner sind moeglich. Verschachtelte Quellordner werden blockiert, damit keine doppelten oder widerspruechlichen Alben entstehen.</p>
					</div>
					<button id="ska-folder-open" type="button" title="Ordner aus deinem Dateienbereich auswaehlen.">Ordner hinzufuegen</button>
				</div>
				${renderSourceWarnings()}
				${renderSourceRuleGuide()}
				${renderSourceTable()}
				${folderBrowserOpen ? renderFolderBrowser() : ""}
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-save" class="primary" type="button" title="Speichert deine SakuraAlbum-Einstellungen und merkt bei aktiver Automatik einen Hintergrundlauf vor.">Speichern und Hintergrundlauf vormerken</button>
				<button id="ska-enable-auto-save" type="button" title="Aktiviert SakuraAlbum und automatische Aktualisierung fuer dein Konto, wenn der Administrator Automatik erlaubt.">Automatik einschalten</button>
				<button id="ska-queue-update" type="button" title="Merkt deine Quellordner fuer den naechsten Hintergrundlauf vor.">Update vormerken</button>
				<button id="ska-preview" type="button" title="Zeigt die geplanten Alben ohne Schreibaktion.">Vorschau</button>
				<button id="ska-runs" type="button" title="Zeigt die letzten SakuraAlbum-Laeufe fuer dein Konto.">Letzte Laeufe</button>
				<button id="ska-diagnostic-report" type="button" title="Bereitet einen redigierten Fehlerbericht fuer spaeteren Mailversand vor.">Fehlerbericht vorbereiten</button>
			</div>
			<details class="sakuraalbum-details sakuraalbum-advanced-tools">
				<summary>Erweiterte manuelle Testfunktionen</summary>
				<div class="sakuraalbum-inline-help">Normale Nutzung laeuft ueber den Hintergrundjob. Diese Funktionen sind fuer kontrollierte Tests und schreiben nur nach Dry-Run, Fingerprint und exakter Textbestaetigung.</div>
				<div class="sakuraalbum-actions">
					<button id="ska-dry-run" type="button" title="Prueft den echten Schreibplan und erstellt einen Sicherheits-Fingerprint.">Dry-Run</button>
					<button id="ska-write" type="button" title="Schreibt nur den zuletzt sicher geprueften Dry-Run-Plan.">Manuell schreiben</button>
				</div>
			</details>
			<div class="sakuraalbum-actions sakuraalbum-actions-danger">
				<button id="ska-managed" type="button" title="Listet nur Alben, die SakuraAlbum selbst verwaltet.">Verwaltete Alben</button>
				<button id="ska-delete-preview" type="button" title="Prueft die markierten verwalteten Alben vor dem Loeschen.">Loeschvorschau</button>
				<button id="ska-delete-all-preview" class="sakuraalbum-button-danger" type="button" title="Prueft alle von SakuraAlbum verwalteten Alben innerhalb des Admin-Limits.">Alle verwalteten pruefen</button>
				<button id="ska-reset-preview" class="sakuraalbum-button-danger" type="button" title="Prueft einen vollstaendigen SakuraAlbum-Reset fuer dein Konto.">Konto-Reset pruefen</button>
			</div>
			<div id="ska-status" class="sakuraalbum-status"></div>
			<div id="ska-preview-output"></div>
		`;

    bindMainActions();
    bindSourceActions();
    bindFolderBrowserActions();
    bindStatusActions();
  }

  function renderEffectiveState() {
    const sources =
      Array.isArray(effectiveSettings.sourceFolders) && effectiveSettings.sourceFolders.length > 0
        ? effectiveSettings.sourceFolders
            .filter((source) => source.enabled !== false)
            .map((source) => source.path)
            .join(", ")
        : sourceFolders
            .filter((source) => source.enabled !== false)
            .map((source) => source.path)
            .join(", ");
    return `
			<div class="sakuraalbum-meta-grid">
				<div class="sakuraalbum-meta"><strong>${adminReleaseLabel()}</strong>Admin-Freigabe</div>
				<div class="sakuraalbum-meta"><strong>${settings.enabled ? "Aktiv" : "Aus"}</strong>Dein Konto</div>
				<div class="sakuraalbum-meta"><strong>${autoStateLabel()}</strong>Automatische Aktualisierung</div>
				<div class="sakuraalbum-meta"><strong>${escapeText(sources || "(Admin-Vorgabe)")}</strong>Wirksame Quellordner</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>${effectiveSettings.enabled ? "Bereit fuer Vorschau und Hintergrundlauf." : "Noch nicht schreibbereit."}</strong>
				<span>${effectiveStateText()}</span>
			</div>
		`;
  }

  function effectiveStateText() {
    if (!adminSettings.enabled) {
      return "Ein Administrator muss SakuraAlbum zuerst zentral freigeben.";
    }
    if (effectiveSettings.adminGroupAllowed === false) {
      return "Dein Konto ist nicht in einer vom Administrator freigegebenen SakuraAlbum-Gruppe.";
    }
    if (!settings.enabled) {
      return "Aktiviere SakuraAlbum fuer dein Konto und speichere. Danach wird die Erstgenerierung im Hintergrund vorgemerkt, wenn Automatik erlaubt ist.";
    }
    if (adminSettings.autoSyncMode !== "file_events") {
      return "Automatische Aktualisierung ist zentral auf manuell gestellt; ein Administrator muss sie zuerst freigeben.";
    }
    if (!settings.autoSyncEnabled) {
      return "Automatische Aktualisierung ist vom Administrator erlaubt. Aktiviere den Schalter, damit neue, verschobene, geloeschte oder umbenannte Dateien und Ordner Alben automatisch nachziehen.";
    }
    return `Dateiaenderungen werden gesammelt, entprellt und nach mindestens ${escapeText(adminSettings.autoSyncDebounceSeconds || 0)} Sekunden per Hintergrundjob verarbeitet.`;
  }

  function renderAutoSetupPanel() {
    const allowed = adminSettings.autoSyncMode === "file_events";
    const active = allowed && settings.enabled && settings.autoSyncEnabled;
    const triggerText = allowed
      ? "Neue, geaenderte, verschobene, geloeschte oder umbenannte Dateien und Ordner in deinen Quellordnern merken ein Update vor."
      : "Automatik ist zentral noch nicht freigegeben; Vorschau und manuelle Testfunktionen bleiben moeglich.";
    const nextAction = active
      ? "Aenderungen werden nach Entprellzeit und Cron-Lauf in kleinen Hintergrundstuecken verarbeitet."
      : allowed
        ? "Aktiviere SakuraAlbum und Automatisch aktuell halten, speichere danach, dann startet die Erstgenerierung im Hintergrund."
        : "Bitte einen Administrator, Automatik auf Bei Dateiaenderungen zu stellen.";

    return `
			<div class="sakuraalbum-automation-card ${active ? "sakuraalbum-automation-card-active" : ""}">
				<div>
					<strong>${escapeText(active ? "Automatische Albumaktualisierung aktiv" : "Automatische Albumaktualisierung nicht aktiv")}</strong>
					<span>${escapeText(triggerText)}</span>
					<span>${escapeText(nextAction)}</span>
				</div>
				<div class="sakuraalbum-mini-table">
					<div><span>Status</span><strong>${escapeText(autoStateLabel())}</strong></div>
					<div><span>Wartezeit</span><strong>${escapeText(adminSettings.autoSyncDebounceSeconds || 0)} s</strong></div>
					<div><span>Quelle</span><strong>${escapeText(activeSourceCount())}</strong></div>
					<div><span>Chunklimit</span><strong>${escapeText(adminSettings.maxJobFiles || 0)}</strong></div>
					<div><span>Fenster</span><strong>${escapeText(windowLabel(adminSettings))}</strong></div>
					<div><span>Quote</span><strong>${escapeText(quotaLabel(adminSettings))}</strong></div>
				</div>
			</div>
		`;
  }

  function renderLiveStatus() {
    const runs = (syncStatus && syncStatus.runs) || [];
    const queue = (syncStatus && syncStatus.queue) || {};
    const cursor = (syncStatus && syncStatus.cursor && syncStatus.cursor.current) || null;
    const latest = runs[0] || null;
    const summary = (latest && latest.summary) || {};
    const pending = (queue.counts && queue.counts.pending) || 0;
    const processing = (queue.counts && queue.counts.processing) || 0;
    const failed = (queue.counts && queue.counts.failed) || 0;
    const percent = progressPercent(latest, queue, cursor);
    const label = progressLabel(latest, queue);

    return `
			<div class="sakuraalbum-progress-panel">
				<div class="sakuraalbum-progress-head">
					<strong>${escapeText(label)}</strong>
					<span>${escapeText(percent)}%</span>
				</div>
				<div class="sakuraalbum-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${escapeAttr(percent)}">
					<div class="sakuraalbum-progress-fill" style="width: ${escapeAttr(percent)}%"></div>
				</div>
				<div class="sakuraalbum-progress-meta">
					<span>${escapeText(pending)} wartend</span>
					<span>${escapeText(processing)} in Arbeit</span>
					<span>${escapeText(failed)} fehlerhaft</span>
					<span>${cursor ? `${escapeText(cursor.chunkCount || 0)} Hintergrund-Teilstuecke` : "Noch kein Hintergrund-Cursor"}</span>
					<span>${queue.nextDueAt ? `Naechster Lauf fruehestens ${escapeText(formatTime(queue.nextDueAt))}` : "Kein Lauf vorgemerkt"}</span>
					<span>${escapeText(queueWindowLabel(queue))}</span>
				</div>
				<details class="sakuraalbum-details">
					<summary>Details zur aktuellen Aktivitaet</summary>
					<div class="sakuraalbum-detail-grid">
						<div>
							<strong>Aktueller Lauf</strong>
							${latest ? renderRunMini(latest) : '<div class="sakuraalbum-empty">Noch kein Lauf sichtbar.</div>'}
						</div>
						<div>
							<strong>Warteschlange</strong>
							${renderQueueSamples(queue.samples || [])}
						</div>
					</div>
					${cursor ? renderCursorDetails(cursor) : ""}
					${summary.progressStage ? `<div class="sakuraalbum-inline-help">Phase: ${escapeText(progressStageLabel(summary.progressStage))}</div>` : ""}
				</details>
			</div>
		`;
  }

  function renderRunMini(run) {
    const summary = run.summary || {};
    return `
			<div class="sakuraalbum-mini-table">
				<div><span>Status</span><strong>${escapeText(run.status || "")}</strong></div>
				<div><span>Alben</span><strong>${escapeText(summary.processedAlbums || 0)} / ${escapeText(summary.plannedWritableAlbums || summary.plannedAlbums || 0)}</strong></div>
				<div><span>Links</span><strong>${escapeText(summary.processedLinks || summary.linkedFiles || 0)} / ${escapeText(summary.plannedWritableLinks || summary.plannedLinks || 0)}</strong></div>
				<div><span>Fehler</span><strong>${escapeText((summary.fileErrors || 0) + (summary.albumErrors || 0))}</strong></div>
			</div>
		`;
  }

  function renderQueueSamples(samples) {
    if (!samples.length) {
      return '<div class="sakuraalbum-empty">Keine vorgemerkten Aenderungen.</div>';
    }
    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table">
					<thead><tr><th>Ordner</th><th>Status</th><th>Aenderungen</th><th>Letzte Meldung</th></tr></thead>
					<tbody>
						${samples
              .map(
                (sample) => `
							<tr>
								<td>${escapeText(sample.path || "/")}</td>
								<td>${escapeText(queueStatusText(sample.status || ""))}</td>
								<td>${escapeText(sample.changeCount || 0)}</td>
								<td>${escapeText(sample.lastError || eventTypeText(sample.eventType || ""))}</td>
							</tr>
						`,
              )
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function renderCursorDetails(cursor) {
    return `
			<div class="sakuraalbum-inline-help">
				Hintergrund-Fortsetzung: Status ${escapeText(cursorStatusText(cursor.status || ""))}, ca. ${escapeText(cursor.estimatedProgressPercent || 0)}%, ${escapeText(cursor.processedFiles || 0)} verarbeitete Medienlinks, ${escapeText(cursor.processedAlbums || 0)} Album-Schritte, letzter Cursor ${escapeText(cursor.cursorPath || "Start")}.
			</div>
		`;
  }

  function renderSourceWarnings() {
    const warnings = sourceConflictWarnings();
    if (!warnings.length) {
      return "";
    }
    return warnings
      .map((warning) => `<div class="sakuraalbum-warning">${escapeText(warning)}</div>`)
      .join("");
  }

  function renderSourceRuleGuide() {
    return `
			<div class="sakuraalbum-rule-guide">
				<div>
					<strong>Standard</strong>
					<span>Uebernimmt Album-Tiefe, Namensschema und Trenner aus dem Bereich darueber.</span>
				</div>
				<div>
					<strong>Eigene Tiefe</strong>
					<span>Nur dieser Quellordner bekommt eine eigene Tiefe; andere Regeln bleiben Standard, wenn leer.</span>
				</div>
				<div>
					<strong>Alles in ein Album</strong>
					<span>Alle Medien aus diesem Ordner und allen Unterordnern werden in einem Album gesammelt.</span>
				</div>
			</div>
		`;
  }

  function renderSourceTable() {
    if (!sourceFolders.length) {
      return '<div class="sakuraalbum-empty">Noch keine Quellordner ausgewaehlt. Ohne Auswahl nutzt SakuraAlbum die Admin-Vorgabe.</div>';
    }

    return `
			<div class="sakuraalbum-table-wrap">
				<table class="sakuraalbum-preview-table sakuraalbum-source-table">
					<thead>
						<tr>
							<th>Aktiv</th>
							<th>Ordner</th>
							<th>Regel</th>
							<th>Tiefe</th>
							<th>Name</th>
							<th>Trenner</th>
							<th>Aktion</th>
						</tr>
					</thead>
					<tbody>
						${sourceFolders.map(renderSourceRow).join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function renderSourceRow(source) {
    const mode = source.mode || "default";
    const customDepth = mode === "depth";
    return `
			<tr data-source-id="${escapeAttr(source.id)}">
				<td><input class="ska-source-enabled" type="checkbox" ${source.enabled === false ? "" : "checked"} title="Diesen Quellordner verwenden."></td>
				<td><strong>${escapeText(source.path)}</strong>${source.usesDefaultRules ? '<br><span class="sakuraalbum-field-help">Standardregeln</span>' : ""}</td>
				<td>
					<select class="ska-source-mode" title="Ordnerregel fuer diesen Quellordner.">
						<option value="default" ${mode === "default" ? "selected" : ""}>Standard verwenden</option>
						<option value="depth" ${mode === "depth" ? "selected" : ""}>Eigene Tiefe setzen</option>
						<option value="single_album" ${mode === "single_album" ? "selected" : ""}>Alles in ein Album</option>
					</select>
					<br><span class="sakuraalbum-field-help">${escapeText(sourceModeHelp(mode))}</span>
				</td>
				<td><input class="ska-source-depth" type="number" min="0" max="${escapeAttr(adminSettings.maxScanDepth || 20)}" value="${escapeAttr(numberValue(source.albumDepth, 1))}" ${customDepth ? "" : "disabled"} title="Eigene Tiefe nur fuer diesen Ordner."></td>
				<td>
					<select class="ska-source-template" title="Optionales eigenes Namensschema fuer diesen Ordner.">
						<option value="" ${(source.namingTemplate || "") === "" ? "selected" : ""}>Standard</option>
						<option value="root_relative" ${source.namingTemplate === "root_relative" ? "selected" : ""}>Hauptordner + Pfad</option>
						<option value="parent_leaf" ${source.namingTemplate === "parent_leaf" ? "selected" : ""}>Oberordner + Ordner</option>
						<option value="leaf" ${source.namingTemplate === "leaf" ? "selected" : ""}>Ordner</option>
					</select>
				</td>
				<td><input class="ska-source-separator" type="text" maxlength="20" value="${escapeAttr(source.separator || "")}" placeholder="${escapeAttr(settings.separator || " - ")}" title="Optionaler eigener Trenner fuer diesen Ordner."></td>
				<td><button class="ska-source-remove sakuraalbum-button-danger" type="button" title="Quellordner entfernen.">Entfernen</button></td>
			</tr>
		`;
  }

  function renderFolderBrowser() {
    const current = (folderBrowser && folderBrowser.current) || { path: folderBrowserPath, name: "" };
    const folders = (folderBrowser && folderBrowser.folders) || [];
    return `
			<div class="sakuraalbum-folder-browser">
				<div class="sakuraalbum-folder-toolbar">
					<strong>${escapeText(current.path || "/")}</strong>
					<div class="sakuraalbum-actions">
						${folderBrowser && folderBrowser.parent ? `<button class="ska-folder-up" type="button" data-path="${escapeAttr(folderBrowser.parent)}">Hoeher</button>` : ""}
						<button class="ska-folder-select-current" type="button" data-path="${escapeAttr(current.path || "/")}">Diesen Ordner verwenden</button>
						<button id="ska-folder-close" type="button">Schliessen</button>
					</div>
				</div>
				${folderBrowser && folderBrowser.truncated ? `<div class="sakuraalbum-warning">Die Ordnerliste wurde bei ${escapeText(folderBrowser.limit)} Eintraegen begrenzt.</div>` : ""}
				<div class="sakuraalbum-folder-list">
					${folders.length ? folders.map(renderFolderBrowserRow).join("") : '<div class="sakuraalbum-empty">Keine Unterordner gefunden.</div>'}
				</div>
			</div>
		`;
  }

  function renderFolderBrowserRow(folder) {
    return `
			<div class="sakuraalbum-folder-row">
				<div>
					<strong>${escapeText(folder.name)}</strong>
					<span>${escapeText(folder.path)}</span>
				</div>
				<div class="sakuraalbum-actions">
					<button class="ska-folder-select" type="button" data-path="${escapeAttr(folder.path)}">Auswaehlen</button>
					<button class="ska-folder-open-path" type="button" data-path="${escapeAttr(folder.path)}" ${folder.hasChildren ? "" : "disabled"}>Oeffnen</button>
				</div>
			</div>
		`;
  }

  function bindMainActions() {
    document.getElementById("ska-save").addEventListener("click", save);
    document.getElementById("ska-enable-auto-save").addEventListener("click", enableAutomationAndSave);
    document.getElementById("ska-queue-update").addEventListener("click", queueUpdate);
    document.getElementById("ska-preview").addEventListener("click", preview);
    document.getElementById("ska-dry-run").addEventListener("click", dryRun);
    document.getElementById("ska-write").addEventListener("click", writeAlbums);
    document.getElementById("ska-runs").addEventListener("click", loadRuns);
    document.getElementById("ska-diagnostic-report").addEventListener("click", loadDiagnosticReport);
    document.getElementById("ska-managed").addEventListener("click", loadManagedAlbums);
    document.getElementById("ska-delete-preview").addEventListener("click", deleteDryRunSelected);
    document.getElementById("ska-delete-all-preview").addEventListener("click", deleteDryRunAll);
    document.getElementById("ska-reset-preview").addEventListener("click", resetDryRun);
    document.getElementById("ska-folder-open").addEventListener("click", () => {
      syncSourcesFromDom();
      folderBrowserOpen = true;
      render();
      loadFolders(folderBrowserPath);
    });

    if (mount.dataset.planResetBound !== "1") {
      mount.addEventListener("input", resetPreparedPlans);
      mount.addEventListener("change", resetPreparedPlans);
      mount.dataset.planResetBound = "1";
    }
  }

  function bindSourceActions() {
    Array.from(document.querySelectorAll("[data-source-id]")).forEach((row) => {
      const id = row.dataset.sourceId;
      row.querySelector(".ska-source-enabled").addEventListener("change", (event) => {
        updateSource(id, { enabled: event.target.checked });
        render();
      });
      row.querySelector(".ska-source-mode").addEventListener("change", (event) => {
        updateSource(id, { mode: event.target.value });
        render();
      });
      row.querySelector(".ska-source-depth").addEventListener("input", (event) =>
        updateSource(id, { albumDepth: Number.parseInt(event.target.value, 10) }),
      );
      row.querySelector(".ska-source-template").addEventListener("change", (event) =>
        updateSource(id, { namingTemplate: event.target.value }),
      );
      row.querySelector(".ska-source-separator").addEventListener("input", (event) =>
        updateSource(id, { separator: event.target.value }),
      );
      row.querySelector(".ska-source-remove").addEventListener("click", () => {
        sourceFolders = sourceFolders.filter((source) => source.id !== id);
        lastWriteRequest = null;
        lastDeleteRequest = null;
        lastResetRequest = null;
        render();
      });
    });
  }

  function bindFolderBrowserActions() {
    const close = document.getElementById("ska-folder-close");
    if (close) {
      close.addEventListener("click", () => {
        folderBrowserOpen = false;
        render();
      });
    }
    Array.from(document.querySelectorAll(".ska-folder-open-path, .ska-folder-up")).forEach((button) => {
      button.addEventListener("click", () => loadFolders(button.dataset.path || "/"));
    });
    Array.from(document.querySelectorAll(".ska-folder-select, .ska-folder-select-current")).forEach((button) => {
      button.addEventListener("click", () => addSourceFolder(button.dataset.path || "/"));
    });
  }

  function bindStatusActions() {
    const button = document.getElementById("ska-queue-update-inline");
    if (button) {
      button.addEventListener("click", queueUpdate);
    }
  }

  function updateSource(id, patch) {
    sourceFolders = sourceFolders.map((source) =>
      source.id === id ? normalizeSource(Object.assign({}, source, patch)) : source,
    );
    lastWriteRequest = null;
    lastDeleteRequest = null;
    lastResetRequest = null;
  }

  function syncSourcesFromDom() {
    Array.from(document.querySelectorAll("[data-source-id]")).forEach((row) => {
      updateSource(row.dataset.sourceId, {
        enabled: row.querySelector(".ska-source-enabled").checked,
        mode: row.querySelector(".ska-source-mode").value,
        albumDepth: Number.parseInt(row.querySelector(".ska-source-depth").value, 10),
        namingTemplate: row.querySelector(".ska-source-template").value,
        separator: row.querySelector(".ska-source-separator").value,
      });
    });
  }

  function collect() {
    syncSourcesFromDom();
    const enabledPaths = sourceFolders
      .filter((source) => source.enabled !== false)
      .map((source) => source.path);

    return {
      enabled: document.getElementById("ska-enabled").checked,
      includePaths: enabledPaths,
      sourceFolders,
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
      sourceFolders = normalizeSourceFolders(settings.sourceFolders, settings.includePaths, effectiveSettings.sourceFolders);
      lastWriteRequest = null;
      lastDeleteRequest = null;
      lastResetRequest = null;
      render();
      document.getElementById("ska-status").textContent = saveStatusText(response.queuedRefresh);
      await loadStatus(true);
    } catch (error) {
      status.textContent = `Fehler: ${error.message}`;
    }
  }

  async function enableAutomationAndSave() {
    const status = document.getElementById("ska-status");
    if (adminSettings.autoSyncMode !== "file_events") {
      status.textContent = "Automatik ist zentral nicht auf Dateiaenderungen gestellt.";
      return;
    }

    document.getElementById("ska-enabled").checked = true;
    document.getElementById("ska-auto-sync").checked = true;
    document.getElementById("ska-images").checked = true;
    await save();
  }

  async function queueUpdate() {
    const status = document.getElementById("ska-status");
    status.textContent = "Merke Update vor...";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/sync/queue-update", "POST", {});
      syncStatus = Object.assign({}, syncStatus || {}, { queue: response.queue || (syncStatus && syncStatus.queue) });
      updateLiveStatus();
      status.textContent = response.queued
        ? `${response.queuedPaths.length} Quellordner fuer den Hintergrundlauf vorgemerkt.`
        : `Update nicht vorgemerkt: ${queueReasonText(response.reason)}`;
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
      effectiveSettings = response.effectiveSettings || effectiveSettings;
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
      await loadStatus(true);
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
    loadStatus(true);
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
      await loadStatus(true);
    } catch (error) {
      status.textContent = `Schreibjob blockiert: ${error.message}`;
      await loadStatus(true);
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

  async function loadDiagnosticReport() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Bereite Fehlerbericht vor...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/diagnostics/report?limit=30", "GET");
      renderDiagnosticReport(response.report || {}, false);
      status.textContent = "Fehlerbericht vorbereitet. Versand per Mail wird spaeter angebunden.";
    } catch (error) {
      status.textContent = `Fehlerbericht konnte nicht erstellt werden: ${error.message}`;
    }
  }

  async function loadStatus(silent) {
    try {
      syncStatus = await request("/apps/sakuraalbum/api/v1/sync/status?limit=5&sampleLimit=8", "GET");
      settings = syncStatus.settings || settings;
      effectiveSettings = syncStatus.effectiveSettings || effectiveSettings;
      adminSettings = syncStatus.adminSettings || adminSettings;
      updateLiveStatus();
    } catch (error) {
      if (!silent) {
        const status = document.getElementById("ska-status");
        if (status) {
          status.textContent = `Status konnte nicht geladen werden: ${error.message}`;
        }
      }
    }
  }

  async function loadFolders(path) {
    const status = document.getElementById("ska-status");
    folderBrowserPath = path || "/";
    if (status) {
      status.textContent = "Lade Ordner...";
    }
    try {
      folderBrowser = await request(
        `/apps/sakuraalbum/api/v1/folders?path=${encodeURIComponent(folderBrowserPath)}&limit=150`,
        "GET",
      );
      folderBrowserPath = (folderBrowser.current && folderBrowser.current.path) || folderBrowserPath;
      syncSourcesFromDom();
      render();
      const nextStatus = document.getElementById("ska-status");
      if (nextStatus) {
        nextStatus.textContent = "Ordner geladen.";
      }
    } catch (error) {
      const nextStatus = document.getElementById("ska-status");
      if (nextStatus) {
        nextStatus.textContent = `Ordner konnten nicht geladen werden: ${error.message}`;
      }
    }
  }

  function addSourceFolder(path) {
    syncSourcesFromDom();
    const normalizedPath = displayPath(path);
    const exists = sourceFolders.some((source) => source.path.toLowerCase() === normalizedPath.toLowerCase());
    if (!exists) {
      sourceFolders.push(
        normalizeSource({
          id: sourceId(normalizedPath),
          path: normalizedPath,
          enabled: true,
          mode: "default",
          albumDepth: numberValue(settings.albumDepth, 1),
          namingTemplate: "",
          separator: "",
        }),
      );
    }
    lastWriteRequest = null;
    lastDeleteRequest = null;
    lastResetRequest = null;
    render();
    document.getElementById("ska-status").textContent = exists
      ? "Dieser Quellordner ist bereits in der Liste."
      : "Quellordner hinzugefuegt. Bitte speichern.";
  }

  async function loadManagedAlbums() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Lade verwaltete Alben...";
    output.innerHTML = "";
    lastDeleteRequest = null;
    lastResetRequest = null;
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
    lastResetRequest = null;
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
      lastResetRequest = null;
      status.textContent =
        response.status === "delete_completed"
          ? "Loeschjob abgeschlossen."
          : "Loeschjob abgeschlossen, bitte Ergebnis pruefen.";
      renderDeleteResult(response);
    } catch (error) {
      status.textContent = `Loeschjob blockiert: ${error.message}`;
    }
  }

  async function resetDryRun() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    status.textContent = "Pruefe Konto-Reset...";
    output.innerHTML = "";
    lastWriteRequest = null;
    lastDeleteRequest = null;
    lastResetRequest = null;
    try {
      const response = await request("/apps/sakuraalbum/api/v1/account/reset/dry-run", "POST", {});
      lastResetRequest = {
        canReset: response.canReset === true,
        confirmationText: response.confirmationText || "RESET_SAKURAALBUM",
        planFingerprint: response.planFingerprint || "",
        deletePlanFingerprint: response.deletePlanFingerprint || "",
      };
      status.textContent =
        response.canReset === true
          ? "Reset-Vorschau bereit."
          : "Reset-Vorschau bereit. Zuruecksetzen ist aktuell blockiert.";
      renderResetResult(response);
    } catch (error) {
      status.textContent = `Reset-Vorschau blockiert: ${error.message}`;
    }
  }

  async function resetAccount() {
    const status = document.getElementById("ska-status");
    const output = document.getElementById("ska-preview-output");
    if (!lastResetRequest || !lastResetRequest.canReset || !lastResetRequest.planFingerprint) {
      status.textContent = "Bitte zuerst eine sichere Reset-Vorschau erstellen.";
      return;
    }

    const confirmation = window.prompt(
      `Gib ${lastResetRequest.confirmationText} ein, um SakuraAlbum fuer dein Konto zurueckzusetzen.`,
    );
    if (confirmation === null) {
      return;
    }

    status.textContent = "Setze SakuraAlbum fuer dein Konto zurueck...";
    output.innerHTML = "";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/account/reset", "POST", {
        confirmation,
        planFingerprint: lastResetRequest.planFingerprint,
        deletePlanFingerprint: lastResetRequest.deletePlanFingerprint,
      });
      settings = response.settings || settings;
      effectiveSettings = response.effectiveSettings || effectiveSettings;
      sourceFolders = normalizeSourceFolders(settings.sourceFolders, settings.includePaths, effectiveSettings.sourceFolders);
      lastWriteRequest = null;
      lastDeleteRequest = null;
      lastResetRequest = null;
      render();
      document.getElementById("ska-status").textContent = "SakuraAlbum wurde fuer dein Konto zurueckgesetzt.";
      renderResetResult(response);
      await loadStatus(true);
    } catch (error) {
      status.textContent = `Reset blockiert: ${error.message}`;
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
    lastResetRequest = null;
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

    const [path, query] = String(url).split("?", 2);
    const finalUrl = `${OC.generateUrl(path)}${query ? `?${query}` : ""}`;
    const response = await fetch(finalUrl, options);
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

  async function copyDiagnosticReport(json) {
    const status = document.getElementById("ska-status");
    try {
      if (!navigator.clipboard || !navigator.clipboard.writeText) {
        throw new Error("Zwischenablage nicht verfuegbar");
      }
      await navigator.clipboard.writeText(json);
      status.textContent = "Fehlerbericht kopiert.";
    } catch (error) {
      status.textContent = `Kopieren nicht moeglich: ${error.message}`;
    }
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
							<th>Download</th>
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
								<td><button class="ska-managed-download" type="button" data-managed-id="${escapeAttr(album.managedId)}" title="Prueft Limits und laedt dieses verwaltete Album als ZIP herunter.">ZIP</button></td>
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
    Array.from(document.querySelectorAll(".ska-managed-download")).forEach((button) =>
      button.addEventListener("click", () => prepareManagedDownload(Number.parseInt(button.dataset.managedId, 10))),
    );
    updateManagedSelectedCount();
  }

  async function prepareManagedDownload(managedId) {
    const status = document.getElementById("ska-status");
    if (!Number.isFinite(managedId) || managedId <= 0) {
      status.textContent = "Ungueltiges verwaltetes Album.";
      return;
    }

    status.textContent = "Pruefe Album-Download...";
    try {
      const response = await request("/apps/sakuraalbum/api/v1/albums/managed/download/prepare", "POST", {
        managedId,
      });
      status.textContent = `Download startet: ${response.fileCount || 0} Dateien, ${formatBytes(response.totalBytes || 0)}.`;
      const path = "/apps/sakuraalbum/api/v1/albums/managed/download";
      window.location.href = `${OC.generateUrl(path)}?managedId=${encodeURIComponent(String(managedId))}`;
    } catch (error) {
      status.textContent = `Download blockiert: ${error.message}`;
    }
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

  function renderResetResult(result) {
    const output = document.getElementById("ska-preview-output");
    const summary = result.summary || {};
    const issues = result.resetBlockedReasons || [];
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(summary.plannedAlbums || 0)}</strong><br>Alben geprueft</div>
				<div><strong>${escapeText(summary.wouldDeletePhotosAlbums || summary.deletedPhotosAlbums || 0)}</strong><br>Photos-Alben</div>
				<div><strong>${escapeText(summary.wouldCleanupTrackingRecords || summary.cleanedTrackingRecords || 0)}</strong><br>Tracking</div>
				<div><strong>${escapeText(summary.deletedDirtyPaths || 0)}</strong><br>Queue</div>
				<div><strong>${escapeText(summary.deletedSyncCursors || 0)}</strong><br>Cursor</div>
				<div><strong>${summary.willResetSettings || result.status === "account_reset_completed" ? "Ja" : "Nein"}</strong><br>Einstellungen</div>
			</div>
			${issues.map((issue) => `<div class="sakuraalbum-warning">${escapeText(humanDeleteIssueText(issue))}</div>`).join("")}
			${result.canReset ? renderResetConfirm(result) : ""}
			${result.deleteResult ? renderDeleteTable(result.deleteResult.albums || []) : ""}
		`;
    const resetConfirm = document.getElementById("ska-reset-confirm");
    if (resetConfirm) {
      resetConfirm.addEventListener("click", resetAccount);
    }
  }

  function renderResetConfirm(result) {
    return `
			<div class="sakuraalbum-confirm-panel">
				<strong>Konto-Reset bereit</strong>
				<span>Dieser Schritt loescht nur eindeutig SakuraAlbum-verwaltete Photos-Alben, bereinigt SakuraAlbum-Queue und Cursor und setzt deine SakuraAlbum-Einstellungen auf Aus. Fehlerlogs bleiben fuer Diagnose erhalten.</span>
				<span>Die Bestaetigung lautet ${escapeText(result.confirmationText || "RESET_SAKURAALBUM")}.</span>
				<div class="sakuraalbum-actions"><button id="ska-reset-confirm" class="sakuraalbum-button-danger" type="button">Jetzt SakuraAlbum zuruecksetzen</button></div>
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
							<th>Quelle</th>
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
								<td>${escapeText(album.sourceRoot || "")}</td>
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
							<th>Fortschritt</th>
							<th>Alben</th>
							<th>Links</th>
							<th>Bereits drin</th>
							<th>Fehler</th>
						</tr>
					</thead>
					<tbody>
						${runs
              .map((run) => {
                const summary = run.summary || {};
                return `
							<tr>
								<td>${escapeText(formatTime(run.startedAt))}</td>
								<td>${escapeText(run.runType)}</td>
								<td>${escapeText(run.status)}</td>
								<td>${escapeText(summary.progressPercent !== undefined ? summary.progressPercent : progressPercent(run, {}))}%</td>
								<td>${escapeText(summary.plannedAlbums || summary.processedAlbums || 0)}</td>
								<td>${escapeText(summary.linkedFiles || summary.plannedLinks || 0)}</td>
								<td>${escapeText(summary.alreadyLinkedFiles || 0)}</td>
								<td>${escapeText((summary.fileErrors || 0) + (summary.albumErrors || 0))}</td>
							</tr>
						`;
              })
              .join("")}
					</tbody>
				</table>
			</div>
		`;
  }

  function renderDiagnosticReport(report, adminScope) {
    const output = document.getElementById("ska-preview-output");
    const meta = report.meta || {};
    const logs = report.logs || [];
    const queue = adminScope ? (report.autoSync || {}) : ((report.sync && report.sync.queue) || {});
    const managed = report.managedAlbums || {};
    const json = JSON.stringify(report, null, 2);
    output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${escapeText(meta.scope || "")}</strong><br>Bereich</div>
				<div><strong>${escapeText(formatTime(meta.createdAt))}</strong><br>Erstellt</div>
				<div><strong>${escapeText(logs.length)}</strong><br>Logs</div>
				<div><strong>${escapeText(((queue.counts || {}).pending) || 0)}</strong><br>Wartend</div>
				<div><strong>${escapeText((managed.total !== undefined ? managed.total : ""))}</strong><br>Verwaltete Alben</div>
				<div><strong>${report.sendMailReady ? "Ja" : "Nein"}</strong><br>Mail aktiv</div>
			</div>
			<div class="sakuraalbum-note">
				<strong>Bericht vorbereitet</strong>
				<span>Bekannte Geheimnisse werden vor dem Speichern von Log-Kontext redigiert. Der direkte Mailversand ist noch nicht aktiv.</span>
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-copy-diagnostic-report" type="button" title="Kopiert den sichtbaren JSON-Bericht in die Zwischenablage.">Bericht kopieren</button>
			</div>
			<pre class="sakuraalbum-report-json">${escapeText(json)}</pre>
		`;
    const copy = document.getElementById("ska-copy-diagnostic-report");
    if (copy) {
      copy.addEventListener("click", () => copyDiagnosticReport(json));
    }
  }

  function normalizeSourceFolders(storedSources, includePaths, effectiveSources) {
    const base =
      Array.isArray(storedSources) && storedSources.length > 0
        ? storedSources
        : Array.isArray(includePaths) && includePaths.length > 0
          ? includePaths.map((path) => ({ path }))
          : Array.isArray(effectiveSources)
            ? effectiveSources
            : [];
    const seen = new Set();
    const result = [];
    base.forEach((source) => {
      const normalized = normalizeSource(source);
      const key = normalized.path.toLowerCase();
      if (!seen.has(key)) {
        seen.add(key);
        result.push(normalized);
      }
    });
    return result;
  }

  function normalizeSource(source) {
    const path = displayPath(source.path || "/");
    const mode = ["default", "depth", "single_album"].includes(source.mode) ? source.mode : "default";
    const template = ["", "root_relative", "parent_leaf", "leaf"].includes(source.namingTemplate || "")
      ? source.namingTemplate || ""
      : "";
    return {
      id: /^[a-zA-Z0-9_-]{8,64}$/.test(source.id || "") ? source.id : sourceId(path),
      path,
      enabled: source.enabled !== false,
      mode,
      albumDepth: clampNumber(source.albumDepth, 0, numberValue(adminSettings.maxScanDepth, 20), numberValue(settings.albumDepth, 1)),
      namingTemplate: template,
      separator: String(source.separator || "").slice(0, 20),
      usesDefaultRules: source.usesDefaultRules === true,
    };
  }

  function sourceConflictWarnings() {
    const enabled = sourceFolders
      .filter((source) => source.enabled !== false)
      .map((source) => ({ path: displayPath(source.path), normalized: normalizeClientPath(source.path) }));
    const warnings = [];
    for (let i = 0; i < enabled.length; i++) {
      for (let j = i + 1; j < enabled.length; j++) {
        if (pathsOverlap(enabled[i].normalized, enabled[j].normalized)) {
          warnings.push(
            `Quellordner ueberschneiden sich: ${enabled[i].path} und ${enabled[j].path}. Bitte nur einen davon aktiv lassen.`,
          );
        }
      }
    }
    return warnings;
  }

  function pathsOverlap(left, right) {
    if (left === right) {
      return true;
    }
    if (left === "" || right === "") {
      return true;
    }
    return left.startsWith(`${right}/`) || right.startsWith(`${left}/`);
  }

  function progressPercent(run, queue, cursor) {
    if (cursor && cursor.status === "completed") {
      return 100;
    }
    if (cursor && cursor.status === "pending") {
      return clampNumber(cursor.estimatedProgressPercent, 1, 99, 25);
    }
    const summary = (run && run.summary) || {};
    if (summary.progressPercent !== undefined && run && run.status !== "auto_chunk_partial") {
      return clampNumber(summary.progressPercent, 0, 100, 0);
    }
    if (run && String(run.status || "").startsWith("write_completed")) {
      return 100;
    }
    if (run && run.status === "running") {
      return 1;
    }
    const pending = (queue && queue.counts && queue.counts.pending) || 0;
    const processing = (queue && queue.counts && queue.counts.processing) || 0;
    if (processing > 0) {
      return 10;
    }
    if (pending > 0) {
      return 0;
    }
    return 100;
  }

  function progressLabel(run, queue) {
    if (run && run.status === "running") {
      return `Laeuft: ${progressStageLabel((run.summary && run.summary.progressStage) || run.runType)}`;
    }
    if (run && run.status === "auto_chunk_partial") {
      return "Grosser Hintergrundlauf wird fortgesetzt";
    }
    const pending = (queue && queue.counts && queue.counts.pending) || 0;
    const processing = (queue && queue.counts && queue.counts.processing) || 0;
    if (processing > 0) {
      return "Hintergrundlauf in Arbeit";
    }
    if (pending > 0) {
      return "Update vorgemerkt";
    }
    if (run && String(run.status || "").includes("error")) {
      return "Letzter Lauf mit Fehlern";
    }
    return "Aktuell keine Arbeit offen";
  }

  function progressStageLabel(stage) {
    const labels = {
      preparing: "Vorbereitung",
      linking_files: "Medien werden verknuepft",
      album_completed: "Album abgeschlossen",
      album_error: "Albumfehler protokolliert",
      cleanup_missing_albums: "Veraltete verwaltete Alben werden geprueft",
      completed: "Abgeschlossen",
      dry_run: "Dry-Run",
      write: "Schreiben",
    };
    return labels[stage] || stage || "";
  }

  function updateLiveStatus() {
    const wrap = document.getElementById("ska-live-status-wrap");
    if (!wrap) {
      return;
    }
    wrap.innerHTML = renderLiveStatus();
    bindStatusActions();
  }

  function startStatusPolling() {
    if (statusInterval !== null) {
      return;
    }
    loadStatus(true);
    statusInterval = window.setInterval(() => loadStatus(true), 5000);
  }

  function saveStatusText(queuedRefresh) {
    if (queuedRefresh && queuedRefresh.queued) {
      return "Gespeichert. Die Erst-/Neuberechnung wurde fuer den Hintergrundlauf vorgemerkt.";
    }
    if (queuedRefresh && queuedRefresh.reason) {
      return `Gespeichert. Hintergrundlauf nicht vorgemerkt: ${queueReasonText(queuedRefresh.reason)}`;
    }
    return "Gespeichert.";
  }

  function queueReasonText(reason) {
    const labels = {
      admin_auto_sync_disabled_global: "Automatik ist zentral deaktiviert",
      admin_auto_sync_disabled: "Automatik ist zentral nicht auf Dateiaenderungen gestellt",
      admin_auto_sync_mode_manual: "Automatik ist zentral nicht auf Dateiaenderungen gestellt",
      admin_group_not_allowed: "dein Konto ist nicht in einer freigegebenen SakuraAlbum-Gruppe",
      user_auto_sync_disabled: "SakuraAlbum oder automatische Aktualisierung ist fuer dieses Konto nicht aktiv",
      no_source_folders: "kein aktiver Quellordner vorhanden",
    };
    return labels[reason] || reason || "unbekannter Grund";
  }

  function queueStatusText(status) {
    const labels = {
      pending: "wartend",
      processing: "in Arbeit",
      failed: "fehlerhaft",
    };
    return labels[status] || status || "";
  }

  function cursorStatusText(status) {
    const labels = {
      pending: "wird fortgesetzt",
      completed: "abgeschlossen",
      failed: "fehlgeschlagen",
    };
    return labels[status] || status || "";
  }

  function eventTypeText(eventType) {
    const labels = {
      chunk_continue: "Fortsetzung",
      manual_refresh: "manuell vorgemerkt",
      settings_update: "Einstellungen gespeichert",
      created: "erstellt",
      written: "geaendert",
      deleted: "geloescht",
      renamed_source: "umbenannt/verschoben Quelle",
      renamed_target: "umbenannt/verschoben Ziel",
    };
    return labels[eventType] || eventType || "";
  }

  function autoStateLabel() {
    if (effectiveSettings.adminGroupAllowed === false) {
      return "Nicht freigegeben";
    }
    if (adminSettings.autoSyncMode !== "file_events") {
      return "Zentral manuell";
    }
    return settings.autoSyncEnabled ? "Aktiv" : "Aus";
  }

  function adminReleaseLabel() {
    if (!adminSettings.enabled) {
      return "Zentral gesperrt";
    }
    if (effectiveSettings.adminGroupAllowed === false) {
      return "Gruppe gesperrt";
    }
    return "Freigegeben";
  }

  function windowLabel(source) {
    const start = source.autoSyncWindowStart || source.windowStart || "";
    const end = source.autoSyncWindowEnd || source.windowEnd || "";
    return start && end ? `${start}-${end}` : "Immer";
  }

  function queueWindowLabel(queue) {
    if (!queue || !queue.windowStart || !queue.windowEnd) {
      return "Wartungsfenster immer offen";
    }
    if (queue.windowActive) {
      return `Wartungsfenster offen (${queue.windowStart}-${queue.windowEnd})`;
    }
    return `Wartet auf Wartungsfenster ${queue.windowStart}-${queue.windowEnd}`;
  }

  function quotaLabel(source) {
    const albums = Number.parseInt(source.maxManagedAlbumsPerUser || 0, 10);
    const files = Number.parseInt(source.maxManagedFilesPerUser || 0, 10);
    if (!albums && !files) {
      return "Keine";
    }
    if (albums && files) {
      return `${albums} Alben / ${files} Medien`;
    }
    return albums ? `${albums} Alben` : `${files} Medien`;
  }

  function activeSourceCount() {
    const count = sourceFolders.filter((source) => source.enabled !== false).length;
    if (count > 0) {
      return `${count} Ordner`;
    }
    const fallback = Array.isArray(adminSettings.defaultIncludePaths) ? adminSettings.defaultIncludePaths.length : 0;
    return fallback > 0 ? `${fallback} Admin-Vorgabe` : "0";
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

  function sourceModeHelp(mode) {
    if (mode === "single_album") {
      return "Erzeugt ein Album fuer diesen Quellordner, auch wenn viele Unterordner enthalten sind.";
    }
    if (mode === "depth") {
      return "Nutzt die Tiefe aus dieser Zeile; tiefere Ordner werden im passenden Oberalbum gesammelt.";
    }
    return "Nutzt die Standardregel oben. Aenderungen am Standard wirken auf diesen Ordner mit.";
  }

  function humanIssueText(issue) {
    const code = issue.code || "";
    const warningCode = issue.warningCode || "";
    if (code === "plan_truncated") {
      return "Der Scan wurde durch ein Admin-Limit gestoppt. Es wird nichts geschrieben, bis die Vorschau vollstaendig ist.";
    }
    if (code === "blocking_warning" && warningCode === "overlapping_source_paths") {
      return "Quellordner ueberschneiden sich. Bitte verschachtelte Quellen entfernen oder deaktivieren.";
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
      if (issue.adminGroupAllowed === false) {
        return "SakuraAlbum ist fuer dein Konto nicht freigegeben, weil du nicht Mitglied einer erlaubten Admin-Gruppe bist.";
      }
      return "SakuraAlbum muss zentral und fuer dieses Konto aktiv sein, bevor Alben geschrieben werden.";
    }
    if (code === "managed_album_quota_exceeded") {
      return `Die Admin-Quote fuer verwaltete Alben wuerde ueberschritten: geplant ${issue.projected || 0}, erlaubt ${issue.limit || 0}.`;
    }
    if (code === "managed_file_quota_exceeded") {
      return `Die Admin-Quote fuer verwaltete Medienlinks wuerde ueberschritten: geplant ${issue.projected || 0}, erlaubt ${issue.limit || 0}.`;
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
    if (code === "overlapping_source_paths") {
      return `Quellordner ueberschneiden sich: ${warning.path || ""} und ${warning.otherPath || ""}.`;
    }
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

  function displayPath(path) {
    const normalized = normalizeClientPath(path);
    return normalized === "" ? "/" : `/${normalized}`;
  }

  function normalizeClientPath(path) {
    return String(path || "")
      .replaceAll("\\", "/")
      .split("/")
      .map((part) => part.trim())
      .filter((part) => part && part !== "." && part !== "..")
      .join("/");
  }

  function sourceId(path) {
    let hash = 2166136261;
    String(path).split("").forEach((char) => {
      hash ^= char.charCodeAt(0);
      hash = Math.imul(hash, 16777619);
    });
    return `src_${(hash >>> 0).toString(16)}_${String(path).length}`;
  }

  function clampNumber(value, min, max, fallback) {
    const parsed = Number.parseInt(value, 10);
    if (!Number.isFinite(parsed)) {
      return fallback;
    }
    return Math.max(min, Math.min(max, parsed));
  }

  function formatTime(timestamp) {
    if (!timestamp) {
      return "";
    }
    return new Date(timestamp * 1000).toLocaleString();
  }

  function formatBytes(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value <= 0) {
      return "0 B";
    }
    const units = ["B", "KB", "MB", "GB"];
    let current = value;
    let unit = 0;
    while (current >= 1024 && unit < units.length - 1) {
      current /= 1024;
      unit++;
    }
    return `${current.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
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
  startStatusPolling();
})();
