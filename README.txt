Controlled Residential Ventilation – Humidity Control
====================================================

1. Zweck
--------
Dieses Modul steuert eine kontrollierte Wohnraumlüftung
feuchtegeführt auf Basis der absoluten Feuchte (g/m³).

2. Warum absolute Feuchte?
--------------------------
Relative Feuchte ist temperaturabhängig.
Absolute Feuchte (g/m³) erlaubt:
- korrekte Sommer-/Winterregelung
- Vergleich von Innen- und Außenluft
- stabile Regelung ohne Fehlinterpretationen

3. Sensorik
-----------
- Bis zu 10 Innensensoren
- Jeder Sensor besteht aus:
  - Temperatur (Float, °C)
  - Feuchte (Float, 0–100 %)
- Es wird IMMER der Sensor mit der höchsten absoluten Feuchte verwendet

4. Feuchtesprung
----------------
Ein Feuchtesprung wird erkannt, wenn:
- Δ rF ≥ konfigurierter Wert
- innerhalb des definierten Zeitfensters

Reaktion:
- +3 Lüftungsstufen
- unabhängig von Sommer/Winter
- nachts ggf. temporäre Übersteuerung

5. Nachtabschaltung
-------------------
- Aktivierung über Boolean-Variable
- Zeitfenster frei definierbar
- Lüftung wird vollständig deaktiviert

6. Nachtübersteuerung
---------------------
- Bei Feuchtesprung während Nachtabschaltung
- Lüftung wird für max. X Minuten aktiviert
- Visualisierung über Variable "Nachtübersteuerung aktiv"

7. Stellwert-Ausgabe
--------------------
- Ausgabe erfolgt in Prozent (0–100)
- Empfohlenes Profil: ~Intensity.100
- Die Variable MUSS schreibbar sein

8. Rückmeldung
--------------
- Optional
- Wird zur Plausibilitätsprüfung genutzt
- Verzögerung bis 30 Sekunden zulässig

9. Status & Ampel
-----------------
⚫ Nachtabschaltung
🟢 Lüftung aktiv
🟡 Außenluft ungünstig
🔴 Fehler
🔵 Nachtübersteuerung

10. Validierung
---------------
Beim Speichern der Konfiguration wird geprüft:
- Existenz aller Variablen
- Variablentyp (Boolean / Integer / Float)
- Lesbarkeit / Schreibbarkeit
- Profilprüfung für Prozentwerte

Fehler → Modulstatus rot  
Warnung → Modulstatus gelb  

11. Universelle Nutzung
-----------------------
Das Modul ist hersteller- und protokollunabhängig.
KNX, MQTT, ModBus etc. sind NICHT erforderlich.
