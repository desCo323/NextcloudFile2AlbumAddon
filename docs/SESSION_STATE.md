# SakuraAlbum Session State

Datum: 2026-05-07 19:35:46 CEST

Neueste operative Notiz (2026-05-07 20:00 CEST):
- Benutzer-UX-Befund fuer spaeteres Update aufgenommen:
  - Buttontexte wie `Alle verwalteten pruefen` und `Konto-Reset pruefen` sind in der UI zu blass und kaum lesbar.
  - Backlog-Eintrag `UI-11` in `docs/TEST_BACKLOG.md` ergaenzt: alle SakuraAlbum-Buttons auf Kontrast pruefen und Textfarben fuer normale, Hover-, Fokus-, Disabled-, Light- und Dark-Theme-Zustaende korrigieren.
  - Prioritaet `P0`, weil schlechte Lesbarkeit besonders bei Sicherheits-/Reset-Aktionen riskant ist.

Neueste operative Notiz (2026-05-07 19:56 CEST):
- Abschluss kontrolliertes Testfenster/Hotfix:
  - Finaler Production-Preflight auf sauberem Git-Stand erfolgreich; Live-Nextcloud gesund und SakuraAlbum `1.0.7` installiert.
  - Preflight-Artefaktlauf meldete `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.7.tar.gz` mit SHA256 `d586d100d4afd42590d1158e72d729913f0fcc3e8ed02cb051fabafecc27c68a`.
  - Commits `665bc2a Fix info-level diagnostic log writes` und `f72f500 Document 1.0.7 live test results` wurden auf GitHub `main` gepusht.
  - Secret-Scan vor Push ohne Treffer; `origin` weiterhin ohne eingebettetes Token.
  - Nach einem Neustart direkt mit Live-App `1.0.7` weiterarbeiten; naechste sinnvolle Arbeit ist Browser-UX-Testfenster fuer Benutzeroberflaeche, Quellordner-/Regelbedienung und CSV-Download-Buttons.

Neueste operative Notiz (2026-05-07 19:54 CEST):
- Kontrolliertes Testfenster abgeschlossen und wegen gefundenem Logging-Bug auf Hotfix `1.0.7` erweitert.
- Live-Status final:
  - Nextcloud gesund: `maintenance=false`, `needsDbUpgrade=false`.
  - SakuraAlbum live: `1.0.7`.
  - Admin-Automatik wieder aktiv: `autoSyncMode=file_events`, `debugMode=1`.
- 1.0.7-Backup:
  - Pfad: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-107-logfix-20260507-195033`.
  - Enthalten: Live-App vor 1.0.7, SQL-Dump, `occ`-Status/App-Liste, Restore-Prompt, SHA256SUMS.
- Tests nach 1.0.7:
  - `info`-Level-Log-Selbsttest erfolgreich: Log-ID `1631`, Event `info_log_write_self_test_1778176335`, Level `info`.
  - DB-Spalte `oc_sakuraalbum_logs.level` hat jetzt Default `info`.
  - Auto-Sync-Dateievent-Test erneut erfolgreich: Queue nach Dateioperationen `pending=1`, `changeCount=10`; Nextcloud BackgroundJob verarbeitet; danach `pending=0`, `failed=0`, 1 verwaltetes Album mit 1 Medium; Cleanup erfolgreich.
  - Abschlusspruefung `albentest`: 0 aktive SakuraAlbum-Alben, 0 Dirty-Paths, 0 Cursor, 0 Downloadjobs, 0 Photos-Alben, keine Testordner.
  - Nextcloud-Serverlog nach 1.0.7: keine `SakuraAlbum failed to write app log`-Eintraege mehr.
  - Nicht-SakuraAlbum-Befund: `files_versions`/Trashbin-Warnung zu `/mnt/clouddata/files_trashbin/versions` sowie Level-0 Deprecation/Lazy-Config-Notices waehrend UI-Polling; als operative Nacharbeit in `docs/TEST_BACKLOG.md` aufgenommen.
- Lokaler Git-Stand:
  - Hotfix-Commit `665bc2a Fix info-level diagnostic log writes` erstellt.
  - `./scripts/self-check.sh`, `git diff --check`, Secret-Scan und `./scripts/production-update.sh --preflight` erfolgreich.
  - Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.7.tar.gz`, SHA256 `7f306b0845f729def039db5165e8f3808a07dafe8c67e4224d053f338e58be37`.
  - Naechster Schritt: Dokumentation committen und GitHub pushen.

Neueste operative Notiz (2026-05-07 19:40 CEST):
- Kontrolliertes Testfenster SakuraAlbum 1.0.6 gestartet.
- Live-Ausgangszustand:
  - Nextcloud gesund: `maintenance=false`, `needsDbUpgrade=false`.
  - Live-App vor Deploy: `sakuraalbum 1.0.4`, aktiviert.
  - Admin-Automatik vor Deploy: `autoSyncMode=file_events`, `debugMode=1`.
- Backup vor Live-Aenderung:
  - Pfad: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-106-test-20260507-193858`.
  - Enthalten: Live-App-Verzeichnis, `occ`-Status/App-Liste, SakuraAlbum-Tabellenliste, SQL-Dump fuer `oc_sakuraalbum_*` plus relevante Nextcloud-Konfiguration/Jobs/Photos-Tabellen, `SHA256SUMS`, `RESTORE_PROMPT.txt`.
  - SHA256-Pruefung des Backups erfolgreich.
- Naechster Schritt im Testfenster: Deploy von lokalem Stand `1.0.6`, danach `occ upgrade/status`, Reset von `albentest`, Auto-Sync-Test und Debuglog-Auswertung.

Neueste operative Notiz (2026-05-07 19:49 CEST):
- Ergebnis nach Deploy/Test von `1.0.6`:
  - Deploy erfolgreich; Nextcloud danach gesund (`maintenance=false`, `needsDbUpgrade=false`), Live-App `1.0.6`.
  - Live-Regressionslauf mit `albentest` bestanden: Dry-Run 3 Alben/3 Links, Auto-Sync initial erfolgreich, fehlendes verwaltetes Photos-Album automatisch neu aufgebaut, Album-Export abgeschlossen, finaler Reset sauber.
  - Echter Dateievent-Test bestanden: Datei erstellt/geschrieben/umbenannt/geloescht, Queue `pending=1` mit `changeCount=10`, AutoSyncJob per Nextcloud Background-Job-Executor verarbeitet, danach `pending=0`, `failed=0`, 1 verwaltetes Album mit 1 Medium.
  - Nach Cleanup: `albentest` hat 0 aktive SakuraAlbum-Alben, 0 Dirty-Paths, 0 Cursor, 0 Downloadjobs, 0 Photos-Alben und keine Testordner.
- Debuglog-Auswertung:
  - SakuraAlbum-App-Logs fuer das Testfenster enthalten keine neuen Fehler; eine Warnung `auto_sync_missing_managed_album_refresh_queued` ist erwarteter Testfall fuer Wiederaufbau geloeschter verwalteter Alben.
  - Nextcloud-Serverlog zeigte jedoch neue Fehler `SakuraAlbum failed to write app log` mit `Field 'level' doesn't have a default value`.
  - Ursache: `AppLog::$level` hatte PHP-Default `info`; bei `info`-Logs markiert der Entity-Mapper das Feld nicht als geaendert und insertet es nicht.
- Fix vorbereitet als `1.0.7`:
  - `AppLog::$level` Default auf leer geaendert, damit jeder gesetzte Level persistiert.
  - Neue Migration `Version100700Date20260507180000` setzt DB-Default `level=info` fuer bestehende Installationen.
  - Version/Assets/Doku auf `1.0.7` angehoben; lokale `./scripts/self-check.sh` und `git diff --check` erfolgreich.
  - Naechster Schritt: 1.0.7 deployen, Info-Log-Schreibtest wiederholen, Nextcloud-Serverlog erneut auf SakuraAlbum-Fehler pruefen.

Neueste operative Notiz (2026-05-07 19:35 CEST):
- Nacharbeit zum Lizenz-/GitHub-/Testbacklog-Block:
  - `./scripts/production-update.sh --preflight` erneut erfolgreich: PHP/JS-Syntax, `appinfo/info.xml` gegen offizielles Nextcloud-Schema, Sicherheits-Smokes, Nextcloud-Status und Artefaktbau gruen.
  - Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.6.tar.gz` mit gemeldetem Hash `0cb0ebe77558a7b63b763e8d493c7c4aceb967390f1eec60a375e1caefd72d6c`.
  - Commit `0642009 Add preview license and test backlog` wurde auf GitHub `main` gepusht.
  - Remote geprueft: `origin` ist weiterhin `https://github.com/desCo323/NextcloudFile2AlbumAddon.git` ohne eingebettetes Token.
  - Remote-Head geprueft: `0642009512e5df80bcdf69e95a9e63c33a7de12e refs/heads/main`.
  - Secret-Scan auf GitHub-Token/Testpasswortmuster ohne Treffer.
  - Arbeitsbaum nach Push sauber.

Neueste operative Notiz (2026-05-07 19:32 CEST):
- Benutzerauftrag: vorlaeufige nichtkommerzielle Lizenz einfuegen, GitHub-Seite weiter ausbauen, manuelle Installation/Updates erklaeren, Tests/UX-Backlog planen und mit Testnutzer erste Checks spielen.
- Lizenz:
  - Neue `LICENSE.md`: "SakuraAlbum Preliminary Development and Evaluation License"; deutsche Fassung ist massgeblich, englische Zusammenfassung enthalten.
  - Kernaussagen: unfertige Entwicklungs-/Evaluierungsversion, keine kommerzielle Nutzung ohne schriftliche Erlaubnis, keine Weiterverbreitung ohne Erlaubnis, alle Rechte vorbehalten, keine Gewaehrleistung, Haftung soweit gesetzlich zulaessig ausgeschlossen.
  - `composer.json` auf `proprietary` gesetzt.
  - `appinfo/info.xml` behaelt technisch/schema-kompatibles `AGPL-3.0-or-later`, beschreibt aber den vorlaeufigen Lizenzstatus; offizielle Nextcloud-Dokumentation verlangt fuer App-Store-Verteilung AGPL-3.0-or-later oder kompatibel. Store-Release ist mit aktueller `LICENSE.md` blockiert, bis formal relicensed wurde.
- GitHub-Seite/Dokumentation:
  - `README.md` und `README.en.md` um Lizenzhinweis, Use Cases, Strukturregel-Diagramm, Architekturdiagramm, manuelle Installation ohne Store und Update-Ablauf erweitert.
  - `docs/UPDATE_POLICY.md` und `docs/STORE_RELEASE_CHECKLIST.md` um Lizenz-/Store-Blocker ergaenzt.
  - Neues `docs/TEST_BACKLOG.md`: Preflight, Funktions-, Sicherheits-, UX-/Lesbarkeits-Tests, UX-Verbesserungsbacklog, naechstes kontrolliertes Testfenster und Ergebnisvorlage.
- Checks lokal:
  - `./scripts/self-check.sh` erfolgreich, inkl. offizieller Nextcloud-XML-Schema-Pruefung.
  - `git diff --check` erfolgreich.
  - ASCII-Scan fuer geaenderte Doku erfolgreich.
  - Secret-Scan auf GitHub-Token/Testpasswortmuster ohne Treffer.
- Kontrollierter Live-Smoke mit `albentest`:
  - Backup vor Test: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-license-doc-smoke-20260507-193126`.
  - Restore-Prompt liegt in `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-license-doc-smoke-20260507-193126/RESTORE_PROMPT.txt`.
  - Backup enthaelt Live-App, `occ`-Status/App-Liste, SakuraAlbum-Tabellen, `oc_appconfig`, `oc_preferences`, `oc_jobs`, SHA256SUMS; Checksum-Pruefung im Backup-Verzeichnis erfolgreich.
  - Live-Smoke: isolierter Ordner `/Photos/SakuraAlbumV1Smoke`, 1 Testbild, Dry-run 1 Album/1 Link, Write 1 Album/1 Link, ZIP-Prepare 1 Datei/68 Bytes, Reset erfolgreich.
  - Nachpruefung: Nextcloud gesund (`maintenance=false`, `needsDbUpgrade=false`), `albentest` hat 0 Photos-Alben, 0 Photos-Albumlinks, 0 Dirty-Paths, 0 Cursor, 0 Downloadjobs, 0 aktive SakuraAlbum-Alben; historische SakuraAlbum-Zeilen sind nur `deleted`.
  - Abgedeckt: `FUN-01`, `FUN-11`, `FUN-13` baseline pass. Nicht abgedeckt: Browser-UX, mobile Layouts, Auto-Sync-Dateievents, CSV-Endpunkte und 1.0.6-spezifische UI-Health-Diagnose bis zum kontrollierten 1.0.6-Deploy.

Neueste operative Notiz (2026-05-07 19:17 CEST):
- Benutzerauftrag: pruefen, ob die SakuraAlbum-Debuglogs auf der Live-Installation arbeiten.
- Live-Pruefung ohne Album-/Dateiaenderung:
  - Nextcloud gesund: `maintenance=false`, `needsDbUpgrade=false`.
  - Live-Debug-Konfiguration: `debugMode=1`, `debugRetentionDays=14`, `debugMaxContextLength=8000`.
  - Vorhandene Debug-Aktivitaet bestaetigt: aktuelle `auto_sync_process_completed`-Eintraege werden regelmaessig in `sakuraalbum_logs` geschrieben.
  - Harmloser Testeintrag ueber `LogService::debug()` fuer Benutzer `albentest` erzeugt und direkt aus der Datenbank gelesen: Log-ID `1508`, Event `debug_logging_self_test_1778174225`, Level `debug`, Message `Debug logging self-test.`
  - Redaction funktioniert: Kontextfeld `token` wurde als `"[redacted]"` gespeichert, normales Feld `plain` blieb sichtbar.
  - Letzte Stunde enthaelt 8 SakuraAlbum-`debug`-Logs; keine Aenderung an Alben, Dateien, Queue oder Benutzerkonfiguration.

Neueste operative Notiz (2026-05-07 19:13 CEST):
- Benutzerauftrag: GitHub-Projektseite werbewirksam und informativ gestalten, deutsch als Hauptseite und separate englische Version, Funktionen/Ablaufe bei Bedarf illustrieren.
- Umsetzung:
  - `README.md` wurde zur deutschen GitHub-Startseite umgebaut: Logo, Badges, klare Produktpositionierung, Nutzenvergleich, Feature-Tabelle, Mermaid-Ablaufdiagramme, Benutzer-/Admin-Erlebnis, Sicherheitsmodell, Diagnose, Album-Downloads, Status, Installation, Dokumentationslinks und Roadmap.
  - Neue `README.en.md` als eigenstaendige englische Version mit gleicher Struktur und Link zur deutschen Hauptseite.
  - Die Darstellung nutzt vorhandenes `img/app.svg` und GitHub-kompatible Mermaid-Diagramme; keine Live-Nextcloud-Aenderung.
- Checks:
  - `./scripts/self-check.sh` erfolgreich.
  - `git diff --check` erfolgreich.
  - Secret-Scan auf GitHub-Token/Testpasswortmuster ohne Treffer.
- GitHub:
  - Commit `30a8fba Improve GitHub project page` wurde auf `main` gepusht.
  - Repository-Beschreibung per GitHub API aktualisiert: "SakuraAlbum: Nextcloud app for automatically maintained Photos albums from folders, with previews, auto-sync, exports, and diagnostics."
  - Topics gesetzt: `albums`, `automation`, `diagnostics`, `nextcloud`, `nextcloud-app`, `nextcloud-photos`, `photos`, `php`, `self-hosted`, `sakuraalbum`.
  - `origin` enthaelt keinen Token; Secret-Scan bleibt ohne Treffer.
- Naechster Schritt:
  - Bei Bedarf spaeter echte UI-Screenshots oder kurze Demo-Grafiken ergaenzen, sobald die Live-Version `1.0.6` kontrolliert ausgerollt und visuell geprueft wurde.

Neueste operative Notiz (2026-05-07 19:01 CEST):
- Benutzerauftrag: Debug-/Diagnose-Logging so erweitern, dass Fehler, haengende Verarbeitung, Queue-/Cron-/Cursor-/Export-Probleme und Betriebsstoerungen aus Logs sicher erkannt werden koennen; Logging in der aktuellen Live-Version aktiv halten; Logs fuer Tests immer nutzen und als CSV herunterladbar/serverseitig verfuegbar machen.
- Live-Status ohne Code-Deployment:
  - Nextcloud gesund: `maintenance=false`, `needsDbUpgrade=false`.
  - SakuraAlbum Live-Debug ist aktiv: `debugMode=1`, `debugRetentionDays=14`, `debugMaxContextLength=8000`; `debugMode` wurde idempotent erneut gesetzt (`Config value were not updated`).
  - Live-Logauswertung fand Debug-Aktivitaet und konkrete Warn-/Fehlerhistorie: u.a. `auto_sync_file_event_failed` bei `renamed_source`/`OC\Files\Node\NonExistingFile`, fruehere `album_export_job_failed` aus Tests und erwartete Warnungen fuer fehlende verwaltete Testalben. Diese Befunde muessen bei jedem Test vor/nachher geprueft werden.
- Lokaler Entwicklungsstand `1.0.6`:
  - Neuer `OperationalHealthService` bewertet Debug-Status, globale Auto-Sync-Gates, Wartungsfenster, fehlgeschlagene/ueberfaellige Queue-Eintraege, stale Processing-Locks, haengende Runs, fehlgeschlagene/stale Cursor, fehlgeschlagene/stale Album-Exports, aktuelle Warn-/Fehlerlogs, fehlende verwaltete Photos-Alben und Nextcloud-Cron-Alter.
  - Diagnoseberichte fuer Admins und Benutzer enthalten jetzt `health`; Controller loggen zusammengefasste Health-Befunde maschinenlesbar, ohne Health-Warnungen durch eigene Health-Logs dauerhaft selbst zu erzeugen.
  - Neue CSV-Exports: `/api/v1/admin/diagnostics/logs.csv` und `/api/v1/diagnostics/logs.csv`; UI-Buttons `CSV herunterladen` und `Fehlerlog CSV`. Jeder Export enthaelt Health-Befunde, Queue-Samples, Runs und App-Logs und speichert zusaetzlich eine Kopie in Nextcloud AppData unter `appdata_*/sakuraalbum/diagnostics/csv`.
  - Auto-Sync-File-Events wurden gegen nicht mehr existierende Rename/Delete-Source-Nodes gehaertet: der Benutzer wird bei Bedarf aus dem Pfad `/user/files/...` abgeleitet, damit `renamed_source` nicht mehr als generischer Eventfehler endet.
  - Version/Assets auf `1.0.6`: `admin-settings-106.js`, `personal-settings-106.js`, `appinfo/info.xml`, `package.json`, Changelogs, README, Admin Guide, Testplan und Self-Check aktualisiert.
- Checks bisher:
  - `php -l` fuer neue/geaenderte Services/Controller erfolgreich.
  - `node --check` fuer Admin-/Personal-JS erfolgreich.
  - `git diff --check` erfolgreich.
  - `./scripts/self-check.sh` erfolgreich.
  - `./scripts/production-update.sh --preflight` erfolgreich; Artefakt liegt unter `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.6.tar.gz`. Hash bei Bedarf direkt mit `sha256sum` neu pruefen, weil diese Sitzungsdatei selbst Teil des Artefakts ist.
- GitHub:
  - Code-Commit `9eca259 Add operational diagnostic exports` wurde auf `main` gepusht.
  - `origin` enthaelt keinen Token, Secret-Scan auf GitHub-Tokenmuster bleibt ohne Treffer.
- Naechster Schritt:
  - Live-Deployment von `1.0.6` erst in einem kontrollierten Backup-/Updatefenster. Danach sofort Admin-/Personal-Diagnosebericht und CSV ziehen und die Health-Befunde gegen Live-Logs pruefen.

Neueste operative Notiz (2026-05-07 00:59 CEST):
- Benutzerauftrag: alle Photos-Alben des Nextcloud-Benutzers `frithjofe` loeschen.
- Sicherheitsumfang:
  - Es werden nur Eintraege der Nextcloud-Photos-Albumtabellen geloescht, keine Mediendateien im Filesystem.
  - Vorbefund: Benutzer existiert; Nextcloud gesund (`maintenance=false`, `needsDbUpgrade=false`); Photos `6.0.0`, SakuraAlbum `1.0.4`.
  - Betroffen: 90 Photos-Alben, 18.983 Album-Dateiverknuepfungen, 0 Album-Collabs.
  - SakuraAlbum fuer `frithjofe`: keine aktiven Tracking-Datensaetze, keine Dirty-Queue.
- Backup vor Loeschung:
  - `/home/cloud/sakuraalbum-backups/photos-albums-frithjofe-pre-delete-20260507-005920/`
  - Enthalten: `occ-status-before.txt`, `user-info-before.txt`, `albums-before.json`, selektive SQL-Dumps fuer `photos_albums`, `photos_albums_files`, `photos_albums_collabs`, `sakuraalbum_albums`, `db-meta.json`, `SHA256SUMS`.
  - Wiederherstellungsprompt: "Importiere bei Bedarf die SQL-Dumps aus `/home/cloud/sakuraalbum-backups/photos-albums-frithjofe-pre-delete-20260507-005920/` in die Nextcloud-Datenbank, beginnend mit `photos_albums_frithjofe.sql`, danach `photos_albums_files_frithjofe.sql` und `photos_albums_collabs_frithjofe.sql`; pruefe danach `sudo -u www-data php /var/www/nextcloud/occ status` und zaehle `photos_albums` fuer `frithjofe`."
- Loeschung ausgefuehrt:
  - Geloescht ueber `OCA\\Photos\\Album\\AlbumMapper::delete()` fuer die 90 vorher gesicherten Album-IDs.
  - Ergebnis: `deletedAlbums=90`, `remainingAlbums=0`, `remainingLinksForDeletedIds=0`, `remainingCollabsForDeletedIds=0`.
  - Nachpruefung: Nextcloud weiterhin gesund (`maintenance=false`, `needsDbUpgrade=false`); fuer `frithjofe` `owned_photos_albums=0`, `links_for_owned_albums=0`, `collabs_for_owned_albums=0`, `sakura_dirty=0`, `sakura_tracked=0`.
  - Backup-Pruefsummen mit `sha256sum -c SHA256SUMS` erfolgreich.

Neueste operative Notiz (2026-05-07 00:43 CEST):
- Benutzer meldet nach `1.0.4`: keine sichtbaren Alben, Personal-Status zeigt nur `wartend 1`.
- Live-Befund ohne Eingriff:
  - Nextcloud gesund: `maintenance=false`, `needsDbUpgrade=false`, SakuraAlbum `1.0.4`.
  - `globalEnabled=1`, `autoSyncMode=file_events`, `jobIntervalMinutes=5`.
  - System-Cron ist aktiv und `www-data` ruft alle 5 Minuten `/usr/local/sbin/nextcloud-cron-lowprio` auf.
  - SakuraAlbum-Queue: `albentest`, Pfad `Photos`, `settings_update`, `pending`, `last_seen_at=1778107269`.
  - Entprellung 60s macht den Eintrag ab `1778107329` faellig; der letzte Cron war `1778107201`, also vor Faelligkeit. Naechster Schritt: auf den naechsten echten Cron warten und pruefen, ob er ohne manuellen Trigger verarbeitet.
- Ergebnis nach echtem Cron um 2026-05-07 00:45 CEST:
  - Cron verarbeitete automatisch ohne manuellen Trigger: `processedUsers=1`, `succeededUsers=1`, Queue danach leer.
  - SakuraAlbum erstellte 4 Photos-Alben fuer `albentest`: `Photos` (5 Dateien), `Photos - OrnerinPhotos 1` (3 Dateien), `Photos - testbilder` (136 Dateien), `Photos - _sakura_live_20260506191458` (1 Datei).
  - Detail-Log `auto_sync_user_completed`: `foldersScanned=14`, `filesScanned=158`, `mediaFiles=145`, `plannedAlbums=4`, `createdAlbums=4`, `linkedFiles=145`, `fileErrors=0`, `albumErrors=0`, `durationMs=717`.
  - Wichtige Diagnosekorrektur: erfolgreiche verwaltete Alben haben Status `synced`, nicht `active`; direkte SQL-Zaehler muessen `status != deleted` verwenden oder `ManagedAlbumMapper::countActiveForUser()`.

Neueste operative Notiz (2026-05-07 00:34 CEST):
- Benutzerauftrag in diesem Block: Personal-UI einfacher machen, Ordner-Ausnahmen mit eigener Tiefe/Zusammenfassen/Auslassen bauen, spaetere Einstellungswechsel zerstoerungsfrei abgleichen, grosse Album-Downloads als Hintergrundjobs mit ZIP-Teilen ab 1 GiB ergaenzen und den lang bestehenden Auto-Sync-/Cron-Fehler endgueltig nachweisen.
- Implementierter Stand `1.0.4`:
  - Personal-UI hat eine einfachere Struktur mit Quellordnern, `Ordner-Regeln`, Vorschau/Status, sicherem Reset und `Album-Downloads`.
  - `folderRules` speichern Unterordner-Ausnahmen separat von den Standardwerten: `depth`, `single_album` und `exclude`. Die Plan-/Sync-Logik waehlt die passendste aktive Regel je Pfad.
  - Sync-Konfigurationshash enthaelt die Ordnerregeln; dadurch werden spaetere Aenderungen erkannt. Neue App-Alben werden erzeugt, alte eindeutig SakuraAlbum-verwaltete Albumcontainer koennen ueber die Admin-Sicherheitsoption bereinigt werden; Mediendateien werden nicht geloescht.
  - Auto-Sync erkennt jetzt aktive SakuraAlbum-Tracking-Zeilen, deren `photos_album_id` extern aus `photos_albums` verschwunden ist, merkt betroffene Benutzer sofort als faellig vor und loggt `auto_sync_missing_managed_album_refresh_queued`.
  - Neuer Exportpfad: SakuraAlbum- und native Photos-Alben koennen als Hintergrundjob nach `/SakuraAlbum Exports/<Album>-<JobId>/` geschrieben werden. Ab 1 GiB wird in `.partNNN.zip` geteilt; `.nomedia`/`.noimage` verhindern Rekursion in SakuraAlbum-Scans.
  - Konto-Reset loescht jetzt auch Downloadjob-Datensaetze und die passenden Nextcloud-Queue-Eintraege fuer `AlbumExportJob`.
- Frisches Live-Backup vor finalem Regressionstest:
  - Backup: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-final-regression-20260507-002408/`.
  - Enthalten: App-Verzeichnis, `occ-status-before.txt`, `occ-app-list-before.txt`, `db-relevant-before-test.sql`, `SHA256SUMS`.
  - Wiederherstellungsprompt: "Stelle `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-final-regression-20260507-002408/app` nach `/var/www/nextcloud/apps/sakuraalbum` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-final-regression-20260507-002408/db-relevant-before-test.sql`, fuehre `sudo -u www-data php /var/www/nextcloud/occ upgrade` aus, pruefe `occ status`, `occ app:list | grep sakuraalbum`, `occ background-job:list | grep SakuraAlbum` und setze Maintenance aus."
- Finale Live-Tests mit `albentest`:
  - `scripts/live-regression-104.php` lief erfolgreich: Testdaten erzeugt, 3 Alben/3 Links geplant, Auto-Sync Erstlauf erzeugte 3 verwaltete Alben, extern geloeschtes Photos-Album wurde im naechsten Lauf erkannt und repariert (`missingManagedAlbumsSeen=1`, `missingManagedAlbumUsersQueued=1`), Exportjob `jobId=4` wurde abgeschlossen, finaler Reset loeschte 3 Photos-Alben, 1 Cursor, 1 Downloadjob und 1 Export-Queue-Eintrag.
  - Zusaetzlicher echter Nextcloud-Hintergrundjob-Test: Queue fuer `/Photos/SakuraAlbumBgJobRegression` vorbereitet, `occ background-job:execute 80842478984863745 --force-execute` ausgefuehrt, danach 2 verwaltete Alben erstellt; nach externer Albumloeschung reparierte ein weiterer echter Background-Job-Lauf die fehlende Photos-Album-ID.
  - Cleanup danach: `active=0`, `dirty=0`, `cursors=0`, `downloads=0`, `missing=0` fuer `albentest`; Nextcloud `maintenance=false`, `needsDbUpgrade=false`, AutoSyncJob ist als einziger SakuraAlbum-Job registriert.
- Lokale Checks nach finalem Queue-Reset-Fix: `./scripts/self-check.sh`, `git diff --check`, `php -l lib/Service/AccountResetService.php`, `php -l lib/Db/DownloadJobMapper.php` erfolgreich.
- Finales Artefakt neu gebaut und entpackt erneut mit `./scripts/self-check.sh` geprueft: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.4.tar.gz`, SHA256 `f133d976add2a81b356fda9bf45f8b9e35f225a225ddff88f05b434d7991c0e1`.
- GitHub aktualisiert: Commit `22e708325b63dac05d412cbb46e7cb1d3255b6d3` wurde auf `main` nach `https://github.com/desCo323/NextcloudFile2AlbumAddon` gepusht. `origin` enthaelt keinen Token; `rg "ghp_[A-Za-z0-9_]+" -n .` findet nichts.

Vorherige operative Notiz (2026-05-06 23:45 CEST):
- Benutzerauftrag: Personal-UI vereinfachen, Unterordner-Regeln mit eigener Tiefe/Auslassen/Zusammenfassen ergaenzen, zerstoerungsfreie Anpassung nach spaeteren Einstellungswechseln sicherstellen, Album-Downloads als Hintergrundjob fuer SakuraAlbum- und native Photos-Alben inklusive grosser ZIP-Teile ab 1 GiB bauen, und den lang bestehenden Auto-Sync-Fehler endgueltig finden.
- Auto-Sync-Befund aus Live-Logs/DB:
  - Der vorherige 1.0.3-Fix verarbeitet faellige Dirty-Queue-Eintraege korrekt; `albentest` hatte nach Cron-Lauf 4 aktive SakuraAlbum-Alben und 144 Medienlinks.
  - Direkter WebDAV-PROPFIND auf Photos-Alben zeigte die erzeugten Alben.
  - Plausible Restursache fuer den vom Benutzer gemeldeten Fall "alle Alben geloescht, danach keine Neuerstellung": Extern geloeschte Photos-Alben erzeugen keinen Datei-Event in den Quellordnern. SakuraAlbum kann fehlende verwaltete Photos-Album-IDs reparieren, wenn ein Lauf startet, aber bisher wurde ohne Datei-/Settings-Event kein Lauf vorgemerkt.
- Umsetzung in Arbeit fuer `1.0.4`:
  - `ManagedAlbumMapper` erkennt aktive SakuraAlbum-Tracking-Zeilen, deren `photos_album_id` nicht mehr in `photos_albums` existiert.
  - `AutoSyncService` prueft diese Luecke am Anfang jedes Hintergrundlaufs, merkt betroffene Benutzer sofort als faellig vor und loggt `auto_sync_missing_managed_album_refresh_queued`.
  - Datei-Event-Ignores bekommen detaillierteren Match-Kontext, damit falsche Quellpfad-Zuordnung im Log erkennbar ist.
  - Benutzer-Einstellungen speichern nun `folderRules`; Plan- und Sync-Services beruecksichtigen Unterordner-Regeln fuer eigene Tiefe, ein Album oder Auslassen.
  - Veraltete SakuraAlbum-verwaltete Albumstrukturen koennen beim Sync ueber alle aktiven App-Alben hinweg sicher bereinigt werden, wenn der Admin die sichere Bereinigung aktiviert; Mediendateien werden dabei nicht geloescht.
  - Naechster Teil dieses Blocks: asynchroner Album-Export in Benutzerdateien mit Jobstatus, nativen Photos-Alben, SakuraAlbum-Alben und Teil-ZIP-Dateien ab 1 GiB.
- Zwischenstand Code (2026-05-06 23:58 CEST):
  - Neue Tabelle/Entity/Mapper: `sakuraalbum_download_jobs`, `DownloadJob`, `DownloadJobMapper`.
  - Neuer Hintergrundjob: `AlbumExportJob`, nicht parallel, startet einzelne Exportjobs ueber Job-Argument `jobId`.
  - Neuer Service/Controller/Routen: `AlbumExportService`, `AlbumExportController`, `/api/v1/albums/export/albums`, `/jobs`, `POST /export`, `GET /export/download`.
  - Exportjobs validieren Besitzer und Quelle, schreiben ZIPs nach `/SakuraAlbum Exports/<Album>-<JobId>/`, setzen `.nomedia` und `.noimage`, splitten oberhalb 1 GiB in `.partNNN.zip`, loggen Start, Teilabschluss, Erfolg und Fehler.
  - Personal-UI hat jetzt `Album-Downloads` mit Albumliste, Jobliste, Fortschrittsbalken, Teil-ZIP-Links und Polling fuer laufende Jobs.
  - README, Benutzer- und Admin-Doku beschreiben Ordner-Ausnahmen, Hintergrunddownloads und die fehlende-verwaltete-Alben-Erkennung.
  - Version auf `1.0.4` angehoben; cache-busting Assets `admin-settings-104.js` und `personal-settings-104.js` erzeugt.
  - Lokale Checks bis hier: PHP-Syntax, JS-Syntax, `git diff --check`, `./scripts/self-check.sh`, Artefakt-Build und entpacktes Artefakt-Self-Check erfolgreich.
  - Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.4.tar.gz`, SHA256 `2c165d669a75917d5f94b8106fcb77802b1669df5477a17ec75e427cc7e5e64a`.
  - Live-Testfenster vorbereitet:
    - Backup: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-export-rules-20260506-235950/`.
    - Enthalten: App-Verzeichnis, `occ-status-before.txt`, `occ-app-list-before.txt`, `db-relevant-before-test.sql`, `SHA256SUMS`.
    - Wiederherstellungsprompt: "Stelle `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-export-rules-20260506-235950/app` nach `/var/www/nextcloud/apps/sakuraalbum` wieder her, setze Eigentümer `www-data:www-data`, importiere bei Bedarf `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.4-export-rules-20260506-235950/db-relevant-before-test.sql`, pruefe danach `sudo -u www-data php /var/www/nextcloud/occ upgrade`, `occ status`, `occ app:list | grep sakuraalbum`, `occ background-job:list | grep SakuraAlbum` und setze Maintenance aus."
  - Live-Deployment `1.0.4` ausgefuehrt:
    - Nextcloud kurz in Maintenance gesetzt, Paket nach `/var/www/nextcloud/apps/sakuraalbum` synchronisiert, `occ upgrade` erfolgreich, Maintenance wieder aus.
    - Status danach: `maintenance=false`, `needsDbUpgrade=false`, SakuraAlbum `1.0.4`.
    - Neue Export-Routen sind in `occ route:list` vorhanden.
Neueste operative Notiz (2026-05-06 23:24 CEST):
- Benutzer meldet: Personal-UI zeigt `1 wartend`, `Naechster Lauf fruehestens ...` verschiebt sich scheinbar weiter und startet nicht.
- Befund:
  - Dirty-Queue: `albentest`, Pfad `Photos`, `settings_update`, `pending`, `last_seen_at=2026-05-06 23:19:33`.
  - Cron lief um `23:20:03`; wegen 60s Entprellzeit war der Eintrag erst ab `23:20:33` faellig.
  - SakuraAlbum-Job setzte nach diesem fruehen Leerlauf `last_run=23:20:03`; Nextcloud plant Intervalljobs danach erst wieder nach 300s (`23:25:03`).
  - Status setzte `nextDueAt=max(now,nextDueAt)`, wodurch fällige Arbeit bei jedem UI-Refresh wie ein wandernder "fruehestens"-Zeitpunkt aussah.
- Umsetzung in Arbeit fuer `1.0.3`:
  - Auto-Sync-Nudge wird nach `debounceSeconds + 1` statt pauschal nach 15s geplant.
  - `forceAutoSyncRunnerToRunSoon()` faellt auch nach Nextcloud-API-Reset in die direkte DB-Korrektur durch, damit `last_run + Intervall` nicht weiter blockiert.
  - `queueStatus()` und `queueStatusForUser()` liefern das echte `nextDueAt` ohne `max(now, ...)`.
  - Admin- und Personal-UI zeigen bei fälligen Einträgen stabil `Faellig, wartet auf Cron` statt einer wandernden Zeit.
- Abschluss dieses Blocks:
  - Backup vor Deployment: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.3-autosync-schedule-20260506-232502/`.
  - Wiederherstellungsprompt: "Stelle `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-1.0.3-autosync-schedule-20260506-232502/app` nach `/var/www/nextcloud/apps/sakuraalbum` wieder her, setze Eigentümer `www-data:www-data`, pruefe `occ upgrade`, `occ status` und die gesicherten `oc_appconfig_sakuraalbum.tsv`/`oc_sakuraalbum_dirty_paths.tsv`."
  - `1.0.3` live ausgerollt; `occ upgrade` erfolgreich; Nextcloud danach `maintenance=false`, `needsDbUpgrade=false`, SakuraAlbum `1.0.3`.
  - Bestehender wartender Eintrag von `23:19:33` wurde nach Deployment per echtem Low-Priority-Cron verarbeitet: `processedUsers=1`, `succeededUsers=1`, Queue danach 0.
  - Ergebnis der Erstverarbeitung fuer `albentest` `/Photos`: 4 verwaltete Alben, 144 Medienlinks, `fileErrors=0`, `albumErrors=0`.
  - Regression fuer den Langzeitfehler: Direkt nach erfolgreichem Lauf erneut `queueUserRefresh('albentest')` ausgefuehrt. Neuer Jobzustand war korrekt `last_run=0`, `last_checked=now+61s`; nach Entprellzeit verarbeitete `/usr/local/sbin/nextcloud-cron-lowprio` den Eintrag sofort. Ergebnis: `processedUsers=1`, `succeededUsers=1`, `alreadyLinkedFiles=144`, `linkedFiles=0`, Queue 0, offene Cursor 0.
  - Die 4 aktiven verwalteten Alben fuer `albentest` wurden bewusst stehen gelassen, damit der Benutzer sie im finalen UI-Test sehen kann.

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

Neueste operative Notiz (2026-05-07):
- Anlass: Benutzer meldete, dass das Oeffnen von Alben im Browser bei `frithjofe` sehr lange dauert.
- Befund aus Logs/DB:
  - `/var/www/nextcloud/data/nextcloud.log` ist leer; SakuraAlbum-App-Logs zeigen keine harten Fehler fuer diesen Vorgang.
  - PHP-FPM meldete in der Vergangenheit Lastspitzen und `pm.max_children`, aktuell aber keinen neuen PHP-Fatal-Fehler.
  - Apache-Access-Log zeigt beim Photos-Albumaufruf viele `/apps/photos/api/v1/preview/...`-Requests.
  - Photos-Alben von `frithjofe` enthalten sehr grosse SakuraAlbum-Alben, u.a. `S - Lebensabschnitte` mit 9470 Medien, `S - Freunde und Kollegen` mit 6073 Medien und `S - Kunst` mit 3309 Medien. Solche Alben laden in Nextcloud Photos langsam, weil sehr viele Vorschaubilder angefragt werden.
  - SakuraAlbum-Einstellungsseite erzeugte zusaetzlich starres Statuspolling alle 5 Sekunden; bei mehreren offenen Tabs entstanden parallele Statusanfragen.
- Umsetzung im Arbeitsstand 1.0.5:
  - `js/personal-settings-104.js` und das cache-busting Release-Asset `js/personal-settings-105.js` verhindern ueberlappende Statusanfragen, pausieren bei unsichtbaren Tabs und pollen im Idle nur noch alle 30 Sekunden statt alle 5 Sekunden.
  - Grosse verwaltete Alben werden im Benutzer-UI als `gross` ab 1000 Medien bzw. `sehr gross` ab 5000 Medien markiert.
  - Die verwaltete-Alben-Ansicht zeigt einen Performance-Hinweis mit Empfehlung, Album-Tiefe oder Ordner-Regeln zu nutzen, damit Nextcloud Photos kleinere Alben laden muss.
  - `appinfo/info.xml`, `package.json`, `CHANGELOG.md`, `CHANGELOG.en.md`, `lib/Settings/Admin.php`, `lib/Settings/Personal.php` und die JS-Assets `admin-settings-105.js`/`personal-settings-105.js` auf 1.0.5 vorbereitet.
- Live-Hotfix:
  - Backup vor Deployment: `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-performance-105-20260507-123710/app`.
  - Geaenderte Live-Dateien: `js/personal-settings-104.js`, `css/settings.css`, `CHANGELOG.md`, `CHANGELOG.en.md`.
  - Die zunaechst mitkopierte Versionserhoehung auf 1.0.5 hat Nextcloud korrekt als ausstehenden App-Upgrade-Zustand erkannt (`needsDbUpgrade: true`). Zur Stabilisierung wurde die Live-Version in `appinfo/info.xml` und `package.json` sofort wieder auf 1.0.4 gesetzt.
  - Nach Ruecksetzung: `occ status` meldet `maintenance: false` und `needsDbUpgrade: false`; `occ app:list` zeigt `sakuraalbum: 1.0.4`.
- Wiederherstellungsprompt fuer Neustart:
  - "Stelle SakuraAlbum aus `/home/cloud/sakuraalbum-backups/sakuraalbum-pre-performance-105-20260507-123710/app` nach `/var/www/nextcloud/apps/sakuraalbum/` wieder her, setze Eigentümer `www-data:www-data`, pruefe danach `sudo -u www-data php /var/www/nextcloud/occ status` und stelle sicher, dass `maintenance: false` und `needsDbUpgrade: false` sind."

Neueste operative Notiz (2026-05-07):
- Projektregel ab jetzt: SakuraAlbum wird als produktiv genutzte Nextcloud-App behandelt. Jede Korrektur und Erweiterung laeuft ueber versionierte Updates, Backups, Preflight, kontrollierten Deploy und dokumentierten Rollback.
- Neu angelegt:
  - `docs/UPDATE_POLICY.md` mit Produktionsannahmen, Versionierung, Datenkompatibilitaet, verbotenen Update-Mustern, Standard-Release-Flow, Deploy-Guardrail, Rollback, Testpolitik und Dokumentationspflichten.
  - `scripts/production-update.sh` als produktiver Update-Gatekeeper.
- Verhalten des Update-Skripts:
  - Standard: `./scripts/production-update.sh --preflight` prueft Versionen, App-Metadaten, Self-Check, Build-Artefakt, Token-Scan, sauberen Git-Stand und Nextcloud-Status.
  - Deploy: `SAKURAALBUM_PRODUCTION_UPDATE=1 ./scripts/production-update.sh --deploy` ist absichtlich explizit, legt zuerst ein Backup an, synchronisiert die App, setzt Eigentümer, fuehrt bei Bedarf das Nextcloud-Upgrade aus und gibt einen Restore-Prompt aus.
- README und Store-Release-Checkliste verweisen jetzt auf die Update-Policy und den Preflight.
- Naechste sichere Regel: keine normalen Live-Hotfixes mehr ohne Version/Preflight; Hotfix ohne Version nur bei Produktionsrettung, mit Backup und anschliessender Aufnahme in den naechsten normalen Release.

Neueste operative Notiz (2026-05-07 22:06 CEST):
- Auftrag: Sicherheitspruefung und Haertung gegen missbraeuchliche Requests, Ressourcenangriffe und Diagnose-/Log-Missbrauch, ohne bestehende SakuraAlbum-Funktionen zu beschaedigen.
- Bisherige Audit-Befunde:
  - Controller deaktivieren CSRF nicht; Admin-Routen nutzen `AuthorizedAdminSetting`, persoenliche Routen verwenden die authentifizierte Benutzer-ID.
  - Kritische Schreib-/Loeschoperationen besitzen weiterhin exakte Bestaetigung und serverseitig gespeicherte Dry-Run-Fingerprints.
  - Haertungspotenzial besteht bei sehr langen Request-Pfaden, unbegrenzter serverseitiger Diagnose-CSV-Ablage, zu detailreichem Preview-Debug-Kontext und grossen Hintergrund-Albumexporten.
- Geplanter Patchblock:
  - Pfadnormalisierung mit Laengen-/Segmentgrenzen.
  - Diagnose-CSV mit Zellgroessenbegrenzung und AppData-Aufbewahrungsgrenze.
  - Hintergrund-Exports mit separaten Admin-Limits fuer Datei- und Bytezahl, plus besserer Tempfile-Bereinigung bei Fehlern.
  - Preview-Logging nur noch mit Zusammenfassung statt rohem Settings-Payload.
  - Sicherheitskonzept und konkrete Angriffsszenarien in der Dokumentation/Backlog festhalten.
- Kein Live-Deploy/Testfenster gestartet; produktive Nextcloud-Instanz wird in diesem Patchblock nicht veraendert.

Fortschritt (2026-05-07 22:15 CEST):
- Code-Haertung umgesetzt im Arbeitsstand 1.0.8:
  - `PathHelper` blockiert nun zu lange Pfade, zu lange Segmente, zu tiefe Pfade, Kontrollzeichen, NUL und Traversal.
  - `DiagnosticCsvExportService` begrenzt CSV-Zellen, schuetzt vor Spreadsheet-Formel-Injection und entfernt alte/ueberzaehlige AppData-CSV-Kopien.
  - `LogService` loescht periodisch alte SakuraAlbum-App-Logs gemaess `debugRetentionDays`.
  - `PreviewController` loggt nur noch eine Settings-Zusammenfassung.
  - `SettingsService`/Admin-UI haben neue Hintergrund-Exportlimits `maxExportFiles` und `maxExportBytes`.
  - `AlbumExportService` prueft Exportlimits vor Queue und erneut vor Ausfuehrung, sampled grosse Albumlisten und bereinigt Tempfiles bei Fehlern.
  - `AlbumExportController` setzt sichere `Content-Disposition`-Header mit ASCII-Fallback und UTF-8-Dateiname.
- Dokumentation ergaenzt:
  - `docs/SECURITY_MODEL.md` mit Sicherheitsprinzipien und Angriffsszenarien.
  - `SECURITY.md`, README, Admin-/User-Guide und `docs/TEST_BACKLOG.md` aktualisiert.
- Version vorbereitet: `appinfo/info.xml`, `package.json`, Settings-Assets auf `1.0.8`; neue JS-Assets `admin-settings-108.js` und `personal-settings-108.js`.
- Noch offen in diesem Block: Syntax-/Self-Check, Build-Artefakt, Token-Scan, ggf. Preflight; kein Live-Deploy ohne kontrolliertes Backup-Testfenster.

Validierung (2026-05-07 22:20 CEST):
- Neuer Smoke-Test `tests/Smoke/SecuritySmokeTest.php` prueft die Pfad-Haertung gegen Traversal, NUL, Kontrollzeichen, zu lange Segmente, zu viele Segmente und zu lange Pfade.
- Der Smoke-Test fand zuerst eine echte Reihenfolge-Luecke: `trim()` haette fuehrende NUL-/Kontrollzeichen im Segment entfernt. `PathHelper` prueft Kontrollzeichen jetzt vor dem Trimmen des Pfads.
- `git diff --check`: bestanden.
- `bash scripts/self-check.sh`: bestanden inkl. PHP-/JS-Syntax, App-Metadata-Schema, Smoke-Tests, Token-Scan, CSRF-Check, Fingerprint-Guards, Auto-Sync-Safety und neuen Security-Hardening-Checks.
- `bash scripts/build-artifact.sh`: bestanden; Artefakt `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.8.tar.gz`, SHA256 `0d15cca4a75b033b3a13b0dfeca8e77cc8afcad951931d159e3c91ebf1de57e3`.
- Kein produktiver Deploy ausgefuehrt; Live-Testfenster fuer 1.0.8 bleibt der naechste Schritt nach Commit/Push und Backup.

Abschluss Sicherheitsblock (2026-05-07 22:28 CEST):
- Commit erstellt: `04cd68b Harden SakuraAlbum security controls`.
- `bash scripts/production-update.sh --preflight`: bestanden.
  - Enthielt erneuten Self-Check, Build-Artefakt und Secret-Scan.
  - Nextcloud-Status: `installed: true`, `version: 33.0.3.2`, `maintenance: false`, `needsDbUpgrade: false`.
  - Preflight-Artefakt: `/home/cloud/NextcloudFile2AlbumAddon-work/artifacts/sakuraalbum-1.0.8.tar.gz`, SHA256 `694285607df9a1452d6984ac903fdac4e8deaa07785a3719e4f613ab0e9a6976`.
- GitHub-Push auf `main` erfolgreich: `318d863..04cd68b`.
- Nach Push geprueft:
  - `git remote -v` enthaelt nur `https://github.com/desCo323/NextcloudFile2AlbumAddon.git`, kein Token.
  - Secret-Scan nach GitHub-PAT-Mustern und Testpasswort-Muster fand keine Treffer.
- Kein Live-Deploy ausgefuehrt; naechster sicherer Schritt ist ein kontrolliertes 1.0.8 Backup-/Deploy-/Smoke-Testfenster mit `albentest`.
