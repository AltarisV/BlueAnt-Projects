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

// Departments abrufen
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

// Caches
$clientNameCache = [];
$statusCache = [];
$runningProjects = [];

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

    if (isset($data['customer']['text'])) {
        $cache[$clientId] = $data['customer']['text'];
        return $data['customer']['text'];
    } else {
        return 'unbekannt';
    }
}

function getPersonName($client, $token, $personId, &$cache) {
    if (isset($cache[$personId])) {
        return $cache[$personId];
    }

    try {
        $response = $client->get('v1/human/persons/' . $personId, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        // Vorname und Nachname auslesen und speichern
        if (isset($data['person']['firstname'], $data['person']['lastname'])) {
            $fullname = $data['person']['firstname'] . ' ' . $data['person']['lastname'];
            $cache[$personId] = $fullname;
            return $fullname;
        }
    } catch (Exception $e) {
        return 'unbekannt';
    }

    return 'unbekannt';
}

function getStatusDetails($client, $token, $statusId, &$cache) {
    // Prüfen, ob die Daten bereits im Cache vorhanden sind
    if (isset($cache[$statusId])) {
        return $cache[$statusId];
    }

    try {
        // API-Call für den spezifischen Status
        $response = $client->get('v1/masterdata/projects/statuses/' . $statusId, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (isset($data['projectStatus']['text'], $data['projectStatus']['noteWhenStatusChanges'])) {
            $cache[$statusId] = [
                'text' => $data['projectStatus']['text'],
                'note' => $data['projectStatus']['noteWhenStatusChanges']
            ];
            return $cache[$statusId];
        }
    } catch (Exception $e) {
        // Fehlerfall
        return ['text' => 'unbekannt', 'note' => 'Keine zusätzlichen Informationen verfügbar'];
    }

    return ['text' => 'unbekannt', 'note' => 'Keine zusätzlichen Informationen verfügbar'];
}

// API-Aufruf: Alle Währungen abrufen und Mapping aufbauen
$currenciesResponse = $client->get('v1/masterdata/currencies', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$currenciesData = json_decode($currenciesResponse->getBody()->getContents(), true);

if (!isset($currenciesData['currencies'])) {
    echo "Keine Währungen gefunden oder Fehler beim API-Call.";
    exit;
}

$currencies = $currenciesData['currencies'];

// Mapping: currencyId -> currencyText
$currencyMapping = [];
foreach ($currencies as $currency) {
    if (isset($currency['id'], $currency['name'])) {
        $currencyMapping[$currency['id']] = $currency['name'];
    }
}

// API-Aufruf: Alle Projekttypen abrufen und Mapping aufbauen
$typesResponse = $client->get('v1/masterdata/projects/types', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$typesData = json_decode($typesResponse->getBody()->getContents(), true);

if (!isset($typesData['types'])) {
    echo "Keine Projekttypen gefunden oder Fehler beim API-Call.";
    exit;
}

// Mapping: typeId -> shortDescription
$typeMapping = [];
foreach ($typesData['types'] as $type) {
    if (isset($type['id'], $type['shortDescription'])) {
        $typeMapping[$type['id']] = $type['shortDescription'];
    }
}

$prioritiesResponse = $client->get('v1/masterdata/projects/priorities', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$prioritiesData = json_decode($prioritiesResponse->getBody()->getContents(), true);

if (!isset($prioritiesData['priorities'])) {
    echo "Keine Prioritäten gefunden oder Fehler beim API-Call.";
    exit;
}

// Mapping: priorityId -> priorityText
$priorityMapping = [];
foreach ($prioritiesData['priorities'] as $priority) {
    if (isset($priority['id'], $priority['text'])) {
        $priorityMapping[$priority['id']] = $priority['text'];
    }
}

$customFieldsResponse = $client->get('v1/masterdata/customfield/definitions/Project', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ]
]);

$customFieldsData = json_decode($customFieldsResponse->getBody()->getContents(), true);

if (!isset($customFieldsData['customFields'])) {
    echo "Keine Custom Fields gefunden oder Fehler beim API-Call.";
    exit;
}

$customFieldMapping = [];
foreach ($customFieldsData['customFields'] as $field) {
    if (isset($field['id'], $field['name'])) {
        $customFieldMapping[$field['id']] = [
            'name' => $field['name'],
            'options' => $field['options'] ?? [] // Optionen speichern, falls vorhanden
        ];
    }
}


$fieldOrder = [
    'name' => 'Projektname',
    'number' => 'Projektnummer',
    'departmentId' => 'Unternehmensbereich',
    'typeId' => 'Projektart',
    'statusId' => 'Projektstatus',
    'projectLeaderId' => 'Projektleiter',
    'clients' => 'Kunden',
    'priorityId' => 'Priorität',
    'costCentreNumber' => 'Kostenstelle',
    'planningType' => 'Planungsart',
    //'billingType' => 'Abrechnungsart',
    'customFields' => 'Custom Fields',
    'start' => 'Startdatum',
    'end' => 'Enddatum'
];


$now = new DateTime();

// Laufende Projekte filtern
foreach ($projects as $project) {
    if (isset($project['start'], $project['end'])) {
        $start = new DateTime($project['start']);
        $end = new DateTime($project['end']);

        if ($start <= $now && $end >= $now) {
            $runningProjects[] = $project;
        }
    }
}

echo <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laufende Projekte</title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
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

    foreach ($fieldOrder as $key => $label) {
        if (!isset($project[$key])) {
            continue; // Überspringen, falls das Feld nicht vorhanden ist
        }

        $value = $project[$key];

        if ($key === 'departmentId') {
            $depName = $departmentMapping[$value] ?? 'unbekannt';
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($depName) . "</li>";
        } elseif ($key === 'typeId') {
            $typeName = $typeMapping[$value] ?? 'unbekannt';
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($typeName) . "</li>";
        } elseif ($key === 'statusId') {
            $statusDetails = getStatusDetails($client, $token, $value, $statusCache);
            $statusText = $statusDetails['text'];
            $noteWhenStatusChanges = $statusDetails['note'];

            // Status und Info-Feld anzeigen
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($statusText) . "</li>";
            // echo "<li style='font-size: 0.9em; color: gray;'>
            //  <em>Info:</em> " . htmlspecialchars($noteWhenStatusChanges) . "
            // </li>";
        } elseif ($key === 'projectLeaderId') {
            $leaderName = getPersonName($client, $token, $value, $personNameCache);
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($leaderName) . "</li>";
        } elseif ($key === 'clients' && is_array($value)) {
            echo "<li><strong>{$label}:</strong><ul>";
            foreach ($value as $clientData) {
                if (isset($clientData['clientId'])) {
                    $currentClientId = $clientData['clientId'];
                    $clientText = getClientText($client, $token, $currentClientId, $clientNameCache);
                    echo "<li>" . htmlspecialchars($clientText) . " (Anteil: " . htmlspecialchars((string)($clientData['share'] ?? '')) . "%)</li>";
                }
            }
            echo "</ul></li>";
        } elseif ($key === 'priorityId') {
            $priorityText = $priorityMapping[$value] ?? 'unbekannt';
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($priorityText) . "</li>";
        } elseif ($key === 'customFields' && is_array($value)) {
            $customFieldOrder = [
                'Vertraulichkeit',
                'Klassifikation',
                'Strategiebeitrag'
            ];

            echo "<li><strong>Zusätzliche Informationen:</strong><br><ul>";

            foreach ($customFieldOrder as $fieldName) {
                $found = false;

                foreach ($value as $fieldId => $fieldValue) {
                    $actualFieldName = $customFieldMapping[$fieldId]['name'] ?? '';

                    if ($actualFieldName === $fieldName) {
                        // Optionen prüfen und auflösen
                        $options = $customFieldMapping[$fieldId]['options'] ?? [];
                        $resolvedValue = $fieldValue;

                        if (!empty($options)) {
                            foreach ($options as $option) {
                                if (isset($option['key'], $option['value']) && $option['key'] == $fieldValue) {
                                    $resolvedValue = $option['value'];
                                    break;
                                }
                            }
                        }

                        if (!empty(trim(strip_tags($resolvedValue)))) {
                            echo "<li><strong>" . htmlspecialchars($fieldName) . ":</strong> " . nl2br($resolvedValue) . "</li>";
                        }
                        $found = true;
                        break;
                    }
                }
            }

            echo "</ul></li>";
        } elseif ($key === 'start' || $key === 'end') {
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars($value) . "</li>";
        } else {
            echo "<li><strong>{$label}:</strong> " . htmlspecialchars((string)$value) . "</li>";
        }
    }

    echo "</ul>";
    echo "</details>";
    echo "</div>";
}

echo "</div>";
echo "</body></html>";
