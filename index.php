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
    ],
    'query' => [
        'includeMemoFields' => 'true'
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
    'subjectMemo' => 'Projektgegenstand',
    'objectiveMemo' => 'Projektziel',
    // 'clients' => 'Kunden',
    'priorityId' => 'Priorität',
    //'costCentreNumber' => 'Kostenstelle',
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
        const filterType = document.getElementById('filterType');
        const projects = document.querySelectorAll('.project-card');

        // Vorschläge für Projektart, Projektstatus und Unternehmensbereich
        const suggestions = {
            'type': Array.from(new Set([...projects].map(p => p.dataset.type).filter(Boolean))),
            'status': Array.from(new Set([...projects].map(p => p.dataset.status).filter(Boolean))),
            'department': Array.from(new Set([...projects].map(p => p.dataset.department).filter(Boolean)))
        };

        const updateSuggestions = () => {
            const selectedType = filterType.value;
            const dataList = document.getElementById('filterSuggestions');
            dataList.innerHTML = '';

            if (['type', 'status', 'department'].includes(selectedType)) {
                suggestions[selectedType].forEach(item => {
                    const option = document.createElement('option');
                    option.value = item;
                    dataList.appendChild(option);
                });
            }
        };

        // Vorschläge beim Klicken ins Eingabefeld aktualisieren
        filterInput.addEventListener('focus', updateSuggestions);
        
            // Filter leeren, wenn der Filtertyp geändert wird
        filterType.addEventListener('change', () => {
            filterInput.value = '';
            updateSuggestions();
            projects.forEach(project => project.style.display = 'block');
        });

        // Vorschläge beim Tippen aktualisieren
        filterInput.addEventListener('input', (e) => {
            const filterText = e.target.value.toLowerCase();
            const selectedType = filterType.value;

            if (['type', 'status', 'department'].includes(selectedType)) {
                const dataList = document.getElementById('filterSuggestions');
                dataList.innerHTML = '';

                suggestions[selectedType].forEach(item => {
                    if (item.toLowerCase().includes(filterText)) {
                        const option = document.createElement('option');
                        option.value = item;
                        dataList.appendChild(option);
                    }
                });
            }

            // Filter anwenden
            projects.forEach(project => {
                const attributeValue = project.dataset[selectedType] || '';
                if (attributeValue.toLowerCase().includes(filterText)) {
                    project.style.display = 'block';
                } else {
                    project.style.display = 'none';
                }
            });
        });
    });
    </script>
    <style>
        details summary {
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
        }
        details summary span {
            font-size: 0.9rem;
            font-weight: normal;
            color: gray;
            margin-left: 10px;
        }
    </style>;
</head>
<body>
    <h1>Laufende Projekte</h1>
    <div class="filter-container">
        <label for="projectFilter"><strong>Filter nach:</strong></label>
        <select id="filterType">
            <option value="name">Projektname</option>
            <option value="number">Projektnummer</option>
            <option value="department">Unternehmensbereich</option>
            <option value="type">Projektart</option>
            <option value="status">Projektstatus</option>
            <option value="leader">Projektleiter</option>
        </select>
        <input type="text" id="projectFilter" placeholder="Filter eingeben..." list="filterSuggestions">
        <datalist id="filterSuggestions"></datalist>
    </div>
HTML;

echo "<table class='project-table'>
        <thead>
            <tr>
                <th>Details</th>
                <th>Projektname</th>
                <th>Projektleiter</th>
                <th>Status</th>
                <th>Laufzeit</th>
                <th>Projektstart</th>
                <th>Bereich</th>
                <th>Listbox</th>
                <th>Text</th>
            </tr>
        </thead>
        <tbody>";

foreach ($runningProjects as $project) {
    $projectName = $project['name'] ?? 'Ohne Namen';
    $projectNumber = $project['number'] ?? '';
    $department = $departmentMapping[$project['departmentId']] ?? 'unbekannt';
    $type = $typeMapping[$project['typeId']] ?? 'unbekannt';
    $statusDetails = getStatusDetails($client, $token, $project['statusId'], $statusCache);
    $status = $statusDetails['text'] ?? 'unbekannt';
    $leader = getPersonName($client, $token, $project['projectLeaderId'], $personNameCache);
    $subjectMemo = $project['subjectMemo'] ?? '';
    $objectiveMemo = $project['objectiveMemo'] ?? '';
    $projectLink = "https://dashboard-examples.blueant.cloud/psap?project=" . urlencode($projectNumber);

    $endDate = isset($project['end']) ? new DateTime($project['end']) : null;
    $interval = $endDate ? $now->diff($endDate) : null;
    $remainingTime = $interval
        ? ($interval->invert ? 'Abgeschlossen' : $interval->format('%a Tage verbleibend'))
        : 'Kein Enddatum';

    echo "<tr class='project-row'
            data-name='" . htmlspecialchars($projectName) . "'
            data-number='" . htmlspecialchars($projectNumber) . "'
            data-department='" . htmlspecialchars($department) . "'
            data-type='" . htmlspecialchars($type) . "'
            data-status='" . htmlspecialchars($status) . "'
            data-leader='" . htmlspecialchars($leader) . "'>";

    echo "<td><a href='" . htmlspecialchars($projectLink) . "' target='_blank'>Details</a></td>";
    echo "<td>" . htmlspecialchars($projectName) . "</td>";
    echo "<td>" . htmlspecialchars($leader) . "</td>";
    echo "<td>" . htmlspecialchars($status) . "</td>";
    echo "<td>" . htmlspecialchars($remainingTime) . "</td>";
    echo "<td>" . htmlspecialchars($project['start'] ?? 'Kein Startdatum') . "</td>";
    echo "<td>" . htmlspecialchars($department) . "</td>";

    // Listbox Spalte (z.B. für die Clients)
    echo "<td>";
    if (isset($project['clients']) && is_array($project['clients'])) {
        echo "<ul>";
        foreach ($project['clients'] as $clientData) {
            if (isset($clientData['clientId'])) {
                $currentClientId = $clientData['clientId'];
                $clientText = getClientText($client, $token, $currentClientId, $clientNameCache);
                echo "<li>" . htmlspecialchars($clientText) . " (Anteil: " . htmlspecialchars((string)($clientData['share'] ?? '')) . "%)</li>";
            }
        }
        echo "</ul>";
    }
    echo "</td>";

    // Text-Spalte (z.B. für Memo-Informationen)
    echo "<td>";
    if (!empty($subjectMemo)) {
        echo "<strong>Gegenstand:</strong> " . nl2br(htmlspecialchars($subjectMemo)) . "<br>";
    }
    if (!empty($objectiveMemo)) {
        echo "<strong>Objektiv:</strong> " . nl2br(htmlspecialchars($objectiveMemo)) . "<br>";
    }
    echo "</td>";

    echo "</tr>";
}

echo "</tbody></table>";

echo <<<JS
<script>
document.addEventListener("DOMContentLoaded", () => {
    const filterType = document.getElementById("filterType");
    const projectFilter = document.getElementById("projectFilter");
    const projectRows = document.querySelectorAll(".project-row");

    projectFilter.addEventListener("input", () => {
        const filterValue = projectFilter.value.toLowerCase();
        const filterKey = filterType.value; // JavaScript-Variable, nicht PHP!

        projectRows.forEach(row => {
            const attributeValue = row.getAttribute(`data-\${filterKey}`)?.toLowerCase() || "";
            if (attributeValue.includes(filterValue)) {
                row.style.display = ""; // Zeige die Zeile, wenn sie dem Filter entspricht
            } else {
                row.style.display = "none"; // Verstecke die Zeile, wenn sie nicht passt
            }
        });
    });
});
</script>
JS;
