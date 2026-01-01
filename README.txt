Controlled Residential Ventilation – Feuchteregelung
Version 3.0 | Build 8
===============================================

1. Zweck des Moduls
-------------------
Dieses Modul steuert eine kontrollierte Wohnraumlüftung
auf Basis der gemessenen Luftfeuchte mehrerer Räume.

Ziel:
- Vermeidung von Feuchteschäden
- Schnelle Reaktion auf Feuchtesprünge (z.B. Duschen)
- Ruhiger Betrieb bei Nacht
- Einfache Einbindung in IP-SYMCON (ohne feste Busbindung)

Die Ausgabe erfolgt ausschließlich als Stellwert in Prozent (0–100 %).


2. Unterstützte Sensoren
------------------------
- Relative Feuchte (% rF)
- Temperatur (°C)

Die Regelung arbeitet intern mit:
→ absoluter Feuchte (g/m³)

Warum?
- Absolute Feuchte ist unabhängig von der Temperatur
- Sommer / Winter automatisch korrekt
- Vergleich Innen / Innen sinnvoll


3. Sensorlogik
--------------
- Es können 1 bis 10 Innensensoren eingebunden werden
- Pro Sensor sind erforderlich:
  - 1x Feuchte (% rF)
  - 1x Temperatur (°C)
- Es wird IMMER der Sensor mit der höchsten Feuchte verwendet
- Niedrige Werte anderer Sensoren reduzieren die Lüftung NICHT


4. Regelzyklus
--------------
- Frei einstellbar zwischen 5 und 30 Minuten
- Standard: 10 Minuten
- Bei jedem Zyklus:
  - Sensoren lesen
  - Absolute Feuchte berechnen
  - Feuchtesprung prüfen
  - Ziel-Stellwert berechnen
  - Ausgabe aktualisieren


5. Feuchtesprung-Erkennung (KRITISCH)
-------------------------------------
Definition:
Ein Feuchtesprung liegt vor, wenn:

- die relative Feuchte
- um mindestens X % rF
- innerhalb eines Regelzyklus ansteigt

(Standard: 10 % rF)

Wirkung:
- Lüftung wird IMMER um +3 Stufen erhöht
- entspricht +36 %
- unabhängig von:
  - Tageszeit
  - Nachtabschaltung
  - Sommer / Winter

Maximalwert: 96 %


6. Nachtabschaltung
-------------------
Optional kann eine BOOL-Variable verknüpft werden.

TRUE  = Nachtbetrieb aktiv → Lüftung AUS
FALSE = Normalbetrieb

WICHTIG:
- Ein Feuchtesprung übersteuert die Nachtabschaltung
- In diesem Fall:
  - Lüftung wird für max. 60 Minuten aktiviert
  - danach automatisch wieder abgeschaltet


7. Ausgabewerte
---------------
Das Modul schreibt ausschließlich:

- einen Prozentwert (0–100 %)

Diese Variable MUSS:
- schreibbar sein
- per RequestAction beschreibbar sein

Typische Anbindung:
- KNX DPT 5.001
- ModBus Holding Register
- Virtuelle Variable mit Weiterverarbeitung


8. Status-Variablen
-------------------
Lüftungsstatus (~CRV_Status):

0 = Aus
1 = Betrieb
3 = Feuchtesprung aktiv
4 = Fehler (keine Sensoren)
5 = Nachtabschaltung

Diese Variable dient:
- Diagnose
- Visualisierung
- WebFront-Ampel


9. Typische Fehler & Lösungen
-----------------------------

Fehler:
"Profilname darf keine Sonderzeichen enthalten"

Ursache:
- Profilname enthält Punkt oder #
- Lösung:
  - Profilname darf nur ~ A–Z a–z 0–9 _ enthalten


Fehler:
"Variable #0 existiert nicht"

Ursache:
- Variable nicht verknüpft
- SensorCount zu hoch
- Lösung:
  - Nur Sensoren 1..N befüllen
  - Rest leer lassen


10. Bekannte Einschränkungen (Version 3.0)
------------------------------------------
- Keine Außenluft-Referenz
- Keine selbstlernende Optimierung
- Keine Mindestlaufzeit je Stufe
- Keine Live-Validierung im Formular

Diese Funktionen werden ERST ergänzt,
wenn der stabile Betrieb bestätigt ist.


11. Empfohlene Testphase
-----------------------
- Mindestens 48 Stunden Betrieb
- Beobachten:
  - Reaktion auf Duschen
  - Nachtverhalten
  - Prozent-Ausgabe
  - Status-Ampel

Erst danach:
→ Erweiterungen aktivieren


12. Support & Erweiterungen
---------------------------
Dieses Modul ist bewusst:
- einfach
- robust
- transparent

Erweiterungen erfolgen:
- schrittweise
- rückwärtskompatibel
- erst nach erfolgreichem Feldtest
