<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$client = new Client([
    'base_uri' => 'https://dashboard-examples.blueant.cloud',
    'timeout'  => 5.0,
]);

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$token = $_ENV['BLUE_ANT_API_TOKEN'];

$response = $client->get('/rest/v1/projects', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$data = json_decode($response->getBody()->getContents(), true);

if (!isset($data['projects'])) {
    echo "Keine Projekte gefunden oder Fehler beim API-Call.";
    exit;
}

$projects = $data['projects'];
$now = new DateTime();

// Array, um alle departmentIds der laufenden Projekte zu sammeln
$departmentIds = [];

// Array für laufende Projekte
$runningProjects = [];

foreach ($projects as $project) {
    if (isset($project['start'], $project['end'])) {
        $start = new DateTime($project['start']);
        $end = new DateTime($project['end']);

        // Prüfen, ob das Projekt läuft: start <= now <= end
        if ($start <= $now && $end >= $now) {
            $runningProjects[] = $project;

            // departmentId sammeln, falls vorhanden
            if (isset($project['departmentId'])) {
                $departmentIds[] = $project['departmentId'];
            }
        }
    }
}

// Doppelte departmentIds entfernen und sortieren
$uniqueDepartmentIds = array_unique($departmentIds);
sort($uniqueDepartmentIds);

// Ausgabe ganz oben
echo "<h1>Laufende Projekte</h1>";

// Anzahl der verschiedenen DepartmentIDs und ihre Liste
echo "<h2>Es gibt " . count($uniqueDepartmentIds) . " verschiedene Department-IDs:</h2>";
echo "<ul>";
foreach ($uniqueDepartmentIds as $depId) {
    echo "<li>Department-ID: $depId</li>";
}
echo "</ul>";

// Nun die einzelnen Projekte ausgeben
foreach ($runningProjects as $project) {
    echo "<hr>";
    echo "<h2>Projekt ID: " . htmlspecialchars($project['id'] ?? '') . "</h2>";
    echo "<ul>";

    foreach ($project as $key => $value) {
        if (is_array($value)) {
            echo "<li><strong>{$key}:</strong><br><ul>";
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    echo "<li>{$subKey}: <pre>" . htmlspecialchars(print_r($subValue, true)) . "</pre></li>";
                } else {
                    echo "<li>{$subKey}: " . htmlspecialchars((string)$subValue) . "</li>";
                }
            }
            echo "</ul></li>";
        } else {
            echo "<li><strong>{$key}:</strong> " . htmlspecialchars((string)$value) . "</li>";
        }
    }

    echo "</ul>";
}
