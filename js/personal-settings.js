(function() {
	'use strict';

	const root = document.getElementById('sakuraalbum-personal-settings');
	if (!root) {
		return;
	}

	let settings = JSON.parse(root.dataset.settings || '{}');
	const mount = root.querySelector('.sakuraalbum-settings');

	function lines(value) {
		return Array.isArray(value) ? value.join('\n') : '';
	}

	function readLines(id) {
		return document.getElementById(id).value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
	}

	function number(id) {
		return Number.parseInt(document.getElementById(id).value, 10);
	}

	function render() {
		mount.innerHTML = `
			<div class="sakuraalbum-header">
				<img class="sakuraalbum-mark" src="${OC.imagePath('sakuraalbum', 'app.svg')}" alt="">
				<div>
					<h2>SakuraAlbum</h2>
				</div>
			</div>
			<div class="sakuraalbum-toggle-row">
				<label class="sakuraalbum-toggle">
					<input id="ska-enabled" type="checkbox" ${settings.enabled ? 'checked' : ''}>
					Aktiv
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-images" type="checkbox" ${settings.includeImages ? 'checked' : ''}>
					Bilder
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-videos" type="checkbox" ${settings.includeVideos ? 'checked' : ''}>
					Videos
				</label>
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel">
				<div class="sakuraalbum-field">
					<label for="ska-includes">Ordner</label>
					<textarea id="ska-includes">${escapeText(lines(settings.includePaths))}</textarea>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-excludes">Ausnahmen</label>
					<textarea id="ska-excludes">${escapeText(lines(settings.excludePatterns))}</textarea>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-template">Albumname</label>
					<select id="ska-template">
						<option value="root_relative" ${settings.namingTemplate === 'root_relative' ? 'selected' : ''}>Hauptordner + Pfad</option>
						<option value="parent_leaf" ${settings.namingTemplate === 'parent_leaf' ? 'selected' : ''}>Oberordner + Ordner</option>
						<option value="leaf" ${settings.namingTemplate === 'leaf' ? 'selected' : ''}>Ordner</option>
					</select>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-separator">Trenner</label>
					<input id="ska-separator" type="text" maxlength="20" value="${escapeAttr(settings.separator || ' - ')}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-depth">Album-Tiefe</label>
					<input id="ska-depth" type="number" min="0" max="20" value="${settings.albumDepth ?? 1}">
				</div>
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-preview" type="button">Vorschau</button>
				<button id="ska-dry-run" type="button">Dry-Run</button>
				<button id="ska-write" type="button">Alben erzeugen</button>
				<button id="ska-runs" type="button">Letzte Laeufe</button>
				<button id="ska-save" class="primary" type="button">Speichern</button>
			</div>
			<div id="ska-status" class="sakuraalbum-status"></div>
			<div id="ska-preview-output"></div>
		`;
		document.getElementById('ska-save').addEventListener('click', save);
		document.getElementById('ska-preview').addEventListener('click', preview);
		document.getElementById('ska-dry-run').addEventListener('click', dryRun);
		document.getElementById('ska-write').addEventListener('click', writeAlbums);
		document.getElementById('ska-runs').addEventListener('click', loadRuns);
	}

	function collect() {
		return {
			enabled: document.getElementById('ska-enabled').checked,
			includePaths: readLines('ska-includes'),
			excludePatterns: readLines('ska-excludes'),
			namingTemplate: document.getElementById('ska-template').value,
			separator: document.getElementById('ska-separator').value,
			albumDepth: number('ska-depth'),
			includeImages: document.getElementById('ska-images').checked,
			includeVideos: document.getElementById('ska-videos').checked,
		};
	}

	async function save() {
		const status = document.getElementById('ska-status');
		status.textContent = 'Speichere...';
		try {
			const response = await request('/apps/sakuraalbum/api/v1/user/settings', 'PUT', { settings: collect() });
			settings = response.settings;
			render();
			document.getElementById('ska-status').textContent = 'Gespeichert.';
		} catch (error) {
			status.textContent = `Fehler: ${error.message}`;
		}
	}

	async function preview() {
		const status = document.getElementById('ska-status');
		const output = document.getElementById('ska-preview-output');
		status.textContent = 'Erstelle Vorschau...';
		output.innerHTML = '';
		try {
			const response = await request('/apps/sakuraalbum/api/v1/preview', 'POST', { settings: collect() });
			status.textContent = 'Vorschau bereit.';
			renderPreview(response.preview);
		} catch (error) {
			status.textContent = `Fehler: ${error.message}`;
		}
	}

	async function dryRun() {
		const status = document.getElementById('ska-status');
		const output = document.getElementById('ska-preview-output');
		status.textContent = 'Pruefe geplante Schreibaktion...';
		output.innerHTML = '';
		try {
			const response = await request('/apps/sakuraalbum/api/v1/sync/dry-run', 'POST', { settings: collect() });
			status.textContent = response.canWrite
				? 'Dry-Run bereit. Nach dem Speichern kann dieser Plan geschrieben werden.'
				: 'Dry-Run bereit. Schreiben ist aktuell blockiert.';
			renderSyncResult(response);
		} catch (error) {
			status.textContent = `Fehler: ${error.message}`;
		}
	}

	async function writeAlbums() {
		const status = document.getElementById('ska-status');
		const output = document.getElementById('ska-preview-output');
		const confirmation = window.prompt('SakuraAlbum nutzt fuer das Erzeugen nur gespeicherte Einstellungen. Gib CREATE_ALBUMS ein, um den Schreibjob zu starten.');
		if (confirmation === null) {
			return;
		}
		status.textContent = 'Starte Schreibjob...';
		output.innerHTML = '';
		try {
			const response = await request('/apps/sakuraalbum/api/v1/sync/write', 'POST', { confirmation });
			status.textContent = response.status === 'write_completed'
				? 'Schreibjob abgeschlossen.'
				: 'Schreibjob abgeschlossen, bitte Ergebnis pruefen.';
			renderSyncResult(response);
		} catch (error) {
			status.textContent = `Schreibjob blockiert: ${error.message}`;
		}
	}

	async function loadRuns() {
		const status = document.getElementById('ska-status');
		const output = document.getElementById('ska-preview-output');
		status.textContent = 'Lade Laeufe...';
		output.innerHTML = '';
		try {
			const response = await request('/apps/sakuraalbum/api/v1/sync/runs?limit=10', 'GET');
			renderRuns(response.runs || []);
			status.textContent = `${(response.runs || []).length} Laeufe geladen.`;
		} catch (error) {
			status.textContent = `Fehler: ${error.message}`;
		}
	}

	async function request(url, method, body) {
		const options = {
			method,
			headers: {
				'Content-Type': 'application/json',
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
				// Keep the HTTP status if the server did not return JSON.
			}
			throw new Error(message);
		}
		return response.json();
	}

	function renderPreview(preview) {
		const output = document.getElementById('ska-preview-output');
		const summary = preview.summary || {};
		const warnings = preview.warnings || [];
		const albums = preview.albums || [];

		output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${summary.plannedAlbums || 0}</strong><br>Alben</div>
				<div><strong>${summary.mediaFiles || 0}</strong><br>Medien</div>
				<div><strong>${summary.foldersScanned || 0}</strong><br>Ordner</div>
				<div><strong>${summary.collisions || 0}</strong><br>Konflikte</div>
			</div>
			${warnings.map((warning) => `<div class="sakuraalbum-warning">${escapeText(warning.code || 'warning')} ${escapeText(warning.path || warning.message || '')}</div>`).join('')}
			<table class="sakuraalbum-preview-table">
				<thead>
					<tr>
						<th>Album</th>
						<th>Zielpfad</th>
						<th>Medien</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					${albums.map((album) => `
						<tr>
							<td>${escapeText(album.albumName)}</td>
							<td>${escapeText(album.targetPath)}</td>
							<td>${album.mediaCount}</td>
							<td>${album.collision ? '<span class="sakuraalbum-warning">Konflikt</span>' : (album.aggregated ? 'Aggregiert' : 'OK')}</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`;
	}

	function renderSyncResult(result) {
		const output = document.getElementById('ska-preview-output');
		const summary = result.summary || {};
		const issues = result.writeBlockedReasons || [];
		const warnings = result.warnings || [];
		const albums = result.albums || [];

		output.innerHTML = `
			<div class="sakuraalbum-summary">
				<div><strong>${summary.plannedAlbums || 0}</strong><br>Geplante Alben</div>
				<div><strong>${summary.plannedLinks || 0}</strong><br>Geplante Links</div>
				<div><strong>${summary.createdAlbums || 0}</strong><br>Erstellt</div>
				<div><strong>${summary.linkedFiles || 0}</strong><br>Verknuepft</div>
				<div><strong>${summary.alreadyLinkedFiles || 0}</strong><br>Bereits drin</div>
				<div><strong>${summary.safetyIssueCount || 0}</strong><br>Blocker</div>
			</div>
			${issues.map((issue) => `<div class="sakuraalbum-warning">${escapeText(issue.code || 'blockiert')} ${escapeText(issue.warningCode || issue.message || '')}</div>`).join('')}
			${warnings.map((warning) => `<div class="sakuraalbum-warning">${escapeText(warning.code || 'warning')} ${escapeText(warning.path || warning.message || '')}</div>`).join('')}
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
					${albums.map((album) => `
						<tr>
							<td>${escapeText(album.albumName)}</td>
							<td>${escapeText(album.targetPath)}</td>
							<td>${album.mediaCount}</td>
							<td><span class="sakuraalbum-action">${escapeText(actionLabel(album.writeAction))}</span></td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`;
	}

	function renderRuns(runs) {
		const output = document.getElementById('ska-preview-output');
		if (runs.length === 0) {
			output.innerHTML = '<div class="sakuraalbum-empty">Noch keine Laeufe vorhanden.</div>';
			return;
		}

		output.innerHTML = `
			<table class="sakuraalbum-preview-table">
				<thead>
					<tr>
						<th>Zeit</th>
						<th>Typ</th>
						<th>Status</th>
						<th>Alben</th>
						<th>Links</th>
					</tr>
				</thead>
				<tbody>
					${runs.map((run) => `
						<tr>
							<td>${escapeText(formatTime(run.startedAt))}</td>
							<td>${escapeText(run.runType)}</td>
							<td>${escapeText(run.status)}</td>
							<td>${escapeText((run.summary && run.summary.plannedAlbums) || 0)}</td>
							<td>${escapeText((run.summary && run.summary.plannedLinks) || 0)}</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`;
	}

	function actionLabel(action) {
		const labels = {
			create: 'Wuerde erstellen',
			update_managed: 'Verwaltetes Album aktualisieren',
			blocked_existing_album: 'Blockiert: existiert bereits',
			blocked_collision: 'Blockiert: Namenskonflikt',
		};
		return labels[action] || 'Pruefen';
	}

	function formatTime(timestamp) {
		if (!timestamp) {
			return '';
		}
		return new Date(timestamp * 1000).toLocaleString();
	}

	function escapeText(value) {
		return String(value)
			.replaceAll('&', '&amp;')
			.replaceAll('<', '&lt;')
			.replaceAll('>', '&gt;')
			.replaceAll('"', '&quot;');
	}

	function escapeAttr(value) {
		return escapeText(value).replaceAll("'", '&#039;');
	}

	render();
})();
