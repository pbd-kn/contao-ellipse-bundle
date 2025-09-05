<?php
use PbdKn\ContaoEllipseBundle\Controller\ContentElement\EllipseController;

$GLOBALS['TL_DCA']['tl_content']['palettes']['ce_ellipse']
    = '{type_legend},type,headline;{ellipse_legend},ellipse_template,ellipse_major_axis,ellipse_minor_axis,ellipse_circle_radius,ellipse_point_offset,ellipse_angle_limit,ellipse_step_size;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_template'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_content']['ellipse_template'],
    'exclude' => true,
    'inputType' => 'select',
    'options_callback' => static function () {
        // Alle Templates holen, die mit 'ce_ellipse_' beginnen
        $options = \Contao\Controller::getTemplateGroup('ce_ellipse_');

        // Das gewünschte Standard-Template
        $defaultTemplate = 'ce_ellipse';

        // Prüfen, ob das Standard-Template bereits in der Liste ist
        if (!isset($options[$defaultTemplate])) {
            // Nicht vorhanden ? an den Anfang setzen und '(Standard)' ergänzen
            $options = [$defaultTemplate => $defaultTemplate . ' (Standard)'] + $options;
        } else {
            // Vorhanden ? nur '(Standard)' ergänzen
            $options[$defaultTemplate] .= ' (Standard)';
        }

        return $options;
    },
    'eval' => ['tl_class' => 'clr w50', 'includeBlankOption' => false],
    'sql' => "varchar(128) NOT NULL default ''",
];


$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_major_axis'] = [
    'label'     => ['Länge der großen Halbachse', 'z. B. 200'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'digit', 'default'=>200],
    'sql'       => "int(10) unsigned NOT NULL default 200"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_minor_axis'] = [
    'label'     => ['Länge der kleinen Halbachse', 'z. B. 150'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'digit', 'default'=>150],
    'sql'       => "int(10) unsigned NOT NULL default 150"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_circle_radius'] = [
    'label'     => ['Radius des Hilfskreises', 'z. B. 40'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'digit', 'default'=>40],
    'sql'       => "int(10) unsigned NOT NULL default 40"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_offset'] = [
    'label'     => ['Punktabstand', 'z. B. 15'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'digit', 'default'=>15],
    'sql'       => "int(10) unsigned NOT NULL default 15"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_angle_limit'] = [
    'label'     => ['Grenzwinkel (in Grad)', 'z. B. 360'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'digit', 'default'=>360],
    'sql'       => "int(10) unsigned NOT NULL default 360"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_step_size'] = [
    'label'     => ['Schrittweite (Radiant)', 'z. B. 0.05'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['tl_class' => 'w50', 'mandatory'=>true, 'rgxp'=>'numeric', 'default'=>0.05],
    'sql'       => "varchar(16) NOT NULL default '0.05'"
];
