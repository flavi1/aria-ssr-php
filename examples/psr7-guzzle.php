<?php
require 'vendor/autoload.php';

use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Response;
use AriaML\AriaMLDocument;
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLResponseFactory;

// 1. Capture de la requête entrante via Guzzle
// Cette méthode statique parse $_SERVER, $_GET, $_POST, $_COOKIE et $_FILES
$request = ServerRequest::fromGlobals();

// 2. Création d'une réponse de base
$response = new Response(200);

// 3. Initialisation du moteur AriaML
$reqFactory = new AriaMLRequestFactory($request);
$doc = new AriaMLDocument([
    "name" => "Interface Guzzle PSR-7",
    "inLanguage" => "fr-FR",
    "direction" => "ltr",
    "url" => (string)$request->getUri()
]);

// 4. Synchronisation via la Response Factory
$respFactory = new AriaMLResponseFactory($response);
$preparedResponse = $respFactory->applyTo($reqFactory, $doc);

// --- Début du rendu ---

// On applique les headers calculés par AriaML au flux de sortie PHP
// Si on n'utilise pas d'émetteur PSR-7, on extrait les infos manuellement :
http_response_code($preparedResponse->getStatusCode());
foreach ($preparedResponse->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header(sprintf('%s: %s', $name, $value), false);
    }
}

echo $doc->startTag();
?>

    <script type="application/ld+json" nav-slot="dynamic-definition">
        <?= $doc->consumeDefinition(['name', 'inLanguage', 'direction']);	//dynamique, actualisé à chaque changement de contexte ?>
    </script>
	<?php if (!$reqFactory->isFragment()): ?>
    <script type="application/ld+json">
        <?= $doc->consumeDefinition(); // tout le reste ?>
    </script>
	<?php endif; ?>

    <main nav-slot="content">
        <h1>Propulsé par Guzzle PSR-7</h1>
        <p>Le mode fragment est : <?= $doc->isFragment ? 'ACTIF (206)' : 'INACTIF (200)' ?></p>
    </main>
<?php
echo $doc->endTag();
