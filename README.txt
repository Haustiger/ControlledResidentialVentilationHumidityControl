Controlled Residential Ventilation – Humidity Control
====================================================

1. Zweck
--------
Dieses IP-SYMCON Modul steuert eine kontrollierte Wohnraumlüftung
feuchtegeführt auf Basis der absoluten Feuchte (g/m³).

2. Absolute Feuchte
-------------------
g/m³ beschreibt die tatsächliche Menge an Wasserdampf pro Kubikmeter Luft
und ist unabhängig von der Temperatur. Dadurch ist eine korrekte
Sommer-/Winterregelung möglich.

3. Sensorik
-----------
- Bis zu 10 Innensensoren (KNX DPT 9.007 Feuchte, 9.001 Temperatur)
- Optionaler Außensensor
- Es wird immer der Sensor mit der höchsten absoluten Feuchte verwendet

4. Feuchtesprung-Erkennung
--------------------------
Ein Feuchtesprung liegt vor, wenn:
- die relative Feuchte um mindestens den konfigurierten Wert steigt
- innerhalb eines Regelzyklus (≤ 30 Minuten)

Reaktion:
- Erhöhung der aktuellen Lüftungsstufe um +3
- unabhängig von Sommer-/Winterbetrieb

5. Nachtabschaltung
-------------------
Die Lüftung kann nachts über eine KNX-Variable (DPT 1.001) deaktiviert werden.
Zeitfenster frei konfigurierbar.

6. Nachtübersteuerung bei Feuchtesprung
---------------------------------------
Wird während der Nachtabschaltung ein Feuchtesprung erkannt:
- wird die Lüftung für maximal 60 Minuten aktiviert
- Statusanzeige: "Nachtübersteuerung aktiv"
- Ampel: 🔵

7. Statusanzeigen
-----------------
⚫ Nachtabschaltung
🟢 Lüftung aktiv
🟡 Außenluft ungünstig
🔴 Fehler
🔵 Nachtübersteuerung durch Feuchtesprung

8. KNX Hinweise
---------------
- Stellwert: DPT 5.001 (Scaling), schreibbar
- Rückmeldung: DPT 5.001, lesbar

9. Rechtlicher Hinweis
---------------------
Dieses Modul ist herstellerneutral und nicht an einen
bestimmten Lüftungsgerätehersteller gebunden.
