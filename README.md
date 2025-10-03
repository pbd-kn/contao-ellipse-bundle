# Contao Ellipse Bundle

Dieses Bundle erweitert Contao um zwei Content-Elemente zur **Darstellung und Simulation von Ellipsen**.  
Die Ellipsen werden mathematisch berechnet und als **SVG-Grafik** im Frontend ausgegeben.  
Alle wichtigen Parameter lassen sich �ber das Contao-Backend einstellen oder optional im Frontend-Formular �ndern.  

---

## Inhalt

Das Bundle stellt zwei Content-Elemente bereit:

### Ellipse (klassisch, Punkte auf Ellipse)
- Stellt eine Ellipse auf Basis der gro�en und kleinen Halbachse dar.  
- Visualisiert Linien zwischen Punkten entlang der Ellipse.  
- Parameter wie Schrittweite, Uml�ufe und Farben steuerbar.  
- **Typ:** `EllipseController::TYPE`

### Ellipse Krell (Kreis �ber Ellipse)
- Erweiterung der klassischen Ellipse.  
- Zus�tzlich l�uft auf der Ellipse ein Kreis ab.
- �bewr kreisradius und Abstand dses Kreiamittelpunkts werden die zu verbindenden Punkte bestimmt  
- 
- **Typ:** `EllipseKrellController::TYPE`

---

## Funktionen

Die Berechnung der Ellipse erfolgt �ber die **Polarkoordinatenformel**:

```math
r(f) = \frac{b}{\sqrt{1 - e \cdot \cos^2(f)}}
```

Dabei:  
- `a` = gro�e Halbachse (`ellipse_x`)  
- `b` = kleine Halbachse (`ellipse_y`)  
- `e` = numerische Exzentrizit�t  

Im Frontend werden die Punkte in **SVG** ausgegeben:  
- Farben und Linien werden dynamisch berechnet 
- Linien zwischen den Punkten ,unter der Ber�cksichtigung der Sequenz, mit den gew�hlten Linienparameter ausgegeben  
- optional die Ellipse selbst (`showEllipse`)  
- optionale Punkte (`showCircle`)  

- Die Muster k�nnen als pdf oder als SVG zur weiteren Verwendung gespeichert werden. 

---

## Parameter und ihre Wirkung

### Geometrische Parameter

| Feld                          | Beschreibung          | Wirkung 
|-------------------------------|-----------------------|---------
| `ellipse_x (A)`               | Gro�e Halbachse       | Streckung in X-Richtung (Breite) |
| `ellipse_y (B)`               | Kleine Halbachse      | Streckung in Y-Richtung (H�he) |
| `ellipse_umlauf`              | Uml�ufe               | Anzahl der Durchl�ufe um die Ellipse |
| `ellipse_schrittweite_pkt`    | Schrittweite (S)      | Abstand der Punkte eergibt die Anzahl Punkte die gezeichnez werden |
| `ellipse_point_sequence (R)`  | Punktreihenfolge      | Gibt an in welcher Reihenfolge die Punkte verbunden werden

| bei kreis auf Ellipse 
| `ellipse_circle_radius (R)`   | Kreisradius           | Gr��e der Hilfskreise |
| `ellipse_point_radius (R1)`   | Punkt-Radius          | Gr��e der einzelnen Punkte |

### Linien & Farben

| `ellipse_line_thickness`      | Linienst�rke          | Dicke der Linien |
| `ellipse_line_mode`           | Linienmodus           | `fixed` = feste Farbe, `cycle` = wechselnde Farben |
| `ellipse_line_color`          | Linienfarbe (fixed)   | Feste Linienfarbe |
| `ellipse_cycle_color1..6`     | Zyklusfarben          | Farben, die bei `cycle` nacheinander verwendet werden 
|                               |                       | die Farben k�nnen in englisch blue, Yellow oder #rrggbb angeben wewrdn


### Anzeigeoptionen

| `template_selection_active`   | Eingbe Frontend       | im FE k�nnen die Parameter ewingeghen werden|
| `ellipse_template`            | Template-Auswahl      | Auswahl eines eigenen `ce_ellipse_*` Templates |
| `showEllipse`                 | Ellipse anzeigen      | f�r Debug zeigt zu Ellipse  noch ZusatzInfo an rote Umrandung sichtbar |
| `showCircle`                  | Punkte anzeigen       | f�r Debug zeigt Punkte noch ZusatzInfo  als `<circle>` sichtbar |


---

## Beispiele

[Ellipse Krell Funktionsweise](docs/explanation/ellipse_tangent_krell_with_text.jpg)




---

## Hinweise

- Beide Content-Elemente nutzen mathematische Formeln f�r die Punkterzeugung.  
- Bei kleinen Schrittweiten entstehen viele Punkte ? Performance beachten.  
- Mit der Punktreihenfolge (`R`) lassen sich geometrische Muster wie Sterne oder Rosetten erzeugen.  
- Die Frontend-Formularoption erlaubt es, Parameter **live im Browser zu �ndern**.  

---

## Installation

�ber Composer installieren:

```bash
composer require pbd-kn/contao-ellipse-bundle
vendor/bin/contao-console contao:migrate
```

Danach steht das Bundle im Contao-Backend als Content-Element zur Verf�gung.  

---

## Lizenz

LGPL-3.0-or-later  
(c) 2025 pbd-kn
