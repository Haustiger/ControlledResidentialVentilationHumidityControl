Controlled Residential Ventilation – Humidity Control
Version 3.2 – Build 9
Author: Haustiger

ZWECK
Dieses Modul steuert eine kontrollierte Wohnraumlüftung
auf Basis der absoluten Feuchte (g/m³).

GRUNDPRINZIP
- Umrechnung relativer Feuchte + Temperatur → absolute Feuchte
- Mittelwertbildung über bis zu 10 Innensensoren
- Ermittlung MIN / MAX (laufend)
- 8-stufige Lüftungskennlinie
- Optionaler Feuchtesprung (z.B. Duschen, Kochen)

LÜFTUNGSSTUFEN
Stufe 1 = 12 %
Stufe 2 = 24 %
Stufe 3 = 36 %
Stufe 4 = 48 %
Stufe 5 = 60 %
Stufe 6 = 72 %
Stufe 7 = 84 %
Stufe 8 = 96 %

FEUCHTESPRUNG
Ein Feuchtesprung liegt vor, wenn:
Δ relative Feuchte ≥ eingestellter Schwellwert innerhalb von 5 Minuten.

Wirkung:
- aktuelle Lüftungsstufe +3
- zeitlich begrenzt (15 Minuten)
- Debug-Variablen dokumentieren Erkennung und Dauer

NACHTABSCHALTUNG
- Optional aktivierbar
- Zeitbereich frei definierbar
- Während der Nacht wird die Regelung ausgesetzt
- Feuchtesprung-Override ist vorbereitet (folgende Builds)

AUSSENBEWERTUNG
- Absolute Feuchte außen wird berechnet
- Aktuell nur diagnostisch
- Gewichtung und Sommer/Winter-Logik folgen

DEBUG / STATUS
- Alle Regelentscheidungen werden im SYMCON-Status protokolliert
- Zusätzliche Debug-Variablen zur Nachvollziehbarkeit

HINWEISE
- Relative Feuchte kann als Prozent (0–100) oder Faktor (0–1) geliefert werden
- Stellwert-Variable muss schreibbar sein (kein ReadOnly)
- KNX, Modbus oder virtuelle Variablen sind geeignet

NÄCHSTE SCHRITTE (geplant)
- Nachtabschaltung mit Feuchtesprung-Override
- adaptive Außen-/Innen-Gewichtung
- selbstlernende Stufenanpassung
- Lüftungsampel im WebFront
