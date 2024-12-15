<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$client = new Client([
    'base_uri' => 'https://dashboard-examples.blueant.cloud/rest/',
    'timeout'  => 5.0,
]);

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['BLUE_ANT_API_TOKEN'];

// Projekte abrufen
$projectsResponse = $client->get('v1/projects', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$projectsData = json_decode($projectsResponse->getBody()->getContents(), true);

if (!isset($projectsData['projects'])) {
    echo "Keine Projekte gefunden oder Fehler beim API-Call.";
    exit;
}

$projects = $projectsData['projects'];

// Departments abrufen und Mapping aufbauen
$departmentsResponse = $client->get('v1/masterdata/departments', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$departmentsData = json_decode($departmentsResponse->getBody()->getContents(), true);

if (!isset($departmentsData['departments'])) {
    echo "Keine Departments gefunden oder Fehler beim API-Call.";
    exit;
}

$departments = $departmentsData['departments'];

// Mapping: departmentId -> departmentText
$departmentMapping = [];
foreach ($departments as $dept) {
    if (isset($dept['id'], $dept['text'])) {
        $departmentMapping[$dept['id']] = $dept['text'];
    }
}

// Hilfs-Array zum Cachen der Client-Namen
$clientNameCache = [];

// Hilfsfunktion, um den Client-Text per API abzurufen
function getClientText($client, $token, $clientId, &$cache) {
    // Prüfen, ob Name bereits im Cache ist
    if (isset($cache[$clientId])) {
        return $cache[$clientId];
    }

    // API-Call, um den Client-Text zu erhalten
    $response = $client->get('v1/masterdata/customers/' . $clientId, [
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ]
    ]);

    $data = json_decode($response->getBody()->getContents(), true);

    // Auf $data['customer']['text'] zugreifen
    if (isset($data['customer']['text'])) {
        $cache[$clientId] = $data['customer']['text'];
        return $data['customer']['text'];
    } else {
        return 'unbekannt';
    }
}

// Laufende Projekte filtern
$now = new DateTime();
$runningProjects = [];

foreach ($projects as $project) {
    if (isset($project['start'], $project['end'])) {
        $start = new DateTime($project['start']);
        $end = new DateTime($project['end']);

        if ($start <= $now && $end >= $now) {
            $runningProjects[] = $project;
        }
    }
}

// Ausgabe
echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laufende Projekte</title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
        // JavaScript für den aktiven Filter
        document.addEventListener('DOMContentLoaded', () => {
            const filterInput = document.getElementById('projectFilter');
            const projects = document.querySelectorAll('.project-card');

            filterInput.addEventListener('input', () => {
                const filterText = filterInput.value.toLowerCase();

                projects.forEach(project => {
                    const projectText = project.textContent.toLowerCase();
                    if (projectText.includes(filterText)) {
                        project.style.display = 'block';
                    } else {
                        project.style.display = 'none';
                    }
                });
            });
        });
    </script>
</head>
<body>
    <h1>Laufende Projekte</h1>
    <!-- Filter-Formular -->
    <div class="filter-container">
        <label for="projectFilter"><strong>Filter nach Projektname:</strong></label>
        <input type="text" id="projectFilter" placeholder="Projektname eingeben...">
    </div>
    <div class="projects-grid">
HTML;

foreach ($runningProjects as $project) {
    $projectName = $project['name'] ?? 'Ohne Namen';

    echo "<div class='project-card'>";
    echo "<details>";
    echo "<summary>" . htmlspecialchars($projectName) . "</summary>";
    echo "<ul>";

    foreach ($project as $key => $value) {
        if ($key === 'departmentId') {
            // Department-Name anzeigen
            $depName = $departmentMapping[$value] ?? 'unbekannt';
            echo "<li><strong>{$key}:</strong> " . htmlspecialchars((string)$value) . " (" . htmlspecialchars($depName) . ")</li>";
        } elseif ($key === 'clients' && is_array($value)) {
            // Clients ausgeben
            echo "<li><strong>clients:</strong><br><ul>";
            foreach ($value as $clientData) {
                // Prüfen, ob eine clientId vorhanden ist und eine Zahl ist
                if (isset($clientData['clientId']) && is_numeric($clientData['clientId'])) {
                    $currentClientId = $clientData['clientId'];
                    $clientText = getClientText($client, $token, $currentClientId, $clientNameCache);
                    // Client-Info ausgeben (ID und Name)
                    echo "<li>clientId: " . htmlspecialchars((string)$currentClientId) . " (" . htmlspecialchars($clientText) . "), share: " . htmlspecialchars((string)($clientData['share'] ?? '')) . "</li>";
                } else {
                    // Falls clientId nicht im erwarteten Format vorhanden ist
                    echo "<li>client: unvollständige Daten</li>";
                }
            }
            echo "</ul></li>";
        } elseif (is_array($value)) {
            // Sonstige verschachtelte Arrays
            echo "<li><strong>" . htmlspecialchars($key) . ":</strong><br><ul>";
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    echo "<li>" . htmlspecialchars($subKey) . ": <pre>" . htmlspecialchars(print_r($subValue, true)) . "</pre></li>";
                } else {
                    echo "<li>" . htmlspecialchars($subKey) . ": " . htmlspecialchars((string)$subValue) . "</li>";
                }
            }
            echo "</ul></li>";
        } else {
            echo "<li><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars((string)$value) . "</li>";
        }
    }

    echo "</ul>";
    echo "</details>";
    echo "</div>";
}

echo "</div>"; // Schließt die projects-grid Div
echo "</body></html>";
