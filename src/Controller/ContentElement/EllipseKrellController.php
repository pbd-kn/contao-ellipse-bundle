<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(EllipseKrellController::TYPE, category: 'Ellipse', template: 'ce_ellipse_krell')]
class EllipseKrellController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse_krell';
    private bool $debug = false;

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        // --- Backend Wildcard ---
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_ellipse_wildcard');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Ellipse Krell';
            $wildcard->id = $model->id;
            $wildcard->wildcard = '### Ellipse Krell ###<br>ID: ' . $model->id;
            return new Response($wildcard->parse());
        }

        $debugline = [];

        $currentCeId = $model->id;

        // Hilfsfunktion: GET > DB > Default
        $val = function (string $getKey, string $dbField, $default = null) use ($request, $model, $currentCeId) {
            $keyWithId = $getKey . '_' . $currentCeId;
            $fromGetWithId = $request->query->get($keyWithId);
            if ($fromGetWithId !== null && $fromGetWithId !== '') {
                return $fromGetWithId;
            }

            if ($model->{$dbField} !== null && $model->{$dbField} !== '') {
                return $model->{$dbField};
            }

            return $default;
        };

        // === Parameter laden ===
        $A  = (float) $val('A', 'ellipse_x', 10.0);
        $B  = (float) $val('B', 'ellipse_y', 6.0);
        $GRaw = (string) $val('Umdrehungen', 'ellipse_umlauf', '1');  // default 1 Umdrehung
        $Umdrehungen = (float) str_replace(',', '.', $GRaw);
        $grenzWinkel = $Umdrehungen*360;
        $ReihenfolgePkt = (int) $val('ReihenfolgePkt', 'ellipse_point_sequence', 20);    // Reihenfolge in der die Punkte gezeichnet werden
        $Schrittweite = (float) $val('Schrittweite', 'ellipse_schrittweite_pkt', M_PI / 18);
        $Kreisradius  = (int)   $val('Kreisradius', 'ellipse_circle_radius', 2);
        $Abstand = (float) $val('Abstand', 'ellipse_point_radius', 1.0);
        

        // === Checkboxen ===
        $templateSelectionActive = (bool) ((int) $request->query->get('templateSelectionActive_' . $currentCeId, $model->template_selection_active ? 1 : 0));

        $showEllipse = (bool) ((int) $request->query->get('showEllipse_' . $currentCeId, ($model->showEllipse ?? 1) ? 0 : 1));
        $showCircle = (bool) ((int) $request->query->get('showCircle_' . $currentCeId,($model->showCircle ?? 1) ? 0 : 1));
        if ($showEllipse || $showCircle) $this->debug = true;
        else $this->debug = false;
        $lineWidthRaw = (string) $val('lineWidth', 'ellipse_line_thickness', '3');
        $lineWidth = (float) str_replace(',', '.', $lineWidthRaw);
        $lineColor = '';
        $cycleColors = [];

        $lineMode = (string) $val('lineMode', 'line_mode', 'fixed');
        if ($lineMode === 'fixed') {
            // feste Farbe
            $lineColor = (string) $val('lineColor', 'ellipse_line_color', 'red');
        } else {
            // zyklische Farben: GET > DB > Default
            for ($i = 1; $i <= 6; $i++) {
                $key = "cycleColor{$i}_" . $currentCeId;
                if ($request->query->has($key)) {
                    // explizit GET – auch wenn leer
                    $color = trim((string) $request->query->get($key));
                    if ($color !== '') {
                        $cycleColors[] = $color;
                    }
                } else {
                    // nur wenn kein GET vorhanden, DB prüfen
                    $dbField = "ellipse_cycle_color{$i}";
                    $color = (string) ($model->{$dbField} ?? '');
                    if (trim($color) !== '') {
                        $cycleColors[] = trim($color);
                    }
                }
            }

            // Wenn keine gesetzt: Standardfarben
            if (count($cycleColors) === 0) {
                $cycleColors = ["blue", "green", "red", "orange", "purple", "brown"];
            }

            // Ellipse-Farbe auf erste Zyklusfarbe setzen
            $lineColor = $cycleColors[0];
        }

        // === Punkte berechnen ===
        $points = $this->ellipseSimulation($A, $B, $Kreisradius, $Abstand, $grenzWinkel, $Schrittweite, $debugline);

        $errorMsg = null;
        $viewBox = "0 0 500 500"; // Default

        if (!empty($points[0]['error'])) {
            // Fehlerfall (z.B. R=0)
            $errorMsg = $points[0]['error'];
        } else {
            // === ViewBox bestimmen ===
            $xs = array_column($points, 'x');
            $ys = array_column($points, 'y');

            $minX = min($xs);
            $maxX = max($xs);
            $minY = min($ys);
            $maxY = max($ys);

            // Dynamischer Sicherheitsabstand
            $extra = $Abstand + ($lineWidth ?? 1) / 2;

            $marginX = ($maxX - $minX) * 0.1 + $extra;
            $marginY = ($maxY - $minY) * 0.1 + $extra;

            $viewBox = sprintf(
                "%f %f %f %f",
                $minX - $marginX,
                -$maxY - $marginY,   // wichtig: Y-Achse im SVG-Koordinatensystem oft invertiert
                ($maxX - $minX) + 2 * $marginX,
                ($maxY - $minY) + 2 * $marginY
            );

        }

        // === Template befüllen ===
        $template = $this->createTemplate($model, 'ce_ellipse_krell');
        if ($this->debug) $debugline[] = "Debug: count points: " . count($points) . " | showEllipse=" . ($showEllipse ? '1' : '0'). " | showCircle=" . ($showCircle ? '1' : '0');
        $template->debugline = $debugline;        

        $template->headlineHtml = $model->headline
            ? sprintf(
                '<%1$s>%2$s</%1$s>',
                StringUtil::deserialize($model->headline)['unit'] ?? 'h2',
                StringUtil::deserialize($model->headline)['value'] ?? ''
            )
            : '';

        $template->A = $A;
        $template->B = $B;
        $template->Umdrehungen = $Umdrehungen;
        $template->Schrittweite = $Schrittweite;

        $template->ReihenfolgePkt = $ReihenfolgePkt;
        
        $template->Kreisradius = $Kreisradius;
        $template->Abstand = $Abstand;

        $template->points = $points;

        $template->templateSelectionActive = $templateSelectionActive;
        $template->showEllipse = $showEllipse;
        $template->showCircle  = $showCircle;
        $template->viewBox     = $viewBox;
        $template->errorMsg    = $errorMsg;

        // Linien-Parameter
        $template->lineMode    = $lineMode;
        $template->lineColor   = $lineColor;
        $template->cycleColors = $cycleColors;
        $template->lineWidth   = $lineWidth;

        return $template->getResponse();
    }

    // ==================================
    // Hilfsfunktionen Ellipsenberechnung
    // ==================================

    /**
     * Integrandenfunktion für die numerische Integration.
     * 
     * Berechnet den Wert einer speziellen Funktion in Abhängigkeit
     * von Winkel $w (in Radiant) und einem Parameter $e (Exzentrizität).
     * 
     * Mathematisch:
     *     fnw(w, e) = sqrt( (1 - (2e - e²) * cos²(w)) / (1 - e * cos²(w))³ )
     *
     * @param float $w Winkel (z. B. im Bogenmaß, Radiant)
     * @param float $e Exzentrizität oder anderer elliptischer Parameter
     * @return float Ergebniswert der Funktion
     */
    private function fnw(float $w, float $e): float
    {
        // Nenner-Term hoch 3:
        // (1 - e * cos²(w))³
        // beschreibt den Einfluss der Exzentrizität auf die Form
        $h0 = pow(1 - $e * pow(cos($w), 2), 3);

        // Zähler-Term:
        // 1 - (2e - e²) * cos²(w)
        // → eine Korrekturformel, die ebenfalls die Exzentrizität berücksichtigt
        $h1 = 1 - (2 * $e - $e * $e) * pow(cos($w), 2);

        // Ergebnis: Wurzel aus (h1 / h0)
        // wichtig: h0 > 0 muss gelten, sonst Division durch 0
        return sqrt($h1 / $h0);
    }


    /**
     * Numerische Integration nach der Gauß-Legendre-Methode (2-Punkte-Formel).
     *
     * @param float $u Startwert der Integration (Untere Grenze oder Basispunkt)
     * @param float $h Schrittweite (Abstand der Integrationsintervalle)
     * @param int   $n Anzahl der Teilintervalle (gerade Zahl vorausgesetzt)
     * @param float $e Exzentrizität oder ein Parameter, der an fnw() weitergegeben wird
     *
     * @return float Näherungswert für das Integral
     */
    private function up1(float $u, float $h, int $n, float $e): float
    {
        // Zähler für die Anzahl der ausgeführten Intervalle
        $m  = 0;

        // Akkumulator für die Integral-Summe
        $i4 = 0.0;

        // -------------------------------
        // Startpunkte für die erste Anwendung der 2-Punkte-Formel:
        // Gauß-Legendre mit Knoten ±1/sqrt(3), auf [u, u+2h] verschoben
        // a5 = linker Knotenpunkt, b5 = rechter Knotenpunkt
        // -------------------------------
        $a5 = $u + $h * (1 - 1 / sqrt(3));
        $b5 = $u + $h * (1 + 1 / sqrt(3));

        // Schleife über alle Intervalle
        while (true) {
            // Werte der Integrandenfunktion fnw() an den beiden Knoten addieren
            $i4 += $this->fnw($a5, $e) + $this->fnw($b5, $e);

            // Abbruchbedingung: wenn alle Intervalle bearbeitet sind
            if ($m == $n / 2 - 1) {
                break;
            }

            // Verschiebe die Knoten auf das nächste Intervall
            $a5 += 2 * $h;
            $b5 += 2 * $h;

            $m++;
        }

        // Endergebnis: Summierte Werte * Schrittweite h
        return $i4 * $h;
    }

    
    /* Zusammenfassung:
     * Berechne Punkt auf Ellipse für Winkel $W2$.
     * Bestimme Tangente im Ellipsenpunkt.
     * Verschiebe den Punkt entlang der Tangente → Mittelpunkt eines Hilfskreises.
     * Führe eine Integration durch (elliptisch).
     * Berechne vom Kreismittelpunkt aus einen Punkt auf dem Kreisradius $Abstand.
     * Ergebnis: koordinierter Punkt der Simulation.
     */

    private function ellipseSimulation(float $A, float $B, 
        float $Kreisradius, // $R
        float $Abstand,     // $R1
        float $grenzWinkel, // $G1
        float $Schrittweite, 
        array &$debugline)
        : array
    {
        $punkte = [];
        if ($this->debug) $debugline[] = "Start Berechnung grenzWinkel $grenzWinkel";
        if ($Kreisradius == 0.0) {
            return [
                ['error' => 'Fehler: Der Parameter R (umlaufenfer Kreis ellipse_circle_radius) darf nicht 0 sein.']
            ];
        }

        $E = ($A * $A - $B * $B) / ($A * $A);   /* $A = große Halbachse, $B = kleine Halbachse.
                                                 * $E$ ist die Quadrat-Exzentrizität der Ellipse.
                                                 * Für einen Kreis gilt $E = 0$ (weil $A = B$).
                                                 * Je größer $E$, desto „gestreckter“ ist die Ellipse.
                                                 */
        
        $U = 0.0;                                /* Variablen für die Integration 
                                                  * $U$: speichert den letzten Winkel.
                                                  * $N$: Anzahl der Integrationsschritte.
                                                  * $I5$: Summand aus vorheriger Iteration (für fortlaufende Integration).
                                                  */
        $N = 10;
        $I5 = 0.0;
        $lfdnr=0;
        for ($W2 = 0.0; $W2 < $grenzWinkel; $W2 += $Schrittweite) {    // Schleife über den Winkel in Grad
            $lfdnr++;
            $rad = deg2rad($W2); // EINMAL Umwandlung in Radiant

            // Ellipse Polarkoordinaten → kartesisch
            $R2 = $B / sqrt(1 - $E * pow(cos($rad), 2));
            $X1 = $R2 * cos($rad);
            $Y1 = $R2 * sin($rad);

            // Steigung der Tangente & Richtung
            $Q3 = ($A * $A * tan($rad)) / ($B * $B);
            $Q2 = sqrt(1 + $Q3 * $Q3);
            $F0 = atan($Q3);

            if ($X1 <= 0) { // Falls Ellipsenpunkt links von der y-Achse:
                $Q2 = -$Q2;
                $F0 += M_PI;
            }

            // Verschiebung des Kreismittelpunkts
            $M1 = $X1 + $Kreisradius / $Q2;
            $N1 = $Y1 + $Kreisradius * ($Q3 / $Q2);

            // Numerische Integration
            $H = ($rad - $U) / $N;       
            $deltaArc = $this->up1($U, $H, $N, $E);  // Länge nur seit letztem Punkt
            $I4 = $I5 + $deltaArc;                   // Gesamtlänge bis hier

            // Drehwinkel für Punktberechnung
            $F1 = $B * $I4 / $Kreisradius;

            // Finaler Punkt
            $X = $M1 - $Abstand * cos($F1 + $F0);
            $Y = $N1 - $Abstand * sin($F1 + $F0);
            $X=round($X,2);
            $Y=round($Y,2);
            $M1=round($M1,2);
            $N1=round($N1,2);
            $X1=round($X1,2);
            $Y1=round($Y,2);
            $deltaArc=round($deltaArc,2);


            $punkte[] = [
                'x' => $X,
                'y' => $Y,
                'm1' => $M1,
                'n1' => $N1,
                'x1' => $X1,
                'y1' => $Y1,
                'ellng' => $deltaArc
            ];
            if ($this->debug) $debugline[] = "$lfdnr: W2 $W2 rad ". round($rad,2) . " X: $X Y: $Y M1: $M1 N1: $N1 X1: $X1 Y1: $Y1 LNG: $deltaArc";   

            $U  = $rad; // auch hier Radiant speichern
            $I5 = $I4;
        }
        if ($this->debug) $debugline[] = "Ende Berechnung grenzWinkel";

        return $punkte;
    }
}
