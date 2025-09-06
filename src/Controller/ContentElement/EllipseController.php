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
        // --- Backend Wildcard ---
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_wildcard');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Ellipse';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $wildcard->wildcard = '### Ellipse ###';
            return new Response($wildcard->parse());
        }

        // --- Hilfsfunktion: GET > DB > Default
        $val = function(string $getKey, string $dbField, $default = null) use ($request, $model) {
            $fromGet = $request->query->get($getKey);
            if ($fromGet !== null && $fromGet !== '') {
                return $fromGet;
            }
            if ($model->{$dbField} !== null && $model->{$dbField} !== '') {
                return $model->{$dbField};
            }
            return $default;
        };

        // --- Parameter aus Formular / DB / Defaults
        $A = (int) $val('A', 'ellipse_major_axis', 400);
        $B = (int) $val('B', 'ellipse_minor_axis', 200);
        $R = (int) $val('R', 'ellipse_circle_radius', 20);
        $G = (int) $val('G', 'ellipse_angle_limit', 360);

        // Schrittweite (Float, egal ob , oder . eingegeben)
        $Sraw = (string) $val('S', 'ellipse_step_size', '0.05');
        $S = (float) str_replace(',', '.', $Sraw);

        // Weitere Parameter
        $circleSize  = (int) $request->query->get('circleSize', 0);
        $textSize    = (int) $request->query->get('textSize', 3);
        $lineWidth   = (float) $request->query->get('lineWidth', 1.0);
        $lineMode    = (string) $request->query->get('lineMode', 'fixed');
        $lineColor   = (string) $request->query->get('lineColor', 'red');

        // Wenn Kreisgröße leer oder <1 ? automatisch aus Textgröße berechnen
        if ($circleSize < 1) {
            $circleSize = max(1, (int) round($textSize * 0.6));
        }

        // Checkboxen
        $submitted = $request->query->has('submitted');
        $showEllipse = $request->query->has('showEllipse') ? true : ($submitted ? false : (bool) $model->showEllipse);
        $showCircle  = $request->query->has('showCircle')  ? true : ($submitted ? false : (bool) $model->showCircle);

        // Zyklische Farben (max. 6)
        $cycleColors = [];
        for ($i = 1; $i <= 6; $i++) {
            $color = trim((string) $request->query->get("cycleColor$i", ''));
            if ($color !== '') {
                $cycleColors[] = $color;
            }
        }
        if (empty($cycleColors)) {
            $cycleColors = ["red", "green", "blue", "orange", "purple"];
        }

        // --- SVG Setup ---
        $margin = 20;
        $viewBox = sprintf("-%d -%d %d %d",
            $A + $margin, $B + $margin,
            2 * ($A + $margin),
            2 * ($B + $margin)
        );

        // --- Punkte berechnen ---
        $points = [];
        for ($angle = 0; $angle <= $G; $angle += $S) {
            $rad = deg2rad($angle);
            $x = $A * cos($rad);
            $y = $B * sin($rad);
            $points[] = ['x' => $x, 'y' => $y];
        }

        // --- Template laden ---
        $template = $this->createTemplate($model, 'ce_ellipse');

        // --- Variablen ins Template ---
        foreach ([
            'A' => $A,
            'B' => $B,
            'R' => $R,
            'G' => $G,
            'S' => $S,
            'showEllipse' => $showEllipse,
            'showCircle'  => $showCircle,
            'circleSize'  => $circleSize,
            'textSize'    => $textSize,
            'lineWidth'   => $lineWidth,
            'lineMode'    => $lineMode,
            'lineColor'   => $lineColor,
            'cycleColors' => $cycleColors,
            'viewBox'     => $viewBox,
            'points'      => $points,
        ] as $key => $value) {
            $template->set($key, $value);
        }

        return $template->getResponse();
    }
}
