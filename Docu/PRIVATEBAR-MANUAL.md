# PrivateBar Version 1 – Funktionsmanual

## 1. Zweck

PrivateBar hilft einem Haushalt, Drinks zu finden, die mit der eigenen Hausbar tatsächlich zubereitet werden können. Die Anwendung verbindet einen fest installierten Touchscreen an der Bar mit einer mobilen Weboberfläche unter `privatebar.vonrufs.ch`.

Der Raspberry Pi ist das lokale Hauptsystem. Er speichert Rezepte und Barbestand so, dass die Bar auch ohne Internet weiter funktioniert. Der Internetteil ermöglicht den Zugriff von unterwegs und gleicht Änderungen regelmässig mit dem Raspberry Pi ab.

PrivateBar Version 1 ist für genau eine gemeinsame Bar und den privaten Gebrauch ausgelegt.

## 2. Geräte und Zugänge

### Touchscreen an der Bar

PrivateBar startet zusammen mit dem Raspberry Pi automatisch im Vollbild. Es sind weder Desktop noch Browserleisten sichtbar. Die Oberfläche ist für einen 10-Zoll-Touchscreen mit 1920 × 1200 Pixeln im Querformat optimiert.

Beim ersten Zugriff nach einem Neustart wird die gemeinsame sechsstellige Kiosk-PIN verlangt. Danach bleibt das Gerät freigeschaltet, bis Browser oder Raspberry Pi neu gestartet werden.

Als lokales Hauptgerät wird ein Raspberry Pi 4 Model B eingesetzt. Er benötigt mindestens 2 GB RAM; empfohlen sind 4 GB. Das System läuft mit Raspberry Pi OS 64-Bit. Als Datenspeicher sind mindestens 32 GB erforderlich. Für einen zuverlässigen Dauerbetrieb wird eine USB-SSD bevorzugt; alternativ kann eine hochwertige High-Endurance-microSD-Karte verwendet werden.

Der Raspberry Pi wird mit einem stabilen 5-V-/3-A-Netzteil und ausreichender passiver oder leiser aktiver Kühlung betrieben. Der Bildschirm wird über HDMI angeschlossen, die Touchfunktion üblicherweise zusätzlich über USB.

### Geräte im Heimnetz

Smartphones, Tablets und Computer im Heimnetz können die lokale PrivateBar-Adresse im Browser öffnen. Auch dort wird nach einem Neustart des jeweiligen Browsers oder Geräts einmalig die gemeinsame PIN verlangt.

### Zugriff von unterwegs

Der externe Teil ist unter `https://privatebar.vonrufs.ch` erreichbar. Jedes Mitglied meldet sich mit der eigenen E-Mail-Adresse und einem persönlichen Passwort an.

Passwörter müssen mindestens zwölf Zeichen lang sein. Grossbuchstaben, Zahlen oder Sonderzeichen sind nicht zwingend vorgeschrieben. Eine Zwei-Faktor-Anmeldung ist in Version 1 nicht vorgesehen.

Nach zwei falschen Anmeldeversuchen wird die Kombination aus E-Mail-Adresse und Internetadresse für 30 Minuten gesperrt. Andere bereits berechtigte Geräte bleiben benutzbar.

## 3. Hauptnavigation

Die Anwendung besitzt folgende Hauptbereiche:

- **Start:** Tagesvorschlag, machbare Drinks und Schnellaktionen.
- **Machbar:** alle Drinks, die mit dem aktuellen Bestand zubereitet werden können.
- **Entdecken:** vollständige Rezeptsuche mit Filtern.
- **Meine Bar:** konkrete Flaschen und andere vorhandene Produkte.
- **Einkaufsliste:** fehlende bekannte Rezeptzutaten.
- **Favoriten:** gemeinsam gespeicherte Lieblingsrezepte.
- **Einstellungen:** gemeinsame Einstellungen, Synchronisation und – nur lokal – Systemfunktionen.

Auf dem Barbildschirm befindet sich die Navigation gut erreichbar an der Seite. Auf Smartphones wird sie platzsparend dargestellt.

## 4. Startseite

Die Startseite zeigt eine zufällige, vollständig machbare Tagesempfehlung. Ein Rezept mit fehlenden oder ersetzten Zutaten kann nicht zur Tagesempfehlung werden.

Darunter erscheinen weitere passende Drinks und folgende Schnellaktionen:

- **Flasche scannen**
- **Einkaufsliste**
- **Ich weiss nicht**

### „Ich weiss nicht“

Diese Schaltfläche wählt sofort ein zufälliges Rezept aus.

Dabei gelten folgende Regeln:

- Nur vollständig machbare Rezepte nehmen teil.
- Ersatzprodukte werden nicht verwendet.
- Alkoholische und alkoholfreie Rezepte werden gemeinsam berücksichtigt.
- Bewertungen beeinflussen die Auswahl nicht.
- Die letzten sechs Zufallsvorschläge werden möglichst nicht wiederholt.
- Sind zu wenige andere Rezepte vorhanden, darf ein älterer Vorschlag erneut erscheinen.

## 5. Meine Bar

„Meine Bar“ zeigt die tatsächlich vorhandenen Produkte. Eine Flasche wird nicht nur als allgemeiner „Gin“ angezeigt, sondern beispielsweise mit Marke, Produktname, Barcode und Produktbild. Zusätzlich ist sichtbar, welcher allgemeinen Rezeptzutat das Produkt entspricht.

Mehrere unterschiedliche Produkte können dieselbe Zutat abdecken. Sind beispielsweise zwei verschiedene Gin-Produkte vorhanden, bleibt „Gin“ für Rezepte verfügbar, bis beide Produkte entfernt wurden.

PrivateBar verfolgt keine:

- Restmengen,
- Flaschengrössen,
- Anzahl identischer Flaschen.

Ein Produkt ist entweder vorhanden oder nicht vorhanden.

### Automatisch vorhandene Zutaten

Übliche Grundzutaten wie Wasser, Eis, Zucker oder Salz können automatisch als vorhanden gelten. Die Liste lässt sich in den Einstellungen bearbeiten.

### Produkt entfernen

Ein konkretes Produkt kann über seine Detailansicht entfernt werden. Wird sein Barcode erneut gescannt, erkennt PrivateBar den vorhandenen Eintrag und bietet ebenfalls das Entfernen an. Vor dem Löschen erscheint eine Bestätigung.

## 6. Flasche per Barcode hinzufügen

Der Barcode-Scanner ist besonders für die Nutzung mit einem Smartphone vorgesehen.

1. Im Internetteil „Flasche scannen“ öffnen.
2. Kamerazugriff erlauben.
3. Barcode gut beleuchtet vor die Kamera halten.
4. Den erkannten Produktvorschlag prüfen.
5. Allgemeine Cocktailzutat und gegebenenfalls Alkoholgehalt kontrollieren.
6. Eintrag bestätigen.

PrivateBar sucht das Produkt zuerst bei Open Food Facts. Unterstützt werden unter anderem Spirituosen, Liköre, Sirupe, Säfte, Bitter, Softdrinks und alkoholfreie Alternativen.

### Bestätigungsansicht

Vor dem Hinzufügen werden angezeigt:

- Barcode,
- Marke,
- Produktname,
- Produktbild,
- vorgeschlagene allgemeine Rezeptzutat,
- erkannter oder angenommener Alkoholgehalt.

Die Zutatenzuordnung und der Alkoholgehalt dürfen korrigiert werden. PrivateBar merkt sich die Korrektur dauerhaft und verwendet sie beim nächsten Scan desselben Produkts bevorzugt.

### Produkt nicht gefunden

Ist der Barcode unbekannt, kann das Produkt manuell erfasst werden. Die private Korrektur wird nicht automatisch an Open Food Facts übertragen.

## 7. Machbarkeit eines Rezepts

PrivateBar unterscheidet vier Zustände:

### Machbar

Alle Pflichtzutaten sind exakt vorhanden. Optionale Zutaten und Garnituren dürfen fehlen.

### Mit Ersatz machbar

Mindestens eine Pflichtzutat wird durch eine hinterlegte Alternative ersetzt. Es fehlt keine weitere Pflichtzutat. Mehrere Ersetzungen sind gleichzeitig möglich.

Beispiel: Eine Regel kann festlegen, dass Limettensaft Zitronensaft ersetzt. Ersatzregeln gelten nur in der ausdrücklich festgelegten Richtung.

### Fast machbar

Es fehlen höchstens zwei Pflichtzutaten.

### Nicht machbar

Es fehlen mindestens drei Pflichtzutaten. Das Rezept bleibt über die allgemeine Suche auffindbar.

Im Rezeptdetail sind vorhandene, ersetzte, optionale und fehlende Zutaten eindeutig gekennzeichnet.

## 8. Rezepte entdecken

Die vollständige Rezeptsuche steht lokal und online zur Verfügung.

Filter:

- Machbarkeit,
- alkoholisch oder alkoholfrei,
- Basisspirituose,
- Geschmacksrichtung,
- Zubereitungsart.

Die Ergebnisse werden zuerst nach Machbarkeit gruppiert:

1. machbar,
2. mit Ersatz machbar,
3. fast machbar,
4. übrige Rezepte.

Innerhalb einer Gruppe werden Rezepte standardmässig nach Beliebtheit sortiert. Alternativ kann alphabetisch sortiert werden.

## 9. Rezeptdetail

Ein Rezept zeigt mindestens:

- Name und Bild,
- Kennzeichnung „Neu“, sofern zutreffend,
- Machbarkeitsstatus,
- alkoholisch oder alkoholfrei,
- Basisspirituose und Geschmacksrichtung,
- Zutaten für genau ein Glas,
- Zubereitungsschritte,
- Glasart und Garnitur,
- fehlende und verwendete Ersatzzutaten,
- geschätzten Alkoholgehalt,
- Quelle und Lizenzhinweis,
- Durchschnittsbewertung.

Flüssigkeitsmengen erscheinen in Zentilitern, beispielsweise `4 cl` oder `0.5 cl`. Eine Umrechnung auf mehrere Gläser gibt es in Version 1 nicht.

### Geschätzter Alkoholgehalt

PrivateBar zeigt einen Näherungswert in `% vol`. Berechnet werden die bekannten Alkoholwerte und Flüssigkeitsmengen der Rezeptzutaten. Eine zusätzliche Verdünnung durch Eis, Rühren oder Schütteln wird nicht berücksichtigt.

Ist der Alkoholgehalt eines Produkts unbekannt, verwendet PrivateBar einen fest hinterlegten typischen Wert der Zutatenkategorie. Der Wert einer konkreten Flasche kann bei der Barcodebestätigung korrigiert werden.

## 10. Bewertungen

Persönlich angemeldete Online-Mitglieder können ein Rezept mit einem bis fünf Sternen bewerten.

- Pro Mitglied und Rezept ist genau eine Bewertung möglich.
- Eine Bewertung kann jederzeit geändert werden.
- Angezeigt wird nur der Durchschnitt der Sterne.
- Die Anzahl der abgegebenen Bewertungen wird nicht angezeigt.
- Unbewertete Rezepte folgen bei der Beliebtheitssortierung nach den bewerteten.
- Bei gleichem Durchschnitt entscheidet die alphabetische Reihenfolge.

Am gemeinsam angemeldeten Kiosk ist die Durchschnittsbewertung sichtbar. Eine persönliche Bewertung wird dort nicht abgegeben, weil keine einzelne Person identifiziert ist.

Favoriten sind unabhängig von Bewertungen und gelten gemeinsam für alle Mitglieder. Sie beeinflussen die Beliebtheit nicht.

## 11. Einkaufsliste

Fehlende Zutaten eines Rezepts können einzeln oder gemeinsam zur Einkaufsliste hinzugefügt werden.

Die Liste:

- enthält in Version 1 nur bekannte Rezeptzutaten,
- gruppiert Zutaten automatisch nach Kategorien,
- sortiert innerhalb jeder Kategorie alphabetisch,
- ist lokal und online gemeinsam bearbeitbar.

Wird eine Zutat als gekauft markiert, verschwindet sie aus der Einkaufsliste und wird dem Barbestand hinzugefügt. Solange keine konkrete Flasche gescannt wurde, erscheint sie dort als generischer Bestandseintrag. Dieser kann später durch ein konkretes Barcodeprodukt ersetzt werden.

Freie Einkaufsartikel und persönliche Notizen sind nicht Teil von Version 1.

## 12. Eigene Rezepte

Mitglieder können eigene Rezepte erstellen. Erforderlich sind mindestens Name, Pflichtzutaten mit Mengen und Zubereitungsschritte.

Zusätzlich können Kategorie, Geschmack, Basisspirituose, Glas, Garnitur und ein Foto gepflegt werden.

Ein Foto kann auf dem Smartphone aufgenommen oder ausgewählt werden. PrivateBar verarbeitet JPEG, PNG und WebP. HEIC wird nicht unterstützt. Das Bild wird zugeschnitten, verkleinert und komprimiert; die grosse Originalaufnahme bleibt nicht gespeichert.

Ein importiertes Rezept kann angepasst werden. Dabei entsteht eine eigene Haushaltskopie. Das importierte Original bleibt unverändert und kann später weiterhin aktualisiert werden.

Eigene Rezepte, Kopien und ihre Bilder werden zwischen Cyon und Raspberry Pi synchronisiert.

## 13. Rezeptquellen und Aktualisierung

PrivateBar verwendet TheCocktailDB als Hauptquelle und OpenDrinks als Ergänzung.

Die Aktualisierung läuft standardmässig einmal täglich bei Cyon. Zeitpunkt und Frequenz lassen sich in den Einstellungen ändern. Cyon normalisiert und übersetzt neue Inhalte. Der Raspberry Pi übernimmt sie beim nächsten Abgleich in seinen lokalen Offlinekatalog.

Neu importierte Rezepte erscheinen sofort und tragen 14 Tage lang die Markierung **„Neu“**.

Ein unerwünschtes Rezept kann ausgeblendet werden. Das Ausblenden gilt für den gesamten Haushalt. Das Rezept bleibt in einer Verwaltungsansicht erhalten und kann wieder eingeblendet werden.

## 14. Übersetzungen

Fremdsprachige Rezepte werden automatisch ins Deutsche übersetzt. Als erster Anbieter ist Microsoft Azure Translator vorgesehen.

Ist noch keine Übersetzung verfügbar:

- bleibt das Rezept sichtbar,
- wird der Originaltext angezeigt,
- erscheint der Hinweis **„Übersetzung ausstehend“**.

Mitglieder dürfen eine deutsche Übersetzung manuell ergänzen oder verbessern. Eine manuell bearbeitete Übersetzung wird geschützt und später nicht von einer automatischen Übersetzung überschrieben.

## 15. Synchronisation

Der automatische Abgleich zwischen Raspberry Pi und Cyon erfolgt:

- alle zehn Minuten,
- nach einem Neustart,
- sobald eine unterbrochene Internetverbindung wieder verfügbar ist.

In den lokalen Einstellungen befindet sich **„Jetzt synchronisieren“**. Dort werden der letzte erfolgreiche Abgleich, der aktuelle Zustand und mögliche Fehler angezeigt.

Änderungen können lokal und online vorgenommen werden. Besteht vorübergehend keine Verbindung, speichert PrivateBar lokale Änderungen und überträgt sie später.

Ändern zwei Geräte denselben Eintrag, gewinnt die zuletzt bestätigte Änderung. Frühere Änderungen bleiben mit Zeitpunkt und Akteur im technischen Verlauf nachvollziehbar.

Der mobile Internetteil benötigt eine aktive Internetverbindung. Eine offline installierbare Smartphone-App ist nicht Teil von Version 1.

## 16. Mitglieder verwalten

Alle Mitglieder besitzen dieselben Rechte. Jedes Mitglied kann:

- neue Personen einladen,
- offene Einladungen widerrufen,
- für andere Mitglieder einen Passwort-Reset erzeugen,
- andere Mitglieder entfernen,
- deren aktive Sitzungen widerrufen.

Das letzte verbleibende Mitglied kann nicht entfernt werden. Das eigene Konto kann nur gelöscht werden, wenn mindestens ein weiteres Mitglied bestehen bleibt.

### Person einladen

1. E-Mail-Adresse der neuen Person eingeben.
2. „Einladung per E-Mail senden“ wählen.
3. Die eingeladene Person öffnet die E-Mail. Link und QR-Code stehen zusätzlich zum Teilen bereit.
4. Die eingeladene Person setzt ihren Namen und ihr Passwort.
5. Sie erhält eine zweite E-Mail und bestätigt damit ihre E-Mail-Adresse. Erst danach öffnet sich die Bar.

Der Link ist an die eingegebene E-Mail-Adresse gebunden, nur einmal verwendbar und 30 Minuten gültig. Bei einem Versandfehler wird die Einladung widerrufen; sie kann erneut erstellt werden.

### E-Mail-Adresse bestätigen

Auch bestehende Mitglieder und das bei der Installation angelegte erste Konto
müssen ihre E-Mail-Adresse bestätigen. Nach der Anmeldung wird automatisch eine
Bestätigungs-E-Mail angefordert. Auf der Bestätigungsseite kann der Versand
nach einer Minute erneut ausgelöst werden. Der Link gilt 30 Minuten und setzt
eine Anmeldung mit demselben Konto voraus. Bei einem abgelaufenen Link zurück
zur Bar navigieren und auf der Bestätigungsseite eine neue E-Mail anfordern.
Ein SMTP-Fehler löscht das Konto nicht; die Bestätigung kann wiederholt werden.

### Passwort zurücksetzen

Ein angemeldetes Mitglied erzeugt für eine andere Person einen einmal verwendbaren Reset-Link mit QR-Code. Dieser ist 30 Minuten gültig.

Ist niemand mehr angemeldet, kann ein solcher Link ausschliesslich direkt am Raspberry Pi nach Eingabe der sechsstelligen Kiosk-PIN erzeugt werden.

## 17. Fotorahmen

Nach standardmässig fünf Minuten ohne Bedienung wechselt die lokale Anwendung in den Fotorahmen. Die Wartezeit ist einstellbar.

- Fotos erscheinen zufällig.
- Direkte Wiederholungen werden vermieden.
- Ein Foto bleibt standardmässig zehn Sekunden sichtbar.
- Die Dauer ist einstellbar.
- Bilder wechseln mit einer weichen Überblendung.
- Die Überblendung dauert standardmässig eine Sekunde und ist einstellbar.
- Bilder werden immer vollständig und ohne Beschnitt dargestellt.
- Freie Randflächen sind schwarz.
- Uhrzeit und Datum werden nicht eingeblendet.

Eine beliebige Berührung beendet den Fotorahmen. Die erste Berührung dient ausschliesslich zum Aufwecken und löst keine darunterliegende Schaltfläche aus. Danach erscheint sofort die zuvor verwendete Ansicht. Eine erneute PIN-Eingabe ist nicht nötig.

### Fotoquelle einrichten

Die Fotos liegen in einem freigegebenen SMB-Ordner im Intranet.

In den lokalen Einstellungen werden eingetragen:

- SMB-Server,
- Freigabename,
- optionaler Unterordner,
- Benutzername,
- Passwort.

Mit **„Verbindung testen“** lässt sich der Zugriff prüfen. PrivateBar liest die Freigabe ausschliesslich und verändert keine Originalbilder. Alle Unterordner werden einbezogen.

Der Fotoindex wird täglich, bei jedem Neustart und nach einer Pfadänderung aktualisiert. Ist der Ordner nicht erreichbar, läuft der Fotorahmen mit bereits zwischengespeicherten Bildern weiter.

Unterstützte Formate:

- JPEG,
- PNG,
- WebP.

HEIC und beschädigte Dateien werden übersprungen.

Der lokale Fotocache ist standardmässig auf 2 GB begrenzt. Die Grenze lässt sich ändern. Bei Platzbedarf entfernt PrivateBar nur lange nicht verwendete Cachekopien, niemals Bilder aus der SMB-Freigabe. Fotorahmenbilder werden nicht ins Internet übertragen.

## 18. Monitor-Ruhezeit

Der Monitor kann nach einem lokalen Zeitplan nachts ausgeschaltet werden. Der Zeitplan ist standardmässig deaktiviert und wird in den lokalen Einstellungen eingerichtet.

Während der Ruhezeit kann eine Berührung den Monitor wieder einschalten. Danach bleibt er 29 Minuten aktiv. Jede weitere Bedienung startet diese 29 Minuten erneut.

## 19. Einstellungen

### Gemeinsam und synchronisiert

Zu den gemeinsamen Einstellungen gehören beispielsweise:

- Zeitpunkt und Häufigkeit des Rezeptimports,
- automatische Grundzutaten,
- gerichtete Ersatzzutaten,
- gemeinsame Rezeptverwaltung.

### Ausschliesslich lokal

Folgende Einstellungen sind nur direkt am Raspberry Pi verfügbar:

- SMB-Fotoquelle und Zugangsdaten,
- Kiosk-PIN,
- Fotorahmenzeiten und Fotocache,
- Monitor-Ruhezeit,
- lokale Netzwerkkonfiguration,
- Wartungsmodus,
- Update des Raspberry Pi.

Vor dem Öffnen sensibler lokaler Einstellungen muss die sechsstellige PIN erneut eingegeben werden. Nach acht falschen Eingaben folgt eine Sperre von fünf Minuten. Ein Neustart hebt sie nicht auf.

Technische Hintergrundfehler werden nur in den Einstellungen angezeigt. Fehler bei einer gerade ausgeführten Benutzeraktion erscheinen weiterhin direkt an dieser Aktion.

## 20. Wartungsmodus und Wiederherstellung

Der Wartungsmodus sperrt PrivateBar vollständig. Währenddessen laufen weder Änderungen noch Rezeptimport oder Synchronisation. Sichtbar ist nur eine Wartungsseite.

Der Modus kann ausschliesslich direkt am Raspberry Pi mit der sechsstelligen Kiosk-PIN beendet werden.

Er wird insbesondere verwendet, wenn eine Datenbank aus dem Cyon-Backup wiederhergestellt wird. Dadurch kann eine ältere, gerade wiederhergestellte Datenbank nicht sofort durch eine automatische Synchronisation überschrieben werden.

PrivateBar erstellt keine eigene automatische Datenbanksicherung. Der Benutzer setzt für Wiederherstellungen das vorhandene Cyon-Backup voraus. Die Synchronisation der beiden Datenbanken ist kein eigenständiges Backup.

## 21. Updates

Der Quellcode liegt in einem privaten GitHub-Repository. Neue Versionen werden manuell geprüft und als Release freigegeben.

Reihenfolge:

1. Automatisierte Tests und Codeprüfungen müssen erfolgreich sein.
2. Zuerst wird Cyon per SSH aktualisiert.
3. Danach wird der Raspberry Pi aktualisiert.

Der Pi bietet in den lokalen Einstellungen:

- **„Update prüfen“**
- **„Freigegebene Version installieren“**

Vor der Installation prüft PrivateBar Kompatibilität, freien Speicher und Datenbankmigrationen. Schlägt das lokale Update fehl, kehrt das System automatisch zur vorherigen Programmversion zurück.

Ein separates öffentliches Testsystem gibt es nicht.

## 22. Gestaltung

PrivateBar verwendet ausschliesslich ein dunkles Design. Die visuelle Sprache ist warm, verspielt, illustrativ und mediterran.

Farbwelt:

- dunkle Grundfläche,
- Terrakotta,
- Zitronengelb,
- Olivgrün,
- Türkis als Akzent.

Bedienelemente sind gross genug für Touchbedienung und benötigen keine Hoverfunktion. Die Oberfläche bleibt auch auf Smartphone und Desktop responsiv.

### Markenmaterial für Version 1

- Vollständiges Logo mit dem Schriftzug **„PrivateBar“**.
- Vereinfachtes quadratisches Symbol für Browser und spätere App-Nutzung.
- Kein Slogan.
- Motive: Barutensilien, Flaschen und Gläser.
- Stil: französische Riviera der 1950er/60er Jahre.
- Zusätzlich ein A3-Poster im Hochformat bei 300 dpi.
- Das Poster zeigt eine menschenleere mediterrane Barszene mit Bar, Meerblick, Flaschen und Gläsern.

## 23. Sonder- und Leerzustände

### Noch keine Produkte vorhanden

PrivateBar zeigt den exakten Hinweis:

> Bar bitte füllen

Dazu erscheinen die Aktionen **„Flasche scannen“** und **„Manuell hinzufügen“**.

### Kein Internet

Die lokale Rezeptsuche, der lokale Bestand und der Fotorahmen bleiben nutzbar. Änderungen werden später synchronisiert.

### SMB-Freigabe nicht erreichbar

Bereits zwischengespeicherte Fotos werden weiter angezeigt. Der Fehler ist in den Einstellungen sichtbar.

### Übersetzungsdienst nicht erreichbar

Das Rezept bleibt in der Originalsprache sichtbar und wird als **„Übersetzung ausstehend“** markiert.

### Keine vollständig machbaren Zufallsrezepte

„Ich weiss nicht“ erklärt, dass momentan kein vollständig machbarer Drink verfügbar ist, und verweist auf „Fast machbar“ sowie die Einkaufsliste. Es wird kein ungeeignetes Rezept als machbar ausgegeben.

## 24. Nicht enthaltene Funktionen

Version 1 enthält ausdrücklich keine:

- Verwaltung mehrerer Bars,
- installierbare Smartphone-PWA,
- mobile Offlinefunktion,
- Portionsumrechnung,
- Restmengen- oder Flaschenzählung,
- freien Einkaufsartikel,
- Einkaufsnotizen,
- Zubereitungshistorie,
- schrittweisen Zubereitungsmodus,
- Altersabfrage,
- Konsumhinweise,
- Allergenverwaltung,
- Filterung nach Alkoholstärke,
- HEIC-Unterstützung,
- Zwei-Faktor-Anmeldung.

Für Version 2 vorgemerkt sind die installierbare Smartphone-Web-App, Einkaufsnotizen und ein gross dargestellter Schritt-für-Schritt-Zubereitungsmodus.
