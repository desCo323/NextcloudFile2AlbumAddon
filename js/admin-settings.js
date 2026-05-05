(function() {
	'use strict';

	const root = document.getElementById('sakuraalbum-admin-settings');
	if (!root) {
		return;
	}

	const state = JSON.parse(root.dataset.settings || '{}');
	const mount = root.querySelector('.sakuraalbum-settings');

	function lines(value) {
		return Array.isArray(value) ? value.join('\n') : '';
	}

	function readLines(id) {
		return document.getElementById(id).value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
	}

	function fieldNumber(id) {
		return Number.parseInt(document.getElementById(id).value, 10);
	}

	function render(settings) {
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
					Global aktiv
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-allow-videos" type="checkbox" ${settings.allowVideos ? 'checked' : ''}>
					Videos erlauben
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-confirm-delete" type="checkbox" ${settings.requireBulkDeleteConfirmation ? 'checked' : ''}>
					Bulk-Delete bestaetigen
				</label>
				<label class="sakuraalbum-toggle">
					<input id="ska-debug-mode" type="checkbox" ${settings.debugMode ? 'checked' : ''}>
					Debug-Logging
				</label>
			</div>
			<div class="sakuraalbum-grid sakuraalbum-panel">
				<div class="sakuraalbum-field">
					<label for="ska-default-includes">Standard-Ordner</label>
					<textarea id="ska-default-includes">${escapeText(lines(settings.defaultIncludePaths))}</textarea>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-default-excludes">Standard-Ausnahmen</label>
					<textarea id="ska-default-excludes">${escapeText(lines(settings.defaultExcludePatterns))}</textarea>
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-max-depth">Max. Tiefe</label>
					<input id="ska-max-depth" type="number" min="0" max="20" value="${settings.maxScanDepth}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-preview-folders">Preview: Ordnerlimit</label>
					<input id="ska-preview-folders" type="number" min="10" value="${settings.maxPreviewFolders}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-preview-files">Preview: Dateilimit</label>
					<input id="ska-preview-files" type="number" min="10" value="${settings.maxPreviewFiles}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-job-folders">Job: Ordnerlimit</label>
					<input id="ska-job-folders" type="number" min="10" value="${settings.maxJobFolders}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-job-files">Job: Dateilimit</label>
					<input id="ska-job-files" type="number" min="10" value="${settings.maxJobFiles}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-job-albums">Job: Albumlimit</label>
					<input id="ska-job-albums" type="number" min="1" max="5000" value="${settings.maxAlbumsPerRun}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-job-interval">Job-Intervall Minuten</label>
					<input id="ska-job-interval" type="number" min="5" value="${settings.jobIntervalMinutes}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-debug-retention">Debug-Aufbewahrung Tage</label>
					<input id="ska-debug-retention" type="number" min="1" max="365" value="${settings.debugRetentionDays}">
				</div>
				<div class="sakuraalbum-field">
					<label for="ska-debug-context">Max. Kontextlaenge</label>
					<input id="ska-debug-context" type="number" min="1000" max="100000" value="${settings.debugMaxContextLength}">
				</div>
			</div>
			<div class="sakuraalbum-actions">
				<button id="ska-save-admin" class="primary" type="button">Speichern</button>
				<button id="ska-load-logs" type="button">Logs laden</button>
			</div>
			<div id="ska-admin-status" class="sakuraalbum-status"></div>
			<div id="ska-log-output"></div>
		`;
		document.getElementById('ska-save-admin').addEventListener('click', save);
		document.getElementById('ska-load-logs').addEventListener('click', loadLogs);
	}

	function collect() {
		return {
			enabled: document.getElementById('ska-enabled').checked,
			defaultIncludePaths: readLines('ska-default-includes'),
			defaultExcludePatterns: readLines('ska-default-excludes'),
			maxScanDepth: fieldNumber('ska-max-depth'),
			maxPreviewFolders: fieldNumber('ska-preview-folders'),
			maxPreviewFiles: fieldNumber('ska-preview-files'),
			maxJobFolders: fieldNumber('ska-job-folders'),
			maxJobFiles: fieldNumber('ska-job-files'),
			maxAlbumsPerRun: fieldNumber('ska-job-albums'),
			allowVideos: document.getElementById('ska-allow-videos').checked,
			jobIntervalMinutes: fieldNumber('ska-job-interval'),
			requireBulkDeleteConfirmation: document.getElementById('ska-confirm-delete').checked,
			debugMode: document.getElementById('ska-debug-mode').checked,
			debugRetentionDays: fieldNumber('ska-debug-retention'),
			debugMaxContextLength: fieldNumber('ska-debug-context'),
		};
	}

	async function save() {
		const status = document.getElementById('ska-admin-status');
		status.textContent = 'Speichere...';
		try {
			const response = await fetch(OC.generateUrl('/apps/sakuraalbum/api/v1/admin/settings'), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ settings: collect() }),
			});
			if (!response.ok) {
				throw new Error(`HTTP ${response.status}`);
			}
			const data = await response.json();
			render(data.settings);
			document.getElementById('ska-admin-status').textContent = 'Gespeichert.';
		} catch (error) {
			status.textContent = `Fehler: ${error.message}`;
		}
	}

	async function loadLogs() {
		const status = document.getElementById('ska-admin-status');
		const output = document.getElementById('ska-log-output');
		status.textContent = 'Lade Logs...';
		output.innerHTML = '';
		try {
			const response = await fetch(OC.generateUrl('/apps/sakuraalbum/api/v1/admin/logs?limit=50'), {
				method: 'GET',
				headers: {
					requesttoken: OC.requestToken,
				},
			});
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
		const output = document.getElementById('ska-log-output');
		if (logs.length === 0) {
			output.innerHTML = '<div class="sakuraalbum-empty">Keine Logeintraege gefunden.</div>';
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
					${logs.map((log) => `
						<tr>
							<td>${escapeText(formatTime(log.createdAt))}</td>
							<td><span class="sakuraalbum-level sakuraalbum-level-${escapeText(log.level)}">${escapeText(log.level)}</span></td>
							<td>${escapeText(log.event)}</td>
							<td>${escapeText(log.userId || '')}</td>
							<td>
								${escapeText(log.message || '')}
								${log.context ? `<pre>${escapeText(JSON.stringify(log.context, null, 2))}</pre>` : ''}
							</td>
						</tr>
					`).join('')}
				</tbody>
			</table>
		`;
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

	render(state);
})();
