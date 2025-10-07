<?php

$GLOBALS['TL_DCA']['tl_ellipse_save'] = [
    // =====================================================
    // Konfiguration
    // =====================================================
    'config' => [
        'dataContainer'    => 'Table',
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'member_id' => 'index',
                'ce_id' => 'index'
            ]
        ],
    ],

    // =====================================================
    // Listenansicht im Backend
    // =====================================================
    'list' => [
        'sorting' => [
            'mode'   => 2, // nach tstamp sortiert
            'fields' => ['tstamp DESC'],
            'flag'   => 6,
        ],
        'label' => [
            'fields' => ['ce_type', 'info', 'tstamp'],
            'format' => '%s – %s (am %s)',
        ],
    ],

    // =====================================================
    // Paletten (Backend-Formular)
    // =====================================================
    'palettes' => [
        '__selector__' => [],
        'default' => '{data_legend},member_id,ce_type,ce_id,info,save_data',
    ],

    // =====================================================
    // Felderdefinition
    // =====================================================
    'fields' => [

        // ID und Zeitstempel
        'id' => [
            'sql' => "int(10) unsigned NOT NULL auto_increment"
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        // Benutzerreferenz (Frontend User)
        'member_id' => [
            'label' => ['Mitglied', 'ID des eingeloggten Mitglieds'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly'=>true, 'tl_class'=>'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        // Typ des Content Elements (z. B. ce_ellipse oder ce_ellipse_krell)
        'ce_type' => [
            'label' => ['Content-Element-Typ', 'z. B. ce_ellipse_krell'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly'=>true, 'tl_class'=>'w50'],
            'sql' => "varchar(64) NOT NULL default ''"
        ],

        // Referenz auf das CE (tl_content.id)
        'ce_id' => [
            'label' => ['Content-Element-ID', 'ID des Content-Elements'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['readonly'=>true, 'tl_class'=>'w50'],
            'sql' => "int(10) unsigned NOT NULL default 0"
        ],

        // Beschreibung durch den Benutzer
        'info' => [
            'label' => ['Info', 'Erklärende Information zur gespeicherten Darstellung'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength'=>255, 'tl_class'=>'clr w100'],
            'sql' => "varchar(255) NOT NULL default ''"
        ],

        // Gespeicherte JSON-Daten (z. B. A, B, R, lineColor …)
        'save_data' => [
            'label' => ['Gespeicherte Daten', 'Alle Parameter als JSON'],
            'exclude' => true,
            'inputType' => 'textarea',
            'eval' => ['readonly'=>true, 'style'=>'min-height:200px;', 'tl_class'=>'clr'],
            'sql' => "longtext NULL"
        ],
    ],
];
