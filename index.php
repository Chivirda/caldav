<?php

require_once("./vendor/autoload.php");
require_once("./caldav.php");

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

function display($object, $name = '')
{
    echo "<b>$name</b> \n";
    echo "<pre>";
    echo(print_r($object, true));
    echo "</pre>";
    echo "<hr>";
}

// Данные для аутентификации
$username = $_ENV["USER"];
$password = $_ENV["PASSWORD"];

// URL CalDav сервера для получения всех календарей пользователя
$caldav_url = "https://mail-mo.dvinaland.ru/dav.php/calendars/$username/";


$client = new CalendarClient($caldav_url, $username, $password);
$calendars = $client->getCalendarInfo();
display($calendars, 'Calendars:');


$events = $client->getAllEvents($calendars);
display($events, 'Events:');

foreach ($events as $event) {
    foreach ($event as $key => $value) {
        $json = $client->parseEventForBitrix($value);
        display($json, 'Event:');
    }
}
