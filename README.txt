CRV Humidity Control – Build 11
===============================

Build 11 erweitert Build 10 um eine gewichtete Außenbewertung.

Neue Funktion:
---------------
Die qualitative Außenbewertung (Build 10) beeinflusst nun die
Lüftungsstufe moderat.

Prinzip:
--------
- Grundlage bleibt die Innen-Feuchte
- Außenbewertung kann die Lüftungsstufe senken
- Keine Erhöhung durch Außenbewertung
- Maximal ±2 Stufen
- Gewichtung frei konfigurierbar (0.0 – 1.0)

Neue Property:
--------------
OutdoorWeightFactor
  0.0 = keine Wirkung
  1.0 = maximale Wirkung (-2 Stufen)

Neue Debug-Variablen:
--------------------
OutdoorWeightApplied
  -> tatsächlich verwendeter Gewichtungsfaktor

OutdoorStageCorrection
  -> angewandte Stufenkorrektur (-2 … 0)

Sicherheit:
-----------
- Keine bestehende Logik entfernt
- Keine GUID- oder Klassenänderung
- Keine Abhängigkeit von Außensensoren erzwungen
- Fehlerfreie Rückkehr auf Build 10 jederzeit möglich

Empfehlung:
-----------
OutdoorWeightFactor = 0.3 … 0.6 für realistische Wirkung
