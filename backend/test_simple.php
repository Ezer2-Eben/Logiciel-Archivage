<?php

// Test simple de connexion
$url = 'http://127.0.0.1:8000/';

echo "Test de connexion au serveur Laravel...\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Code HTTP: $httpCode\n";
if ($error) {
    echo "Erreur cURL: $error\n";
} else {
    echo "Réponse reçue (premiers 200 caractères):\n";
    echo substr($response, 0, 200) . "...\n";
}

if ($httpCode === 200) {
    echo "\n✅ Le serveur Laravel fonctionne !\n";
} else {
    echo "\n❌ Le serveur ne répond pas (code: $httpCode)\n";
} 