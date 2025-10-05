<?php

use PbdKn\ContaoEllipseBundle\Controller\ContentElement\EllipseController;
use PbdKn\ContaoEllipseBundle\Controller\ContentElement\EllipseKrellController;

/**
 * ============================================================================
 * DCA: tl_content – Ellipsen-Elemente
 * ============================================================================
 * Dieses DCA definiert zwei Content-Elemente:
 *  1️⃣ ce_ellipse         → klassische Ellipse
 *  2️⃣ ce_ellipse_krell   → Simulation nach Krell
 * 
 * Besonderheiten:
 *  - Keine Colorpicker (nur Textwerte für Farben)
 *  - Float-Eingaben erlaubt (Text + rgxp)
 *  - Checkboxen korrekt mit eval['tl_class'] und leerem Default
 *  - Paletten vollständig klickbar im Backend
 * ============================================================================
 */

/**
 * -------------------------------------------------------------------------
 * Paletten
 * -------------------------------------------------------------------------
 */

// Ellipse (klassisch)
$GLOBALS['TL_DCA']['tl_content']['palettes'][EllipseController::TYPE]
    = '{type_legend},type,headline,ellipse_be_id;
       {ellipse_legend},
           ellipse_template,
           ellipse_x,ellipse_y,ellipse_umlauf,ellipse_schrittweite_pkt,
           ellipse_point_sequence,ellipse_line_thickness,ellipse_line_mode,
           ellipse_line_color,ellipse_cycle_color1,ellipse_cycle_color2,
           ellipse_cycle_color3,ellipse_cycle_color4,ellipse_cycle_color5,
           ellipse_cycle_color6,
           showEllipse,showCircle,template_selection_active;
       {template_legend:hide},customTpl;
       {protected_legend:hide},protected;
       {expert_legend:hide},guests,cssID;
       {invisible_legend:hide},invisible,start,stop';

// Ellipse Krell (Simulation)
$GLOBALS['TL_DCA']['tl_content']['palettes'][EllipseKrellController::TYPE]
    = '{type_legend},type,headline,ellipse_be_id;
       {ellipse_legend},
           ellipse_x,ellipse_y,ellipse_umlauf,ellipse_schrittweite_pkt,
           ellipse_point_sequence,ellipse_circle_radius,ellipse_point_radius,
           ellipse_line_thickness,ellipse_line_mode,ellipse_line_color,
           ellipse_cycle_color1,ellipse_cycle_color2,ellipse_cycle_color3,
           ellipse_cycle_color4,ellipse_cycle_color5,ellipse_cycle_color6,
           showEllipse,showCircle,template_selection_active,ellipse_template;
       {template_legend:hide},customTpl;
       {protected_legend:hide},protected;
       {expert_legend:hide},guests,cssID;
       {invisible_legend:hide},invisible,start,stop';


/**
 * -------------------------------------------------------------------------
 * Felder – Allgemein
 * -------------------------------------------------------------------------
 */

// große Halbachse (A)
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_x'] = [
    'label'     => ['Große Halbachse (A)', 'Länge der großen Halbachse'],
    'exclude'   => true,
    'inputType' => 'text', // kein number → Float + Komma möglich
    'eval'      => [
        'mandatory' => true,
        'rgxp'      => 'prcnt', // erlaubt auch Komma-/Punktwerte
        'tl_class'  => 'w50',
    ],
    'sql'       => "varchar(16) NOT NULL default '100'",
];

// kleine Halbachse (B)
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_y'] = [
    'label'     => ['Kleine Halbachse (B)', 'Länge der kleinen Halbachse'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'rgxp' => 'prcnt', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '60'",
];

// Umläufe
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_umlauf'] = [
    'label'     => ['Umläufe', 'Anzahl der Umläufe auf der Ellipse'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '1'",
];

// Schrittweite
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_schrittweite_pkt'] = [
    'label'     => ['Schrittweite', 'Abstand der Punkte auf der Ellipse'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory' => true, 'rgxp' => 'prcnt', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '1'",
];

// Linienstärke
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_thickness'] = [
    'label'     => ['Linienstärke', 'Stärke der gezeichneten Linie'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'prcnt', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '1'",
];

// Linienmodus
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_mode'] = [
    'label'     => ['Linienmodus', 'Feste Farbe oder zyklische Farben'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['fixed' => 'Fest', 'cycle' => 'Zyklisch'],
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default 'fixed'",
];
// Linienfarben 1–6 (Textfelder, keine Picker)
$defaultColors = [
    1 => 'blue', // Blau     1 ist defaultfarbe fuer fest
    2 => 'green', // Grün
    3 => 'red', // Rot
    4 => '#ffff00', // Gelb
    5 => '#ff00ff', // Magenta
    6 => '#00ffff'  // Cyan
];

// Linienfarbe
$defaultfest = $defaultColors['1'] ?? '#000000'; // Fallback: Schwarz
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_color'] = [
    'label'     => ['Linienfarbe (fest)', 'Wird nur bei Modus „fest“ verwendet'],
    'exclude'   => true,
    'inputType' => 'text', // einfacher Text statt Colorpicker
    'eval'      => ['tl_class' => 'w50 clr'],
    'sql'       => "varchar(64) NOT NULL default '{$defaultfest}'",
];


for ($i = 1; $i <= 6; $i++) {
    $default = $defaultColors[$i] ?? '#000000'; // Fallback: Schwarz

    $GLOBALS['TL_DCA']['tl_content']['fields']["ellipse_cycle_color{$i}"] = [
        'label'     => ["Zyklusfarbe {$i}", 'Nur bei Modus „zyklisch“'],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => [
            'maxlength' => 64,
            // abwechselnd w50 / w50 clr für saubere Darstellung
            'tl_class'  => ($i % 2 === 0) ? 'w50' : 'w50 clr',
        ],
        'sql'       => "varchar(64) NOT NULL default '{$default}'",
    ];
}


// Ellipse anzeigen
$GLOBALS['TL_DCA']['tl_content']['fields']['showEllipse'] = [
    'label'     => ['Ellipse anzeigen', 'Steuert die Ausgabe der Ellipse'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50 clr'],
    'sql'       => "char(1) NOT NULL default ''",
];

// Hilfskreis anzeigen
$GLOBALS['TL_DCA']['tl_content']['fields']['showCircle'] = [
    'label'     => ['Hilfskreise anzeigen', 'Aktiviert die Kreise um die Ellipse'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50'],
    'sql'       => "char(1) NOT NULL default ''",
];

// Anzeige der Konfiguration aktiv
$GLOBALS['TL_DCA']['tl_content']['fields']['template_selection_active'] = [
    'label'     => ['Konfiguration anzeigen', 'Aktiviert die Konfigurationsauswahl im Frontend'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr w50'],
    'sql'       => "char(1) NOT NULL default ''",
];

/**
 * -------------------------------------------------------------------------
 * Zusätzliche Felder
 * -------------------------------------------------------------------------
 */

// Punktreihenfolge
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_sequence'] = [
    'label'     => ['Punktreihenfolge (R)', 'Reihenfolge der verbundenen Punkte'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'digit', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '1'",
];

// Kreisradius (nur Krell)
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_circle_radius'] = [
    'label'     => ['Kreisradius (R)', 'Radius der Hilfskreise'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'prcnt', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '2'",
];

// Punkt-Radius (nur Krell)
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_radius'] = [
    'label'     => ['Punkt-Radius (R1)', 'Radius der Punkte'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'prcnt', 'tl_class' => 'w50'],
    'sql'       => "varchar(16) NOT NULL default '1'",
];

// Template-Auswahl
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_template'] = [
    'label'     => ['Ellipsen-Template', 'Template für die Ausgabe auswählen'],
    'exclude'   => true,
    'inputType' => 'select',
    'options_callback' => static function () {
        return \Contao\Controller::getTemplateGroup('ce_ellipse');
    },
    'eval'      => ['includeBlankOption' => true, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

// Backend-ID (nur Anzeigezwecke)
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_be_id'] = [
    'label'     => ['Backend-ID', 'Interne ID für die Wildcard-Anzeige'],
    'exclude'   => false,
    'inputType' => 'text',
    'eval'      => ['maxlength' => 64, 'tl_class' => 'w50'],
    'sql'       => "varchar(64) NOT NULL default 'BE_ID'",
];
