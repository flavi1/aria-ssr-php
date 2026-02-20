Pour appliquer cette logique à **toutes** les réponses de manière transversale dans CakePHP 5, la solution la plus élégante et performante est de passer par un **Middleware**.

Le middleware va intercepter la requête avant qu'elle n'atteigne les contrôleurs, initialiser le triptyque AriaML, et s'assurer que la réponse finale porte les bons headers et le bon code statut, quel que soit le contrôleur qui a généré le contenu.

### 1. Création du Middleware AriaML

Créez le fichier `src/Middleware/AriaMLMiddleware.php` :

```php
<?php
namespace App\Middleware;

use AriaML\AriaMLDocument;
use AriaML\AriaMLRequestFactory;
use AriaML\AriaMLResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AriaMLMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Initialisation de la Request Factory
        $reqFactory = new AriaMLRequestFactory($request);

        // 2. Initialisation d'un document "vide" ou par défaut
        // Il pourra être récupéré ou écrasé dans les contrôleurs via l'attribut de requête
        $doc = new AriaMLDocument();

        // On injecte les instances dans les attributs de la requête pour les retrouver dans les contrôleurs
        $request = $request->withAttribute('ariaml_req_factory', $reqFactory);
        $request = $request->withAttribute('ariaml_doc', $doc);

        // 3. Poursuite du cycle de vie (exécution du contrôleur)
        $response = $handler->handle($request);

        // 4. Synchronisation finale de la réponse via la Response Factory
        $respFactory = new AriaMLResponseFactory($response);
        
        // On récupère le document potentiellement modifié par le contrôleur
        $finalDoc = $request->getAttribute('ariaml_doc');
        
        // On applique la logique AriaML (Headers, Status 206, Content-Type)
        return $respFactory->applyTo($reqFactory, $finalDoc);
    }
}

```

### 2. Enregistrement dans `Application.php`

Pour que ce middleware s'applique à toutes les routes, ajoutez-le dans la méthode `middleware` de votre fichier `src/Application.php` :

```php
public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue
        // ... autres middlewares (routing, etc.)
        ->add(new \App\Middleware\AriaMLMiddleware());

    return $middlewareQueue;
}

```

### 3. Utilisation simplifiée dans vos Contrôleurs

Désormais, vos contrôleurs n'ont plus à se soucier des headers ou de la configuration. Ils se contentent de récupérer le document et la factory :

```php
public function index()
{
    // Récupération des instances pré-configurées par le middleware
    $doc = $this->getRequest()->getAttribute('ariaml_doc');
    $reqFactory = $this->getRequest()->getAttribute('ariaml_req_factory');

    // On peuple le document avec les données de la page
    $doc->set('name', 'Ma Page CakePHP');
    $doc->set('csrfToken', $this->getRequest()->getAttribute('csrfToken'));

    // Le rendu peut se faire ici ou être passé à une vue
    $this->set(compact('doc', 'reqFactory'));
}

```

### Pourquoi c'est la meilleure approche :

* **Centralisation** : La logique du code **206 Partial Content** et du header **Vary** est gérée en un seul endroit. Si vous changez le protocole AriaML, vous ne modifiez que le middleware.
* **Compatibilité Web Extension** : Le header `ariaml-force-html` est détecté par le middleware pour toutes les pages du site, garantissant que l'extension voit toujours le bon `Content-Type`.
* **Transparence** : Même si un contrôleur renvoie une erreur (404, 500), le middleware passera dessus. Si l'erreur est demandée en mode fragment, AriaMLResponseFactory s'assurera que le code 206 (ou l'erreur correspondante) est correctement formaté.

