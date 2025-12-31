Controlled Residential Ventilation – Feuchtebasierte Regelung
Version 3.0 (Build 8)
============================================================

ZIEL
----
Dieses IP-SYMCON-Modul steuert eine kontrollierte Wohnraumlüftung
auf Basis der ABSOLUTEN Feuchte (g/m³).

Bis zu 10 Innensensoren werden unterstützt.
Immer der höchste Feuchtewert bestimmt die Lüftungsleistung.

WARUM ABSOLUTE FEUCHTE?
-----------------------
Relative Feuchte ist temperaturabhängig.
Absolute Feuchte beschreibt die tatsächliche Wassermenge
in der Luft und ist physikalisch korrekt für Lüftungsregelungen.

FORMEL
-------
Absolute Feuchte (g/m³):
  AH = 216.7 * (rF/100 * Dampfdruck) / (273.15 + Temperatur)

LÜFTUNGSSTUFEN
--------------
Die Lüftung wird ausschließlich in Prozent angesteuert:

Stufe 1 → 12 %
Stufe 2 → 24 %
Stufe 3 → 36 %
Stufe 4 → 48 %
Stufe 5 → 60 %
Stufe 6 → 72 %
Stufe 7 → 84 %
Stufe 8 → 96 %

REGELZYKLUS
-----------
Frei einstellbar: 5–30 Minuten

NACHTABSCHALTUNG
----------------
Über eine externe Boolean-Variable.
Bei Aktivierung wird die Lüftung deaktiviert
(Statusanzeige blau).

STATUSAMPEL
-----------
Aus            → Grau
Betrieb        → Grün
Fehler         → Rot
Nachtbetrieb   → Blau

KOMPATIBILITÄT
--------------
• KNX optional
• Keine Herstellerbindung
• Jede SYMCON-Variable nutzbar
• Vollständig updatefähig

ZUKÜNFTIGE ERWEITERUNGEN
-----------------------
• Selbstlernende Regelung
• Erfolgskontrolle Lüften
• Automatische Sommer-/Winterlogik
• Taupunktbasierte Optimierung
