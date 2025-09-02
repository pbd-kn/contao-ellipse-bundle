<?php

$GLOBALS['TL_DCA']['tl_content']['palettes']['ellipse_element']
    = '{type_legend},type,headline;{ellipse_legend},ellipse_major_axis,ellipse_minor_axis,ellipse_circle_radius,ellipse_point_offset,ellipse_angle_limit,ellipse_step_size;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_major_axis'] = [
    'label'     => ['Länge der großen Halbachse', 'z. B. 200'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'digit'],
    'sql'       => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_minor_axis'] = [
    'label'     => ['Länge der kleinen Halbachse', 'z. B. 120'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'digit'],
    'sql'       => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_circle_radius'] = [
    'label'     => ['Radius des Hilfskreises', 'z. B. 40'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'digit'],
    'sql'       => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_point_offset'] = [
    'label'     => ['Punktabstand', 'z. B. 15'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'digit'],
    'sql'       => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_angle_limit'] = [
    'label'     => ['Grenzwinkel (in Grad)', 'z. B. 360'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'digit'],
    'sql'       => "int(10) unsigned NOT NULL default 0"
];

$GLOBALS['TL_DCA']['tl_content']['fields']['ellipse_step_size'] = [
    'label'     => ['Schrittweite (Radiant)', 'z. B. 0.05'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => ['mandatory'=>true, 'rgxp'=>'numeric'],
    'sql'       => "varchar(16) NOT NULL default ''"
];
