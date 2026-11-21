<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

use app\Configuration;
session_start();
require_once("app/Configuration.php");

date_default_timezone_set('America/Argentina/Buenos_Aires');
$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    Request::get("controller", "inicio"),
    Request::get("method", "show")
);