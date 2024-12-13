<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://dashboard-examples.blueant.cloud',
    'timeout'  => 5.0,
]);

$token = 'BLUE_ANT_TOKEN_HIER'; // Hier API-Token

$response = $client->get('/v1/projects', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$data = json_decode($response->getBody()->getContents(), true);

// Überprüfen, ob 'projects' im Response ist
if (!isset($data['projects'])) {
    echo "Keine Projekte gefunden oder Fehler beim API-Call.";
    exit;
}

$projects = $data['projects'];

$now = new DateTime();

echo "<h1>Laufende Projekte</h1>";
echo "<ul>";

foreach ($projects as $project) {
    if (isset($project['start'], $project['end'])) {
        $start = new DateTime($project['start']);
        $end = new DateTime($project['end']);

        // Überprüfen, ob das Projekt aktuell läuft:
        // start <= now <= end
        if ($start <= $now && $end >= $now) {
            $name = htmlspecialchars($project['name'] ?? 'Unbenannt');

            // weitere details
            $number = htmlspecialchars($project['number'] ?? '');
            $statusId = htmlspecialchars($project['statusId'] ?? '');

            echo "<li>";
            echo "<strong>Projektname:</strong> $name<br>";
            echo "<strong>Nummer:</strong> $number<br>";
            echo "<strong>Status-ID:</strong> $statusId<br>";
            echo "<strong>Start:</strong> " . $start->format('Y-m-d') . "<br>";
            echo "<strong>Ende:</strong> " . $end->format('Y-m-d') . "<br>";
            echo "</li>";
        }
    }
}

echo "</ul>";
