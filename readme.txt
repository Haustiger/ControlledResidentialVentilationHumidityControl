Controlled Residential Ventilation – Humidity Control
Version 3.2 – Build 8
Author: Haustiger

Beschreibung
------------
Dieses Modul steuert eine kontrollierte Wohnraumlüftung
auf Basis der absoluten Luftfeuchte (g/m³).

Es können bis zu 10 Innensensoren (relative Feuchte + Temperatur)
verknüpft werden. Aus diesen wird die absolute Feuchte berechnet
und ein Mittelwert gebildet.

Zusätzlich werden:
- Minimum der absoluten Feuchte (seit Modulstart)
- Maximum der absoluten Feuchte (seit Modulstart)
geführt.

Funktionen
----------
- Berechnung absolute Feuchte (physikalisch korrekt)
- Mittelwertbildung über mehrere Räume
- 8-stufige Lüftungskennlinie
- Feuchtesprung-Erkennung (konfigurierbar)
- Debug-Variablen zur Nachvollziehbarkeit
- Manuelle und zyklische Regelung

Feuchtesprung
-------------
Ein Feuchtesprung wird erkannt, wenn die durchschnittliche
relative Feuchte innerhalb von 5 Minuten den konfigurierten
Schwellwert überschreitet.

In diesem Fall:
- Erhöhung der Lüftungsstufe um +3
- Laufzeit 15 Minuten
- Debug-Informationen werden gesetzt

Button
------
„Regelung jetzt ausführen“ startet die Regelung manuell.

Bekannte Einschränkungen
------------------------
- Keine Nachtabschaltung (kommt in späterem Build)
- Keine selbstlernende Regelung (geplant)
- Keine Außenbewertung / Gewichtung (geplant)

Status
------
Build 8 gilt als stabiler Referenzstand.
Weitere Builds bauen ausschließlich darauf auf.




Controlled Residential Ventilation – Humidity Control
Version 3.2 – Build 9
Author: Haustiger

Änderung gegenüber Build 8
--------------------------
Bugfix: Absolute Feuchte außen

In Build 8 war die Variable "Absolute Feuchte außen" registriert,
wurde jedoch nicht berechnet oder beschrieben.

Build 9 ergänzt:
- Berechnung absolute Feuchte außen aus:
  - Außen-Temperatur
  - Außen-relativer Feuchte
- Physikalisch korrekte Formel
- Aktualisierung bei jeder Regelung

Keine weiteren Änderungen.
Keine Funktionen entfernt.




Controlled Residential Ventilation – Humidity Control
Version 3.2 Build 10
Author & Manufacturer: Haustiger

NEU in Build 10:
- Einführung der Außenbewertung
- Berechnung absolute Feuchte außen
- Vergleich Innen Ø vs. Außen
- Debug-Variablen:
  - Außenbewertung aktiv
  - Feuchte-Differenz
  - Bewertungsergebnis

Keine entfernten Funktionen.
Keine GUID-Änderungen.
Kompatibel zu IP-SYMCON.


