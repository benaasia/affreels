<?php
error_reporting(0);
header('Content-Type: application/json');
require_once 'remote_api_helper.php';

$res = smartCheckAPIStatus();
echo json_encode($res);
