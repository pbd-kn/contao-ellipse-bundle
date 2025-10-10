<?php

$GLOBALS['TL_DCA']['tl_ellipse_save'] = [
    'config' => [
        'dataContainer'    => 'Table',
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary'
            ],
        ],
    ],

    'fields' => [
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'title' => [
            'label' => ['Titel', 'Bezeichnung der gespeicherten Konfiguration'],
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
        'parameters' => [
            'label' => ['Parameter', 'Gespeicherte Parameter (JSON)'],
            'inputType' => 'textarea',
            'eval' => ['style' => 'height:60px', 'readonly' => true],
            'sql' => "text NULL"
        ],
        'createdAt' => [
            'sql' => "int(10) unsigned NULL"
        ],
        'createdBy' => [
            'sql' => "varchar(255) NULL"
        ],
    ],
];
