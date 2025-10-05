<?php

$GLOBALS['TL_DCA']['tl_ellipse_save'] = [
    'config' => [
        'dataContainer' => 'Table',
        'sql' => ['keys' => ['id' => 'primary']],
    ],
    'list' => [
        'sorting' => [
            'mode' => 2,
            'fields' => ['tstamp DESC'],
            'flag' => 6,
        ],
        'label' => [
            'fields' => ['ceType', 'info', 'tstamp'],
            'format' => '%s – %s (am %s)',
        ],
    ],
    'palettes' => [
        'default' => '{data_legend},info,saveData',
    ],
    'fields' => [
        'id' => ['sql' => "int(10) unsigned NOT NULL auto_increment"],
        'tstamp' => ['sql' => "int(10) unsigned NOT NULL default 0"],
        'memberId' => [
            'label' => ['Mitglied', 'ID des eingeloggten Mitglieds'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'ceType' => [
            'label' => ['Content-Element Typ', 'z. B. ce_ellipse_krell'],
            'sql' => "varchar(64) NOT NULL default ''"
        ],
        'ceId' => [
            'label' => ['CE-ID', 'ID des Content-Elements'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],
        'saveData' => [
            'label' => ['Gespeicherte Daten', 'Alle aktuellen Parameter als JSON'],
            'inputType' => 'textarea',
            'eval' => ['readonly'=>true, 'style'=>'min-height:200px;'],
            'sql' => "longtext NULL"
        ],
        'info' => [
            'label' => ['Info', 'Erklärende Information'],
            'inputType' => 'text',
            'eval' => ['maxlength'=>255],
            'sql' => "varchar(255) NOT NULL default ''"
        ],
    ],
];
