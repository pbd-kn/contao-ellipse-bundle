<?php

namespace PbdKn\ContaoEllipseBundle\Controller\ContentElement;

use Contao\System;
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


#[AsContentElement(EllipseKrellController::TYPE, category: 'Ellipse', template: 'ce_ellipse_krell')]
class EllipseKrellController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse_krell';
    private bool $debug = false;
    
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
        // --- Backend Wildcard ---
        $scope = System::getContainer()->get('request_stack')?->getCurrentRequest()?->attributes?->get('_scope');
        if ('backend' === $scope) {
            $wildcard = new BackendTemplate('be_ellipse_wildcard');
            $wildcard->title = StringUtil::deserialize($model->headline)['value'] ?? 'Ellipse Krell';
            $wildcard->id = $ceId;
            $wildcard->wildcard = '### Ellipse Krell ###';
            return new Response($wildcard->parse());
        }

        $debugline = [];
        $currentCeId = $model->id;
        // Hilfsfunktion: GET > DB > Default
        $val = $this->paramHelper->makeValueResolver($request, $model, $ceId);   
        //----------------------------------------------------------
        // 🔹 5. POST-Handling
        //----------------------------------------------------------
        $this->logger->debugMe("--------------- Request -------------------");
            //----------------------------------------------------------
            // 🔹 3. Initiale Template-Werte
            //----------------------------------------------------------
        if ($request->isMethod('POST')) {
            $postData=$post->all();
            $this->logger->debugDumpMe($postData, "postdata val");
        }

        // === Parameter laden ===
        $A  = (float) $val('A', 'ellipse_x', 10.0);
        $B  = (float) $val('B', 'ellipse_y', 6.0);
        $GRaw = (string) $val('Umdrehungen', 'ellipse_umlauf', '1');  
        $Umdrehungen  = (float) $val('Umdrehungen', 'ellipse_umlauf', 1);
        $Schrittweite = (float) $val('Schrittweite', 'ellipse_schrittweite_pkt', M_PI / 18);
        $ReihenfolgePkt = (int) $val('ReihenfolgePkt', 'ellipse_point_sequence', 20);    
        $Kreisradius  = (float)   $val('Kreisradius', 'ellipse_circle_radius', 2);
        $Abstand = (float) $val('Abstand', 'ellipse_point_radius', 1.0);

        // === Checkboxen ===
        $showEllipse  = (bool) $val('showEllipse', 'showEllipse', 'true');
        $showCircle  = (bool) $val('showCircle', 'showCircle', 'true');
        $templateSelectionActive  = (bool) $val('templateSelectionActive', 'template_selection_active', 'false');

        $lineWidth  = (float) $val('lineWidth', 'ellipse_line_thickness', 1);
        $lineColor  = $val('lineColor', 'ellipse_line_color', 'blue');

        $lineMode  = $val('lineMode', 'ellipse_line_mode', 'fixed');
        $cycleColors = [];
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
            $points=[];

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
                    $points = $this->createEllipsePoints($A, $B, $Kreisradius, $Abstand, $Umdrehungen, $Schrittweite, $debugline);
                    $this->logger->debugMe('🌀 Ellipse nach laden neu berechnet');
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
            // 🔹 7. Punkte erzeugen falls nicht durch laden schon geschehen
            //----------------------------------------------------------
        if (empty($points)) { 
            $points = $this->createEllipsePoints($A, $B, $Kreisradius, $Abstand, $Umdrehungen, $Schrittweite, $debugline); 
            $this->logger->debugMe('🌀 Ellipse neu berechnet');
        }

        // === Punkte berechnen ===
        

        $errorMsg = null;
        //----------------------------------------------------------
        // 🔹 8. ViewBox
        //----------------------------------------------------------
        $viewBox = "0 0 500 500"; // Default
        $viewBoxOffset=10 + $Abstand;
        if ($showCircle) $viewBoxOffset=(2 * $Kreisradius)  + $viewBoxOffset;
        if (!empty($points[0]['error'])) {
            $errorMsg = $points[0]['error'];
        } else {
            $viewBox = sprintf('-%d -%d %d %d', $A + $viewBoxOffset, $B + $viewBoxOffset, ($A + $viewBoxOffset) * 2, ($B + $viewBoxOffset) * 2 );       
        }

        // === Template befüllen ===

        // 🔹 fügt CSS im <head> hinzu
        $GLOBALS['TL_HEAD'][] = '<link rel="stylesheet" href="/bundles/pbdkncontaoellipse/css/ellipse.css">';
        
        $this->logger->debugMe("showEllipse $showEllipse $showCircle $showCircle");

        if ($this->logger->isDebug()) $debugline[] = "Debug: Anzshl Punkte: " . count($points) . " showEllipse: " . ($showEllipse ? '1' : '0'). " showCircle: " . ($showCircle ? '1' : '0');
        $template->debugline = $debugline;        
        $template->headlineHtml = $model->headline
            ? sprintf(
                '<%1$s>%2$s</%1$s>',
                StringUtil::deserialize($model->headline)['unit'] ?? 'h2',
                StringUtil::deserialize($model->headline)['value'] ?? ''
            )
            : '';

        $template->A = $A ?? null;
        $template->B = $B ?? null;
        $template->Umdrehungen = $Umdrehungen ?? null;
        $template->Schrittweite = $Schrittweite ?? null;
        $template->ReihenfolgePkt = $ReihenfolgePkt ?? null;
        $template->Kreisradius = $Kreisradius ?? 1;
        $template->Abstand = $Abstand ?? 1;
        $template->points = $points ?? null;
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

        $this->logger->debugDumpMe([
            'A' => $template->A,
            'B' => $template->B,
            'Umdrehungen' => $template->Umdrehungen ?? null,
            'Schrittweite' => $template->Schrittweite ?? null,
            'ReihenfolgePkt' => $template->ReihenfolgePkt ?? null,
            'Kreisradius' => $template->Kreisradius ?? null,
            'Abstand' => $template->Abstand ?? null,
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

    private function createEllipsePoints( float $A, float $B, 
        float $Kreisradius, // $R
        float $Abstand,     // $R1
        float $Umdrehungen,
        float $Schrittweite, 
        array &$debugline)
        : array
    {
        $grenzWinkel = $Umdrehungen*360;
        $punkte = [];
        if ($this->logger->isDebug()) $debugline[] = "Start Berechnung Umdrehungen $Umdrehungen Schrittweite $Schrittweite GrenzWinkel $grenzWinkel Kreisradius $Kreisradius";
        $this->logger->debugMe("Start Berechnung Umdrehungen $Umdrehungen Schrittweite $Schrittweite GrenzWinkel $grenzWinkel Kreisradius $Kreisradius");
        if ($Kreisradius == 0.0) {
            return [
                ['error' => 'Fehler: Der Parameter Kreisradius (ellipse_circle_radius) darf nicht 0 sein.']
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
            if ($this->logger->isDebug()) $debugline[] = "$lfdnr: Winkel(W2) $W2 rad(W2) ". round($rad,2) . " Punkt(x,y) $X , $Y Bogenlänge ".round($deltaArc,2);
            $this->logger->debugMe("$lfdnr: Winkel(W2) $W2 rad(W2) ". round($rad,2) . " Punkt(x,y) $X , $Y Bogenlänge ".round($deltaArc,2));
            $U=$rad; $I5=$I4;
        }
        if ($this->logger->isDebug()) $debugline[] = "Ende Berechnung";
        return $punkte;
    }
}
