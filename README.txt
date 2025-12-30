Controlled Residential Ventilation – Humidity Control

1. Überblick

Dieses IP-SYMCON-Modul dient zur intelligenten, feuchtegeführten Steuerung einer kontrollierten Wohnraumlüftung.

Der Fokus liegt auf:

stabiler Regelung über absolute Feuchte

Unterstützung von bis zu 10 Innenraumsensoren

optionalem Außenluft-Abgleich (Sommer/Winter-Logik)

Feuchtesprung-Erkennung (z. B. Duschen)

Nachtabschaltung mit akustischem Schallschutz

robuster Fehlererkennung & Diagnose

universeller Nutzung (KNX nicht erforderlich)

Das Modul ist herstellerneutral und kann mit beliebigen Lüftungsanlagen eingesetzt werden, solange der Stellwert in Prozent steuerbar ist.

2. Funktionsprinzip
2.1 Regelgröße: Absolute Feuchte

Das Modul arbeitet nicht mit relativer Feuchte, sondern mit absoluter Feuchte (g/m³).

Vorteile:

temperaturunabhängig

stabiler Sommer-/Winterbetrieb

realistischer Vergleich Innen ↔ Außen

verhindert falsches Hochlüften bei schwüler Außenluft

Die absolute Feuchte wird aus:

relativer Feuchte (% rF)

Temperatur (°C)

berechnet.

3. Sensorik
3.1 Innensensoren

Bis zu 10 Sensorpaare

Jeder Sensor besteht aus:

Feuchte (% rF, Float)

Temperatur (°C, Float)

➡️ Wichtig:
Nicht konfigurierte Sensoren werden ignoriert und beeinflussen die Regelung nicht.

Die Regelung verwendet immer den kritischsten Wert
→ höchste absolute Feuchte aller aktiven Sensoren.

3.2 Außensensor (optional)

Wird der Außenabgleich aktiviert, berücksichtigt das Modul zusätzlich:

Außen-Feuchte (% rF)

Außen-Temperatur (°C)

Ziel:

Lüften nur dann verstärken, wenn Außenluft trockener ist

automatische Sommer-/Winterlogik

Schutz vor „feucht reinlüften“

⚠️ Feuchtesprung hat immer Vorrang!
(z. B. Duschen → sofortige Lüftung, auch wenn Außenluft ungünstig ist)

4. Lüftungsstufen & Stellwert
4.1 Prozentuale Steuerung

Die Lüftung wird ausschließlich über Prozentwerte (0–100 %) angesteuert.

Interne Zuordnung:

Stufe	Stellwert
1	12 %
2	24 %
3	36 %
4	48 %
5	60 %
6	72 %
7	84 %
8	96 %

➡️ Die Ausgabevariable muss:

Integer

schreibbar

SYMCON-Variable (keine KNX-Objektverknüpfung!)

5. Feuchtesprung-Erkennung (kritisch!)
5.1 Logik

Ein Feuchtesprung liegt vor, wenn:

der Anstieg der relativen Feuchte

≥ konfigurierter Schwellenwert (Standard: 10 %)

innerhalb eines Update-Zyklus (≤ 5 Minuten)

5.2 Reaktion (immer!)

Die Lüftung wird sofort um 3 Stufen erhöht

unabhängig von:

Jahreszeit

Außenluft

Nachtabschaltung

➡️ Sicherheit & Feuchteschutz haben absolute Priorität

6. Nachtabschaltung (Schallschutz)
6.1 Funktion

Während der Nachtzeit kann die Lüftung automatisch:

deaktiviert oder reduziert werden

gesteuert über eine Boolean-Variable

Typischer Einsatz:

KNX Zeitschaltobjekt

Zeitsteuerung in IP-SYMCON

6.2 Ausnahme: Feuchtesprung

Wird während der Nachtabschaltung ein Feuchtesprung erkannt:

Lüftung wird für max. 60 Minuten aktiviert

danach automatische Rückkehr zur Nachtabschaltung

Status wird visualisiert

7. Regelzyklus

Frei einstellbar: 5 bis 30 Minuten

Empfehlung:

Normalbetrieb: 10 Minuten

Hohe Feuchtelast: 5 Minuten

Energiesparbetrieb: 15–20 Minuten

Zusätzlich:

Stellwertausgabe erfolgt zyklisch jede Minute

unabhängig vom Regelzyklus

8. Rückmeldung & Fehlerüberwachung
8.1 Rückmeldevariable (optional, empfohlen)

Die Lüftungsanlage kann ihren aktuellen Stellwert (%) zurückmelden.

Typ: Integer

lesbar

Rückmeldung darf bis zu 30 Sekunden verzögert sein

8.2 Watchdog

Wenn:

keine Rückmeldung

innerhalb von 5 Minuten

➡️ Status wechselt auf Fehler

9. Status- & Diagnosevariablen
9.1 Status Lüftung (CRVHC.Status)
Wert	Bedeutung
0	Aus
1	Ein
2	Nachtabschaltung
3	Feuchtesprung aktiv
4	Fehler

Farblich visualisiert im WebFront.

9.2 Weitere Diagnosevariablen

Lüftungsstellwert (%)

Lüftungsstufe (1–8)

Nachtabschaltung aktiv (Boolean)

Feuchtesprung aktiv (Boolean)

Diagnose (Text)

10. Live-Validierung & Fehlermeldungen

Beim Speichern der Eigenschaften prüft das Modul automatisch:

Existenz der Variablen

Variablentyp (Float / Integer / Boolean)

Lesbarkeit / Schreibbarkeit

Vollständigkeit der Sensoren

Statuscodes:
Code	Bedeutung
102	OK
200	Warnung (läuft eingeschränkt)
201	Fehler (keine Regelung)
11. Häufige Fehlermeldungen (Erklärung)
„Variable is marked as read-only“

➡️ Ursache:

KNX-Statusobjekt als Ausgabevariable gewählt

✅ Lösung:

Ausgabe immer auf eine SYMCON-Variable

KNX nur über Aktions-/Gateway-Module koppeln

„Invalid profile type“

➡️ Ursache:

Profil existiert nicht oder falscher Typ

✅ Lösung:

Modul erzeugt alle benötigten Profile automatisch

12. KNX – empfohlene DPTs (Hinweis)
Zweck	DPT
Feuchte	9.007
Temperatur	9.001
Stellwert %	5.001
Nachtabschaltung	1.001

⚠️ Nicht verpflichtend – Modul ist KNX-unabhängig.

13. Erweiterungsideen (Ausblick)

Bereits vorbereitet / geplant:

selbstlernende Regelung

Bewertung „Lüften wirkt / wirkungslos“

automatische Anpassung:

Mindestlaufzeit

Sommerbegrenzung

Stufensprünge

Statistik & Trendanalyse

WebFront-Diagramme

14. Support & Weiterentwicklung

Dieses Modul wurde entwickelt als:

robuste Basis

transparent nachvollziehbar

leicht erweiterbar

👉 Änderungen, Erweiterungen und Optimierungen sind explizit vorgesehen.