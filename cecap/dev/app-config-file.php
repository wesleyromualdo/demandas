<?php
// LOCAL
error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT);
ini_set('display_errors', 1);

define('IS_PRODUCAO', false);
define('IS_TREINAMENTO', false);
define('EXECUTAR_AUDITORIA', false);
defined('MAXONLINETIME') || define('MAXONLINETIME', 3600);

return [
    'db_pim' => [
        'host'     =>  '10.199.201.204',
        'dbname'   => 'simec_sp',
        'user'     => 'simec',
        'password' => 'pwdsimec',
        'port'     => 5432
    ]
];
