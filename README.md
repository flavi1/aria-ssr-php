# AriaML SSR Polyfill for PHP (aria-ssr-php)
**PHP SSR Implementation of Aria Markup Language**


This is the official PHP reference implementation for the **AriaML SSR Polyfill**. It provides the necessary orchestration to serve fluid AriaML documents while maintaining full SEO and backward compatibility with standard HTML clients.

---

## 1. Installation

### Via Composer (Packagist)
Once the package is published on Packagist, run:
```bash
composer require ariaml/aria-ssr-php
```

### Via GitHub Directly
To use this library before it is published on Packagist, add the repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-username/aria-ssr-php"
        }
    ],
    "require": {
        "ariaml/aria-ssr-php": "dev-main"
    }
}
```

---

## 2. Conceptual Architecture

The AriaML SSR implementation relies on three decoupled pillars:

1.  **Request Factory**: Analyzes client intent (Headers) to determine if the server should render a full document or a fragment.
2.  **AriaML Document**: Structures semantic data (JSON-LD) and manages the transport markup (`<aria-ml>` vs `<aria-ml-fragment>`).
3.  **Response Factory**: Synchronizes the document state and ensures proper HTTP headers are sent.



---

## 3. Communication Protocol (Headers)

The server reacts specifically to the following headers:

| Header | Value / Type | Role |
| :--- | :--- | :--- |
| `Accept` | `text/aria-ml-fragment` | Highest priority: requests a native fragment rendering. |
| `nav-cache` | `JSON Array` | List of DOM keys already present in the client's cache. |
| `ariaml-force-html` | `boolean` | If true, forces `text/html` Content-Type (Web Extensions). |
| `Vary` (Response) | `Accept, nav-cache` | Allow browser and CDN cache isolation. |

---

## 4. Deep Restoration Algorithm

To save bandwidth, the server performs a **Deep Restoration** check for every cached component:

1.  Retrieve the `cacheKey` of the component.
2.  Check if `cacheKey` exists in the `nav-cache` header array.
3.  **If present**: Send an empty shell `<div nav-cache="key"></div>`.
4.  **If absent**: Send the full content `<div nav-cache="key">...content...</div>`.

*AriaML will detect the empty element and automatically re-inject the live DOM node stored locally in the client's NodeCache.*

---

## 5. Usage Example

### Basic PHP implementation
```php
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLDocument;
use AriaML\AriaMLResponseFactory;

// 1. Initialize factories
$reqFactory = new AriaMLRequestFactory();
$doc = new AriaMLDocument([
    "name" => "Product Page",
    "inLanguage" => "en-US",
    "direction" => "ltr"
]);

// 2. Synchronize HTTP state
$respFactory = new AriaMLResponseFactory();
$respFactory->applyTo($reqFactory, $doc);

// 3. Render stream
echo $doc->startTag();
?>

    <script type="application/ld+json" nav-slot="dynamic-definition">
        <?= $doc->consumeDefinition(['name', 'inLanguage']); ?>
    </script>
	<?php if (!$reqFactory->isFragment()): ?>
    <script type="application/ld+json">
        <?= $doc->consumeDefinition(); // tout le reste ?>
    </script>
	<?php endif; ?>

    <main nav-slot="content">
        <?php if ($reqFactory->clientHasCache('main-view')): ?>
            <div nav-cache="main-view"></div>
        <?php else: ?>
            <div nav-cache="main-view">
                <h1>Optimized Content</h1>
            </div>
        <?php endif; ?>
    </main>

<?php
echo $doc->endTag();
```

---

## 6. Security and Integrity

The `nav-base-url` attribute on the root element `aria-ml` defines the trusted zone. Any navigation exiting this origin unloads the AriaML context to return to a classic navigation mode. This ensures that the fragment's slot structure and the document remain consistent, and prevents an external domain from taking control of the document.

---

## License
Distributed under the MIT License. See `LICENSE` for more information.

---

# [VERSION FR] AriaML SSR Polyfill pour PHP (aria-ssr-php)
**Implémentation PHP SSR de l'Aria Markup Language**

Ceci est l'implémentation de référence officielle en PHP du **Polyfill SSR AriaML**. Elle fournit l'orchestration nécessaire pour servir des documents AriaML fluides tout en maintenant une indexation SEO complète et une rétrocompatibilité totale avec les clients HTML standards.

---

## 1. Installation

### Via Composer (Packagist)
Une fois le paquet publié sur Packagist, exécutez :
```bash
composer require ariaml/aria-ssr-php
```

### Via GitHub directement
Pour utiliser cette bibliothèque avant sa publication sur Packagist, ajoutez le dépôt au fichier `composer.json` de votre projet :

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/votre-nom-utilisateur/aria-ssr-php"
        }
    ],
    "require": {
        "ariaml/aria-ssr-php": "dev-main"
    }
}
```

---

## 2. Architecture Conceptuelle

L'implémentation SSR d'AriaML repose sur trois piliers découplés :

1. **Request Factory** : Analyse l'intention du client (Headers) pour déterminer si le serveur doit générer un document complet ou un fragment.
2. **AriaML Document** : Structure les données sémantiques (JSON-LD) et gère le balisage de transport (`<aria-ml>` vs `<aria-ml-fragment>`).
3. **Response Factory** : Synchronise l'état du document et garantit l'envoi des en-têtes HTTP appropriés.

---

## 3. Protocole de Communication (Headers)

Le serveur réagit spécifiquement aux en-têtes suivants :

| Header | Valeur / Type | Rôle |
| :--- | :--- | :--- |
| `Accept` | `text/aria-ml-fragment` | Priorité maximale : demande un rendu natif de fragment. |
| `nav-cache` | `JSON Array` | Liste des clés DOM déjà présentes dans le cache du client. |
| `ariaml-force-html` | `boolean` | Si vrai, force le Content-Type `text/html` (Extensions Web). |
| `Vary` (Réponse) | `Accept, nav-cache` | Permet l'isolation du cache navigateur et CDN. |

---

## 4. Algorithme de Restauration Profonde (Deep Restoration)

Pour économiser de la bande passante, le serveur effectue une vérification de **Restauration Profonde** pour chaque composant mis en cache :

1. Récupérer la `cacheKey` du composant.
2. Vérifier si `cacheKey` existe dans le tableau de l'en-tête `nav-cache`.
3. **Si présente** : Envoyer une "coquille" vide `<div nav-cache="key"></div>`.
4. **Si absente** : Envoyer le contenu complet `<div nav-cache="key">...contenu...</div>`.

*AriaML détectera l'élément vide et réinjectera automatiquement le nœud DOM vivant stocké localement dans le NodeCache du client.*

---

## 5. Exemple d'Utilisation

### Implémentation PHP de base
```php
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLDocument;
use AriaML\AriaMLResponseFactory;

// 1. Initialisation des factories
$reqFactory = new AriaMLRequestFactory();
$doc = new AriaMLDocument([
    "name" => "Page Produit",
    "inLanguage" => "fr-FR",
    "direction" => "ltr"
]);

// 2. Synchronisation de l'état HTTP
$respFactory = new AriaMLResponseFactory();
$respFactory->applyTo($reqFactory, $doc);

// 3. Rendu du flux
echo $doc->startTag();
?>

    <script type="application/ld+json" nav-slot="dynamic-definition">
        <?= $doc->consumeDefinition(['name', 'inLanguage']); ?>
    </script>
	<?php if (!$reqFactory->isFragment()): ?>
    <script type="application/ld+json">
        <?= $doc->consumeDefinition(); // tout le reste ?>
    </script>
	<?php endif; ?>

    <main nav-slot="content">
        <?php if ($reqFactory->clientHasCache('main-view')): ?>
            <div nav-cache="main-view"></div>
        <?php else: ?>
            <div nav-cache="main-view">
                <h1>Contenu Optimisé</h1>
            </div>
        <?php endif; ?>
    </main>

<?php
echo $doc->endTag();
```

---

## 6. Sécurité et Intégrité

L'attribut `nav-base-url` sur l'élément racine `aria-ml` définit la zone de confiance. Toute navigation sortant de cette origine décharge le contexte AriaML pour revenir à un mode de navigation classique. Cela garantit que la structure des slots du fragment et du document reste cohérente, et empêche un domaine externe de prendre le contrôle du document.

---

## Licence
Distribué sous licence MIT. Voir le fichier `LICENSE` pour plus d'informations.
