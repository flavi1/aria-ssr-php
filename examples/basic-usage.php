<?php

require_once __DIR__ . '/../src/AriaMLRequestFactory.php';
require_once __DIR__ . '/../src/AriaMLResponseFactory.php';
require_once __DIR__ . '/../src/AriaMLDocument.php';
/* à remplacer par ../vendor/autoload.php dans vos projets */

use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLResponseFactory;
use AriaML\AriaMLDocument;

// 1. Les objets de base
$reqFactory = new AriaMLRequestFactory(); 
$doc = new AriaMLDocument([
    "name" => "Page Produit",
    "inLanguage" => "fr-FR",
    "direction" => "ltr",
    "csrfToken" => "token-123",
    "url" => "https://monsite.com/chaussures",
]);

// Ajout de métadonnées OpenGraph (properties) et classiques (metadatas)
$doc->set('properties', ['og:type' => 'product', 'og:image' => '/assets/shoes.jpg']);
$doc->set('metadatas', ['robots' => 'index, follow']);


/* STATIC */
// Ajout de ressources persistante avec le nouveau système de PRELOAD
$doc->addStyle([
    'src' => '/css/style.css',
    'preload' => true, // Sera rendu par $this->renderPreloadStyles()
], 'persistant');

// Ajout de ressources thématiques
$doc->addStyle([
    'src' => '/css/dark.css',
    'theme' => 'dark',
    'preload' => true,
    'media-theme' => '(prefers-color-scheme: dark)'
], 'themes');
$doc->addStyle([
    'src' => '/css/light.css',
    'theme' => 'light',
    'preload' => true,
    'media-theme' => '(prefers-color-scheme: light)'
], 'themes');


/* DYNAMIC */
$doc->addStyle([
    'src' => '/css/icons.json',
    'type' => 'icons+json',
    'preload' => true
], 'icons');

$doc->addStyle([
	'content' => 'h1 {color: red;}'	// hors groupe
]);

// 2. Synchronisation globale (Headers + Document state)
$respFactory = new AriaMLResponseFactory();
$respFactory->applyTo($reqFactory, $doc);

// 3. Rendu (Le document sait maintenant s'il doit être un fragment ou non)
echo $doc->startTag(['nav-base-url' => '/']); 
?>
	
	<script type="application/ld+json" nav-slot="dynamic-def">
		<?= $doc->consumeDefinition(['name', 'inLanguage', 'direction', 'csrfToken']);	//dynamique, actualisé à chaque changement de contexte ?>
	</script>
    
    <?php if (!$reqFactory->isFragment()): ?>
    <g id="static">
		<script type="application/ld+json"><?= $doc->consumeDefinition(); // tout le reste ?></script>
		<?= $doc->consumeAppearance(['persistant', 'themes']); ?>
	</g>
	<?php endif; ?>

	<g nav-slot="dynamic-styles">
		<?= $doc->consumeAppearance();	//tout le reste : icons + hors groupe ?>
    </g>

    <main nav-slot="content">
        <?php if ($reqFactory->clientHasCache('main-view')): ?>
            <div nav-cache="main-view"></div>
        <?php else: ?>
            <div nav-cache="main-view">
                <h1>Hello Word!</h1>
                <p>This is my first AriaML Document</p>
            </div>
        <?php endif; ?>
    </main>

<?php
echo $doc->endTag(); 
?>
