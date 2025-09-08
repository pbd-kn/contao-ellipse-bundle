<?php

use Contao\Controller;

$GLOBALS['TL_DCA']['tl_content']['palettes']['__selector__'][] = 'ellipse_line_mode';

$GLOBALS['TL_DCA']['tl_content']['palettes']['ce_ellipse']
    = '{type_legend},type,headline,ellipse_be_id;'
    . '{ellipse_legend},ellipse_template,template_selection_active,'
    . 'ellipse_major_axis,ellipse_minor_axis,ellipse_point_sequence,'
    . 'ellipse_point_offset,ellipse_angle_limit,ellipse_step_size,'
    . 'ellipse_line_thickness,ellipse_line_mode;'
    . '{expert_legend:hide},cssID;'
    . '{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['ellipse_line_mode_fixed']
    = 'ellipse_line_color';

$GLOBALS['TL_DCA']['tl_content']['subpalettes']['ellipse_line_mode_cycle']
    = 'ellipse_cycle_color1,ellipse_cycle_color2,ellipse_cycle_color3,ellipse_cycle_color4,ellipse_cycle_color5,ellipse_cycle_color6';

// ============================================================================
// Felder
// ============================================================================
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_be_id'] = [     //  dient Identifikation in der BE-Liste
    'label'     => ['Identifikation', 'dient Identifikation in der BE-Liste'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','mandatory'=>true,'default'=>'BE_ID'],
    'sql'  => "varchar(128) NOT NULL default 'BE_ID'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['ellipse_template'],
    'exclude' => true,
    'inputType' => 'select',
'options_callback' => static function () {
    $options = \Contao\Controller::getTemplateGroup('ce_ellipse_');

    // Keys normalisieren auf Kleinbuchstaben
    $normalized = [];
    foreach ($options as $k => $v) {
        $normalized[strtolower($k)] = $v;
    }

    $defaultTemplate = 'ce_ellipse';
    if (!isset($normalized[$defaultTemplate])) {
        $normalized = [$defaultTemplate => $defaultTemplate . ' (Standard)'] + $normalized;
    } else {
        $normalized[$defaultTemplate] .= ' (Standard)';
    }

    return $normalized;
},

    'eval' => ['tl_class' => 'clr w50', 'includeBlankOption' => false],
    'sql'  => "varchar(128) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['template_selection_active'] = [
    'label'     => ['Konfiguration über Template zulassen', 'GET-Parameter dürfen Werte überschreiben.'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'w50 clr', 'isBoolean' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

// Zahlenfelder
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_major_axis'] = [
    'label'     => ['Große Halbachse (A)', 'z. B. 400'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','mandatory'=>true,'rgxp'=>'digit','default'=>400],
    'sql'       => "int(10) unsigned NOT NULL default 400",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_minor_axis'] = [
    'label'     => ['Kleine Halbachse (B)', 'z. B. 200'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','mandatory'=>true,'rgxp'=>'digit','default'=>200],
    'sql'       => "int(10) unsigned NOT NULL default 200",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_angle_limit'] = [
    'label'     => ['Grenzwinkel (G)', 'z. B. 1 = eine Umdrehung, 360=360 Grad '],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','mandatory'=>true,'rgxp'=>'digit','default'=>1],
    'sql'       => "int(10) unsigned NOT NULL default 1",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_sequence'] = [
    'label'     => ['Reihenfolge Punkte (R)', 'z. B. 20'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','mandatory'=>true,'rgxp'=>'digit','default'=>20],
    'sql'       => "int(10) unsigned NOT NULL default 20",
];


$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_step_size'] = [
    'label'     => ['Schrittweite', 'z. B. 0.05'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'tl_class'=>'w50 clr',
        'mandatory'=>true,
        'rgxp'=>'custom',
        'customRgxp'=>'/^\d+(?:[.,]\d+)?$/',
        'default'=>0.05,
    ],
    'sql'       => "double NOT NULL default 0.05",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_thickness'] = [
    'label'     => ['Linienstärke', 'z. B. 0.2'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => [
        'tl_class'   => 'w50',
        'mandatory'  => true,
        'rgxp'       => 'custom',
        'customRgxp' => '/^\d+(?:[.,]\d+)?$/', // erlaubt 0.2 oder 0,2
        'default'    => 0.2,
    ],
    'sql'       => "double NOT NULL default 0.2",
];


// Linienmodus
$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_mode'] = [
    'label'     => ['Linienmodus', 'Fest oder zyklische Farben'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => ['fixed', 'cycle'],
    'reference' => [
        'fixed' => 'Feste Farbe',
        'cycle' => 'Zyklische Farben',
    ],
    'eval'      => [
        'tl_class'=>'w50',
        'mandatory'=>true,
        'includeBlankOption'=>false,
        'submitOnChange'=>true,
    ],
    'sql'       => "varchar(16) NOT NULL default 'fixed'",
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_line_color'] = [
    'label'     => ['Linienfarbe (fest)', 'z. B. red oder #ff0000'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class'=>'w50','maxlength'=>64,'default'=>'red'],
    'sql'       => "varchar(64) NOT NULL default 'red'",
];

// Zyklische Farben
for ($i = 1; $i <= 6; $i++) {
    $GLOBALS['TL_DCA']['tl_content']['fields']["ellipse_cycle_color{$i}"] = [
        'label'     => ["Farbe {$i}", "Farbwert für Position {$i} (z. B. red oder #ff0000)"],
        'exclude'   => true,
        'inputType' => 'text',
        'eval'      => ['tl_class'=>'w50','maxlength'=>64],
        'sql'       => "varchar(64) NOT NULL default ''",
    ];
}
