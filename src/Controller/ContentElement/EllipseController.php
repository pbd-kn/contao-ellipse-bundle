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

#[AsContentElement(EllipseController::TYPE, category: 'Ellipse', template: 'ce_ellipse')]
class EllipseController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse';

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        // Gewähltes Template oder Fallback
        $templateName = $model->ellipse_template ?: 'ce_ellipse';

        // --- Backend Wildcard ---
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_ellipse_wildcard');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Ellipse';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;

            $be_id = $model->ellipse_be_id;

            $wildcardtxt  = "### Ellipse Template: $templateName ###<br>";
            $wildcardtxt .= "ID: $be_id";

            $wildcard->wildcard = $wildcardtxt;
            return new Response($wildcard->parse());
        }

        $currentCeId = $model->id;

        // Hilfsfunktion: GET (mit ID) > DB > Default
        $val = function(string $getKey, string $dbField, $default = null) use ($request, $model, $currentCeId) {
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

        $errors = [];

        // Parameter mit Validierung
        $A = (int) $val('A', 'ellipse_x', 400);
        if ($A < 1 || $A > 5000) {
            $errors[] = "A (Halbachse X) muss zwischen 1 und 5000 liegen. Wert wurde begrenzt.";
            $A = min(max($A, 1), 5000);
        }

        $B = (int) $val('B', 'ellipse_y', 200);
        if ($B < 1 || $B > 5000) {
            $errors[] = "B (Halbachse Y) muss zwischen 1 und 5000 liegen. Wert wurde begrenzt.";
            $B = min(max($B, 1), 5000);
        }

        $GRaw = (string) $val('G', 'ellipse_umlauf', '1');  // default 1 Umdrehung
        $G = (float) str_replace(',', '.', $GRaw);
        if ($G > 100) {   // angabe in grad
            $grenzWnkel = $G;
        } else {
            $grenzWnkel = $G*360;
        }

        $Sraw = (string) $val('S', 'ellipse_schrittweite_pkt', '10'); //schritteite der Punkte
        $S = (float) str_replace(',', '.', $Sraw);
        if ($S < 1) {
            $errors[] = "S (Schrittweite) muss mindestens 1 sein. Wert wurde auf 1 gesetzt.";
            $S = 1;
        }

        $maxPoints = 2000;
        $numPoints = (int) ceil($grenzWnkel / $S);
        if ($numPoints > $maxPoints) {
            $errors[] = "Zu viele Punkte ($numPoints). Es werden nur $maxPoints Punkte gezeichnet.";
            $numPoints = $maxPoints;
            $grenzWnkel = $S * $maxPoints;
        }

        $R = (int) $val('R', 'ellipse_point_sequence', 20);    // Reihenfolge in der die Punkte gezeichnet werden

        $lineWidthRaw = (string) $val('lineWidth', 'ellipse_line_thickness', '3');
        $lineWidth = (float) str_replace(',', '.', $lineWidthRaw);

        $lineMode = (string) $val('lineMode', 'ellipse_line_mode', 'fixed');

        $lineColor = '';
        $cycleColors = [];

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

        // Checkboxen: GET-Werte (0/1) haben Vorrang, sonst DB
        $templateSelectionActive = (bool) $request->query->get('templateSelectionActive_' . $currentCeId, $model->template_selection_active);
        
        $showEllipse = (bool) $request->query->get('showEllipse_' . $currentCeId, $model->showEllipse);
        $showCircle  = (bool) $request->query->get('showCircle_' . $currentCeId, $model->showCircle);

        $circleSize = (int) $request->query->get('circleSize_' . $currentCeId, 0);
        $textSize   = (int) $request->query->get('textSize_' . $currentCeId, 3);
        if ($circleSize < 1) {
            $circleSize = max(1, (int) round($textSize * 0.6));
        }

        $margin = 20;
        $viewBox = sprintf("-%d -%d %d %d",
            $A + $margin, $B + $margin,
            2 * ($A + $margin),
            2 * ($B + $margin)
        );

        $points = [];
        for ($angle = 0; $angle <= $grenzWnkel; $angle += $S) {
            $rad = deg2rad($angle);
            $x = $A * cos($rad);
            $y = $B * sin($rad);
            $points[] = ['x' => $x, 'y' => $y];
        }

        $template = $this->createTemplate($model, $templateName);

        if ($model->headline) {
            $hl = StringUtil::deserialize($model->headline);
            $headlineTag = $hl['unit'] ?: 'h2';
            $headlineText = $hl['value'] ?? '';
            $template->headlineHtml = sprintf('<%1$s>%2$s</%1$s>', $headlineTag, $headlineText);
        } else {
            $template->headlineHtml = '';
        }

        $template->A = $A;
        $template->B = $B;
        $template->R = $R;
        $template->G = $G;
        $template->S = $S;
        $template->showEllipse = $showEllipse;
        $template->showCircle  = $showCircle;
        $template->circleSize  = $circleSize;
        $template->textSize    = $textSize;
        $template->lineWidth   = $lineWidth;
        $template->lineMode    = $lineMode;
        $template->lineColor   = $lineColor;
        $template->cycleColors = $cycleColors;
        $template->viewBox     = $viewBox;
        $template->points      = $points;
        $template->templateSelectionActive = $templateSelectionActive;
        $template->errors      = $errors;

        return $template->getResponse();
    }
}
