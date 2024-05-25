<?php

class CalendarClient
{
    private string $url;
    private string $username;
    private string $password;
    private array $headers = [
        "Depth: 1",
        "Content-Type: text/xml; charset=utf-8",
    ];
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

    /**
     * Initializes a cURL session with the given URL and sets the necessary options for authentication.
     *
     * @param string $url The URL to connect to.
     * @throws None
     * @return void
     */
    private function prepareCurl(string $url): void
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => $this->headers,
        ]);
    }

    /**
     * Returns information about all calendars available to the user
     *
     * @return array An associative array with calendar names as keys and their URLs as values
     */
    public function getCalendarInfo(): array
    {
        $this->prepareCurl($this->url);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PROPFIND');

        $calendarQuery = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
        <d:prop>
            <d:displayname />
            <d:resourcetype />
            <cs:getctag />
        </d:prop>
        </d:propfind>
        XML;

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $calendarQuery);
        $result = curl_exec($this->curl);
        curl_close($this->curl);

        $xml = simplexml_load_string($result);
        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('cs', 'http://calendarserver.org/ns/');

        $calendars = $xml->xpath('//d:response');

        $calendarsData = [];
        foreach ($calendars as $calendar) {
            $calendarName = (string)$calendar->xpath('.//d:displayname')[0] ?? 'Без названия';
            $calendarUrl = (string)$calendar->xpath('.//d:href')[0] ?? '';
            if (strlen($calendarUrl) > 47) {
                $calendarsData[$calendarName] = $calendarUrl;
            }
        }

        curl_close($this->curl);
        
        return $calendarsData;
    }

    /**
     * Returns all events in a given calendar
     *
     * @param string $calendarUrl URL of the calendar to query
     * @return array An array of SimpleXMLElement objects containing the event data
     */
    public function getEvents(string $calendarUrl): array
    {
        $this->prepareCurl($this->baseUrl . $calendarUrl);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'REPORT');

        $calendarQuery = <<<XML
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

        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $calendarQuery);
        $result = curl_exec($this->curl);
        $xml = simplexml_load_string($result);
        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('c', 'urn:ietf:params:xml:ns:caldav');

        curl_close($this->curl);

        return $xml->xpath('//c:calendar-data');
    }

    public function getAllEvents(array $calendars): array
    {
        $events = [];
        foreach ($calendars as $calendar) {
            array_push($events, $this->getEvents($calendar));
        }

        return $events;
    }

    /**
     * Parses an iCalendar event and extracts relevant information for Bitrix.
     *
     * @param string $event The iCalendar event to parse.
     * @return array An associative array containing the extracted information:
     *               - 'host': The host of the event (extracted from the ORGANIZER field).
     *               - 'from': The start date and time of the event (extracted from the DTSTART field).
     *               - 'to': The end date and time of the event (extracted from the DTEND field).
     *               - 'name': The summary of the event (extracted from the SUMMARY field).
     *               - 'description': The description of the event (extracted from the DESCRIPTION field).
     * @throws None
     */
    public function parseEventForBitrix($event): array
    {
        if (preg_match('/BEGIN:VEVENT((?:(?!END:VEVENT).)*?)END:VEVENT/s', $event, $matches)) {
            $eventData = $matches[1];

            $event = [
                'host' => $this->extractCNValue($eventData, 'ORGANIZER'),
                'from' => $this->extractDateValue($eventData, 'DTSTART'),
                'to' => $this->extractDateValue($eventData, 'DTEND'),
                'name' => $this->extractValue($eventData, 'SUMMARY'),
                'description' => $this->extractValue($eventData, 'DESCRIPTION'),
            ];

            return $event;
        }

        return [];
    }

    private function extractValue(string $eventData, string $tagName): ?string
    {
        if (preg_match('/' . $tagName . ':(.*?)(?:\r?\n|$)/s', $eventData, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private function extractDateValue(string $eventData, string $tagName): ?string
    {
        if (preg_match('/' . $tagName . '[^:]*:(.*?)(?:\r?\n|$)/s', $eventData, $matches)) {
            $dateTime = trim($matches[1]);
            // Форматирование даты и времени
            $formattedDateTime = date('d.m.Y H:i:s', strtotime($dateTime));
            return $formattedDateTime;
        }
        return null;
    }

    private function extractCNValue(string $eventData, string $tagName): ?string
    {
        if (preg_match('/' . $tagName . '[^:]*;CN=([^;:]*)(?:;|:)/', $eventData, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
}
