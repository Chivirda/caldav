<?php

class CalendarClient
{
    private string $url;
    private string $username;
    private string $password;
    private array $headers = [];
    private $curl;

    public function __construct($url, $username, $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->prepareCurl();
    }

    private function setHeaders(int $depth, string $type): void
    {
        $this->headers = array(
            "Depth: $depth",
            "Content-Type: $type/xml; charset=utf-8",
        );
    }

    private function prepareCurl(): void
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_URL, $this->url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    public function getCalendarInfo(): array
    {
        self::prepareCurl();
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
        self::setHeaders(1, 'text');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array_merge($this->headers, array('Prefer: return-minimal')));

        $body = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
        <d:prop>
            <d:displayname />
            <d:resourcetype />
            <cs:getctag />
        </d:prop>
        </d:propfind>
        XML;

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($this->curl);
        
        if ($result === false) {
            sprintf('Curl error: %s', curl_error($this->curl));
        } else {
            $calendarsData = [];
            // Парсим XML-ответ
            $xml = simplexml_load_string($result);
            $xml->registerXPathNamespace('d', 'DAV:');
            $xml->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');

            // Получаем все календарные ресурсы
            $calendars = $xml->xpath('//d:response');

            // Выводим информацию о каждом календаре
            foreach ($calendars as $calendar) {
                $displayname = $calendar->xpath('.//d:displayname');
                $resourcetype = $calendar->xpath('.//d:resourcetype/d:collection');
                $href = $calendar->xpath('.//d:href');

                if (!empty($resourcetype)) {
                    $name = !empty($displayname) ? (string)$displayname[0] : 'Без названия';
                    $xmlId = !empty($href) ? (string)$href[0] : 'Нет xmlId';
                    if (strlen($xmlId) > 47) {
                        
                        $calendarsData[$name] = $xmlId;
                    }
                }
            }
        }
        curl_close($this->curl);
        return $calendarsData;
    }

    public function getEvents(): string
    {
        self::prepareCurl();
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'REPORT');
        self::setHeaders(1, 'application/xml');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array_merge($this->headers, array('Prefer: return-minimal')));
        $body = <<<XML
        <c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <d:getetag />
                <c:calendar-data />
            </d:prop>
            <c:filter>
                <c:comp-filter name="VCALENDAR" />
            </c:filter>
        </c:calendar-query>
        XML;

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($this->curl);
        if ($result === false) {
            sprintf('Curl error: %s', curl_error($this->curl));
        } else {
            sprintf('Curl response: %s', $result);
        }
        curl_close($this->curl);
        return $result;
    }
}

// function display($object, $name = '')
// {
//     echo "<b>$name</b> \n";
//     echo "<pre>";
//     echo (print_r($object, true));
//     echo "</pre>";
//     echo "<hr>";
// }

// $calendarUrl = "https://mail-mo.dvinaland.ru/dav.php/calendars/chivirda.si@ict29.ru/ec7342315ea0ea7eb7c6ae6a0422137bcaf52f3f-cb76-4f7d-a227-11808eb2f3c9";

// $username = "chivirda.si@ict29.ru";
// $password = "E6n3HMqBcr";

// $curl = curl_init();
// curl_setopt($curl, CURLOPT_URL, $calendarUrl);
// curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
// curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
// curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'REPORT');

// $headers = array(
//     "Depth: 1",
//     "Prefer: return-minimal",
//     "Content-Type: application/xml; charset=utf-8"
// );

// $body = <<<XML
// <c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
//     <d:prop>
//         <d:getetag />
//         <c:calendar-data />
//     </d:prop>
//     <c:filter>
//         <c:comp-filter name="VCALENDAR" />
//     </c:filter>
// </c:calendar-query>
// XML;

// curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
// curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
// $result = curl_exec($curl);

// if ($result === false) {
//     sprintf('Curl error: %s', curl_error($curl));
// } else {
//     sprintf('Curl response: %s', $result);
// }
// curl_close($curl);

// display($result, "Items:");
