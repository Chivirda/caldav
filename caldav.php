<?php

class CalendarClient
{
    private string $url;
    private string $username;
    private string $password;
    private array $headers = [];
    private $curl;
    private string $baseUrl;

    public function __construct($url, $username, $password)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->prepareCurl($this->url);
        $this->baseUrl = parse_url($this->url, PHP_URL_SCHEME) . '://' . parse_url($this->url, PHP_URL_HOST) . '/';
    }

    private function setHeaders(int $depth, string $type): void
    {
        $this->headers = array(
            "Depth: $depth",
            "Content-Type: $type/xml; charset=utf-8",
        );
    }

    private function prepareCurl(string $url): void
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    private function generateBitrixUid(): string
    {
        $uniqueHash = md5(uniqid(mt_rand(), true));
        return $uniqueHash . "@bitrix";
    }

    public function getCalendarInfo(): array
    {
        self::prepareCurl($this->url);
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

    public function getEvents(string $url): array
    {
        self::prepareCurl($this->baseUrl . $url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'REPORT');
        self::setHeaders(1, 'text');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array_merge($this->headers, array('Prefer: return-minimal')));
        $body = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <c:calendar-query xmlns:c="urn:ietf:params:xml:ns:caldav">
        <d:prop xmlns:d="DAV:">
            <d:getetag />
            <c:calendar-data />
        </d:prop>
        <c:filter>
            <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT" />
            </c:comp-filter>
        </c:filter>
        </c:calendar-query>
        XML;

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        $result = curl_exec($this->curl);
        if ($result === false) {
            sprintf('Curl error: %s', curl_error($this->curl));
        } else {
            // Парсим XML-ответ
            $xml = simplexml_load_string($result);
            $xml->registerXPathNamespace('d', 'DAV:');
            $xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

            // Получаем все события
            $events = $xml->xpath('//c:calendar-data');
        }
        curl_close($this->curl);
        return $events;
    }

    public function getAllEvents(array $calendars): array
    {
        $events = [];
        foreach ($calendars as $calendar) {
            array_push($events, $this->getEvents($calendar));
        }

        return $events;
    }
}
