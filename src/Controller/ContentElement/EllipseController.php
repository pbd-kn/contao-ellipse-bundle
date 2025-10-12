<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use PbdKn\ContaoEllipseBundle\Service\EllipseParameterHelper;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Contao\Database;

#[AsContentElement(EllipseController::TYPE, category: 'Ellipse', template: 'ce_ellipse')]
class EllipseController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse';

    public function __construct(
        private readonly EllipseParameterHelper $paramHelper,
        private readonly RequestStack $requestStack
    ) {}

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
    
        $templateName = $model->ellipse_template ?: 'ce_ellipse';
        //$request = $this->requestStack->getCurrentRequest();
        $ceId = (int) $model->id;
        $db = Database::getInstance();

        // === Backend-Wildcard ===
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_ellipse_wildcard');
            $headline = StringUtil::deserialize($model->headline);
            $wildcard->title = $headline['value'] ?? 'Ellipse';
            $wildcard->id = $ceId;
            $wildcard->href = 'contao?do=themes&table=tl_content&id=' . $ceId;
            $wildcard->wildcard = "### Ellipse Template: $templateName ###<br>ID: $ceId";
            return new Response($wildcard->parse());
        }

        $template = $this->createTemplate($model, $templateName);

        // === Parameter via Helper laden ===
        $params = $this->paramHelper->getParameterSet($request, $model, $ceId, [
            'A' => ['field' => 'ellipse_x', 'default' => 400, 'type' => 'int'],
            'B' => ['field' => 'ellipse_y', 'default' => 200, 'type' => 'int'],
            'Umdrehungen' => ['field' => 'ellipse_umlauf', 'default' => 1, 'type' => 'float'],
            'Schrittweite' => ['field' => 'ellipse_schrittweite_pkt', 'default' => 1, 'type' => 'float', 'min' => 0.1],
            'R' => ['field' => 'ellipse_point_sequence', 'default' => 20, 'type' => 'int'],
            'templateSelectionActive' => ['field' => 'templateSelectionActive', 'default' => 0, 'type' => 'bool'],
            'showEllipse' => ['field' => 'showEllipse', 'default' => 0, 'type' => 'bool'],
            'showCircle'  => ['field' => 'showCircle', 'default' => 0, 'type' => 'bool'],
            'lineWidth' => ['field' => 'ellipse_line_thickness', 'default' => 3, 'type' => 'float'],
            'lineMode' => ['field' => 'ellipse_line_mode', 'default' => 'fixed', 'type' => 'string'],
            'lineColor' => ['field' => 'ellipse_line_color', 'default' => 'red', 'type' => 'string'],
        ]);

        $errors = $params['_errors'] ?? [];

        // === Zyklusfarben ===
        $cycleColors = [];
        if ($params['lineMode'] !== 'fixed') {
            for ($i = 1; $i <= 6; $i++) {
                $key = "cycleColor{$i}_{$ceId}";
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
            $params['cycleColors']=$cycleColors;
        }
        // ---------------------------------------------------------------------
        // 🟢 SPEICHERN (über Helper)
        // ---------------------------------------------------------------------
        if ($request->isMethod('POST') && $request->request->get('FORM_SUBMIT') === 'ellipse_save_' . $ceId) {
            $info = trim($request->request->get('info_' . $ceId));

            $saveData = [
                'title'      => $info ?: 'Ohne Titel',
                'parameters' => json_encode($params),
            ];

            //$result = $this->paramHelper->saveParameterSet('tl_ellipse_save', $saveData, $ceId);
            $result = $this->paramHelper->saveParameterSet('tl_ellipse_save', $saveData);                         // ohne contenid

            $template->saveSuccess = in_array($result['status'], ['inserted']);
            $template->saveMessage = $result['message'] ?? 'Speicherfehler.';
            if ($result['status'] === 'db_error' && !empty($result['exception'])) {
                $template->saveMessage .= '<br><small style="color:#555">'
                    . htmlspecialchars($result['exception'])
                    . '</small>';
            }
        }
        
        // ---------------------------------------------------------------------
        // 🔵 LADEN (Anzeige gespeicherter Darstellung)
        // ---------------------------------------------------------------------

        // ✅ Liste der Darstellung über Helper abrufen
        $listResult = $this->paramHelper->getSavedVariants('tl_ellipse_save', $ceId);
        $template->savedVariants = $listResult['items'] ?? [];
        //die ("varianten: ".count($listResult['items']));
        
        // Darstellung laden per POST
        if ( $request->isMethod('POST') && $request->request->get('FORM_SUBMIT') === 'ellipse_load_' . $ceId && $request->request->get('loadAction') === 'load' ) {
            $variantId = (int)$request->request->get('loadVariant');
            if ($variantId > 0) {
                // ✅ Helper verwenden
                $loadResult = $this->paramHelper->loadParameterSet('tl_ellipse_save', $variantId);

                if ($loadResult['status'] === 'loaded') {
                    $params = $loadResult['parameters']; // 👉 wichtig: ersetzt alte Werte
                    if (isset($params['cycleColors'])) $cycleColors=$params['cycleColors'];   // wird unten ins template übernommen
//die ("laden ".var_dump($params));
                    $template->loadedParameters = $loadResult['parameters'];
                    $template->loadedId = $variantId;
                    $template->loadMessage = $loadResult['message'];
                } else {
                    $template->loadMessage = $loadResult['message'];
                }
           } else {
                $template->loadMessage = "Keine Darstellung ausgewählt.";
            }
        }
        $grenzWinkel = $params['Umdrehungen'] * 360;

        // === SVG-Berechnung ===
        $points = [];
        for ($angle = 0; $angle < $grenzWinkel; $angle += $params['Schrittweite']) {
            $rad = deg2rad($angle);
            $x = round($params['A'] * cos($rad), 2);
            $y = round($params['B'] * sin($rad), 2);
            $points[] = ['x' => $x, 'y' => $y];
        }

        $viewBox = sprintf(
            "-%d -%d %d %d",
            $params['A'] + 20,
            $params['B'] + 20,
            2 * ($params['A'] + 20),
            2 * ($params['B'] + 20)
        );



        // ---------------------------------------------------------------------
        // 🔴 LÖSCHEN 
        // ---------------------------------------------------------------------
        if ( $request->isMethod('POST') && $request->request->get('FORM_SUBMIT') === 'ellipse_load_' . $ceId && $request->request->get('loadAction') === 'delete') {
            $delId = (int) $request->request->get('loadVariant');
             // ✅ Helper aufrufen – Rückgabe ist jetzt ein Array
            $deleteResult = $this->paramHelper->deleteParameterSet('tl_ellipse_save', [
                'id'  => $delId,
            ]);

            // ✅ Status & Nachricht auswerten
            $status = $deleteResult['status'] ?? 'error';
            $count  = $deleteResult['count'] ?? 0;
            $msg    = $deleteResult['message'] ?? 'Unbekannter Fehler.';

            // ✅ Rückmeldung an Template
            $template->saveSuccess = ($status === 'deleted');
            $template->saveMessage = match ($status) {
                'deleted'   => "Eintrag #$delId erfolgreich gelöscht ({$count} Zeile).",
                'not_found' => "Eintrag #$delId wurde nicht gefunden.",
                'db_error'  => "Fehler beim Löschen: {$msg}",
                default     => $msg,
            };
        }




        // ---------------------------------------------------------------------
        // Template befüllen
        // ---------------------------------------------------------------------
        $headline = StringUtil::deserialize($model->headline);
        $template->headlineHtml = $headline
            ? sprintf('<%1$s>%2$s</%1$s>', $headline['unit'] ?? 'h2', $headline['value'] ?? '')
            : '';

        $template->A = $params['A'];
        $template->B = $params['B'];
        $template->R = $params['R'];
        $template->Umdrehungen = $params['Umdrehungen'];
        $template->Schrittweite = $params['Schrittweite'];
        $template->showEllipse = $params['showEllipse'];
        $template->showCircle = $params['showCircle'];
        $template->lineWidth = $params['lineWidth'];
        $template->lineMode = $params['lineMode'];
        $template->lineColor = $params['lineColor'];
        $template->cycleColors = $cycleColors;
        $template->templateSelectionActive = $params['templateSelectionActive'];
        $template->viewBox = $viewBox;
        $template->points = $points;
        $template->errors = $errors;

        return $template->getResponse();
    }
}
