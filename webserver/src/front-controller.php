<?php

$uri = $_SERVER['REQUEST_URI'];

switch ($uri) {
    case '/help':
        include 'help.php';
        break;
    case '/calendar':
        include 'calendar.php';
        break;
    default:
        //include 'notfound.php';
        echo "404 not found \n";
        break;
}