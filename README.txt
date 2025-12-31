Controlled Residential Ventilation – Feuchtebasierte Regelung
=============================================================

1. Ziel
-------
Dieses IP-SYMCON-Modul steuert eine kontrollierte Wohnraumlüftung
auf Basis der ABSOLUTEN Feuchte (g/m³).

Es werden bis zu 10 Innensensoren unterstützt.
Immer der höchste Feuchtewert bestimmt die Lüftungsstufe.

2. Unterstützte Eingänge
------------------------
Innensensoren:
- Relative Feuchte (% rF) – Float – lesbar
- Temperatur (°C) – Float – lesbar

Außenluft (optional):
- Relative Feuchte (% rF) – Float – lesbar
- Temperatur (°C) – Float – lesbar

3. Warum absolute Feuchte?
--------------------------
Relative Feuchte ist temperaturabhängig.
Absolute Feuchte (g/m³) beschreibt die tatsächliche Wassermenge
in der Luft und ist ideal zur Lüftungsregelung.

Formel:
  abs = 216.7 * (rF/100 * Dampfdruck) / (273.15 + Temperatur)

4. Regelzyklus
--------------
Einstellbar: 5–30 Minuten
Der Stellwert wird zyklisch neu berechnet.

5. Lüftungsstufen
-----------------
Die Anlage wird ausschließlich in Prozent angesteuert:

Stufe 1 → 12 %
Stufe 2 → 24 %
Stufe 3 → 36 %
Stufe 4 → 48 %
Stufe 5 → 60 %
Stufe 6 → 72 %
Stufe 7 → 84 %
Stufe 8 → 96 %

6. Nachtabschaltung
-------------------
Über eine externe Boolean-Variable.
Ist sie TRUE, wird die Lüftung deaktiviert.
Status wird blau visualisiert.

7. Statusanzeige (Ampel)
------------------------
0 = Aus (grau)
1 = Betrieb (grün)
4 = Fehler (rot)
5 = Nachtabschaltung (blau)

8. Typische Fehler
------------------
- Variable nicht lesbar → wird ignoriert
- Keine gültigen Sensoren → Status FEHLER
- Ausgabevariable nicht schreibbar → keine Ansteuerung

9. Erweiterungen (zukünftig)
----------------------------
- Selbstlernende Regelung
- Erfolgskontrolle Lüftung
- Automatische Sommer-/Winteranpassung
- Taupunkt-basierte Optimierung

10. Hinweis
-----------
Das Modul ist universell einsetzbar.
KNX ist NICHT erforderlich – jede SYMCON-Variable ist geeignet,
wenn Typ und Zugriffsrechte stimmen.
