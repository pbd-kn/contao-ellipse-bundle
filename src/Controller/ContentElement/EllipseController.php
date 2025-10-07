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
use Contao\Database;

#[AsContentElement(EllipseController::TYPE, category: 'Ellipse', template: 'ce_ellipse')]
class EllipseController extends AbstractContentElementController
{
    public const TYPE = 'ce_ellipse';
    private bool $debug = false;

/**
     * 🧩 Der Konstruktor: Symfony injiziert automatisch alle benötigten Services
     * dank deiner services.yaml + ContaoEllipseExtension.
     */
    public function __construct(
        private readonly EllipseParameterHelper $paramHelper,
        private readonly RequestStack $requestStack
    ) {}

    protected function getResponse($template, ContentModel $model, Request $request): Response
    {
        $templateName = $model->ellipse_template ?: 'ce_ellipse';
        $request = $this->requestStack->getCurrentRequest();
        $currentCeId = $model->id;
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
        $currentCeId = $model->id;     // CE Element ID
// Parameter lesen über Helper
// --- 1) Parameter holen ---
        $params = $this->paramHelper->getParameterSet($request, $model, $currentCeId, [
            'A' => ['field' => 'ellipse_x', 'default' => 400, 'type' => 'int'],
            'B' => ['field' => 'ellipse_y', 'default' => 200, 'type' => 'int'],
            'Umdrehungen' => ['field' => 'ellipse_umlauf', 'default' => 1, 'type' => 'float'],
            'Schrittweite' => ['field' => 'ellipse_schrittweite_pkt', 'default' => 1, 'type' => 'float', 'min' => 0.1],
            'R' => ['field' => 'ellipse_point_sequence', 'default' => 20, 'type' => 'int'],
            'templateSelectionActive' => ['templateSelectionActive', 'default' => 0, 'type' => 'bool'],
            'showEllipse' => ['field' => 'showEllipse', 'default' => 0, 'type' => 'bool'],
            'showCircle'  => ['field' => 'showCircle', 'default' => 0, 'type' => 'bool'],

            'lineWidth' => ['field' => 'ellipse_line_thickness', 'default' => 3, 'type' => 'float'],
            'lineMode' => ['field' => 'ellipse_line_mode', 'default' => 'fixed', 'type' => 'string'],
            'lineColor' => ['field' => 'ellipse_line_color', 'default' => 'red', 'type' => 'string'],
        ]);        /*
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
        */
// --- 3) Abgeleitete Werte ---
        $grenzWinkel = $params['Umdrehungen'] * 360;

    // --- 4) Fehler aus Helper übernehmen (falls vorhanden) ---
        $errors = $params['_errors'] ?? [];

    // --- 5) Farben dynamisch ergänzen ---
        $cycleColors = [];
        if ($params['lineMode'] !== 'fixed') {
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
            $lineColor = $cycleColors[0]; // beim ellipsezeicen wird linecolor verwendet
        }        
        // ViewBox
        $margin = 20;
        $viewBox = sprintf("-%d -%d %d %d", $params['A'] + $margin, $params['B'] + $margin, 2 * ($params['A'] + $margin), 2 * ($params['B'] + $margin));

        // Punkte
        $points = [];
        for ($angle = 0; $angle < $grenzWinkel; $angle += $params['Schrittweite']) {
            $rad = deg2rad($angle);
            $x = round($params['A'] * cos($rad),2);
            $y = round($params['B'] * sin($rad),2);
            $points[] = ['x' => $x, 'y' => $y];
            /*
            so ist es bei krell
            $punkte[] = [
                'x' => round($X,2),   // punkt der Linie
                'y' => round($Y,2),
                'm1'=> round($M1,2),  // verschiebung kreismittelpunkt zum Kreis zeichen
                'n1'=> round($N1,2),
                'x1'=> round($X1,2),  // berührungspunkt kreis auf ellipse
                'y1'=> round($Y1,2),
                'ellng'=> round($deltaArc,2)
            ];
            */            
        }
        $template = $this->createTemplate($model, $templateName);

// ------------------------------------------------------------
// 🧩 Speicherung der aktuellen Ellipse-Darstellung in tl_ellipse_save
// ------------------------------------------------------------
        if ( $request->getMethod() === 'POST' && $request->request->get('FORM_SUBMIT') === 'ellipse_save_' . $model->id ) {
            $result = $this->paramHelper->saveParameterSet('tl_ellipse_data', $params, $currentCeId);
            switch ($result['status']) {
                case 'inserted':
                    $template->saveSuccess = true;
                    $template->saveMessage = $result['message'];
                    break;
                case 'duplicate':
                    $template->saveSuccess = false;
                    $template->saveMessage = $result['message'];
                break;
                case 'login_required':
                    $template->saveSuccess = false;
                    $template->saveMessage = $result['message'];
                    break;
                case 'db_error':
                    $message = 
                    $template->saveSuccess = false;
                    $template->saveMessage = "Datenbankfehler: " . htmlspecialchars($result['exception']);
                    break;
                default:
                    $template->saveSuccess = false;
                    $template->saveMessage = "Unbekannter Fehler beim Speichern.";
                    break;
            }
        }               // ende Speichern mit Login und Duplicate-Check

/*
    if ($request->getMethod() === 'POST' && $request->request->get('FORM_SUBMIT') === 'ellipse_save_' . $model->id) {

    // 📝 1. Info-Text aus dem Formular
    $infoText = trim((string) $request->request->get('info_' . $model->id));

    // 👤 2. Aktuell eingeloggten Frontend-Benutzer ermitteln
    $memberId = 0;
    if (defined('FE_USER_LOGGED_IN') && FE_USER_LOGGED_IN && class_exists(\Contao\FrontendUser::class)) {
        $user = \Contao\FrontendUser::getInstance();
        if ($user !== null && $user->id) {
            $memberId = (int) $user->id;
        }
    }

    // 📦 3. Alle Parameter in JSON packen (nur die, die es auch wirklich gibt)
    $saveData = json_encode([
        'A' => $A,
        'B' => $B,
        'Umlauf' => $Umdrehungen ?? $model->ellipse_umlauf,
        'Schrittweite' => $Schrittweite ?? $model->ellipse_schrittweite_pkt,
        'ReihenfolgePkt' => isset($ReihenfolgePkt) ? $ReihenfolgePkt : ($model->ellipse_point_sequence ?? 1),
        'Linienstärke' => $lineWidth ?? $model->ellipse_line_thickness,
        'lineMode' => $lineMode ?? $model->ellipse_line_mode,
        'lineColor' => $lineColor ?? $model->ellipse_line_color,
        'cycleColors' => $cycleColors ?? [],
        'showEllipse' => $showEllipse ?? $model->showEllipse,
        'showCircle'  => $showCircle ?? $model->showCircle,
        'viewBox'     => $viewBox ?? '',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // 🧭 4. DB-Verbindung holen
    $db = \Contao\System::getContainer()->get('database_connection');

    // 🔍 5. Prüfen, ob identischer Datensatz bereits existiert
    $existing = $db->fetchAssociative("
        SELECT id, info, tstamp
        FROM tl_ellipse_save
        WHERE member_id = ?
          AND ce_type = ?
          AND ce_id = ?
          AND save_data = ?
        LIMIT 1
    ", [
        $memberId,
        self::TYPE,
        $model->id,
        $saveData
    ]);

    if ($existing) {
        // ⚠️ Bereits gespeichert — kein neues INSERT
        $template->saveSuccess = false;
        $template->saveMessage = sprintf(
            '⚠️ Diese Ellipse wurde bereits gespeichert (unter „%s“, am %s).',
            $existing['info'] ?: 'ohne Beschreibung',
            date('d.m.Y H:i', $existing['tstamp'])
        );
    } else {
        // 💾 Neu eintragen
        $db->insert('tl_ellipse_save', [
            'tstamp'     => time(),
            'member_id'  => $memberId,
            'ce_type'    => self::TYPE,
            'ce_id'      => $model->id,
            'info'       => $infoText ?: 'ohne Beschreibung',
            'save_data'  => $saveData,
        ]);

        $template->saveSuccess = true;
        $template->saveMessage = '✅ Ellipse wurde erfolgreich gespeichert.';
    }
}
*/

        // Template befüllen
        $template->headlineHtml = $model->headline
            ? sprintf('<%1$s>%2$s</%1$s>', StringUtil::deserialize($model->headline)['unit'] ?? 'h2', StringUtil::deserialize($model->headline)['value'] ?? '')
            : '';

        $template->debugline = $debugline;
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
