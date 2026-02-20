<?php
namespace AriaML;
use Psr\Http\Message\ResponseInterface;

class AriaMLResponseFactory {
    
    protected $psrResponse = null;

    public function __construct($response = null) {
        if ($response instanceof ResponseInterface) {
            $this->psrResponse = $response;
        }
    }

    /**
     * Prépare les headers et synchronise l'état du document
     * à partir des données de la requête.
     */
	public function applyTo(AriaMLRequestFactory $request, AriaMLDocument $doc): ?ResponseInterface {
        $doc->isFragment = $request->isFragment();
        $doc->expectedHtml = $request->expectsHtmlWrapper();

        $status = $request->expectedStatus();
        $contentType = $request->expectedContentType() . '; charset=utf-8';

        // Logique de cache
        $headers = [
            'Content-Type' => $contentType,
            // Dit au navigateur : "Le cache dépend de ces headers clients"
            'Vary' => 'Accept, X-AriaML-Fragment, nav-cache',
            // Pour les fragments, on évite un cache persistant qui polluerait l'historique
            'Cache-Control' => $request->isFragment() ? 'no-cache, no-store, must-revalidate' : 'public, max-age=0'
        ];

        if ($this->psrResponse) {
            $resp = $this->psrResponse->withStatus($status);
            foreach ($headers as $name => $value) {
                $resp = $resp->withHeader($name, $value);
            }
            return $resp;
        }

        // Fallback natif
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        return null;
    }
}
