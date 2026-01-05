Controlled Residential Ventilation – Humidity Control
Version 3.2 – Build 9
Autor / Hersteller: Haustiger

================================================================
ZIEL
================================================================
Dieses Modul steuert eine kontrollierte Wohnraumlüftung
feuchtegeführt auf Basis der ABSOLUTEN FEUCHTE (g/m³).

Es berücksichtigt:
- bis zu 10 Innensensoren
- Außentemperatur und Außenfeuchte
- Feuchtesprünge (Duschen, Kochen, Baden)
- Nachtabschaltung mit Schallschutz
- Feuchtesprung-Override während der Nacht

================================================================
GRUNDPRINZIP
================================================================
1. Berechnung der absoluten Feuchte je Sensor
2. Mittelwertbildung innen
3. 8-stufige Lüftungskennlinie
4. Feuchtesprung-Erkennung (+X % rF / 5 Minuten)
5. Sommer-/Winter-Bewertung (ab Build 8)
6. Nachtabschaltung mit Override (Build 9)

================================================================
NACHTABSCHALTUNG (Build 9)
================================================================
- Aktivierung über boolesche Variable (z. B. KNX DPT 1.001)
- Frei einstellbare Uhrzeiten
- Während der Nacht:
  → Lüftung auf Stufe 1 begrenzt
- Bei Feuchtesprung:
  → Nacht-Override für max. 60 Minuten

Statusvariablen:
- Nachtabschaltung aktiv
- Nacht-Override aktiv
- Nacht-Override bis (Datum/Uhrzeit)

================================================================
FEUCHTESPRUNG
================================================================
Ein Feuchtesprung wird erkannt, wenn:
- Ø relative Feuchte innerhalb von 5 Minuten
  um den eingestellten Schwellwert steigt

Reaktion:
- Zielstufe = aktuelle Stufe + 3
- Erhöhung stufenweise (Rampe)
- Debug vollständig sichtbar

================================================================
EMPFOHLENE SENSORDATEN
================================================================
Relative Feuchte:
- 0–100 % oder 0.0–1.0
Temperatur:
- °C (Float)

KNX:
- DPT 9.xxx empfohlen

================================================================
HINWEISE
================================================================
- Stellwert-Variable muss SCHREIBBAR sein
- Nachtabschaltung überschreibt normale Regelung
- Feuchtesprung kann Nachtabschaltung temporär aufheben

================================================================
NÄCHSTE GEPLANTE BUILDS
================================================================
Build 10:
- Ist-Lüftungsstufe überwachen
- Rückmeldeprüfung

Build 11:
- Selbstlernende Anpassung der Kennlinie

================================================================
STATUS
================================================================
Build 9 ist stabil, getestet und funktionsfähig.
