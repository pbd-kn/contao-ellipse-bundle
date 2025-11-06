<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\BackendTemplate;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use PbdKn\ContaoEllipseBundle\Service\EllipseParameterHelper;
use PbdKn\ContaoEllipseBundle\Service\LoggerService;

#[AsContentElement(EllipseController::TYPE, category: 'Ellipse', template: 'ce_ellipse')]
class EllipseController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse';

    public function __construct(
        private readonly EllipseParameterHelper $paramHelper,
        private readonly RequestStack $requestStack,
        private readonly LoggerService $logger,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ContaoCsrfTokenManager $csrfTokenManager, // ✅ richtiger Typ
    ) {}

    protected function getResponse(\Contao\CoreBundle\Twig\FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        //----------------------------------------------------------
        // 🔹 1. Grunddaten + CSRF-Token
        //----------------------------------------------------------
        $templateName = $model->ellipse_template ?: 'ce_ellipse_krell';
        $template = $this->createTemplate($model, $templateName);
        $ceId = (int) $model->id;
        $template->id = $ceId;
        $post = $request->request;
        $template->csrfToken = $this->csrfTokenManager->getDefaultTokenValue();
        $this->logger->debugMe("$templateName: $templateName CSRF-Token: " . $template->csrfToken);
 
        //----------------------------------------------------------
        // 🔹 2. Backend-Wildcard
        //----------------------------------------------------------
        if ($this->scopeMatcher->isBackendRequest($request)) {
            $be = new BackendTemplate('be_ellipse_wildcard');
            $headline = StringUtil::deserialize($model->headline);
            $headlineText = is_array($headline)
                ? ($headline['value'] ?? 'Ellipse')
                : (string) $headline;
            $be->title = $headlineText;
            $be->wildcard = '### ELLIPSE ### ID ' . $model->id;
            return $be->getResponse();
        }

        //----------------------------------------------------------
        // 🔹 5. POST-Handling
        //----------------------------------------------------------
        $this->logger->debugMe("--------------- Request -------------------");
        // Hilfsfunktion: GET > DB > Default
        $val = $this->paramHelper->makeValueResolver($request, $model, $ceId);   
            //----------------------------------------------------------
            // 🔹 3. Initiale Template-Werte
            //----------------------------------------------------------
        if ($request->isMethod('POST')) {
            $postData=$post->all();
            $this->logger->debugDumpMe($postData, "postdata val");
        }
        $A  = (float) $val('A', 'ellipse_x', 100);
        $B  = (float) $val('B', 'ellipse_y', 60);
        $Umdrehungen  = (float) $val('Umdrehungen', 'ellipse_umlauf', 1);
        $Schrittweite  = (float) $val('Schrittweite', 'ellipse_schrittweite_pkt', 30);
        $ReihenfolgePkt  = (int) $val('ReihenfolgePkt', 'ellipse_point_sequence', 1);
        $lineWidth  = (float) $val('lineWidth', 'ellipse_line_thickness', 1);
        $lineColor  = $val('lineColor', 'ellipse_line_color', 'blue');
        $lineMode  = $val('lineMode', 'ellipse_line_mode', 'fixed');
        $showEllipse  = (bool) $val('showEllipse', 'showEllipse', 'true');
        $showCircle  = (bool) $val('showCircle', 'showCircle', 'true');
        $templateSelectionActive  = (bool) $val('templateSelectionActive', 'template_selection_active', 'false');
        $cycleColorsRaw = $val('cycleColors', 'cycleColors', json_encode(['red','green','blue','orange','purple','cyan']));
        $cycleColors = is_string($cycleColorsRaw) ? json_decode($cycleColorsRaw, true) : (array) $cycleColorsRaw;

        // Falls JSON ungültig war oder leer
        if (empty($cycleColors) || !is_array($cycleColors)) {
            $this->logger->debugMe('cyclecolors undefined');
            $cycleColors = ['red', 'green', 'blue', 'orange', 'purple', 'cyan'];
        }        
        $this->logger->debugDumpMe($cycleColors,'init cyclecolors');
        $isPost = $request->isMethod('POST');
        if ($isPost) {
            $formSubmit = (string) $post->get('FORM_SUBMIT');
            $this->logger->debugMe("POST erkannt: $formSubmit");


            //--------------------------------------------------
            // 🔸 „Konfiguration anzeigen/ausblenden“
            //--------------------------------------------------
            if ($formSubmit === 'ellipse_toggle_' . $ceId) {
                $template->templateSelectionActive = (bool) $post->get('templateSelectionActive_' . $ceId);
            }

            // Punkte neu berechnen
            $points = $this->createEllipsePoints( $A, $B, $Umdrehungen, $Schrittweite );
            $this->logger->debugMe('🌀 Ellipse neu berechnet');

            //--------------------------------------------------
            // 🔸 „Speichern“
            //--------------------------------------------------
            if ($formSubmit === 'ellipse_save_' . $ceId) {
                $blacklist = ['REQUEST_TOKEN', 'FORM_SUBMIT','ceId','templateSelectionActive','templateSelectionActiveCB'];
                $info = trim($post->get('info'));
                $postData=$post->all();
                foreach ($blacklist as $field) {
                    unset($postData[$field]); // kein Problem, auch wenn 'password' oder 'token' fehlen
                }
                
                    // 🔹 CE-ID-Suffix aus Keys entfernen also z.b Umderhungen_28 -> Umdrehungen
                $cleanData = [];
                foreach ($postData as $key => $value) {
                    $newKey = preg_replace('/_' . preg_quote($ceId, '/') . '$/', '', $key);
                    $cleanData[$newKey] = $value;
                }
                $postData = $cleanData;
                $this->logger->debugDumpMe($postData,'Speichern POSTDATA');
                $params = json_encode($postData, JSON_UNESCAPED_UNICODE);
                $saveData = ['pid' => $ceId, 'title' => $info ?: 'Ohne Titel', 'typ' =>self::TYPE, 'parameters' => $params];
                $this->logger->debugDumpMe($saveData,'Speichern saveData');
                $result = $this->paramHelper->saveParameterSet('tl_ellipse_save',  $saveData);
                $saveSuccess = in_array($result['status'], ['ok']);
                $saveMessage = $result['message'] ?? 'Speicherfehler.';
                // ✅ Nur bei „inserted“ erweitern:
                if ($result['status'] === 'ok') {
                    $saveMessage = "$info wurde gespeichert. $saveMessage";   
                }
                $A=$postData['A'];
                $B=$postData['B'];
                $Umdrehungen=$postData['Umdrehungen'];
                $Schrittweite=$postData['Schrittweite'];
                $ReihenfolgePkt=$postData['ReihenfolgePkt'];
                $lineWidth=$postData['lineWidth'];
                $lineColor=$postData['lineColor'];
                $lineMode=$postData['lineMode'];
                $cycleColorsPost=$postData['cycleColors'];
                /*
                 * liefert als $cycleColors als richtige array zurück
                 */
                if (is_string($cycleColorsPost)) {
                    $decoded = json_decode($cycleColorsPost, true);
                    $cycleColors = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded: ['red', 'green', 'blue', 'orange', 'purple', 'cyan'];
                } else {
                    $cycleColors = is_array($cycleColorsPost) ? $cycleColorsPost : ['red', 'green', 'blue', 'orange', 'purple', 'cyan'];
                }
            }

            //--------------------------------------------------
            // 🔸 „Laden“
            //--------------------------------------------------
            if ($formSubmit === 'ellipse_load_' . $ceId && $post->get('loadAction') === 'load') {
                $loadId = (int) $post->get('variantId');
                $data = $this->paramHelper->loadParameterSet('tl_ellipse_save', $loadId);
                if ($data && !empty($data['parameters'])) {
                    // Parameter aus dem Ergebnis holen
                    $parameters = $data['parameters'];
                    $this->logger->debugDumpMe($parameters,"geladene parameter");
                    foreach ($parameters as $key => $value) {
                        // 🧩 Automatische JSON-Erkennung und Dekodierung
                        if (is_string($value)) {
                            $trimmed = trim($value);
                            if (
                                (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) ||
                                (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
                            ) {
                                $decoded = json_decode($value, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                $this->logger->debugMe("key $key json");
                                    $value = $decoded;
                                    $this->logger->debugDumpMe($value,"JSON erkannt und dekodiert");
                                } else {
                                    $this->logger->debugMe("Fehler beim Dekodieren von $key: " . json_last_error_msg());
                                }
                            }
                        }
                        ${$key} = $value;
                        // Ausgabe / Logging – egal ob String, Array oder Objekt:
                        if (is_array(${$key}) || is_object(${$key})) {
                            $dump = print_r(${$key}, true);
                        } else {
                            $dump = (string) ${$key};
                        }
                        $this->logger->debugMe("Variable \$$key erzeugt mit Wert: $dump");                    
                    }
                    // Lade-Metadaten setzen
                    $loadSuccess = true;
                    $info=$parameters['info'] ?? ($data['title'] ?? 'Ohne Titel');
                    $loadMessage = "Darstellung geladen. Titel $info";
                    $points = $this->createEllipsePoints( $A, $B, $Umdrehungen, $Schrittweite );
                } else {
                    $loadSuccess = false;
                    $loadMessage = 'Fehler beim Laden. '.$data['message'];
                }
            }


            //--------------------------------------------------
            // 🔸 „Löschen“
            //--------------------------------------------------
            if ($formSubmit === 'ellipse_load_' . $ceId && $post->get('loadAction') === 'delete') {
                $delId = (int) $post->get('variantId');
                $deleteResult = $this->paramHelper->deleteParameterSet('tl_ellipse_save', [
                    'id'  => $delId,
                    'pid' => $ceId,
                ]);
                $loadMessage = $deleteResult['message'];
                $loadSuccess = $deleteResult['success'];
            }

            //----------------------------------------------------------
            // 🔹 6. Variantenliste laden
            //----------------------------------------------------------
            $listResult = $this->paramHelper->getSavedVariants('tl_ellipse_save', self::TYPE);
            $template->savedVariants = $listResult['items'] ?? [];

        }
            //----------------------------------------------------------
            // 🔹 6. Variantenliste laden
            //----------------------------------------------------------
            $listResult = $this->paramHelper->getSavedVariants('tl_ellipse_save', self::TYPE);
            $template->savedVariants = $listResult['items'] ?? [];
        
            //----------------------------------------------------------
            // 🔹 7. Punkte erzeugen (Fallback)
            //----------------------------------------------------------
        if (empty($points)) { $points = $this->createEllipsePoints($A, $B, $Umdrehungen, $Schrittweite ); }

        //----------------------------------------------------------
        // 🔹 8. ViewBox
        //----------------------------------------------------------
        $viewBox = sprintf('-%d -%d %d %d', $A + 10, $B + 10, ($A + 10) * 2, ($B + 10) * 2 );
        // ----------------------------------------------------------
        // 🔹 Template-Variablen ermitteln (ohne LoggerService umbauen)
        // ----------------------------------------------------------
        // 🔹 fügt CSS im <head> hinzu
        $GLOBALS['TL_HEAD'][] = '<link rel="stylesheet" href="/bundles/pbdkncontaoellipse/css/ellipse.css">';
            
        $template->A = $A ?? null;
        $template->B = $B ?? null;
        $template->Umdrehungen = $Umdrehungen ?? null;
        $template->Schrittweite = $Schrittweite ?? null;
        $template->ReihenfolgePkt = $ReihenfolgePkt ?? null;
        $template->lineWidth   = $lineWidth ?? null;
        $template->lineColor   = $lineColor ?? null;
        $template->lineMode    = $lineMode ?? null;
        $template->templateSelectionActive = $templateSelectionActive ?? null;
        $template->showEllipse = $showEllipse ?? null;
        $template->showCircle  = $showCircle ?? null;
        $template->cycleColors = $cycleColors ?? null;
        $template->viewBox     = $viewBox ?? null;
        $template->errorMsg    = $errorMsg ?? null;
        $template->saveSuccess   = $saveSuccess ?? null;
        $template->saveMessage   = $saveMessage ?? null;
        $template->loadSuccess   = $loadSuccess ?? null;
        $template->loadMessage   = $loadMessage ?? null;
        $template->points = $points ?? null;
        
        $this->logger->debugDumpMe([
            'A' => $template->A,
            'B' => $template->B,
            'Umdrehungen' => $template->Umdrehungen ?? null,
            'Schrittweite' => $template->Schrittweite ?? null,
            'ReihenfolgePkt' => $template->ReihenfolgePkt ?? null,
            'lineWidth' => $template->lineWidth ?? null,
            'lineColor' => $template->lineColor ?? null,
            'lineMode' => $template->lineMode ?? null,
            'showEllipse' => $template->showEllipse ?? null,
            'showCircle' => $template->showCircle ?? null,
            'cycleColors' => $template->cycleColors ?? null,
            'saveSuccess' => $template->saveSuccess ?? null,
            'saveMessage' => $template->saveMessage ?? null,
            'loadSuccess' => $template->loadSuccess ?? null,
            'loadMessage' => $template->loadMessage ?? null,
            'templateSelectionActive' => $template->templateSelectionActive ?? null,
            'errorMsg' => $template->errorMsg ?? null,
        ],'GETRESPONSE TEMPLATE');

        //----------------------------------------------------------
        // ✅ Ausgabe
        //----------------------------------------------------------
        return $template->getResponse();
    }

    //----------------------------------------------------------
    // 🔹 Hilfsfunktion 
    //----------------------------------------------------------
    private function createEllipsePoints(float $A, float $B, float $Umdrehungen, float $Schrittweite): array
    {
        $points = [];
        $max = 360 * $Umdrehungen;
        for ($t = 0; $t < $max; $t += $Schrittweite) {
            $rad = deg2rad($t);
            $points[] = ['x' => $A * cos($rad), 'y' => $B * sin($rad)];
        }
        return $points;
    }
}
