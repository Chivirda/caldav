<?php
require_once("./vendor/autoload.php");
require_once("./caldav.php");

use CalendarClient;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

function display($object, $name = '') {
    echo "<b>$name</b> \n";
    echo "<pre>";
    echo(print_r($object, true));
    echo "</pre>";
    echo "<hr>";
}

// URL CalDav сервера для получения всех календарей пользователя
$caldav_url = 'https://mail-mo.dvinaland.ru/dav.php/calendars/chivirda.si@ict29.ru/';

// Данные для аутентификации
$username = $_ENV["USER"];
$password = $_ENV["PASSWORD"];

$client = new CalendarClient($caldav_url, $username, $password);
$calendars = $client->getCalendarInfo();
display($calendars, 'Calendars:');

$events = $client->getEvents();
display($events, 'Events:');
