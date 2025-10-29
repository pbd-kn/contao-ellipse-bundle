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
        'pid' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'typ' => [                                // type des eintrags ce_ellipse oder ce_ellipse_krell
            'sql' => "varchar(255) NULL"
        ],
        'title' => [
            'sql' => "varchar(255) NOT NULL default 'Ohne Titel'"
        ],
        'erstellDatum' => [
            'sql' => "int(10) unsigned NULL"
        ],
        'ersteller' => [
            'sql' => "varchar(255) NULL"
        ],
        'parameters' => [
            'label' => ['Parameter', 'Gespeicherte Parameter (JSON)'],
            'inputType' => 'textarea',
            'eval' => ['style' => 'height:60px', 'readonly' => true],
            'sql' => "text NULL"
        ],
    ],
];
