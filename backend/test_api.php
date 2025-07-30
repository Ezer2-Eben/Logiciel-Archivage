<?php

// Test simple de l'API des catégories
$url = 'http://127.0.0.1:8000/api/categories';

// Test sans authentification d'abord
echo "Test de l'API des catégories...\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Code HTTP: $httpCode\n";
echo "Réponse:\n$response\n";

if ($httpCode === 200) {
    echo "\n✅ L'API fonctionne !\n";
} else {
    echo "\n❌ L'API ne fonctionne pas (code: $httpCode)\n";
} 