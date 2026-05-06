# SakuraAlbum Session State

Datum: 2026-05-06 23:18:00 CET

Neueste operative Notiz (2026-05-06 23:10 CEST):
- Benutzerauftrag: Nextcloud/SakuraAlbum soll wirklich automatisch laufen.
- Befund:
  - Nextcloud ist gesund (`maintenance=false`, `needsDbUpgrade=false`) und System-Cron ruft alle 5 Minuten `/usr/local/sbin/nextcloud-cron-lowprio` als `www-data` auf.
  - Nextcloud speichert den echten Cron-Zeitstempel unter `oc_appconfig`: `core.lastcron=1778101512`; `core.backgroundjobs_mode=cron`.
  - SakuraAlbum las fuer seine Cron-Diagnose faelschlich `getSystemValue('lastcron')` und meldete deshalb `cron_not_recorded`, obwohl Nextcloud Cron laeuft.
  - Alter Nebenbefund in `/var/log/nextcloud-cron.log`: ein historischer `Segmentation fault` vor diesem Block; seit dem Reboot sind Cron-Sessions kurz und regelmaessig durchgelaufen.
- Umsetzung in Arbeit fuer `1.0.2`:
  - `AutoSyncService::backgroundJobsState()` liest `core.lastcron` per App-Konfiguration.
  - `AutoSyncService::backgroundJobsMode()` liest `core.backgroundjobs_mode` per App-Konfiguration.
  - Version und cache-busting Assets werden auf `1.0.2` angehoben.
- Abschluss dieses Blocks:
  - Backup vor Deployment: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.2-cron-health-20260506-231046/`.
  - Wiederherstellungsprompt: "Stelle `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.2-cron-health-20260506-231046/app` nach `/var/www/nextcloud/apps/sakuraalbum` wieder her, setze Eigentümer `www-data:www-data`, pruefe `occ upgrade`, `occ status` und `appconfig_core_sakuraalbum.tsv`."
  - `1.0.2` live ausgerollt; `occ upgrade` erfolgreich; Nextcloud danach `maintenance=false`, `needsDbUpgrade=false`, SakuraAlbum `1.0.2`.
  - Admin-Auto-Status nach Fix: `backgroundJobsCronHealthy=true`, `automationBlockingReason=null`, `backgroundJobsMode=cron`, `backgroundJobsLastCronAt` gesetzt.
  - Echter Cron-Verarbeitungstest mit `albentest`: isolierter Ordner `/Photos/SakuraAlbumCron102`, Queue vorgemerkt, nach Entprellzeit `/usr/local/sbin/nextcloud-cron-lowprio` ausgefuehrt. Ergebnis: `auto_sync_user_started`, `auto_chunk_completed`, `auto_sync_user_completed`, `auto_sync_process_completed`; erzeugtes Testalbum `SakuraAlbumCron102` mit 1 Medium.
  - Cleanup: Account-Reset loeschte 1 Test-Photos-Album und 1 Cursor; Testordner entfernt; danach `active_managed=0`, `dirty=0`, `cursors=0` fuer `albentest`.
  - Cron-Log wurde vor dem Test nach Backup kopiert und neu begonnen; waehrend des 1.0.2-Tests keine neue Ausgabe/kein Segfault in `/var/log/nextcloud-cron.log`.
  - Checks: `php -l`, `node --check`, `git diff --check`, `./scripts/self-check.sh`, Artefakt-Build und entpacktes Artefakt-Self-Check erfolgreich. Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.2.tar.gz`, SHA256 `0b7bf2f4ddf28e784aaa2b273f440745b5b8c617f7a4229f733af62353da0978`.

Neueste operative Notiz (2026-05-06 22:55 CEST):
- Benutzer meldet: Personal-UI zeigt "Automatische Albumaktualisierung nicht aktiv", obwohl Admin auf `Bei Dateiaenderungen` steht.
- Befund aus Live-Konfiguration/Logs: Admin ist `globalEnabled=1`, `autoSyncMode=file_events`, aber `albentest` hatte gespeicherte Benutzerwerte `enabled=false` und `autoSyncEnabled=false`.
- Umsetzung in Arbeit fuer `1.0.1`:
  - Admin-Default `autoSyncMode` wird `file_events`.
  - Benutzer-Auto-Sync ist kein zweiter wirksamer Opt-in-Blocker mehr: Wenn Admin Datei-Events erlaubt und der Benutzer `SakuraAlbum verwenden` aktiviert, ist `autoSyncActive=true`.
  - Personal-UI zeigt `Automatisch aktuell halten` als Admin-Standard/Status statt als separaten aktivierbaren Pflichtschalter.
  - Admin- und Benutzerdokumentation werden an diese Logik angepasst.
- Abschluss dieses Blocks:
  - `1.0.1` live nach Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.1-autosync-default-20260506-225633/` ausgerollt.
  - Backup-Wiederherstellungsprompt: "Stelle SakuraAlbum aus `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.1-autosync-default-20260506-225633/app` nach `/var/www/nextcloud/apps/sakuraalbum` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf die `oc_sakuraalbum_*.sql` Dumps und stelle `oc_appconfig_sakuraalbum.tsv` sowie `oc_preferences_albentest_sakuraalbum.tsv` manuell wieder her; danach `occ upgrade`, `occ app:list` und `occ status` pruefen."
  - Live-Service-Test: Speichern mit `enabled=true` und absichtlich `autoSyncEnabled=false` setzt serverseitig `autoSyncEnabled=true`; effektive Werte danach `autoSyncAvailable=true`, `autoSyncActive=true`.
  - Live-Auto-Sync-Test mit isoliertem Testordner `/Photos/SakuraAlbumAuto101`: Queue vorgemerkt, nach Entprellzeit `processDueChanges()` mit `processedUsers=1`, `succeededUsers=1`; Reset loeschte 1 erzeugtes Photos-Album und 1 Cursor. Testordner entfernt; Queue danach 0, Cursor 0, aktive Testalben 0.
  - Kurzzeitiger Fehler im Test: ein versehentlich gestarteter `maintenance:repair --include-expensive` wurde abgebrochen; danach wurde `maintenance:mode --off` gesetzt und `occ status` bestaetigte `maintenance=false`, `needsDbUpgrade=false`.
  - Nebenbefund ausserhalb SakuraAlbum: Nextcloud `lastcron` ist leer und `/var/log/nextcloud-cron.log` enthaelt `Segmentation fault`; SakuraAlbum Admin-Status meldet deshalb korrekt `cron_not_recorded`.
  - Checks: `./scripts/self-check.sh`, JS/PHP Syntax, `git diff --check`, Artefakt-Build und entpacktes Artefakt-Self-Check erfolgreich. Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.1.tar.gz`, SHA256 `970f28475f04b52c793edb8844ce75453763889100d86f8a96dc2a9d002c40fb`.

Neueste operative Notiz (2026-05-06 22:39 CET):
- Version-1-Releaseblock gestartet.
- Lokaler Stand wurde auf `1.0.0` angehoben:
  - `appinfo/info.xml` und `package.json` auf `1.0.0`.
  - Neue cache-busting Assets `admin-settings-100.js` und `personal-settings-100.js`; Settings laden diese Dateien.
  - `CHANGELOG.md`, `CHANGELOG.en.md`, README, Testplan und Store-Checklist aktualisiert.
  - Neues kontrolliertes Smoke-Test-Hilfsscript `scripts/live-smoke.php`.
- Lokale Checks vor Live-Fenster:
  - `php -l scripts/live-smoke.php` erfolgreich.
  - `./scripts/self-check.sh` erfolgreich.
  - `node --check js/admin-settings-100.js` und `node --check js/personal-settings-100.js` erfolgreich.
  - `git diff --check` erfolgreich.
  - Secret-Scan auf GitHub-Token/Testpasswort/DB-Passwort erfolgreich ohne Treffer.
  - `./scripts/build-artifact.sh` erfolgreich: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.0.tar.gz`, SHA256 `95dc5824ad1aa6651b18dc64a934db3fe3cd0feb35c0290328d33d627e248099`.
  - Entpacktes Paket hat `./scripts/self-check.sh` bestanden.
- Backup vor V1-Smoke-Testfenster: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-v1-smoke-20260506-223925/app/`.
- Wiederherstellungsprompt fuer Neustart: "Stelle SakuraAlbum aus Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-v1-smoke-20260506-223925/app/` nach `/var/www/nextcloud/apps/sakuraalbum/` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-v1-smoke-20260506-223925/db-relevant-before-test.sql`, setze danach `occ config:app:set sakuraalbum globalEnabled --value=false`, `occ config:app:set sakuraalbum autoSyncMode --value=manual`, `occ config:app:set sakuraalbum debugMode --value=false`, starte `systemctl restart php8.3-fpm`, pruefe `occ status`, `occ app:list | grep sakuraalbum`, `occ route:list | grep sakuraalbum` und `https://chaosnet.me/status.php`."
- V1-Live-Testfenster Ergebnis:
  - Deployment nach `/var/www/nextcloud/apps/sakuraalbum/` durchgefuehrt.
  - Versionserhoehung loeste erwartbar Nextcloud-Upgrade-Modus aus; `occ upgrade` wurde im Backup-Testfenster zweimal ausgefuehrt. Ergebnis: Maintenance aus, `needsDbUpgrade=false`, SakuraAlbum `1.0.0`.
  - Nebenbefund: Nextcloud deaktivierte die fremde App `files_bpm` als inkompatibel; nicht automatisch wieder aktiviert.
  - Erster direkter PHP-Smoke-Aufruf war auf dieser Installation nicht geeignet; Wrapper `scripts/live-smoke.sh` wurde ergaenzt und getestet.
  - Produktiver Smoke-Befehl erfolgreich: `sudo -u www-data SAKURAALBUM_LIVE_SMOKE=1 bash /var/www/nextcloud/apps/sakuraalbum/scripts/live-smoke.sh`.
  - Smoke-Ergebnis: 1 Testdatei in `/Photos/SakuraAlbumV1Smoke`, 1 geplantes Album, 1 Link geschrieben, ZIP-Prepare `fileCount=1`, `totalBytes=68`, Reset loeschte 1 verwaltetes Photos-Album.
  - Cleanup geprueft: 0 aktive SakuraAlbum-Alben, 0 Dirty-Paths, 0 Cursor, 0 Smoke-Photos-Alben, 0 Smoke-Dateien.
  - Nach Test SakuraAlbum produktiv wieder sicher gestellt: `globalEnabled=0`, `autoSyncMode=manual`, `debugMode=0`.
  - Nextcloud geprueft: `occ status` sauber, `https://chaosnet.me/status.php` HTTP 200.
  - Finale Paket-SHA256 nach Wrapper-Korrektur: `46a77f7c639b38d74a15a3744c3074e5ecc8fd8c1497d39e52ed30d5b8758cc5` fuer `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.0.tar.gz`.

Neueste operative Notiz (2026-05-06 23:18 CET):
- Neuer Version-1-Härtungsblock gestartet: Konto-Reset, bessere Ordnerregel-Erklaerung und Reset-Selbsttests.
- Backup vor Reset-Testfenster: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-account-reset-20260506-221636/app/`.
- Wiederherstellungsprompt fuer Neustart: "Stelle SakuraAlbum aus Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-account-reset-20260506-221636/app/` nach `/var/www/nextcloud/apps/sakuraalbum/` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-account-reset-20260506-221636/db-relevant-before-test.sql`, pruefe danach `occ app:list`, `occ route:list | grep sakuraalbum` und SakuraAlbum-Logs."
- Implementiert:
  - Neue API-Routen `POST /api/v1/account/reset/dry-run` und `POST /api/v1/account/reset`.
  - Neuer `AccountResetService`: Reset-Vorschau wrappt die bestehende sichere Delete-Dry-Run-Logik; Schreib-Reset verlangt `RESET_SAKURAALBUM`, nutzt die verwaltete Albumloeschung und bereinigt danach Queue/Cursor und persoenliche SakuraAlbum-Einstellungen.
  - `DirtyPathMapper::deleteForUser`, `SyncCursorMapper::deleteForUser`, `SettingsService::resetUserSettings`.
  - Personal-UI: `Konto-Reset pruefen`, Reset-Ergebnis, exakte Bestaetigung, deutlichere Erklaerungen fuer Standard/Eigene Tiefe/Unterordner zusammenfassen.
  - `scripts/self-check.sh` prueft Reset-Service, Reset-Routen, UI und exakte Bestaetigung.
- Fruehe Checks:
  - `php -l` fuer neue/geaenderte PHP-Dateien erfolgreich.
  - `node --check js/personal-settings-026.js` und `node --check js/personal-settings.js` erfolgreich.
- Naechster Schritt:
  - Vollstaendiges `./scripts/self-check.sh`, Backup fuer kontrolliertes Testfenster, Deployment auf `/var/www/nextcloud/apps/sakuraalbum`, Reset-/Auto-Sync-Live-Test mit `albentest`, anschliessend Testaccount auf 0 zuruecksetzen.
- Live-Test-Fortschritt vor Server-Neustart:
  - Produktiv-Deployment des Reset-Blocks wurde durchgefuehrt.
  - Service-Test mit `albentest`: `single_album` fuer `/Photos` plante 1 Album mit 145 Medienlinks, Schreibtest erstellte 1 verwaltetes Album, Reset-Dry-Run war schreibbar, Reset loeschte 1 verwaltetes Photos-Album und setzte User-Settings zurueck.
  - Nach Befund blieb eine deaktivierte `oc_preferences`-Zeile fuer `albentest`; lokale Korrektur: `SettingsService::resetUserSettings()` loescht die UserConfig jetzt statt Defaults zu speichern.
- Server-Sicherheitsstatus nach Neustart (2026-05-06 ca. 22:33 CET):
  - Server hatte harten Neustart: `last -x` zeigt vorherige Sitzung als `crash`, kein sauberer Shutdown-Eintrag.
  - Nextcloud wieder geprueft: `occ status` sauber, Maintenance aus, DB-Upgrade nicht noetig, `https://chaosnet.me/status.php` via lokaler SNI-Pruefung HTTP 200.
  - MariaDB lebt, Testnutzer-Restzustand: 0 aktive SakuraAlbum-Alben, 0 Dirty-Paths, 0 Cursor, 0 Photos-Alben.
  - Produktive SakuraAlbum-App wurde aus Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-account-reset-20260506-221636/app/` zurueckgerollt.
  - Produktive SakuraAlbum-Freigaben wurden sicher deaktiviert: `globalEnabled=0`, `autoSyncMode=manual`, `debugMode=0`; PHP-FPM wurde neu gestartet.
  - Weiterentwicklung nur lokal fortsetzen, bis ein Mensch bestaetigt, dass ein neues Testfenster erlaubt ist.

Neueste operative Notiz (2026-05-06 22:05 CET):
- Neuer Version-1-Block: direkter ZIP-Download fuer einzelne SakuraAlbum-verwaltete Alben.
- Backup vor Download-Testfenster: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-download-20260506-220450/app/`.
- Wiederherstellungsprompt fuer Neustart: "Stelle SakuraAlbum aus Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-download-20260506-220450/app/` nach `/var/www/nextcloud/apps/sakuraalbum/` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-download-20260506-220450/db-relevant-before-test.sql`, pruefe danach `occ app:list`, `occ route:list | grep sakuraalbum` und SakuraAlbum-Logs."
- Implementiert:
  - `ManagedAlbumDownloadService` prueft verwaltetes Album, Photos-Album-ID, Besitzer, Name, Datei-Anzahl, Lesbarkeit und Gesamtgroesse vor dem ZIP.
  - Neue Routen: `POST /api/v1/albums/managed/download/prepare` und `GET /api/v1/albums/managed/download`.
  - Admin-Limits: `maxDownloadFiles`, `maxDownloadBytes`; UI-Felder `Download: Dateilimit`, `Download: Bytelimit`.
  - Personal-UI zeigt pro verwaltetem Album eine `ZIP`-Aktion.
- Tests:
  - `./scripts/self-check.sh` erfolgreich.
  - Live-Test mit `albentest`: verwaltete Alben erzeugt, Download-Prepare fuer ein 1-Datei-Album erfolgreich (`fileCount=1`, `totalBytes=221080`), `zipPlan` konnte die Datei lesen.
  - Limit-Test: `maxDownloadFiles=1` blockiert groesseres Album korrekt mit `album_download_file_limit_exceeded`; danach auf `1000` zurueckgesetzt.
  - Cleanup: SakuraAlbum-Alben, Photos-Alben, Queue, Cursor und Runs fuer `albentest` wieder auf 0 gesetzt.

Neueste operative Notiz (2026-05-06 21:48 CET):
- Lokaler Stand wurde erneut gegen die produktive Installation geprueft.
- Backup vor Live-Deployment angelegt: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-autosync-debug-20260506-214810/app/`.
- Wiederherstellungsprompt fuer Neustart: "Stelle SakuraAlbum aus Backup `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-autosync-debug-20260506-214810/app/` nach `/var/www/nextcloud/apps/sakuraalbum/` wieder her, setze Eigentümer `www-data:www-data`, fuehre `occ app:update --all` nicht aus, pruefe danach `occ app:list`, `occ route:list | grep sakuraalbum` und SakuraAlbum-Logs."
- Lokale Pruefung vor Deployment: `node --check js/admin-settings-026.js`, `node --check js/admin-settings.js`, `php -l lib/Service/AutoSyncService.php`, `./scripts/self-check.sh` erfolgreich.
- Admin-JS priorisiert jetzt den in dieser Nextcloud-Installation nachweislich funktionierenden Status-Endpunkt `/api/v1/admin/auto_status`, behaelt aber Fallbacks fuer neue Alias-Routen.
- Admin-JS normalisiert den gespeicherten Automatikmodus strikt auf `manual` oder `file_events`, damit UI-Speichern nicht durch unerwartete Werte auf Manuell zurueckfaellt.
- Live-Test mit `albentest`:
  - Testaccount wurde vor dem Test auf 0 SakuraAlbum-Alben/Queue/Cursor/Run-Zeilen gesetzt.
  - `occ files:put` nach `/albentest/files/Photos/_sakura_occ_event_20260506215253.txt` hat echte Nextcloud-Dateievents erzeugt.
  - Logs: `auto_sync_runner_nudged`, `auto_sync_dirty_path_recorded` fuer `created` und `written`, danach durch normalen Host-Cron `auto_sync_user_started`, `album_write_completed`, `auto_chunk_completed`, `auto_sync_user_completed`, `auto_sync_process_completed`.
  - Ergebnis: 4 verwaltete Photos-Alben fuer `albentest`, 145 Medienlinks, Queue danach leer, keine Fehler.
  - Cleanup danach: Testdateien entfernt, verwaltete Test-Alben/Photos-Alben/Queue/Cursor/Run-Zeilen fuer `albentest` wieder entfernt; Debug-Logs bleiben zur Analyse erhalten.
- WebDAV-Basic-Auth gegen `https://chaosnet.me/remote.php/dav/files/albentest/` lieferte trotz Passwortreset 401. Automatisierte Tests sollen lokal vorerst `occ files:put` verwenden; WebDAV/Auth ist ein separater Server-/Auth-Befund.

Neueste operative Notiz (2026-05-06):
- Diagnosepunkt für Auto-Status deutlich erweitert: Admin-UI zeigt jetzt explizit, ob Automatik durch globalen Schalter, Dateiaenderungs-Modus, Wartungsfenster, Cron-Healtcheck oder fehlenden Job-Record blockiert ist.
- Auto-Status-Route-Aufrufe prüfen jetzt mehrere kompatible Endpunkte inkl. `auto-status`, `auto-status/` und robustes Fallback bei älteren Admin-URL-Mustern.
- Fehlermeldungen bei 404/401/403 im Admin-UI enthalten jetzt einen direkten Handlungshinweis (Session, Berechtigung, Cache/Route).
- `AutoSyncService::queueStatus()` liefert `automationBlockingReason` mit den Werten `global_disabled`, `manual_mode`, `outside_window`, `missing_job_record`, `cron_not_recorded`, `cron_stale`.
- `Auto-Status`-UI gibt diese Infos als `sakuraalbum-warning` aus, damit der Unterschied zwischen "blockiert durch Konfiguration" und "stummem Laufzeitproblem" klar bleibt.

Aktueller Stand:
- Arbeitsbereich: 0.2.7-Entwicklungsstand mit behobener Auto-Sync-Einbindung in den Nextcloud-Cron (zeitnahe Verarbeitung nach Dateiänderungen).
- Geaenderte Dateien: `appinfo/routes.php`, `lib/Controller/AdminSettingsController.php`, `lib/Service/AutoSyncService.php`, `lib/BackgroundJob/AutoSyncJob.php`, `js/admin-settings-026.js`.
- Wichtige Verbesserungen:
  - Auto-Sync-Status/Process-Routen haben jetzt zusätzliche Alias-Varianten inkl. Slash-/Legacy-Formen.
  - JS-Admin-Frontend benutzt Fallback-Routen statt fester URL, reduziert 404-Miss bei geänderten Pfaden.
  - Job-Health-Lesen zeigt jetzt klarer Status (Bootstrapped/Konfig/Running) inkl. `job.stale` Diagnose-Hinweis.
  - `autoSyncJobHealth()` berechnet Staleness auf Basis von `max(last_checked, last_run)` und liefert `lastRunAgeSeconds`.
  - Diagnosefehler `autoSyncJobDiagnostic` prüft korrekt auf `status === "bootstrapped"`.
  - Auto-Sync-Queueing stößt jetzt einen background-nudge auf den Auto-Job an (`scheduleAfter`) und nutzt ein Throttle, damit neue Dateiänderungen automatisch im nächsten Lauf aufgenommen werden.
  - Auto-Status enthält zusätzlich Infrastrukturdaten zur Cron- oder Hintergrundmodus-Diagnose (`backgroundJobsMode`, `backgroundJobsLastCronAt`, `backgroundJobsCronHealthy`, `backgroundJobsCronReason`).
  - Admin-UI zeigt im Statusbereich neue Diagnosetexte zur Hintergrundjobs-Integrität und Cron-Health.
  - Auto-Sync-Job läuft jetzt als `TIME_SENSITIVE`, damit er außerhalb des Nextcloud-Wartungsfensters (globaler low-load-Kanal) korrekt ausgeführt wird.
  - `ensureAutoSyncRunnerQueued()` verwendet jetzt `scheduleAfter(time()+15)` statt fälschlicher Verzögerungssekundzahl.
  - Beim Nudge wird der Job-Foreground-Flag (`time_sensitive`) per DB auf `1` gesetzt, damit alte Installationen korrekt auf neue Semantik migriert werden.
  - Die Laststeuerung bei `run_after` nutzt jetzt ein hartes Debounce-Fenster (`15s`) und setzt den Job per `reset`/Fallback auf sofortige Verarbeitung.

Durchgefuehrt:
- Route-Alias-Pfade sind in `appinfo/routes.php` und `lib/Controller/AdminSettingsController.php` für:
  - `/api/v1/admin/auto-sync/status/`
  - `/api/v1/admin/auto-status`
  - `/api/v1/admin/auto-status/`
  - `/api/v1/admin/auto-sync/trigger/`
  - `/api/v1/admin/auto-sync/process-due/`
  - `/api/v1/admin/autosync/process-due/`
  - `/api/v1/admin/auto_sync/process-due/`
- JS nutzt dieselben Kandidatenlisten in `autoStatusRoutes` und `autoRunRoutes`.

Wesentlicher Befund:
- Ursache der letzten Blockade war die Kombination aus `TIME_INSENSITIVE` + `maintenance_window_start` (Nextcloud führt außerhalb der Low-Load-Phasen nur zeit-sensible Jobs aus).
- Zusätzlich war der persistente `time_sensitive`-Wert der bestehenden `oc_jobs`-Zeile auf `0` stehengeblieben; dadurch wurde der neu gesetzte Wert im Job-Code nicht wirksam.
- Testlauf hat bestätigt: nach Nudge mit `runAfter` und aktivierter Zeit-Sensitivität wird der Auto-Sync innerhalb von ~<20s im regulären Cron-Lauf verarbeitet.
- Die `auto_sync_process_completed`-Sequenz erscheint jetzt automatisch nach Dateiänderung in `Photos` (ohne manuelles `background-job:execute`).

Nächste Schritte:
- Repository weiterhin auf Produktivstatus ausrollen und dann 4-Minuten-Regressionstest fahren:
  - Testaccount-Upload im Ordner `Photos` (Textdatei oder Bilddatei),
  - Auto-Status kurz nach Upload beobachten (Nudge-Log `auto_sync_runner_nudged`, dann `auto_sync_process_completed` ohne manuelles Trigger),
  - 5 Minuten mit Cron beobachten, ob kein zusätzlicher Backlog entsteht.
- Danach gezielt Fenster-Szenarien prüfen:
  - globale `autoSyncWindowStart/End` gesetzt -> Verarbeitung erst im gewünschten Fenster,
  - hohe Event-Last -> `maxUsersPerRun`, `maxEventsPerRun` und Debounce-Effekt im Auto-Bericht prüfen.

Sicherheits-/Stabilitaetshinweis:
- Keine Änderung an produktiv kritischen Systemkomponenten ausser der App-Struktur.
- Keine sensiblen Secrets im Code ergänzt.
- Debug-Logging bleibt auf `debugMode` begrenzt; sensible Werte werden via `LogService` redacted.
