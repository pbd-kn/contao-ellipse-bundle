<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;
use Contao\Database;
use Contao\FrontendUser;
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
        $GRaw = (string) $val('Umdrehungen', 'ellipse_umlauf', '1');  
        $Umdrehungen = (float) str_replace(',', '.', $GRaw);
        $grenzWinkel = $Umdrehungen*360;
        $ReihenfolgePkt = (int) $val('ReihenfolgePkt', 'ellipse_point_sequence', 20);    
        $Schrittweite = (float) $val('Schrittweite', 'ellipse_schrittweite_pkt', M_PI / 18);
        $Kreisradius  = (int)   $val('Kreisradius', 'ellipse_circle_radius', 2);
        $Abstand = (float) $val('Abstand', 'ellipse_point_radius', 1.0);

        // === Checkboxen ===
        $templateSelectionActive = (bool) ((int) $request->query->get('templateSelectionActive_' . $currentCeId, $model->template_selection_active ? 1 : 0));
        $showEllipse = (bool) ((int) $request->query->get('showEllipse_' . $currentCeId, ($model->showEllipse ?? 1) ? 0 : 1));
        $showCircle = (bool) ((int) $request->query->get('showCircle_' . $currentCeId,($model->showCircle ?? 1) ? 0 : 1));

        $this->debug = ($showEllipse || $showCircle);

        $lineWidthRaw = (string) $val('lineWidth', 'ellipse_line_thickness', '3');
        $lineWidth = (float) str_replace(',', '.', $lineWidthRaw);
        $lineColor = '';
        $cycleColors = [];

        $lineMode = (string) $val('lineMode', 'line_mode', 'fixed');
        if ($lineMode === 'fixed') {
            $lineColor = (string) $val('lineColor', 'ellipse_line_color', 'red');
        } else {
            for ($i = 1; $i <= 6; $i++) {
                $key = "cycleColor{$i}_" . $currentCeId;
                if ($request->query->has($key)) {
                    $color = trim((string) $request->query->get($key));
                    if ($color !== '') {
                        $cycleColors[] = $color;
                    }
                } else {
                    $dbField = "ellipse_cycle_color{$i}";
                    $color = (string) ($model->{$dbField} ?? '');
                    if (trim($color) !== '') {
                        $cycleColors[] = trim($color);
                    }
                }
            }
            if (count($cycleColors) === 0) {
                $cycleColors = ["blue", "green", "red", "orange", "purple", "brown"];
            }
            $lineColor = $cycleColors[0];
        }

        // === Punkte berechnen ===
        $points = $this->ellipseSimulation($A, $B, $Kreisradius, $Abstand, $grenzWinkel, $Schrittweite, $debugline);

        $errorMsg = null;
        $viewBox = "0 0 500 500"; // Default

        if (!empty($points[0]['error'])) {
            $errorMsg = $points[0]['error'];
        } else {
            $xs = array_column($points, 'x');
            $ys = array_column($points, 'y');

            $minX = min($xs);
            $maxX = max($xs);
            $minY = min($ys);
            $maxY = max($ys);

            $extra = $Abstand + ($lineWidth ?? 1) / 2;

            $marginX = ($maxX - $minX) * 0.1 + $extra;
            $marginY = ($maxY - $minY) * 0.1 + $extra;

            $viewBox = sprintf(
                "%f %f %f %f",
                $minX - $marginX,
                -$maxY - $marginY,   
                ($maxX - $minX) + 2 * $marginX,
                ($maxY - $minY) + 2 * $marginY
            );
        }

        // === Speicherung der aktuellen Darstellung ===
        if ($request->get('saveEllipse_' . $currentCeId)) {
            $userId = 0;
            if (FE_USER_LOGGED_IN && ($user = FrontendUser::getInstance())) {
                $userId = (int) $user->id;
                /* $user->id              // ID des Benutzers
                 * $user->username        // Benutzername
                 * $user->firstname       // Vorname
                 * $user->lastname        // Nachname
                 * $user->email           // E-Mail-Adresse
                 * if (FE_USER_LOGGED_IN && ($user = \Contao\FrontendUser::getInstance())) {
                 * $groups = \Contao\StringUtil::deserialize($user->groups, true);
                 * // Prüfen, ob der Benutzer in der Gruppe mit ID 5 ist (z. B. "Frontend-Admin")
                 *  if (in_array(5, $groups)) {      // 1 ist standard mitglied 
                 *  // Benutzer ist in der Admin-Gruppe
                 *      $isAdmin = true;
                 *      } else {
                 *      $isAdmin = false;
                 *  }
                 * }

*/
            }

            $params = [
                'A' => $A,
                'B' => $B,
                'Umdrehungen' => $Umdrehungen,
                'Schrittweite' => $Schrittweite,
                'ReihenfolgePkt' => $ReihenfolgePkt,
                'Kreisradius' => $Kreisradius,
                'Abstand' => $Abstand,
                'points' => $points,
                'viewBox' => $viewBox,
                'lineMode' => $lineMode,
                'lineColor' => $lineColor,
                'cycleColors' => $cycleColors,
                'lineWidth' => $lineWidth,
                'options' => [
                    'showEllipse' => $showEllipse,
                    'showCircle'  => $showCircle,
                ]
            ];

            Database::getInstance()->prepare("
                INSERT INTO tl_ellipse_save
                    (tstamp, memberId, ceType, ceId, saveData, info)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute(
                time(),
                $userId,
                $model->type,
                $model->id,
                json_encode($params, JSON_PRETTY_PRINT),
                $request->get('info_' . $currentCeId) ?: 'Ellipse gespeichert'
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
        $template->lineMode    = $lineMode;
        $template->lineColor   = $lineColor;
        $template->cycleColors = $cycleColors;
        $template->lineWidth   = $lineWidth;

        return $template->getResponse();
    }

    // ==================================
    // Hilfsfunktionen Ellipsenberechnung
    // ==================================

    private function fnw(float $w, float $e): float
    {
        $h0 = pow(1 - $e * pow(cos($w), 2), 3);    // Nenner-Term hoch 3: (1 - e * cos²(w))³
        $h1 = 1 - (2 * $e - $e * $e) * pow(cos($w), 2); // Zähler-Term
        return sqrt($h1 / $h0);                    // Ergebnis: Wurzel aus (h1/h0)
    }

    private function up1(float $u, float $h, int $n, float $e): float
    {
        $m  = 0;         // Zähler für Intervalle
        $i4 = 0.0;       // Akkumulator für Integral
        $a5 = $u + $h * (1 - 1 / sqrt(3)); // linker Knotenpunkt
        $b5 = $u + $h * (1 + 1 / sqrt(3)); // rechter Knotenpunkt

        while (true) {
            $i4 += $this->fnw($a5, $e) + $this->fnw($b5, $e);
            if ($m == $n / 2 - 1) {
                break;
            }
            $a5 += 2 * $h;
            $b5 += 2 * $h;
            $m++;
        }
        return $i4 * $h;
    }

    private function ellipseSimulation(
        float $A, float $B, 
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
                ['error' => 'Fehler: Der Parameter R (ellipse_circle_radius) darf nicht 0 sein.']
            ];
        }

        $E = ($A * $A - $B * $B) / ($A * $A);   /* $A = große Halbachse, $B = kleine Halbachse.
                                                 * $E$ ist die Quadrat-Exzentrizität der Ellipse.
                                                 * Für einen Kreis gilt $E = 0$ (weil $A = B$).
                                                 * Je größer $E$, desto „gestreckter“ ist die Ellipse.
                                                 */
        
        $U = 0.0;   // letzter Winkel
        $N = 10;    // Integrationsschritte
        $I5 = 0.0;  // Zwischensumme
        $lfdnr=0;

        for ($W2 = 0.0; $W2 < $grenzWinkel; $W2 += $Schrittweite) {   
            $lfdnr++;
            $rad = deg2rad($W2); // Umwandlung in Radiant

            $R2 = $B / sqrt(1 - $E * pow(cos($rad), 2));  // Ellipse Polarkoordinaten
            $X1 = $R2 * cos($rad);
            $Y1 = $R2 * sin($rad);

            $Q3 = ($A * $A * tan($rad)) / ($B * $B);  // Steigung der Tangente
            $Q2 = sqrt(1 + $Q3 * $Q3);
            $F0 = atan($Q3);

            if ($X1 <= 0) { // Ellipsenpunkt links der y-Achse
                $Q2 = -$Q2;
                $F0 += M_PI;
            }

            $M1 = $X1 + $Kreisradius / $Q2;          // Verschiebung des Kreismittelpunkts
            $N1 = $Y1 + $Kreisradius * ($Q3 / $Q2);

            $H = ($rad - $U) / $N;       
            $deltaArc = $this->up1($U, $H, $N, $E);  // Länge seit letztem Punkt
            $I4 = $I5 + $deltaArc;                   // Gesamtlänge bis hier

            $F1 = $B * $I4 / $Kreisradius;           // Drehwinkel

            $X = $M1 - $Abstand * cos($F1 + $F0);    // Endpunkt
            $Y = $N1 - $Abstand * sin($F1 + $F0);

            $punkte[] = [
                'x' => round($X,2),
                'y' => round($Y,2),
                'm1'=> round($M1,2),
                'n1'=> round($N1,2),
                'x1'=> round($X1,2),
                'y1'=> round($Y1,2),
                'ellng'=> round($deltaArc,2)
            ];
            if ($this->debug) $debugline[] = "$lfdnr: W2 $W2 rad ". round($rad,2) . " X:$X Y:$Y";
            $U=$rad; $I5=$I4;
        }
        if ($this->debug) $debugline[] = "Ende Berechnung grenzWinkel";
        return $punkte;
    }
}
