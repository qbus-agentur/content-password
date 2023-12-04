<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Password protection for content',
    'description' => '',
    'category' => 'plugin',
    'author' => 'Benjamin Franzke',
    'author_email' => 'bfr@qbus.de',
    'state' => 'beta',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '0.3.1',
    'constraints' => array(
        'depends' => array(
            'typo3' => '10.4.0-10.4.99',
            'gridelements' => '10.0.0-10.99.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
);
