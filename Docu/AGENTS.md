# PrivateBar – Anweisungen für Codex

## Auftrag

Baue und pflege **PrivateBar**, eine private, nicht kommerzielle Webanwendung für genau eine Hausbar. Die Anwendung kennt den vorhandenen Barbestand und findet Drinks, die damit zubereitet werden können.

Diese Datei ist für Version 1 verbindlich. Bei Widersprüchen gilt folgende Reihenfolge:

1. die aktuelle ausdrückliche Anweisung des Benutzers,
2. diese Datei,
3. bestehende und nachweislich absichtliche Projektkonventionen,
4. eigene technische Annahmen.

Triff kleine technische Detailentscheidungen selbstständig, wenn sie die hier beschriebene Funktion nicht verändern. Dokumentiere wesentliche Annahmen. Erweitere den Funktionsumfang nicht eigenmächtig.

## Sprache und Schreibweise

- Benutzeroberfläche, Hilfetexte und Fehlermeldungen sind auf Deutsch.
- Verwende Schweizer Rechtschreibung ohne `ß`.
- Zeitzone ist `Europe/Zurich`; CET und CEST müssen automatisch korrekt behandelt werden.
- Datums-, Zeit- und Zahlenformat entsprechen der deutschsprachigen Schweiz.
- Flüssigkeitsmengen werden bevorzugt in `cl` angezeigt, auch bei kleinen Werten wie `0.5 cl`.
- Speichere Zeitpunkte intern in UTC und konvertiere nur bei der Anzeige.

## Zielumgebungen

### Lokal

- Raspberry Pi 4 Model B mit mindestens 2 GB RAM; 4 GB RAM sind empfohlen.
- Raspberry Pi OS 64-Bit in einer aktuell unterstützten stabilen Version.
- Mindestens 32 GB Datenspeicher. Bevorzugt wird eine USB-SSD; alternativ ist eine hochwertige High-Endurance-microSD-Karte zulässig.
- Verwende ein stabiles 5-V-/3-A-Netzteil sowie ein passiv gekühltes Gehäuse oder eine leise aktive Kühlung.
- 10-Zoll-Touchscreen, 1920 × 1200 Pixel, Querformat.
- Automatischer Start im Vollbild-Kioskmodus ohne sichtbare Browserleisten oder Desktop.
- Zugriff zusätzlich über Browser im Heimnetz.
- Vollständige Kernfunktion ohne Internetverbindung.

### Online

- Cyon Webhosting Single.
- Öffentliche Adresse: `https://privatebar.vonrufs.ch`.
- Bereitstellung per SSH oder als vorbereitetes Paket über my.cyon-Dateimanager und Cronjobs.
- HTTPS mit Let’s Encrypt ist verpflichtend.
- Keine dauerhaft laufenden Worker, WebSocket-Server oder Node-/Python-Dienste voraussetzen.

## Verbindlicher Technologie-Stack

- PHP 8.3.
- Laravel 13 beziehungsweise die bei Implementierungsbeginn aktuelle, unterstützte und mit PHP 8.3 kompatible Laravel-Version.
- Blade für die Oberflächen.
- MariaDB lokal und bei Cyon.
- Composer mit festgeschriebener `composer.lock`.
- Möglichst wenig clientseitiges JavaScript; keine schwere SPA ohne nachgewiesenen Bedarf.
- Frontend-Dateien werden vor der Bereitstellung kompiliert.
- Dieselbe Codebasis läuft lokal und online. Verwende klar benannte Umgebungsmodi und Feature-Schalter.
- Keine direkte MariaDB-Replikation zwischen Pi und Cyon.

## Architekturprinzipien

- Organisiere den Laravel-Code nach Fachbereichen, nicht als Sammlung grosser Controller.
- Trenne Domänenlogik, Persistenz, externe Anbieter, HTTP/API und Darstellung.
- Kapsle TheCocktailDB, OpenDrinks, Open Food Facts und Azure Translator hinter eigenen Schnittstellen.
- Externe Anbieterobjekte dürfen nicht direkt zum internen Domänenmodell werden.
- Verwende stabile UUIDs für alle synchronisierbaren Datensätze.
- Import- und Synchronisationsoperationen müssen idempotent und wiederholbar sein.
- Validierung findet immer serverseitig statt; clientseitige Validierung ist nur zusätzliche Bedienhilfe.
- Geheimnisse gehören ausschliesslich in geschützte Umgebungskonfigurationen und nie in Git, HTML oder Browser-JavaScript.
- Lege nur die notwendigen Daten im öffentlichen Webroot ab. Laravel muss über `public/index.php` bereitgestellt werden.

## Ressourcenregeln

- Der Raspberry Pi 4 Model B mit 2 GB RAM ist die massgebliche Leistungsgrenze.
- Keine Microservices, Redis-Pflicht, Suchserver oder dauerhaften Queue-Worker.
- Hintergrundaufgaben in kurze, fortsetzbare und sperrbare Schritte teilen.
- Datenbankabfragen indexieren, paginieren und auf N+1-Probleme prüfen.
- Bilder verkleinern und komprimieren; keine vollständige Fotobibliothek in den Arbeitsspeicher laden.
- Protokolle begrenzen und rotieren.
- Produktionsbetrieb verwendet Laravel-Konfigurations-, Routen- und View-Caches sowie optimierten Composer-Autoloader.
- Normale Ansichten müssen auf dem Pi innerhalb von zwei Sekunden laden.
- Suche und Filter müssen innerhalb einer Sekunde reagieren.
- Ein Hintergrundfehler darf Kiosk und Rezeptanzeige nicht blockieren.

## Verantwortlichkeit der Instanzen

### Cyon ist führend für

- persönliche Onlinekonten, Sitzungen und Einladungen,
- externe Rezeptimporte,
- automatische Übersetzungen,
- den normalisierten öffentlichen Rezeptkatalog.

### Der Raspberry Pi ist führend für

- Kioskmodus und lokale gemeinsame PIN,
- SMB-Fotoquelle und Fotocache,
- Monitor- und Ruhezeitsteuerung,
- lokale Systemkonfiguration.

### Bidirektional synchronisiert werden

- konkrete Barprodukte und deren Zutatenzuordnungen,
- Einkaufsliste,
- Favoriten,
- Bewertungen,
- eigene und angepasste Rezepte,
- Rezeptfotos eigener Rezepte,
- gemeinsame nicht sensible Einstellungen.

Private Fotorahmenbilder, SMB-Zugangsdaten, Kiosk-PIN und lokale Systemkonfiguration dürfen Cyon nie erreichen.

## Synchronisationsprotokoll

- Der Pi kommuniziert ausschliesslich ausgehend über eine versionierte JSON-API unter HTTPS.
- Versioniere die API ab `/api/v1`.
- Das Gerät verwendet eigene widerrufbare Zugangsdaten; keine Benutzerpasswörter als Geräteauthentifizierung.
- Synchronisiere automatisch alle zehn Minuten, beim lokalen Neustart und nach Wiederherstellung der Internetverbindung.
- Biete lokal unter Einstellungen **„Jetzt synchronisieren“** an.
- Zeige dort letzten erfolgreichen Lauf, aktuellen Zustand und Fehlerdetails.
- Hintergrund- und Synchronisationsfehler erscheinen nicht als globale Warnung auf der Startseite.
- Verwende einen dauerhaften Outbox-/Inbox-Mechanismus, Cursor, Idempotenzschlüssel, Versionsnummern und Löschmarkierungen.
- Bei Konflikten gewinnt die zuletzt bestätigte Änderung.
- Bewahre den Änderungsverlauf mit Zeitpunkt und Akteur auf, damit Konflikte nachvollziehbar bleiben.
- Lokale Kioskänderungen erhalten einen eindeutigen Systemakteur, da dort keine Person angemeldet ist.
- Der vollständige importierte Rezeptkatalog wird von Cyon zum Pi übertragen und dort offline gehalten.
- Synchronisation ist unterbrechbar und darf nach einem Abbruch ohne Doppelungen fortgesetzt werden.
- Änderungen am API-Schema müssen mindestens eine vorherige Clientversion unterstützen.

## Wartungsmodus

- Der Wartungsmodus wird in den lokalen Einstellungen aktiviert.
- Er sperrt Anwendung, API, Änderungen, Rezeptimport und Synchronisation vollständig.
- Sichtbar bleibt nur eine Wartungsseite.
- Nur direkt am Raspberry Pi kann der Wartungsmodus nach erneuter Eingabe der sechsstelligen Kiosk-PIN beendet werden.
- Dafür darf ein minimaler, streng abgesicherter lokaler Wartungs-Endpunkt erreichbar bleiben.
- Verwende den Wartungsmodus insbesondere bei einer Wiederherstellung der Cyon-Datenbank.

## Zugang und Sicherheit

### Lokaler Zugang

- Gemeinsame sechsstellige numerische Kiosk-PIN.
- PIN wird pro Browser/Gerät beim ersten Zugriff und nach Browser- oder Geräteneustart erneut verlangt.
- Aufwecken aus dem Fotorahmen verlangt keine PIN.
- Speichere nur einen sicheren Hash der PIN.
- Nach acht falschen Eingaben: fünf Minuten Sperre.
- Die Sperre muss einen Neustart überstehen.
- Sensible lokale Einstellungen verlangen immer eine erneute PIN-Eingabe.

### Onlinekonten

- Anmeldung mit E-Mail-Adresse und Passwort.
- Onlinezugriff erfordert eine bestätigte E-Mail-Adresse; das gilt auch für bestehende und per Installation angelegte Konten.
- Bestätigungs-E-Mails werden ausschliesslich auf Cyon per SMTP versendet. Signierte Links sind an Konto und aktuelle E-Mail-Adresse gebunden und 30 Minuten gültig.
- Erneuter Versand ist einmal pro Minute möglich. SMTP-Fehler erscheinen direkt am Vorgang; Zugangsdaten und Zugangslinks werden nicht in Mail-Logs geschrieben.
- Passwort mindestens zwölf Zeichen; keine erzwungenen Zeichenklassen.
- Keine Zwei-Faktor-Anmeldung in Version 1.
- Keine öffentliche Registrierung.
- Alle Mitglieder haben dieselben Rechte.
- Jedes Mitglied darf andere Mitglieder entfernen und deren Sitzungen widerrufen.
- Das letzte Mitglied darf nicht entfernt werden.
- Das eigene Konto darf nur gelöscht werden, wenn mindestens ein anderes Mitglied bestehen bleibt.
- Nach zwei fehlgeschlagenen Anmeldungen: 30 Minuten Sperre für die Kombination aus E-Mail-Adresse und Quell-IP.
- Verwende Laravel-Passwort-Hashing, sichere Cookies, CSRF-Schutz, Session-Rotation, HTTPS und Rate Limiting.

### Einladungen und Passwort-Reset

- Ein bestehendes Mitglied erstellt eine Einladung für eine konkrete E-Mail-Adresse.
- Einladungen werden per SMTP an die angegebene E-Mail-Adresse versendet. Link und QR-Code bleiben zusätzlich zum Kopieren beziehungsweise Scannen sichtbar.
- Nach Annahme einer Einladung bestätigt das neue Mitglied seine E-Mail-Adresse über eine separate Bestätigungs-E-Mail.
- Schlägt der Einladungsversand fehl, wird die erzeugte Einladung widerrufen und ein erneuter Versuch angeboten.
- Passwort-Resetlinks bleiben manuell teilbar; es gibt weiterhin keinen öffentlichen Passwort-Resetversand.
- Einladung ist einmal verwendbar und 30 Minuten gültig.
- Offene Einladungen werden mit Ablaufzeit angezeigt und können widerrufen werden.
- Ein Mitglied kann für ein anderes Mitglied einen einmal verwendbaren, 30 Minuten gültigen Reset-Link samt QR-Code erzeugen.
- Wenn niemand mehr angemeldet ist, kann ein Reset-Link nur direkt am Pi nach Kiosk-PIN erzeugt werden.
- Speichere Einladungs- und Reset-Token ausschliesslich gehasht.

## Kerndatenmodell

Plane mindestens getrennte Entitäten für:

- `users`, `sessions`, `invitations`, `password_reset_tokens`,
- `devices` und widerrufbare Gerätezugänge,
- `ingredients`, Synonyme, Kategorien und gerichtete `ingredient_substitutions`,
- konkrete `products` mit Barcode, Marke, Produktname, Bild und optionalem Alkoholgehalt,
- `product_ingredient_mappings` und manuelle Korrekturen,
- `bar_inventory` ohne Mengen- oder Flaschenzählung,
- `recipes`, `recipe_sources`, Originalfassungen, Übersetzungen und Versionen,
- `recipe_ingredients` mit Menge, Einheit, Pflicht/optional/Garnitur,
- `recipe_ratings`, gemeinsame `favorites`,
- `shopping_list_items`,
- `sync_events`, Cursor, Tombstones und Audit-Einträge,
- getrennte lokale und synchronisierte Einstellungen,
- lokalen Fotoindex und Fotocache-Metadaten.

Wähle konkrete Tabellennamen konsistent. Verwende Fremdschlüssel, eindeutige Constraints und Indizes. Eine Barcode-Zuordnung darf nicht unkontrolliert doppelt entstehen.

## Barbestand und Zutaten

- Bestand kennt nur vorhanden oder nicht vorhanden.
- Keine Restmengen, Flaschengrössen oder Anzahl identischer Flaschen verfolgen.
- Zeige konkrete Flaschen detailliert mit Marke, Produktname, Barcode, Bild und zugeordneter allgemeiner Cocktailzutat.
- Mehrere unterschiedliche Produkte dürfen dieselbe Zutat erfüllen.
- Eine Zutat gilt als vorhanden, solange mindestens ein zugeordnetes Produkt vorhanden ist.
- Produkte lassen sich einzeln entfernen.
- Automatisch vorhandene Grundzutaten sind vorkonfiguriert und in den Einstellungen bearbeitbar.
- Unterstütze Zutaten-Synonyme und normalisierte kanonische Zutaten.
- Liefere eine kuratierte Grundliste von Ersatzzutaten aus.
- Ersatzregeln sind gerichtet, bearbeitbar und deaktivierbar.
- Mehrere Ersetzungen in einem Rezept sind erlaubt.

## Barcode-Scanner

- Barcodeerfassung gehört zu Version 1 und ist für Smartphones optimiert.
- Nutze die Kamera nur in einem sicheren HTTPS-Kontext.
- Bevorzuge die native Browsererkennung und verwende bei Bedarf einen kleinen, lokal gebündelten Fallback.
- Primäre Produktquelle ist Open Food Facts über offizielle API-Endpunkte.
- Sende einen klar identifizierenden User-Agent gemäss Anbieteranforderungen.
- Cache Treffer und beachte API-Limits sowie Lizenzen.
- Unterstütze Spirituosen, Liköre, Sirupe, Säfte, Bitter, Softdrinks und alkoholfreie Alternativen.
- Zeige vor dem Hinzufügen eine Bestätigung mit Barcode, Produktname, Marke, Bild, vorgeschlagener allgemeiner Zutat und Alkoholgehalt.
- Nutzer dürfen Zuordnung und Alkoholgehalt korrigieren.
- Korrekturen werden dauerhaft bevorzugt.
- Unbekannte Produkte sind manuell erfassbar.
- Erneuter Scan eines vorhandenen Barcodes bietet nach Bestätigung das Entfernen an.
- Keine privaten Korrekturen oder Fotos ohne ausdrückliche Zustimmung zu Open Food Facts hochladen.
- Quellen- und Lizenzhinweis muss sichtbar sein.

## Rezepte und Import

- Hauptquelle: TheCocktailDB über offizielle API-Endpunkte.
- Ergänzung: MIT-lizenziertes OpenDrinks-Dataset.
- Cyon importiert standardmässig einmal täglich.
- Zeitpunkt und Frequenz sind in den gemeinsamen Einstellungen konfigurierbar.
- Importiere inkrementell, in kleinen Batches und transaktional.
- Fehlerhafte Läufe dürfen bestehende Rezepte nicht beschädigen.
- Speichere Quelle, externe ID, Lizenz, Originalsprache, Originaltext, Importzeitpunkt und Quelländerungsstand.
- Führe Dubletten anhand normalisierter Namen, Zutaten und Quell-IDs kontrolliert zusammen.
- Zeige neue Importrezepte sofort und markiere sie 14 Tage als **„Neu“**.
- Rezepte können für den gesamten Haushalt ausgeblendet und später wieder eingeblendet werden.
- Verwende TheCocktailDB-Bilder nur bei nachvollziehbar zulässiger Nutzung.
- Nutze andernfalls passende Wikimedia-Commons-Bilder mit Lizenznachweis oder einen lokalen Platzhalter.
- Importierte Rezept- und Lizenzdaten werden zu Cyon gespeichert und zum Pi gespiegelt.

## Übersetzung und Normalisierung

- Vorgesehener Dienst: Microsoft Azure Translator F0.
- Implementiere eine austauschbare `TranslationProvider`-Schnittstelle.
- API-Schlüssel bleibt serverseitig auf Cyon.
- Übersetze nur neue oder geänderte Originaltexte und cache das Resultat dauerhaft.
- Verwende ein eigenes Cocktailglossar für Zutaten, Gläser und Zubereitungsbegriffe.
- Noch nicht übersetzte Rezepte bleiben sichtbar, zeigen den Originaltext und die Kennzeichnung **„Übersetzung ausstehend“**.
- Mitglieder können eine deutsche Übersetzung manuell ergänzen oder korrigieren.
- Manuell bearbeitete Übersetzungen dürfen nie automatisch überschrieben werden.
- Normalisiere Mengen metrisch; bewahre die ursprüngliche Angabe auf.
- Zeige Mengen nur für ein Glas; keine Portionsskalierung in Version 1.

## Eigene und angepasste Rezepte

- Mitglieder dürfen eigene Rezepte erstellen und bearbeiten.
- Ein importiertes Rezept wird nur als Haushaltskopie angepasst; das Original bleibt unverändert.
- Eigene Rezepte und Kopien werden bidirektional synchronisiert.
- Ein Rezeptfoto kann am Smartphone aufgenommen oder ausgewählt werden.
- Akzeptiere JPEG, PNG und WebP; HEIC wird verständlich abgelehnt.
- Verkleinere, beschneide und komprimiere Bilder serverseitig.
- Speichere die Originalaufnahme nicht dauerhaft.
- Synchronisiere komprimierte eigene Rezeptbilder zu Cyon.

## Machbarkeitslogik

Berechne für jedes Rezept exakt einen Hauptstatus:

1. **Machbar:** alle Pflichtzutaten exakt vorhanden.
2. **Mit Ersatz machbar:** keine Pflichtzutat fehlt, aber mindestens eine wird über eine gerichtete Ersatzregel erfüllt.
3. **Fast machbar:** höchstens zwei Pflichtzutaten fehlen.
4. **Nicht machbar:** mindestens drei Pflichtzutaten fehlen.

Optionale Zutaten und Garnituren blockieren die Machbarkeit nicht. Zeige im Rezept klar, welche Zutaten vorhanden, ersetzt, optional oder fehlend sind.

## Suche, Sortierung und Filter

Unterstütze Filter nach:

- Machbarkeit,
- alkoholisch oder alkoholfrei,
- Basisspirituose,
- Geschmacksrichtung,
- Zubereitungsart.

Gruppiere Ergebnisse standardmässig nach Machbarkeit: machbar, mit Ersatz machbar, fast machbar, übrige. Innerhalb einer Gruppe ist die Standardsortierung die Beliebtheit. Alphabetische Sortierung ist auswählbar.

Beliebtheit entspricht dem absteigenden Durchschnitt der Mitgliederbewertungen. Unbewertete Rezepte folgen nach den bewerteten. Bei gleichem Durchschnitt alphabetisch sortieren.

## Bewertungen und Favoriten

- Jedes persönliche Onlinekonto darf pro Rezept genau eine Bewertung von einem bis fünf Sternen abgeben.
- Die eigene Bewertung ist jederzeit änderbar.
- Zeige nur den Durchschnitt, nicht die Anzahl der Bewertungen.
- Im gemeinsamen Kiosk ohne persönliche Anmeldung ist die Bewertung nur sichtbar, nicht bearbeitbar.
- Favoriten sind für den ganzen Haushalt gemeinsam.
- Favoriten beeinflussen die Beliebtheitssortierung nicht.
- Zubereitungen und Zubereitungszeitpunkte werden nicht protokolliert.

## Zufallsrezept

- Die Startseite zeigt eine zufällige vollständig machbare Tagesempfehlung.
- Biete eine grosse Touch-Schaltfläche mit dem exakten Text **„Ich weiss nicht“**.
- Wähle ausschliesslich vollständig machbare Rezepte ohne Ersatz und ohne fehlende Pflichtzutaten.
- Alkoholische und alkoholfreie Rezepte nehmen gemeinsam teil.
- Bewertung beeinflusst die Zufallsauswahl nicht.
- Schliesse die letzten sechs Zufallsvorschläge aus, solange genügend andere Rezepte vorhanden sind.

## Alkoholgehalt

- Zeige im Rezeptdetail einen geschätzten Alkoholgehalt in `% vol`.
- Berechne ohne Wasserverdünnung durch Eis, Schütteln oder Rühren.
- Grundformel: Summe aus Zutatenvolumen × Zutaten-ABV geteilt durch gesamtes berücksichtigtes Flüssigkeitsvolumen.
- Fehlt der Produktwert, verwende einen fest hinterlegten typischen Kategorienwert.
- Kategorienwerte sind nicht in der Oberfläche bearbeitbar.
- Der Alkoholgehalt einer konkreten Flasche ist bei Barcodebestätigung korrigierbar.
- Kennzeichne das Resultat als Näherungswert.
- Kein Suchfilter nach Alkoholstärke.

## Einkaufsliste

- Fehlende Zutaten eines Rezepts lassen sich einzeln oder gemeinsam hinzufügen.
- Liste ausschliesslich bekannte Rezeptzutaten; keine freien Artikel in Version 1.
- Gruppiere nach Kategorien wie Spirituosen, Säfte, Sirupe und Garnituren.
- Sortiere innerhalb einer Kategorie alphabetisch.
- Wird eine Zutat als gekauft markiert, entferne sie aus der Liste und füge sie dem Barbestand hinzu.
- Wenn zur gekauften Zutat noch kein konkretes Produkt bekannt ist, lege einen klar markierten generischen Bestandseintrag an, der später durch einen Barcode ersetzt werden kann.
- Freie Notizen pro Listeneintrag gehören erst zu Version 2.

## Leerer Barbestand

Zeige bei leerem Bestand den exakten Hinweis **„Bar bitte füllen“** sowie die Aktionen **„Flasche scannen“** und **„Manuell hinzufügen“**.

## Benutzeroberfläche

- Ausschliesslich dunkles Design; kein heller Modus.
- Visuelle Richtung: mediterran, warm, verspielt und hochwertig.
- Farben: dunkle Basis, Terrakotta, Zitronengelb, Olivgrün und Türkis.
- Touchziele mindestens ungefähr 44 × 44 CSS-Pixel mit ausreichendem Abstand.
- Keine Funktion darf Hover voraussetzen.
- Zustände für Fokus, Aktiv, Gedrückt und Deaktiviert müssen klar sein.
- Keine horizontale Seitennavigation auf regulären Ansichten.
- Zielmonitor verwendet eine grosse touchfreundliche Seitennavigation.
- Smartphoneansicht verwendet eine kompakte Navigation.
- Hauptbereiche: Start, Machbar, Entdecken, Meine Bar, Einkaufsliste, Favoriten, Einstellungen.
- Semantisches HTML, Tastaturbedienung, sichtbare Fokusmarkierungen und ausreichende Kontraste sind verpflichtend.
- Respektiere `prefers-reduced-motion`.
- Binde wesentliche Schriftarten, Symbole und Frontend-Ressourcen lokal ein.
- Hintergrundfehler erscheinen nur in Einstellungen. Direkte Formular- oder Aktionsfehler müssen trotzdem am betroffenen Vorgang verständlich angezeigt werden.
- Keine Altersabfrage, kein allgemeiner Konsumhinweis und keine Allergenkennzeichnung.

## Fotorahmen

- Vollständiger Bestandteil von Version 1.
- Startet standardmässig nach fünf Minuten ohne Interaktion; Wert ist konfigurierbar.
- Während kritischer Dialoge, Updates und Wartung nicht starten.
- Erste Berührung beendet nur den Fotorahmen und darf keine darunterliegende Aktion auslösen.
- Danach ohne Neuladen zur vorherigen Ansicht zurückkehren.
- Keine Uhr- oder Datumsanzeige über den Fotos.
- Bilder zufällig anzeigen und unmittelbare Wiederholungen vermeiden.
- Standardanzeigedauer zehn Sekunden; konfigurierbar.
- Weiche Überblendung, standardmässig eine Sekunde; konfigurierbar und ressourcenschonend.
- Bilder immer vollständig und ohne Beschnitt zeigen; freie Flächen sind schwarz.
- Quelle ist eine rekursive, nur lesbare SMB-Freigabe im Intranet.
- SMB-Server, Freigabe, Unterpfad, Benutzername und Passwort sind ausschliesslich lokal konfigurierbar.
- Biete **„Verbindung testen“** an.
- Speichere SMB-Passwort verschlüsselt und zeige es nie wieder im Klartext.
- Aktualisiere Fotoindex täglich, bei Neustart und nach Änderung des Pfads.
- Überspringe beschädigte und nicht unterstützte Dateien.
- Unterstütze JPEG, PNG und WebP; kein HEIC.
- Lokaler Cache ist standardmässig auf 2 GB begrenzt und konfigurierbar.
- Entferne bei Platzbedarf die am längsten nicht verwendeten Cachekopien; Originale nie verändern.
- Bei nicht erreichbarer Freigabe den vorhandenen Cache weiterverwenden.
- Fotorahmenbilder niemals zu Cyon übertragen.

## Monitor-Ruhezeit

- Ein-/Ausschaltplan ist lokal konfigurierbar und standardmässig deaktiviert.
- Während der Ruhezeit darf eine Berührung den Monitor aufwecken.
- Danach bleibt der Monitor 29 Minuten aktiv.
- Jede weitere Interaktion startet die 29 Minuten erneut.
- Implementiere notwendige lokale Betriebssystemintegration als kleinen, dokumentierten Dienst; die Webanwendung bleibt die steuernde Oberfläche.

## Logo und Poster für Version 1

Erstelle beziehungsweise integriere:

- ein vollständiges PrivateBar-Logo mit Schriftzug,
- eine vereinfachte quadratische Variante für Browser-Icon und App-Symbol,
- eine hochauflösende A3-Posterversion im Hochformat bei 300 dpi.

Stil: verspielt-illustrativ, mediterran, französische Riviera der 1950er/60er Jahre. Motive: Barutensilien, Flaschen, Gläser, Bar und Meerblick. Keine Menschen, kein Slogan. Das Poster zeigt eine dekorative Barszene; das Logo bleibt darin klar erkennbar. Bewahre eine skalierbare Masterdatei und druckfähige Exportdatei auf.

## Updates und Bereitstellung

- Privates GitHub-Repository.
- Releases werden manuell als geprüfte Git-Tags beziehungsweise GitHub-Releases freigegeben.
- Ein Merge allein darf keine Bereitstellung auslösen.
- Kein separates öffentliches Staging-System.
- Aktualisiere zuerst Cyon, danach den Pi.
- Cyon-API bleibt während des Übergangs mit der vorherigen Pi-Version kompatibel.
- Cyon wird separat per SSH oder nach dokumentiertem Paket-/Cron-Ablauf aktualisiert.
- Lokale Einstellungen bieten **„Update prüfen“** und **„Freigegebene Version installieren“**.
- Vor lokaler Installation Kompatibilität, Signatur/Prüfsumme, freien Speicher und Migrationen prüfen.
- Bei fehlgeschlagenem Pi-Update automatisch zur vorherigen Programmversion zurückkehren.
- Migrationen müssen transaktional, rückwärtskompatibel oder mit sicherem Wiederanlauf entworfen sein.
- Ein Release ist blockiert, solange Tests, statische Analyse oder Formatprüfung fehlschlagen.

## Backupentscheidung

- PrivateBar erstellt in Version 1 keine eigenen automatischen Datenbanksicherungen.
- Die Wiederherstellungsquelle ist das vom Benutzer vorausgesetzte Cyon-Hosting-Backup.
- Dokumentiere, dass Synchronisation kein Backup ersetzt und Cyon keine bestimmten Sicherungspunkte garantiert.
- Eine Wiederherstellung muss im Wartungsmodus durchgeführt und danach kontrolliert zum Pi synchronisiert werden.

## Version 1 – verbindlicher Umfang

- Lokale Pi-Anwendung, Kiosk, Touchoberfläche und Offlinebetrieb.
- Cyon-Webteil und persönliche Konten.
- Einladung per E-Mail und zusätzlichem Link/QR-Code, E-Mail-Verifizierung sowie Passwort-Reset per Link/QR-Code.
- Bidirektionale Synchronisation.
- Barbestand, Barcode-Scanner und Open-Food-Facts-Zuordnung.
- Einkaufsliste.
- Rezeptimport aus TheCocktailDB und OpenDrinks.
- Deutsche Übersetzung und metrische Normalisierung.
- Vollständige Rezeptsuche lokal und online.
- Bewertungen und gemeinsame Favoriten.
- Eigene und angepasste Rezepte samt Foto.
- Zufallsrezept **„Ich weiss nicht“**.
- Geschätzter Alkoholgehalt.
- Fotorahmen mit SMB-Quelle.
- Monitor-Ruhezeit.
- Logo, quadratisches Symbol und A3-Poster.
- Manueller, abgesicherter Updateprozess.

## Ausdrücklich nicht in Version 1

- Mehrere Bars oder Haushalte.
- Installierbare PWA.
- Mobiler Offlinebetrieb.
- Zubereitungsmodus mit einzeln dargestellten Schritten.
- Skalierung auf mehrere Gläser.
- Mengen- oder Restbestandsverwaltung.
- Freie Einkaufsartikel und Einkaufsnotizen.
- Zubereitungsverlauf.
- Altersabfrage, Konsumhinweis oder Allergenverwaltung.
- Filter nach Alkoholstärke.
- HEIC-Unterstützung.
- Zwei-Faktor-Anmeldung.

## Tests und Definition of Done

Ergänze automatisierte Tests mindestens für:

- alle vier Machbarkeitsstatus und mehrere gerichtete Ersetzungen,
- automatische Grundzutaten und mehrere Produkte pro Zutat,
- Barcodebestätigung, Korrektur und erneutes Scannen,
- Einkaufsliste und Übergang gekauft → Bestand,
- Ratingsortierung, unbewertete Rezepte und Gleichstand,
- Zufallsauswahl mit Ausschluss der letzten sechs,
- ABV-Berechnung und Kategorienfallback,
- Einladungs- und Reset-Ablauf, Token-Einmaligkeit und Widerruf,
- Online- und PIN-Ratenbegrenzung,
- Synchronisationswiederholung, Konfliktauflösung, Tombstones und API-Kompatibilität,
- Import-Deduplizierung, Übersetzungsschutz und Lizenzmetadaten,
- Wartungsmodus und lokale Entsperrung,
- Fotocache-Grenze, beschädigte Dateien und ausgefallene SMB-Freigabe.

Prüfe zusätzlich:

- Zielansicht bei 1920 × 1200 im Querformat,
- Smartphonebreiten und Touchbedienung,
- Tastaturfokus und Kontraste,
- Betrieb ohne Internet,
- mehrstündigen Fotorahmen ohne stetig steigenden Speicherverbrauch,
- normale Seiten unter zwei Sekunden und Suche unter einer Sekunde auf dem Pi,
- Produktionsbetrieb auf ARM und Cyon Shared Hosting.

Eine Funktion ist erst fertig, wenn sie fachlich korrekt, getestet, touchbedienbar, fehlertolerant, dokumentiert und in beiden betroffenen Umgebungen geprüft ist.
