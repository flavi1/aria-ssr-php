<?php
namespace AriaML;
use Psr\Http\Message\ServerRequestInterface;

class AriaMLRequestFactory {
    
    protected $headers = [];
    protected $clientNavCache = [];

    /**
     * Le constructeur accepte soit une instance PSR-7, soit rien (fallback global)
     */
    public function __construct($request = null) {
        if ($request instanceof ServerRequestInterface) {
            $this->parsePsr7($request);
        } else {
            $this->parseGlobals();
        }

        // Extraction du cache de navigation
        if (isset($this->headers['nav-cache'])) {
            $decoded = json_decode($this->headers['nav-cache'], true);
            $this->clientNavCache = is_array($decoded) ? $decoded : [];
        }
    }

    /**
     * Extraction depuis une interface PSR-7 (Symfony, Laravel, Slim, etc.)
     */
    protected function parsePsr7(ServerRequestInterface $request): void {
        foreach ($request->getHeaders() as $name => $values) {
            // PSR-7 retourne les headers sous forme de tableaux
            $this->headers[strtolower($name)] = implode(', ', $values);
        }
    }

    /**
     * Extraction depuis l'environnement classique (Apache/Nginx/CGI)
     */
    protected function parseGlobals(): void {
        if (function_exists('getallheaders')) {
            $this->headers = array_change_key_case(getallheaders(), CASE_LOWER);
        } else {
            // Fallback manuel via $_SERVER si getallheaders n'est pas dispo
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with($name, 'HTTP_')) {
                    $key = str_replace('_', '-', strtolower(substr($name, 5)));
                    $this->headers[$key] = $value;
                }
            }
        }
    }
    
	public function expectedContentType(): string {
        $forceHtml = ($this->headers['ariaml-force-html'] ?? '') === 'true';
        
        if ($this->isFragment()) {
            // Si on force le HTML pour l'extension, même le fragment passe en text/html
            return $forceHtml ? 'text/html' : 'text/aria-ml-fragment';
        }

        if ($this->expectsHtmlWrapper()) {
            return 'text/html';
        }

        // Cas d'un document AriaML natif (sans wrapper HTML)
        return $forceHtml ? 'text/html' : 'text/aria-ml';
    }
    
	public function expectedStatus(): int {
        return $this->isFragment() ? 206 : 200;
    }

    public function isFragment(): bool {
        $accept = $this->headers['accept'] ?? '';
        $fragmentTypes = ['text/aria-ml-fragment', 'application/aria-xml-fragment'];
        
        foreach ($fragmentTypes as $type) {
            if (str_starts_with($accept, $type)) return true;
        }

        return isset($this->headers['x-ariaml-fragment']);
    }

    public function expectsHtmlWrapper(): bool {
        if ($this->isFragment()) return false;

        $accept = $this->headers['accept'] ?? '';
        if (str_contains($accept, 'text/aria-ml') || str_contains($accept, 'application/aria-xml')) {
            return false;
        }

        return true;
    }

    public function clientHasCache(string $cacheKey): bool {
        return in_array($cacheKey, $this->clientNavCache);
    }

}
