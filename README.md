# Contao Ellipse Bundle

Dieses Bundle erweitert Contao um zwei Content-Elemente zur **Darstellung von Grafiken die über Ellipsenerzeugt werden**.  
Die Ellipsen werden mathematisch berechnet und als **SVG-Grafik** im Frontend ausgegeben.  
Alle wichtigen Parameter lassen sich über das Contao-Backend einstellen oder optional im Frontend-Formular ändern.  

---

## Inhalt

Das Bundle stellt zwei Content-Elemente bereit:

### Ellipse (klassisch, Punkte auf Ellipse)
- Stellt eine Ellipse auf Basis der großen und kleinen Halbachse dar.  
- Visualisiert Linien zwischen Punkten entlang der Ellipse.  
- Parameter wie Schrittweite, Umläufe und Farben steuerbar.  
- **Typ:** `EllipseController::TYPE`

### Ellipse Krell (Kreis über Ellipse)
- Erweiterung der klassischen Ellipse.  
- Zusätzlich läuft auf der Ellipse ein Kreis ab.
- Über Kreisradius und Abstand vom Kreismittelpunkts werden die zu verbindenden Punkte bestimmt  
- 
- **Typ:** `EllipseKrellController::TYPE`

---

## Funktionen

Die Berechnung der Ellipse erfolgt über die **Polarkoordinatenformel**:

```math
r(f) = \frac{b}{\sqrt{1 - e \cdot \cos^2(f)}}
```

Dabei:  
- `a` = große Halbachse (`ellipse_x`)  
- `b` = kleine Halbachse (`ellipse_y`)  
- `e` = numerische Exzentrizität  

Im Frontend werden die Punkte in **SVG** ausgegeben:  
- Farben und Linien werden dynamisch berechnet 
- Linien zwischen den Punkten ,unter der Berücksichtigung der Sequenz, mit den gewählten Linienparameter ausgegeben  
- optional die Ellipse selbst (`showEllipse`)  
- optionale Punkte (`showCircle`)  

- Die Muster können als pdf oder als SVG zur weiteren Verwendung gespeichert werden. 

---

## Parameter und ihre Wirkung

### Geometrische Parameter

| Feld                          | Beschreibung          | Wirkung 
|-------------------------------|-----------------------|---------
| `ellipse_x (A)`               | Große Halbachse       | Streckung in X-Richtung (Breite) |
| `ellipse_y (B)`               | Kleine Halbachse      | Streckung in Y-Richtung (Höhe) |
| `ellipse_umlauf`              | Umläufe               | Anzahl der Durchläufe um die Ellipse |
| `ellipse_schrittweite_pkt`    | Schrittweite (S)      | Abstand der Punkte eergibt die Anzahl Punkte die gezeichnez werden |
| `ellipse_point_sequence (R)`  | Punktreihenfolge      | Gibt an in welcher Reihenfolge die Punkte verbunden werden |
|                               |                       |
| bei Kreis auf Ellipse         |                       |                       |
| `ellipse_circle_radius (R)`   | Kreisradius           | Größe der Hilfskreise |
| `ellipse_point_radius (R1)`   | Abstand               | Punktabstand des Punktes vom Kreismittelpunkt |

### Linien & Farben
| Feld                          | Beschreibung          | Wirkung 
|-------------------------------|-----------------------|---------
| `ellipse_line_thickness`      | Linienstärke          | Dicke der Linien |
| `ellipse_line_mode`           | Linienmodus           | `fixed` = feste Farbe, `cycle` = wechselnde Farben |
| `ellipse_line_color`          | Linienfarbe (fixed)   | Feste Linienfarbe |
| `ellipse_cycle_color1..6`     | Zyklusfarben          | Farben, die bei `cycle` nacheinander verwendet werden 
|                               |                       | die Farben können in englisch blue, Yellow oder #rrggbb angeben werden |


### Anzeigeoptionen
### Linien & Farben
| Feld                          | Beschreibung          | Wirkung 
|-------------------------------|-----------------------|---------
| `template_selection_active`   | Eingbe Frontend       | im FE können die Parameter ewingeghen werden|
| `ellipse_template`            | Template-Auswahl      | Auswahl eines eigenen `ce_ellipse_*` Templates |
| `showEllipse`                 | Ellipse anzeigen      | für Debug zeigt zu Ellipse  noch ZusatzInfo an rote Umrandung sichtbar |
| `showCircle`                  | Punkte anzeigen       | für Debug zeigt Punkte noch ZusatzInfo  als `<circle>` sichtbar |


---

## Beispiele





---

## Hinweise

- Beide Content-Elemente nutzen mathematische Formeln für die Punkterzeugung.  
- Bei kleinen Schrittweiten entstehen viele Punkte ? Performance beachten.  
- Mit der Punktreihenfolge (`R`) lassen sich geometrische Muster wie Sterne oder Rosetten erzeugen.  
- Die Frontend-Formularoption erlaubt es, Parameter **live im Browser zu ändern**.  

---

## Installation

Über Composer installieren:

```bash
composer require pbd-kn/contao-ellipse-bundle
vendor/bin/contao-console contao:migrate
```

Danach steht das Bundle im Contao-Backend als Content-Element zur Verfügung.  

---

## Lizenz

LGPL-3.0-or-later  
(c) 2025 pbd-kn
