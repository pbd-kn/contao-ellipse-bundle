<?php

use PbdKn\ContaoEllipseBundle\Controller\ContentElement\EllipseController;
use PbdKn\ContaoEllipseBundle\Controller\ContentElement\EllipseKrellController;

/**
 * Paletten
 */

// Ellipse (klassisch)
$GLOBALS['TL_DCA']['tl_content']['palettes'][EllipseController::TYPE]
    = '{type_legend},type,headline;{ellipse_legend},ellipse_be_id,ellipse_x,ellipse_y,ellipse_umlauf,ellipse_schrittweite_pkt,ellipse_point_sequence,ellipse_line_thickness,ellipse_line_mode,ellipse_line_color,ellipse_cycle_color1,ellipse_cycle_color2,ellipse_cycle_color3,ellipse_cycle_color4,ellipse_cycle_color5,ellipse_cycle_color6,showEllipse,showCircle,template_selection_active,ellipse_template;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';

// Ellipse Krell (Simulation)
$GLOBALS['TL_DCA']['tl_content']['palettes'][EllipseKrellController::TYPE]
    = '{type_legend},type,headline;{ellipse_legend},ellipse_be_id,ellipse_x,ellipse_y,ellipse_umlauf,ellipse_schrittweite_pkt,ellipse_point_sequence,ellipse_circle_radius,ellipse_point_radius,ellipse_line_thickness,ellipse_line_mode,ellipse_line_color,ellipse_cycle_color1,ellipse_cycle_color2,ellipse_cycle_color3,ellipse_cycle_color4,ellipse_cycle_color5,ellipse_cycle_color6,showEllipse,showCircle,template_selection_active,ellipse_template;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},guests,cssID;{invisible_legend:hide},invisible,start,stop';


/**
 * Felder – Allgemein
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_x'] = [
    'label'     => ['Große Halbachse (A)', 'Länge der großen Halbachse'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['mandatory'=>true, 'tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 100",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_y'] = [
    'label'     => ['Kleine Halbachse (B)', 'Länge der kleinen Halbachse'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['mandatory'=>true, 'tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 60",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_umlauf'] = [
    'label'     => ['Umläufe', 'Anzahl Umläufe auf Ellipse'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['mandatory'=>true, 'tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_schrittweite_pkt'] = [
    'label'     => ['Schrittweite', 'Schrittweite auf Ellipse von einem Punkt zum nächsten'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['mandatory'=>true, 'tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_thickness'] = [
    'label'     => ['Linienstärke', 'Stärke der gezeichneten Linie'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_mode'] = [
    'label'     => ['Linienmodus', 'Feste Farbe oder zyklische Farben'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['fixed' => 'Fest', 'cycle' => 'Zyklisch'],
    'eval'      => ['tl_class'=>'w50'],
    'sql'       => "varchar(16) NOT NULL default 'fixed'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_color'] = [
    'label'     => ['Linienfarbe (fest)', 'Nur bei Modus „fest“'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>64, 'tl_class'=>'w50 clr', 'colorpicker'=>true],
    'sql'       => "varchar(64) NOT NULL default '#000000'",
];

for ($i = 1; $i <= 6; $i++) {
    $GLOBALS['TL_DCA']['tl_content']['fields']["ellipse_cycle_color{$i}"] = [
        'label'     => ["Zyklusfarbe {$i}", 'Nur bei Modus „zyklisch“'],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => ['tl_class'=>'w50', 'colorpicker'=>true],
        'sql'       => "varchar(64) NOT NULL default '#0000ff'",
    ];
}

$GLOBALS['TL_DCA']['tl_content']['fields']['showEllipse'] = [
    'label'     => ['Ellipse anzeigen', 'Steuert die Ausgabe der Ellipse'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['showCircle'] = [
    'label'     => ['Kreise anzeigen', 'Steuert die Ausgabe des Hilfskreises'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['template_selection_active'] = [
    'label'     => ['Konfigurationsanzeige aktiv', 'Schaltet die Auswahl der Konfiguration ein/aus'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => "char(1) NOT NULL default '1'",
];

/**
 * Spezielle Felder für Ellipse (klassisch)
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_sequence'] = [
    'label'     => ['Punktreihenfolge (R)', 'Reihenfolge der Punkte'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['rgxp'=>'digit', 'tl_class'=>'w50'],
    'sql'       => "int(10) unsigned NOT NULL default 1",
];

/**
 * Spezielle Felder für Ellipse Krell
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_circle_radius'] = [
    'label'     => ['Kreisradius (R)', 'Radius des Hilfskreises'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 2",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_radius'] = [
    'label'     => ['Punkt-Radius (R1)', 'Radius der Punkte'],
    'exclude'   => true,
    'inputType' => 'number',
    'eval'      => ['tl_class'=>'w50', 'decimal'=>true],
    'sql'       => "double NOT NULL default 1",
];

/**
 * Gemeinsame Felder für Wildcard/Template
 */
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_template'] = [
    'label'     => ['Ellipsen-Template', 'Template für die Ausgabe auswählen'],
    'exclude'   => true,
    'inputType' => 'select',
    'options_callback' => function() {
        return \Contao\Controller::getTemplateGroup('ce_ellipse');
    },
    'eval'      => ['includeBlankOption'=>true, 'tl_class'=>'w50'],
    'sql'       => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_be_id'] = [
    'label'     => ['Backend-ID', 'Interne ID für die Wildcard-Anzeige'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['maxlength'=>64, 'tl_class'=>'w50'],
    'sql'       => "varchar(64) NOT NULL default 'BE_ID'",
];
