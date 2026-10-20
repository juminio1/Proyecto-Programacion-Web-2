<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
use app\Configuration;

session_start();
require_once("app/Configuration.php");

$configuration = new Configuration();
$router = $configuration->getRouter();

$router->dispatch(
    Request::get("controller", "inicio"),
    Request::get("method", "show")
);