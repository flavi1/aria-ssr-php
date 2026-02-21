<?php
require 'vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use AriaML\AriaMLDocument;
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLResponseFactory;

// 1. Création des instances PSR-7 à partir de l'environnement global
$psr17Factory = new Psr17Factory();
$creator = new ServerRequestCreator(
    $psr17Factory, // ServerRequestFactory
    $psr17Factory, // UriFactory
    $psr17Factory, // UploadedFileFactory
    $psr17Factory  // StreamFactory
);

// Obtention de la requête (ServerRequestInterface)
$request = $creator->fromGlobals();

// Création d'une réponse vide (ResponseInterface)
$response = $psr17Factory->createResponse(200);

// 2. Initialisation du Triptyque AriaML
$reqFactory = new AriaMLRequestFactory($request);
$respFactory = new AriaMLResponseFactory($response);

$doc = new AriaMLDocument([
    "name" => "Catalogue",
    "inLanguage" => "fr-FR",
    "direction" => "ltr",
]);

// 3. Synchronisation (Headers & Document State)
// On récupère la réponse PSR-7 configurée pour AriaML
$preparedResponse = $respFactory->applyTo($reqFactory, $doc);

// ---------------------------------------------------------
// À ce stade, $doc est prêt pour le rendu et $preparedResponse
// contient les bons headers (Content-Type, Status 206, etc.)
// ---------------------------------------------------------

echo $doc->startTag(['nav-base-url' => '/']);
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
        <h1>Navigation PSR-7 avec Nyholm</h1>
    </main>
<?php
echo $doc->endTag();

// Note : Dans une architecture 100% PSR-7, on utiliserait un Emitter 
// pour envoyer $preparedResponse au navigateur à la fin.
