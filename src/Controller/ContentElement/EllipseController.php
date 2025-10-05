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
use Contao\Database;

#[AsContentElement(EllipseController::TYPE, category: 'Ellipse', template: 'ce_ellipse')]
class EllipseController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse';
    private bool $debug = false;

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $templateName = $model->ellipse_template ?: 'ce_ellipse';

        // --- Backend Wildcard ---
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_ellipse_wildcard');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Ellipse';
            $wildcard->id = $model->id;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $model->id;
            $wildcard->wildcard = "### Ellipse Template: $templateName ###<br>ID: " . $model->id;
            return new Response($wildcard->parse());
        }

        $debugline = [];
        $currentCeId = $model->id;

        // Hilfsfunktion: GET > DB > Default
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

        // === Parameter ===
        $A = (int) $val('A', 'ellipse_x', 400);
        $B = (int) $val('B', 'ellipse_y', 200);

        $GRaw = (string) $val('Umdrehungen', 'ellipse_umlauf', '1');
        $Umdrehungen = (float) str_replace(',', '.', $GRaw);
        $grenzWinkel = $Umdrehungen * 360;

        $Schrittweite = (float) str_replace(',', '.', (string) $val('Schrittweite', 'ellipse_schrittweite_pkt', '1'));
        if ($Schrittweite <= 0) {
            $errors[] = "Schrittweite muss > 0 sein. Wurde auf 1 gesetzt.";
            $Schrittweite = 1;
        }

        $R = (int) $val('R', 'ellipse_point_sequence', 20);

        $lineWidth = (float) str_replace(',', '.', (string) $val('lineWidth', 'ellipse_line_thickness', '3'));
        $lineMode = (string) $val('lineMode', 'ellipse_line_mode', 'fixed');

        $lineColor = '';
        $cycleColors = [];

        if ($lineMode === 'fixed') {
            $lineColor = (string) $val('lineColor', 'ellipse_line_color', 'red');
        } else {
            for ($i = 1; $i <= 6; $i++) {
                $key = "cycleColor{$i}_" . $currentCeId;
                if ($request->query->has($key)) {
                    $color = trim((string) $request->query->get($key));
                    if ($color !== '') $cycleColors[] = $color;
                } else {
                    $dbField = "ellipse_cycle_color{$i}";
                    $color = (string) ($model->{$dbField} ?? '');
                    if (trim($color) !== '') $cycleColors[] = trim($color);
                }
            }
            if (count($cycleColors) === 0) {
                $cycleColors = ["blue", "green", "red", "orange", "purple", "brown"];
            }
            $lineColor = $cycleColors[0];
        }

        $templateSelectionActive = (bool) $request->query->get('templateSelectionActive_' . $currentCeId, $model->template_selection_active);
        $showEllipse = (bool) $request->query->get('showEllipse_' . $currentCeId, $model->showEllipse);
        $showCircle  = (bool) $request->query->get('showCircle_' . $currentCeId, $model->showCircle);
        $this->debug = $showEllipse || $showCircle;

        // ViewBox
        $margin = 20;
        $viewBox = sprintf("-%d -%d %d %d", $A + $margin, $B + $margin, 2 * ($A + $margin), 2 * ($B + $margin));

        // Punkte
        $points = [];
        for ($angle = 0; $angle < $grenzWinkel; $angle += $Schrittweite) {
            $rad = deg2rad($angle);
            $points[] = ['x' => $A * cos($rad), 'y' => $B * sin($rad)];
        }

        // === Speicherung in tl_ellipse_save ===
        if ($request->getMethod() === 'POST' && $request->request->get('FORM_SUBMIT') === 'ellipse_save_'.$currentCeId) {
            $info = (string) $request->request->get('info_'.$currentCeId);
            $params = [
                'A' => $A,
                'B' => $B,
                'Umdrehungen' => $Umdrehungen,
                'Schrittweite' => $Schrittweite,
                'R' => $R,
                'lineWidth' => $lineWidth,
                'lineMode' => $lineMode,
                'lineColor' => $lineColor,
                'cycleColors' => $cycleColors,
                'showEllipse' => $showEllipse,
                'showCircle' => $showCircle
            ];
            Database::getInstance()->prepare("
                INSERT INTO tl_ellipse_save
                (tstamp, ce_type, ce_id, data, info)
                VALUES (?, ?, ?, ?, ?)
            ")->execute(time(), self::TYPE, $currentCeId, json_encode($params), $info);
        }

        // Template befüllen
        $template = $this->createTemplate($model, $templateName);
        $template->headlineHtml = $model->headline
            ? sprintf('<%1$s>%2$s</%1$s>', StringUtil::deserialize($model->headline)['unit'] ?? 'h2', StringUtil::deserialize($model->headline)['value'] ?? '')
            : '';

        $template->debugline = $debugline;
        $template->A = $A;
        $template->B = $B;
        $template->R = $R;
        $template->Umdrehungen = $Umdrehungen;
        $template->Schrittweite = $Schrittweite;
        $template->showEllipse = $showEllipse;
        $template->showCircle = $showCircle;
        $template->lineWidth = $lineWidth;
        $template->lineMode = $lineMode;
        $template->lineColor = $lineColor;
        $template->cycleColors = $cycleColors;
        $template->viewBox = $viewBox;
        $template->points = $points;
        $template->templateSelectionActive = $templateSelectionActive;
        $template->errors = $errors;

        return $template->getResponse();
    }
}
