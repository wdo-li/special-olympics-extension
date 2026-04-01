# Special Olympics Extension - Update

Das sind die Grundfunktionen des Plugins. Änderungen werden in der Changelog festgehalten.

## Inhalt
1. Änderungen am bestehenden System
2. Neue Funktion: Trainings mit Trainings-Sessions
3. Neue Funktion: Events
4. Neue Funktion: Lohnabrechnung
5. Neue Funktion: Mitgliederübersicht
6. Neue Funktion: Kontakte
7. Neue Funktion: Token-Anwesenheit (Anwesenheit ohne Login)
8. Neue Funktion: Dashboard: Rollenspezifische Übersichtsseiten


## Informationen über bestehendes System

### Server und erwartete Besucher
- 300 Mitglieder (CPT Mitglied)
- 200 WP User
- Shared Hosting bei cyon.ch (schneller Hoster) -> weitere Infos auf cyon.ch
- LiteSpeed Server (kompatibel zu Apache)
- WordPress 6.9
- PHP 8.3

## Grundsätze
- Es soll so viel wie möglich im Backend gemacht werden.
- Erstelle ausführliche Debugging-Funktion, die für die Entwicklung wichtig sind. Das Debugging soll auf der Einstellungs-Seite aktivierbar sein.
- Intranet: Nicht-eingeloggte Nutzer werden auf die Login-Seite umgeleitet.
- Funktionsvoraussetzung: WP User Rolle ist immer gleich wie ACF "role", wenn entsprechender CPT Mitglied eine user_id hat.
 - Registriere die CPTs sauber mit den Capabilities.


## 1. Änderungen am bestehenden System
### 1. Neues Rollenverständnis
Bestehende Rollen: administrator, ansprechperson, athlet_in, hauptleiter_in, leiter_in, unified.
Neue Rollen (werden über das Members-Plugin erstellt) hinzufügen: assistenztrainer_in, helfer_in, praktikant_in, schueler_in, athlete_leader

### 2. Synchronisation
- WP User: Master in Rolle, Mail, Vorname und Nachname. Werden diese Felder geändert, soll eine Synchronisation in die Betreffenden ACF Felder des Posts im CPT Mitglied mit der user_id des Users statt finden.
- Wird ein Post im CPT "Mitglied" angelegt und es ist keine user_id vorhanden, wird der Wert des ACF Felds "role" auf "athlet_in" gesetzt.
- Die alte Synchronisation (ACF -> WP User wird alles gelöscht, es findet nie mehr eine Synchronisation von ACF -> WP User statt).
- Wird der Vor- und Nachname eines WP User geändert, wird im entsprechenden CPT "Mitglied" der Titel des Posts ebenfalls aktualisiert.

### 3. Account in CPT-Mitglied-Bearbeitung
- Klick auf „Mein Account“ leitet auf den Bearbeitungsbildschirm des verknüpften CPT-Mitglieds weiter.
- Bearbeitung von Vorname, Nachname, E-Mail und Passwort erfolgt dort im Block „Persönliche Daten“ oberhalb der ACF-Felder; Speicherung in den WP-User, Sync in die entsprechenden ACF-Felder.
- Ein Klick auf „Aktualisieren“ speichert sowohl die Persönlichen Daten (im WP-User) als auch die übrigen CPT/ACF-Daten. Keine separate Account-Seite. (Achtung wegen Synchronisatino)
- E-Mail-Eindeutigkeit: neue E-Mail darf nicht bereits bei anderem WP-User vorkommen. Redirect von Mein Profil für Nicht-Admins führt auf die CPT-Mitglied-Bearbeitung.

### 4. WP User
- Für Lohnabrechnung ist Stufe in Rolle wichtig. Deshalb: soe_grade_hauptleiter_in in usermeta aufnehmenn. Bei soe_grade_hauptleiter_in soll es zwei Auswahlmöglichkeiten geben: "1A : Hauptleiter*in 1A" und "1B : Hauptleiter*in 1B".
- Die Standard-Seiten: /wp-admin/profile.php und /wp-admin/user-edit.php sind ausschliesslich für Administratoren zugänglich. Andere Benutzer verwalten ihre Daten (Vorname, Nachname, E-Mail, Passwort) ausschliesslich über die CPT-Mitglied-Bearbeitung.

### 2. Änderungen am CPT "mitglied"

#### Rechte
- Administratoren haben vollen Zugriff.
- Nur Ansprechpersonen und Admins können Mitglieder anlegen.
- Nur Administratoren dürfen Mitglieder löschen. Nicht-Admins sehen den Link „In den Papierkorb verschieben“ auf dem Mitglied-Bearbeitungsbildschirm nicht und haben keine Berechtigung zum Löschen (Papierkorb oder endgültig).
- Ansprechpersonen sehen nur Mitglieder, bei denen sie selbst Autor sind. Diese Posts können sie bearbeiten und speichern. (alle anderen Posts können sie nicht sehen, bearbeiten, speichern oder löschen.)
  - Hinweis: Hauptleiter und Leiter können Posts später im Telefonbuch sehen, aber nicht hier.

#### Darstellung
Idee: Admins sehen Mitglieder (alle), andere Rollen sehen "Athlet*in" hinzufügen, weil sie keine anderen Rollen erfassen müssen.
- Die Ansicht von Admins und anderen Rollen auf den CPT Mitglied unterscheidet sich deshalb. 
- Wenn eine user_id existiert, wird im Bearbeitungsbereich ein Block „Persönliche Daten“ angezeigt. Vorname, Nachname, E-Mail und Passwort (optional) sind editierbar; Rolle und HL-Stufe (falls Hauptleiter) nur angezeigt. Ein Klick auf „Aktualisieren“ speichert sowohl diese Angaben (im WP-User) als auch die übrigen CPT/ACF-Daten. Die ACF-Felder vorname, nachname und e-mail werden in diesem Fall nicht angezeigt (per ACF Conditional Logic). Wenn keine user_id existiert, werden keine Daten aus WP-User geladen und die ACF-Felder normal angezeigt.

#### Meine Athlet*innen (Rolle: Ansprechperson)
- Der Menüpunkt „Mitglieder“ ist für Nicht-Admins ausgeblendet.
- Stattdessen erhalten Nicht-Admins einen Top-Level-Menüpunkt "Athlet*innen.
- Beim Klick darauf wird eine Custom Admin-Seite geladen. Dort sind alle Posts aus dem CPT „Mitglied“ sichtbar, die vom aktuell eingeloggten User angelegt wurden. Andere Posts werden nicht angezeigt.
- Der User kann diese Liste nur anzeigen, keine Athleten löschen.
- Beim Klick auf den Namen oder den Button „Bearbeiten“ gelangt er in die CPT-Bearbeitungsansicht des jeweiligen Posts. Andere Posts (nicht von ihm angelegt) kann er nicht bearbeiten – nur die eigenen.
- Neben der Überschrift ist ein Button "Athlet*in hinzufügen". Beim Klick darauf wird auf `post-new.php?post_type=mitglied` verlinkt, sodass der eingeloggte User ein neues Mitglied anlegen kann.
- E-Mail bei neuem Mitglied: Wenn ein Benutzer ein neues CPT-Mitglied anlegt, kann optional eine Benachrichtigungs-E-Mail gesendet werden. Die E-Mail-Adresse des Empfängers wird in den Einstellungen (Einstellungen → Special Olympics) unter „Neues Mitglied – E-Mail-Empfänger“ hinterlegt. Ist das Feld leer, wird keine E-Mail versendet. Die Mail enthält Angaben zum anlegenden Benutzer, zum Mitglied (Name) und einen Link zur Bearbeitung.

#### 1.Neue Taxonomie:
- Beim CPT „Mitglied“ soll eine shared taxonomie eingefügt werden: sport

#### 2. Erweiterung Anzeige im Backend
- Mitglieder-Liste (edit.php?post_type=mitglied): Archivierte Mitglieder sind standardmäßig ausgeblendet. Ein Filter-Dropdown erlaubt „Aktiv (Standard)“ / „Alle Status“ / „Nur Archivierte“.
- Im Bearbeitungs-Screen von "mitglied" wird ein zusätzlicher Bereich/Tab "Events" angezeigt.
- Die Darstellung erfolgt per Custom Coding (Metabox/Panel), nicht über editierbare ACF-Felder.
- Inhalte im Tab "Events" sind (read-only):
  - Datum
  - Event-Titel
  - Sport
  - Event-Typ
  - Rolle der Person am Event
  - optional: Dauer / Abrechnungs-Kategorie (falls für Lager/Payroll relevant)
  - Link zum jeweiligen Event (Admin-Edit-Link)
- Pro Mitglied wird eine Liste der Event-Teilnahmen als automatisch gepflegter Snapshot gespeichert.
- Diese Snapshot-Daten dürfen nicht manuell im Mitglied bearbeitet werden.
- Der Snapshot dient der schnellen Anzeige/Filterung (Telefonbuch, Trainingslager-Übersichten) und reduziert Cross-CPT-Abfragen.

#### 3. aktiv/archiviert
Wir brauchen beim CPT Mitglied die Möglichkeit, zwischen aktiven Personen und Personen, die nicht mehr dabei sind, zu unterscheiden.
- In der Übersicht oder im Bearbeitungsmodus soll mit einem Klick auf "Person archivieren" die Person archiviert werden können. Wenn sie archiviert ist soll sie auch wieder in den aktiven Status gesetzt werden können.
- Für archivierte Mitglieder gilt folgendes: 
  - Keine Anzeige im Telefonbuch 
  - Nicht auswählbar für Lohnabrechnung
  - In historischer Lohnabrechnung weiterhin referenzierbar
  - Nicht bei Trainings auswählbar
  - Nicht bei Events auswählbar
  - Im Event-Snapshot sichtbar

#### 4. ACF Feld für WP User Passwort
- Passwort-Änderung über ACF-Felder (password, password_confirmation) für Mitglieder mit verbundenem WP-User.
- Validierung: Beide Felder müssen übereinstimmen, mindestens 5 Zeichen. Beide leer = keine Änderung.
- Sync zu WP-User via wp_set_password beim Speichern; Felder werden danach geleert.
- Passwort-Felder nur sichtbar, wenn user_id gesetzt ist.

#### 5. ACF Medizinische Datenblätter
- Für Nicht-Admins wird der einfache Datei-Upload (Datei auswählen) statt der WordPress-Mediathek angezeigt. Dies verhindert, dass Nicht-Admins auf die gesamte Mediathek zugreifen können.
- Behalten vollen Zugriff auf die Mediathek.

##### Berechtigungen
- Admin | Alle Datenblätter
- Uploader (Autor) | Eigene Uploads
- Hauptleiter*in | Mitglieder mit gemeinsamer Sportart
- Leiter*in | Mitglieder mit gemeinsamer Sportart
- Andere Rollen | Kein Zugriff

##### Weiteres
- Wenn eine Datei aus dem ACF-Feld entfernt wird, wird das zugehörige Attachment automatisch aus der Mediathek und vom Dateisystem gelöscht.
- Klickbare Links auch im ACF Editor

## 2. Neue Funktion: Trainings
Grundgedanke: Administratoren legen neue Trainings an, geben die Trainingsdaten (Uhrzeit, Trainingstermine, Personen, usw.) ein. Den Hauptleitern soll es anschliessend auf einer Übersichtsseite möglich sein, die Anwesenheiten von Personen einzutragen. Die Übersichtsseite ist tabellarisch dargestellt. Erste Spalte die Namen, erste Zeile alle Trainingstermine. Mit einem Häkchen sollen die Anwesenheiten eingetragen werden. Alles findet im Backend statt und ist responsiv. Ein Häckchen bedeutet, dass die Person anwesen ist. Per Ajax soll das sofort gespeichert werden.

Alles soll so gespeichert werden, dass es möglichst robust, performant und gut abfragbar ist.

Erstelle einen neuen CPT „training“.

### Rechte
- Administratoren können training posts anlegen, bearbeiten und löschen: Voller Zugriff auf alles.
- Hauptleiter können Anwesenheiten eintragen, aber sonst keine Änderungen vornehmen.
- Hauptleiter dürfen nur trainings sehen, bei denen sie als Hauptleiter eingetragen sind. Alle anderen trainings dürfen sie nicht sehen.
- Hauptleiter darf Teilnehmerlisten der Trainings sehen, in denen er Hauptleiter ist.
- Die restlichen User-Rollen (Leiter, Assistenztrainer, usw.) haben keinen Zugriff auf diesen CPT (nicht sehen, bearbeiten, erstellen oder anderes).

Hauptleiter müssen auf einer Übersichtsseite die Anwesenheiten eintragen können. Wenn sie am Handy sind, muss die Darstellung anders sein.

### Anwesenheit Tablet und Mobile: Datum wählen, dann Personen
- Im Bearbeitungsbildschirm eines Trainings wird die Anwesenheit pro Datum erfasst: Zuerst wählt man im **Dropdown ein Datum** (alle Trainings-Sessions), darunter erscheint eine Tabelle mit **Person (links)** und **Anwesenheit (Checkbox rechts)** nur für dieses Datum. Kein horizontales Scrollen; auf Tablets und Handys gut nutzbar.
- **Vorauswahl im Dropdown:** Heute, falls heute ein Trainingstermin ist; sonst der **nächste Trainingstermin ab heute**. Gibt es keinen zukünftigen Termin, wird der erste (älteste) Termin vorausgewählt.

### Felder
- Titel
- Daten: Daten zum Event. Aufgrund dieser Daten soll ausgerechnet werden, wie viele Trainngs-Sessions es gibt. Mit ausglassenen Terminen sollen bestimmte Daten nicht mit in die Rechnung hinein fliessen. Wenn das Startdatum nicht am ausgewählt Wochentag ist, soll das nächste Datum, das diesem Wochentag entspricht, eingetragen werden. Beispiel: Trainig ist Montag, ab 21.01.2026. Dann ist das erste Training am Montag, 26.01.2026. Gleich bei Enddatum: Es soll das Datum gewählt werden, das noch innerhalb der angegebenen Periode ist.
  - Startdatum: Datumsfeld
  - Enddatum: Datumsfeld
  - Ausgelassene Termine: Kommaseparierte Liste mit Daten, an denen kein Training statt findet. Eingabeformat: tag.monat.jahr (z.B. 20.05.2026). Prüfe direkt nach der Eingabe, ob die Daten korrekt formatiert sind. Bei ungültigen Eingaben gibt einen Fehler zurück.
  - Wochentag: Montag, Dienstag, Mittwoch, Donnerstag, Freitag, Samstag, Sonntag
  - Uhrzeit: Uhrzeitfeld (Start-, Enddatum und Wochentag dient dazu, dass die einzelnen Trainings-Sessions generiert)
  - Dauer: Hier sollen die Werte aus der Einstellungs-Seite ausgewählt werden können. Als Dropdown oder Radio-Button, also nur ein Wert (z.B. 60 Minuten, 90 Minuten, usw.)
- Personen: Für die Anwesenheit.
  - Hauptleiter*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ hauptleiter_in, Leiter*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ leiter_in
  - Athlet*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ athlet_in
  - Unified: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ unified
  - Assistenztrainer*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ assistenztrainer_in
  - Helfer*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ helfer_in
  - Praktikant*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ praktikant_in
  - Schüler*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ schueler_in
- Bemerkungen. Eingabfeld (eine Zeile). Bitte hier vermerken, dass dieser Wert später in der Lohnabrechnung sichtbar ist.

Alternative, falls robuster und sinnvoller: Es können grundsätzlich alle Personen ausgewählt werden und bei jeder Person muss angegeneben werden, in welcher Rolle sie teil nimmt (z.B. Hauptleitung, Leitung, Assistenz, Praktikan usw.)
Die Felder können per ACF oder Custom Code angelegt werden.

### Taxonomien
- Shared Taxonomie: „sport“. Zum Eintragen, um welche Sportart es sich handelt.

### Beachte
- Anwesenheit wird pro gewähltem Datum in einer zweispaltigen Tabelle (Person | Anwesenheit) eingetragen; Standard-Datum im Dropdown ist heute oder der nächste Trainingstermin (siehe Abschnitt „Anwesenheit: Datum wählen, dann Personen“).
- Hauptleiter können im Bearbeitungsmodus eines Trainings auf „Training als abgeschlossen melden“ klicken. Damit wird eine E-Mail an die auf der Einstellungs-Seite konfigurierte Adresse gesendet; Administratoren können das Training daraufhin als abgeschlossen markieren.
- Administratoren können ein abgeschlossenes Training wieder als laufend markieren (Button „Als laufend markieren“ im Status-Bereich).
- Es möglich sein, einzelne Trainings-Sessions manuell hinzuzufügen, zu löschen oder zu bearbeiten.
- **Sessions-Berechtigungen:** Hauptleiter können in ihren zugewiesenen Trainings einzelne Sessions hinzufügen, aber nicht löschen. Administratoren können Sessions hinzufügen und löschen.
- Regel: Falls eine manuelle Trainings-Session auf eine automatisch erstellte Trainings-Session fällt, soll eine Warnung ausgegeben werden. Wenn diese bestätigt wird (Overwrite), gilt die Session als überschrieben (eine Session pro Datum).
- Damit die Übersicht besser gewährtleistet ist, soll man ein Training als abgeschlossen markieren können. Unter "Veröffentlicht" sollen abgeschlossene Trainings nicht angezeigt werden.
- Abgeschlossene Trainings sind dann in "Abgeschlossene Trainings" zu finden
- Ausserdem wäre es besser, wenn Veröffentlicht umbenannte wird in "Laufende Trainings"
- Abgeschlossene Trainings sind vollständig read-only.
- Die Statistik bleibt einsehbar, wird jedoch nicht mehr verändert.
- In der globalen Statistik-Übersicht werden nur laufende Trainings berücksichtigt.
- Hinweis auf der Trainings-Bearbeitungsseite: Wenn das Enddatum in der Vergangenheit liegt, wird daran erinnert, das Training als abgeschlossen zu melden bzw. markieren.
- Hinweis auf der Trainings-Übersicht: Bei laufenden Trainings mit Enddatum in der Vergangenheit erscheint eine Meldung sowie pro Zeile der Hinweis „Kann abgeschlossen werden“.

### Statistik
Die Trainings sollen mir einer Statistik-Funktion ausgestattet werden. Hier soll in ganzen Zahlen und in 100% die Anwesenheit der einzelnen Personen aufgezeigt werden. 

Zwei Ansichten:
1. Navigationsunterpunkt von Training: Die Statistik-Funktion soll einmal als Unterpunkt in der Navigation der Trainings zu finden sein und eine Gesamtübersicht, Gruppiert nach Trainings (nur laufende Trainings, keine abgeschlossenen) zeigen. Hier soll nach Sportart (Taxonomie sport) gefiltert werden können. Folgende Daten sollen in der Statistik in den einzelnen Spalten zu sehen sein: Name, Vorname, Rolle, Sportarten, Anwesenheiten (von Training-Sessions), Anwesenheit in %. Abgeschlossene Trainings zählen nicht mehr in die Statistik.
2. Statistik in jedem Training: In jedem einzelnen Training, wenn man es bearbeitet soll eine Statistik zu sehen sein. Am einfachsten ist eine tabellarische Übersicht.

## 3. Neue Funktion: Events
Erstelle einen neuen CPT "event". Dieser CPT dient dazu, die Lohnabrechnung zu erweitern.

### Rechte
- Administratoren können event posts anlegen, bearbeiten und löschen: Voller Zugriff auf alles.
- Hauptleiter können Person auswählen/eintragen, aber sonst keine Änderungen vornehmen.
- Die restlichen User-Rollen (Leiter, Assistenztrainer, usw.) haben keinen Zugriff auf diesen CPT (nicht sehen, bearbeiten, erstellen oder anderes).

### Felder
- Titel: Eingabe eine Titels für die Veranstaltung
- Datum: Datum der Veranstaltung:
- Dauer: Hier sollen die Werte aus der Einstellungs-Seite ausgewählt werden können. Als Dropdown oder Radio-Button, also nur ein Wert (z.B. 60 Minuten, 90 Minuten, usw.)
- Anwesende Personen (gruppiert). Ausgewählte Personen waren automatisch anwesend.
  - Hauptleiter*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ hauptleiter_in
  - Leiter*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ leiter_in
  - Athlet*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ athlet_in
  - Unified: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ unified
  - Assistenztrainer*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ assistenztrainer_in
  - Helfer*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ helfer_in
  - Praktikant*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ praktikant_in
  - Schüler*in: Alle Personen vom CPT „Mitglied“ mit acf feld „role“ schueler_in
- Bemerkungen. Eingabfeld (eine Zeile). Bitte hier vermerken, dass dieser Wert später in der Lohnabrechnung sichtbar ist.

Diese Felder können mit ACF angelegt werden, wenn sinnvoll.

Alternative, falls robuster und sinnvoller und möglich: Es können in einem Feld grundsätzlich alle Personen ausgewählt werden, aber es muss irgendwo die Rolle zugeteilt werden (z.B. Hauptleiter, Leiter, Assistenz), da das später für die Lohnabrechnung wichtig ist. Ausserdem darf eine Person nicht doppelt ausgewählt werden.

### Beachten
- Anlegen können Events Administratoren und Hauptleiter; Löschen nur Administratoren. 
- Personen zuteilen (Hauptleiter, Leiter usw.) können nur Administrator und Hauptleiter.
- Wird eine Person ausgewählt, heisst dass, dass die an diesem Event anwesend war und entsprechend abgerechnet werden muss.
- Die Felder können mit ACF angelegt werden.
- Es muss möglich sein, eine Person zu einer einzelnen Trainings-Session hinzuzufügen. Wegen der Lohnabrechnung später muss die Rolle der Person (z.B. Leiter*in) ausgewählt werden können.
- Dieselbe Person darf pro Training nur einmal ausgewählt worden sein.
- Wenn eine Person aus dem ganzen Training gelöscht wird, bleiben ihre Anwesenheitsinformationen erhalten.
- Beim Erstellen eines Events wird eine Benachrichtigungs-E-Mail an die in den Einstellungen hinterlegte Adresse gesendet. Empfänger unter „Einstellungen → Special Olympics → Neues Event – E-Mail-Empfänger".

### Events (CPT "event") und Anzeige im CPT "mitglied" (ohne Editierbarkeit im CPT "mitglied")
- Events werden weiterhin im eigenen CPT "event" gepflegt (Source of Truth).
- Im CPT "mitglied" sollen Event-Teilnahmen sichtbar sein, ohne dass sie dort manuell erfasst oder bearbeitet werden müssen.
- Damit kann das Telefonbuch (und weitere Übersichten) primär auf "mitglied" basieren, ohne Cross-CPT-Abfragen.
- Der CPT "event" ist die führende Datenquelle für:
  - Event-Stammdaten (Titel, Datum, Event-Typ, Sport, Dauer etc.)
  - Zuweisung von teilnehmenden Personen inkl. deren Rolle am Event (z. B. Athlet, Hauptleiter, Leiter, Assistenz)
- Sobald ein Event gespeichert/aktualisiert wird, werden die betroffenen Mitglied-Posts automatisch aktualisiert (Snapshot/Sync).

#### Synchronisationsregeln
- Beim Erstellen/Ändern eines Events:
  - Alle neu hinzugefügten Teilnehmenden erhalten den Event-Eintrag im CPT "mitglied".
  - Bei entfernten Teilnehmenden wird der Event-Eintrag aus dem CPT entfernt.
  - Bei Änderungen wird der Snapshot bei allen betroffenen Teilnehmenden aktualisiert.
- Beim Löschen eines Events:
  - Event wird bei allen betroffenen Mitgliedern aus CPT "mitglied" entfernt.

### Taxonomien für event
- Taxonomie (shared): sport. Hier soll angegeben werden können, um welche Sportart (z.B. Fussball, Basketball) es sich handelt.
- Taxonomie: event-type. Hier soll angegeben werden, um was für eine Art von Event es sich handelt (z.B. Weltspiele, Europäische Spiele, Internationale Spiele, Nationale Spiele, Regionale Spiele).

### Event-Zugriff und Übersicht
- Zugriff auf Events: Nur Administratoren und Hauptleiter sehen das Menü „Events“ und können Event-Seiten aufrufen. Alle anderen Rollen haben keinen Zugriff (Menü ausgeblendet, direkte URL führt zu Fehlermeldung).
- Events anlegen: Hauptleiter dürfen neue Events anlegen (Menüpunkt „Event hinzufügen“ und Button in der Übersicht). Beim Anlegen eines Events durch einen Hauptleiter wird eine E-Mail an die in den Einstellungen hinterlegte Adresse „Neues Event – E-Mail-Empfänger“ gesendet (Einstellungen → Special Olympics). Die Mail enthält Angaben zum anlegenden Hauptleiter, Event-Titel und Link zur Bearbeitung. Ist das Feld leer, wird keine E-Mail versendet.
- Übersicht für Nicht-Admins (Hauptleiter): In der Event-Liste werden nur Events angezeigt, an denen die eingeloggte Person selbst teilgenommen hat (ihr verknüpftes CPT-Mitglied ist in den Event-Teilnehmenden). Administratoren sehen weiterhin alle Events.
- Event bearbeiten (Hauptleiter): Ein Hauptleiter kann nur Event-Bearbeitungsseiten von Events öffnen, bei denen er/sie als Teilnehmer eingetragen ist; andernfalls erscheint eine Zugriffsfehlermeldung.

### Event-Übersicht: Filter und Suche
- In der Event-Übersicht können Events nach Sportart, Event-Typ und Datumsbereich (von/bis) gefiltert werden.
- Ein Suchfeld ermöglicht die Suche nach Titel oder Bemerkungen.
- Die Tabelle zeigt zusätzlich die Spalte Event-Typ.

### BH-Nummer (Überschreibung) bei Events
- Analog zum Training kann bei Events eine Buchhaltungsnummer überschrieben werden.
- Das Feld „BH-Nummer (Überschreibung)“ im Bearbeitungsmodus erlaubt, die aus der Sportart abgeleitete Standard-BH-Nummer zu überschreiben (z. B. für Spiele mit eigener BH-Nummer 5630).
- In der Lohnabrechnung wird zuerst die Überschreibung verwendet; falls leer, die BH-Nummer der Sportart.

### Logik: Achtung!
- Wenn mehrere Sportarten auswählbar wären, könnte es durch die automatische BH-Nummer-Zuweisung (die über die Sportart erfolgt) in der Lohnabrechnung sehr kompliziert werden. Es wäre dann einfacher, wenn mehrere Sportarten an einem Event sind, den Event mehrmals (pro Sportart) anzulegen.
- Ähnlich bei dem Event-Typ: Es ist alles einfacher, wenn pro Sportart ein Event-Typ ausgewählt ist.

## 4. Lohnabrechnung
- Anwesenheits- und Gehaltsabrechnungsvorgänge müssen in einem strukturierten, abfrageeffizienten Format gespeichert werden, das Einschränkungen, Prüfpfade und schnelle Aggregation unterstützt.

### Rechte
- Nur Administratoren können Lohnabrechnungen durchführen.
- Alle anderen Rollen haben keinen Zugriff.

### Status, die eine Lohnabrechnung haben kann:
- Entwurf -> erste, automatische Zusammenstellung
- Geprüft -> Entwurf wird geprüft und als „geprüft“ markiert. Änderungen soll noch durchgeführt werden können.
- Abgeschlossen -> Wenn das PDF für den Download generiert wurde oder das Mail versendet wurde, erlangt die Lohnabrechnung den Status „Abgeschlossen“ und kann nicht mehr verändert werden.

### Lohnabrechnung: Einstieg und Übersicht
- Beim Klick auf „Lohnabrechnung“ erscheint die Übersicht der offenen Lohnabrechnungen (Status Entwurf oder Geprüft).
- Von dort aus können neue Lohnabrechnungen angelegt, bestehende bearbeitet oder gelöscht werden.
- Die Historie enthält nur abgeschlossene Lohnabrechnungen.
- Beide Listen (offene, Historie) bieten Filter nach Person. Die Person-Auswahl zeigt nur Personen, die in der jeweiligen Liste vorkommen (offene Liste: nur Personen mit offenen Abrechnungen; Historie: nur Personen mit abgeschlossenen Abrechnungen).
- Alle Datumsangaben werden einheitlich im deutschen Format (z. B. 31.01.2026) angezeigt.
- In der Personauswahl für neue Lohnabrechnungen werden Athleten nicht angezeigt – für sie wird nie eine Lohnabrechnung erstellt.

### Lohnabrechnung: Einstieg und Datum 
Im Backend sollen User mit der Rolle Administrator Lohnabrechnungen erstellen können. Sie sollen dafür auf eine Übersichtsseite die Personen aus dem CPT „Mitglied“ auswählen, bei denen eine Lohnabrechnung erstellt werden kann bzw. bei den Daten für eine Lohnabrechnung verfügbar sind. Zur Sicherheit ist es jedoch nur möglich sein, eine Lohnabrechnung auf einmal zu erstellen, nicht mehrere gleichzeitig.
In diesem Schritt soll ein Datum ausgewählt werden für die Lohnabrechnung (z.B. 01.01.2025 – 31.12.2026). Wenn bereits eine Lohnabrechnung für eine Person in diesem Zeitraum erstellt worden ist und diese Periode nochmals ausgewählt wird, soll eine Benachrichtigung erscheinen (egal, welcher Status die Lohnabrechnung hat) und gefragt werden, wie weiter verfahren werden soll. 

Möglichkeiten zum weiteren Verfahren: 
- Abbrechen
- Neue Lohnabrechnung anlegen: Hat die bestehende Lohnabrechnung den Status „Entwurf“ oder „geprüft“, kann die neu erstellte Lohnabrechnung die ältere Lohnabrechnung überschreiben. Die alte wird gelöscht und durch die neue ersetzt. Hat bestehende Lohnabrechnung den Status „Abgeschlossen“, darf sie nicht überschrieben werden. In diesem Fall wird eine zweite Lohnabrechnung erstellt. Diese Erklärung soll bei der Auswahl angezeigt werden. Dieses Vorgehen soll dazu dienen, dass keine doppelten Lohnabrechnungen erstellt und versendet werden. Die zweite Lohnabrechnung soll mit v2 gekennzeichnet werden.

### Lohnabrechnung: Erstellung und Daten sammeln
Wenn eine Person ausgewählt wurde und das Datum ausgewählt wurde, sollen die Daten für diesen Zeitraum bzw. diese Periode gesammelt werden. Es gibt dabei zwei Datenquellen:

- Trainingsanwesenheiten: Um das zu überprüfen, müssen die Anwesenheiten aus dem CPT "training" überprüft werden. Die Anwesenheiten der jeweiligen Person müssen gezählt werden.
- Events: Administrator legen über einen neuen CPT „Event“ Events an. Diese Events können nur von Administratoren angelegt werden. 

#### Darstellung
Wenn alle Daten automatisch gesammelt wurden, sollen diesen übersichtlich dargestellt werden. In zwei Tabellen.

1. Erste Übersicht über Trainingsanwesenheiten Auflistung folgender Daten:
Sportart: Auflistung aller CPT Training, in denen die Person anwesend war.
Bemerkungen: Feld aus dem CPT training.
Qualifikation: Wurde er in dem jeweiligen Training als Hauptleiter, Leiter, Assistenztrainer oder anderem eingetragen.
Dauer: ersichtlich aus CPT Training -> Dauer Feld.
BH NR: Hier wird eine hinterlegte Buchhaltungsnummer eingefügt. (Einstellungs-Seite)
Anzahl: Summierte Anzahl der Anwesenheiten. Beim Hovern über die Anzahl sollen die einzelnen Daten der Trainings-Sessions sichtbar sein (nur das Datum).
CHF pro Std: Hinterlegter Betrag pro Stunde (Betrag auf Einstellungs-Seite, Stufe bei CPT "Mitglied" ACF-Feld. Die Stufe ist nur in der Rolle Hauptleiter wichtig, da hier zwischen "1A" und "1B" unterschieden wird. Deshalb muss hier zusätzlich das geprüft werden. Der ist im ACF Feld "grade_hl" wie folgt hinterlegt: 1B : Hauptleiter*in 1A; 1B : Hauptleiter*in 1B). Für die Berechnung muss nachgesehen werden, als welche Rolle die Person eingetragen ist und welche Stufe diese Person in dieser Rolle hat.
CHF / Bereich: = Anzahl * CHF pro Std
Ganz am Schluss der Tabelle soll die Summe enthalten sein.

#### Berechnungen
Über die Einstellungs-Seite sollen Werte für Stundenansätze hinterlegt werden.

#### Stufen
- Eine Person kann mehrere Rollen haben.
- Pro Rolle kann eine Person eine eigene Stufe besitzen.

#### Buchhaltungs-Nummern (BH Nummern)
Grundgedanke: Auf der Einstellungs-Seite werden die Buchhaltungsnummern für die Lohnabrechnung hinterlegt. Jede Sportart hat eine Buchhaltungsnummer. Die Sportarten sind in der Taxonomie "sport" aufgeführt.
Hier sind die Zuordnungen der Buchhaltungs-Nummern mit den Sportarten:

Es muss möglich sein, beim jeweiligen Training anzugeben, welche Buchhaltungsnummer das Training entspricht. Entweder über die Taxonomie sport (diese brauchen wir auf jeden Fall, da später auch oft danach gefiltert werden soll) und/oder ein zusätzliches ACF Feld (was mehr sinn macht). Spätere Änderungen soll möglichst ohne Programmieraufwand vorgenommen werden können.

2. Zweite Tabelle zur Übersicht der Events, an denen eine Person teilgenommen hat.
Diese Tabelle hat den gleichen Aufbau wie Tabelle 1. Anstatt Sportart wird einfach Event als Titel geschrieben.

### Lohnabrechnung: Prüfung
Administrator können die Lohnabrechnung nun prüfen.
Administrator können jedoch Anpassungen vornehmen: Sie können Positionen löschen, ändern oder neue hinzufügen.
Wenn alles korrekt ist, soll die Lohnabrechnung durch einen Klick auf einen Button als "geprüft" markiert werden.
Im geprüften Status sollen manuelle Änderungen noch möglich sein.


### Lohnabrechnung: Abschliessen
Stimmt nun alles, kann die Lohnabrechnung abgeschlossen werden (durch einen Klick auf einen "abschliessen"-Button). Der Status wechselt auf abgeschlossen und eine PDF mit der Lohnabrechnung wird erstellt und abgelegt (echte PDF-Datei, z. B. via Dompdf/Composer). Die Datenbasis für die Lohnabrechnung sind nur abgeschlossene Trainings. Dateiname z. B. Name_Vorname_payroll_id_timestamp.pdf.
Mit diesem Status können keine Änderungen mehr vorgenommen werden.
Die Lohnabrechnung ist nun auch nicht mehr löschbar.
Nun kann man folgendes machen: 
1. Lohnabrechnung als PDF downloaden (Button)
Mit einem Klick auf einen Download-Button soll die PDF downloadbar sein.
Du kannst das Template gerne erstellen. Es soll ganz ähnlich wie die Tabelle oben sein. Folgende kommt noch dazu:
- Name und Vorname der Person, Strasse, PLZ und Ort
- Abrechnungsperiode
- Name der Bank und IBAN (ist im ACF Field "bank_name" und "bank_iban")

2. Mail versenden.
Nur Lohnabrechnungen mit Status "abgeschlossen" können per E-Mail versendet werden. Die PDF wird der E-Mail als Anhang beigefügt. Mail-Betreff und Mail-Text werden vorausgefüllt (Einstellungs-Seite), Sende-Button (ggf. Pop-Up). Mailadresse: ACF "e-mail". Gravity SMTP wird genutzt.
Zusatzfunktion: Bulk-Versand zeigt alle abgeschlossenen Lohnabrechnungen ohne versendete Mail; Versand mit Standard-Text und PDF-Anhang.

3. PDF-Generierung (aktueller Stand vs. Snapshot)  
- Für Lohnabrechnungen im Status Entwurf oder geprüft wird die PDF beim Klick auf „PDF herunterladen“ jedes Mal neu generiert, sodass Layout- und Datenänderungen (z. B. manuelle Änderungen) sofort sichtbar sind.  
- Für Lohnabrechnungen im Status abgeschlossen wird die beim Abschliessen erzeugte PDF wiederverwendet (Snapshot); nur falls noch keine Datei existiert (ältere Datensätze), wird einmalig eine PDF generiert und gespeichert. Änderungen am System haben so keinen Einfluss auf die versendeten Abrechnungen.

### Lohnabrechnung: Historie
Auf einer Übersichtsseite sollen alle Personen, für die eine Lohnabrechnung erstellt wurde, aufgelistet werden. Mit einem Klick auf die Person, sieht man alle Details: Erstelle Lohnabrechnungen mit Datum der Erstellung, Möglichkeit des erneuten Downloads oder erneuten Versands per Mail. Man soll auch sehen, ob die Lohnabrechnung per Mail versendet worden ist und mit welchem Text.

### Lohnabrechnung: Löschen (Entwurf und geprüft)
Solange eine Lohnabrechnung den Status „Entwurf“ (draft) oder „geprüft“ hat, soll sie vollständig aus der Datenbank gelöscht werden können. Die Löschfunktion ist an zwei Stellen verfügbar: auf der Historie-Seite (Link „Löschen“ neben „Bearbeiten“ bei Entwürfen und geprüften Abrechnungen) und im Bearbeitungsscreen der Lohnabrechnung (Button „Lohnabrechnung löschen“). Nach Bestätigung werden der Lohnabrechnungs-Datensatz und alle zugehörigen Zeilen (Trainings/Events) gelöscht; anschliessend erfolgt die Weiterleitung zur Historie. Nur Lohnabrechnungen mit Status „abgeschlossen“ können nicht gelöscht werden.

### Beachte
- Automatisches Neusammeln: Beim Öffnen einer Lohnabrechnung im Bearbeitungsmodus (Status Entwurf oder geprüft) werden die Daten automatisch neu gesammelt; die Seite lädt anschliessend mit aktualisierten Daten.
- Links auf Sportart/Event: Ein Klick auf die Sportart (Trainingsanwesenheiten) oder den Event-Titel (Events) führt direkt in den Bearbeitungsmodus des jeweiligen Trainings bzw. Events.
- Bereinigung nicht gebrauchter PDFs mit Cron.

### Lohnabrechnung: Manuelle Änderungen
Unterhalb der Events-Tabelle gibt es einen Bereich «Manuelle Änderungen». Dort können zusätzliche Positionen mit Kommentar und Betrag hinzugefügt werden. Der Betrag kann positiv (wird zur Gesamtsumme addiert) oder negativ (wird abgezogen) sein. Diese Änderungen werden nicht durch «Daten neu sammeln» überschrieben und erscheinen auch in der PDF. Bearbeitung/Löschen nur bei Status Entwurf oder geprüft; abgeschlossene Lohnabrechnungen zeigen die manuellen Änderungen nur noch an.

## 5. Neue Funktion: Telefonbuch
Grundgedanke: Administratoren, Hauptleiter und Leiter (vielleicht später auch andere Rollen) brauchen im Notfall alle Daten der Teilnehmer im Training. Aber auch zur Vorbereitung für die Trainings, Events, Lager usw. müssen die Daten für betroffene Hauptleiter und Leiter verfügbar sein. Es soll wie eine Art Telefonbuch funktionieren.

### Funktionen
- Erstelle in der Hauptnavigation links einen Navigationspunkt "Telefonbuch"
- Starke Suche und Filter-Möglichkeiten, vor allem in der Ansicht "allen Daten".

### Datenquellen
- CPT mitglied

### Externe Skripts
Verwende https://datatables.net/, aber nicht extern laden, sondern benötigte Dateien lokal speichern.


### Darstellung Notfall (Sortierung Nachname A-Z)
 - Header
   - Titel: Telefonbuch
   - Modus-Switch: Notfall | Alle Daten (gut sichtbar)
  - Anzeige (Cards)
    - Vorname Name,
    - Strasse, PLZ, Ort
    - Name Notfallkontakt (name_notfallkontakt) und Telefon Notfallkontakt (telefon_notfallkontakt) mit Tap-to-call (Telefon)
    - Auflistung weitere Kontakte. Infos vom Repeater Field: weitere_kontakte
      - funktion, vorname, nachname, telefon
    - Notfallmedikamente (Repeater field). Nebeneinander: name_medikament_notfall und dosis_medikament_notfall
    - Medikamente (repeater field "medikamentangaben"): name_medikament und dosis_medikament
    - Medizinisches Datenblatt. Download link zu ACF Feld (typ Upload Files) "medizinische_datenblatter"

### Darstellung alle Daten (Sortierung Nachname A-Z)
 - Header
   - Titel: Telefonbuch
   - Modus-Switch: Notfall | Alle Daten (gut sichtbar)
- Standardansicht ist minimal (Name, Vorname, Telefonnummer, e-Mail, Ort).
- Zusätzliche Informationen werden nicht frei spaltenweise, sondern blockweise über definierte Preset-Ansichten ein- und ausgeblendet. Hier die Presets: Allgemein, Kleidung, Kontaktperson und später dann auch die Felder mit den Events.
- Eingeblendete Spalten sind sortier- und filterbar.
- Umsetzung als DataTables.
- Komplexe Felder (Repeater, lange Texte, File-Uploads) werden nicht als Spalten dargestellt, sondern über eine Expand/Detailansicht (Accordion/Drawer) pro Person (umgesetzt: Detail-Button pro Zeile blendet weitere Kontakte, Notfallmedikamente, Medikamentangaben, medizinische Datenblätter, Event-Teilnahmen ein).

### Rechte
- Administratoren sehen alle Personen, Standardansicht: Alle Daten
- Hauptleiter und Leiter (vielleicht weitere Personen) sehen nur Daten von Personen, die in der taxononomie sport in der gleichen Sportart eingetragen sind. Das können einzelne oder mehrere sein. Das heisst: Eine Person ist sichtbar, wenn mindestens ein identischer sport-Term im CPT "mitglied" vorhanden ist. Diese Sichtbarkeitsregel ist bewusst grob gewählt zugunsten von Einfachheit und Performance. Es wird in Kauf genommen, dass Hauptleiter/Leiter Personen sehen, die nicht in derselben konkreten Trainingsgruppe sind.

## 6. Neue Funktion: Kontakte
Grundgedanke: Sammeln aller weiteren Kontakt, die jedoch keine Mitglieder bei SOLie sind.
- Erstelle einen CPT "contact"
- Weitere Felder füge ich mit ACF hinzu.

### Rechte
- Der CPT "contact" ist nur für Administratoren sichtbar

## 7. Token-Anwesenheit (Anwesenheit ohne Login)
Hauptleiter und Leiter sollen Anwesenheit schnell erfassen können, ohne sich im WordPress-Backend anzumelden.

- Ein Token pro Nutzer: Jeder Hauptleiter und jeder Leiter (sowie Administratoren) erhält einen persönlichen, geheimen Token.
- Anzeige der URL auf der Trainings-Übersicht: „Anwesenheit schnell erfassen“.

### Sicherheitsfunktionen
- PIN-Schutz (Pflicht): Zugriff auf die Anwesenheitserfassung erfordert einen 4-6 stelligen numerischen PIN.
- Session-Cookie: Nach korrekter PIN-Eingabe wird ein 15-Minuten-Cookie gesetzt; bei jedem neuen Besuch ist die PIN erneut erforderlich (siehe Abschnitt 23.16).
- Rate-Limiting: Nach 5 fehlgeschlagenen PIN-Versuchen wird der Zugriff für 15 Minuten gesperrt (Brute-Force-Schutz).
- Token-Ablauf: Tokens laufen nach 1 Jahr ab und müssen dann erneuert werden.
- Cookie-Signatur: Das Authentifizierungs-Cookie ist mit HMAC signiert (Token + User-ID + Salt) und HttpOnly/Secure.

### PIN-Verwaltung
- PIN setzen/ändern: Auf der Trainings-Übersichtsseite können Hauptleiter und Leiter ihren persönlichen PIN setzen oder ändern.
- Kein PIN gesetzt: Beim ersten Aufruf der Anwesenheits-URL ohne gesetzten PIN erscheint eine Meldung mit Link zum Backend.
- PIN-Eingabe: Nach Token-Validierung wird ein PIN-Formular angezeigt (mobile-optimiert, numerische Tastatur).

### Token-Verwaltung
- Gültigkeitsdatum: Das Ablaufdatum des Tokens wird auf der Trainings-Übersichtsseite angezeigt.
- Neuen Link erzeugen: Ein Button ermöglicht das Regenerieren eines neuen Tokens (alter Link wird ungültig).

### Ablauf
1. User ruft auf
2. Token wird validiert (existiert, nicht abgelaufen)
3. Prüfung ob PIN gesetzt → Nein: Meldung „Bitte PIN im Backend setzen"
4. Prüfung Rate-Limiting → Gesperrt: Fehlermeldung „Zu viele Versuche"
5. Prüfung Auth-Cookie → Vorhanden & gültig: Direkt zur Anwesenheit
6. PIN-Eingabe anzeigen → Korrekt: Cookie setzen, weiter zur Anwesenheit
7. PIN falsch: Zähler erhöhen, erneut versuchen oder sperren

## 8. Dashboard: Rollenspezifische Übersichtsseiten

Die Übersicht nach dem Login soll je nach Rolle unterschiedliche Inhalte anzeigen. Alle Benutzer (inkl. Administratoren) landen auf der SOE-Dashboard-Seite. Das Standard-WordPress-Dashboard wird vollständig ersetzt.

- Admins erhalten eigene Dashboard-Inhalte: Offene Lohnabrechnungen, Laufende Trainings, Kommende Events, Schnellzugriff (Lohnabrechnung, Trainings, Events, Telefonbuch, Einstellungen, Mitglieder).
- Menü „Übersicht“ und Redirect von `index.php` gelten für alle Rollen.
- Login-Redirect leitet alle Nutzer auf `soe-dashboard`.
- Nach Login wird geprüft, ob das verknüpfte Mitgliedsprofil unvollständig ist (u. a. Vorname, Nachname, E-Mail, Telefon, Geburtsdatum, Adresse, Rolle, Sport). Unter einem konfigurierbaren Schwellwert (z. B. weniger als 6 von 10 Feldern) erscheint eine Lightbox mit Hinweis, Button „Profil bearbeiten“ und „Später erinnern“ (Cookie 7 Tage).

### Übersicht Rollen und Zugriff
| Rolle | Berechtigungen | Aktuelles Menü |
|-------|----------------|----------------|
| **Administrator** | Vollzugriff | Übersicht, Mitglieder, Trainings, Events, Lohnabrechnung, Telefonbuch, Einstellungen, Benutzer |
| **Hauptleiter_in** | edit_trainings, edit_events, view_telefonbuch | Übersicht, Mein Account, Trainings, Events, Telefonbuch |
| **Leiter_in** | edit_trainings (Anwesenheit), view_telefonbuch | Übersicht, Mein Account, Trainings, Telefonbuch |
| **Ansprechperson** | edit_mitglieds, publish_mitglieds | Übersicht, Mein Account, Athlet\*innen |
| **Athlet_in, Helfer_in, etc.** | read_mitglied | Übersicht, Mein Account |

