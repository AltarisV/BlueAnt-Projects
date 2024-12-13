<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://dashboard-examples.blueant.cloud',
    'timeout'  => 5.0,
]);

$token = 'BLUE_ANT_TOKEN_HIER'; // Hier Token rein

$response = $client->get('/v1/projects', [
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token
    ],
    // 'query' => [
    //    'status' => 'running' // BeispielFilter
    // ]
]);

$projects = json_decode($response->getBody()->getContents(), true);

echo "<h1>Laufende Projekte</h1>";
echo "<ul>";
foreach ($projects as $project) {
    echo "<li>" . htmlspecialchars($project['name'] ?? 'Unbenannt') . "</li>";
}
echo "</ul>";
