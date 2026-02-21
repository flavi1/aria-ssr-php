<?php
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLResponseFactory;

// 1. Les objets de base
$reqFactory = new AriaMLRequestFactory(); 
$doc = new AriaMLDocument([
    "name" => "Page Produit",
    "inLanguage" => "fr-FR",
    "direction" => "ltr",
    "url" => "https://monsite.com/chaussures",
    "csrfToken" => "token-123"
]);

// Ajout de métadonnées OpenGraph (properties) et classiques (metadatas)
$doc->set('properties', ['og:type' => 'product', 'og:image' => '/assets/shoes.jpg']);
$doc->set('metadatas', ['robots' => 'index, follow']);

// Ajout de ressources thématiques avec le nouveau système de PRELOAD
$doc->addStyle(null, [
    'src' => '/assets/themes/dark-mode.css',
    'theme' => 'dark',
    'preload' => true, // Sera rendu par $this->renderPreloadStyles()
    'media' => '(prefers-color-scheme: dark)'
], 'appearance');

$doc->addStyle(null, [
    'src' => '/assets/icons/main-pack.icons+json',
    'type' => 'icons+json',
    'preload' => true
], 'appearance');

// 2. Synchronisation globale (Headers + Document state)
$respFactory = new AriaMLResponseFactory();
$respFactory->applyTo($reqFactory, $doc);

// 3. Rendu (Le document sait maintenant s'il doit être un fragment ou non)
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

	<?= $doc->renderStyles(); ?>

    <main nav-slot="content">
        <?php if ($reqFactory->clientHasCache('main-view')): ?>
            <div nav-cache="main-view"></div>
        <?php else: ?>
            <div nav-cache="main-view">
                <h1>Contenu optimisé</h1>
            </div>
        <?php endif; ?>
    </main>

<?php 
echo $doc->endTag(); 
?>
