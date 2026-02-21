<?php
namespace AriaML;

class AriaMLDocument {
    
    public $isFragment = false;
    public $expectedHtml = false;
    public $polyfillJS = 'ariaml/standalone.js';
    protected $definition = [];
    protected $consumedKeysOf = [];
    protected $linkSingletons = ['author', 'license'];

    /**
     * @param array $definition Contenu du JSON-LD AriaML
     */
    public function __construct(array $definition = []) {
		if(!isset($definition["@context"]))
			$definition["@context"] = ["https://schema.org", "https://ariaml.com/ns/"];
		if(!isset($definition["@type"]))
			$definition["@type"] = 'WebPage'
        $this->definition = $definition;
    }
    
	/**
     * Définit une valeur en utilisant la dot notation (ex: "metadatas.robots")
     */
    public function set(string $k, $v): self {
        $keys = explode('.', $k);
        $current = &$this->definition;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $v;
        return $this;
    }

    /**
     * Récupère une valeur (ex: "properties.og:site_name")
     */
    public function get(string $k, $default = null) {
        $keys = explode('.', $k);
        $current = $this->definition;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Vérifie l'existence d'une clé
     */
    public function has(string $k): bool {
        $keys = explode('.', $k);
        $current = $this->definition;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * Supprime une clé de la définition
     */
    public function remove(string $k): self {
        $keys = explode('.', $k);
        $lastKeys = array_pop($keys);
        $current = &$this->definition;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                return $this; // Chemin inexistant, rien à supprimer
            }
            $current = &$current[$key];
        }

        unset($current[$lastKeys]);
        return $this;
    }

    /**
     * Génère le HTML head
     * @return string
     */
    public function renderDefinitionHtmlHead(): string {
        $d = $this->definition;
        
        // 1. Préparation des attributs racine
        $lang = $d['inLanguage'] ?? 'en';
        $dir  = $d['direction'] ?? 'ltr';
        $title = htmlspecialchars($d['name'] ?? '');

        // 2. Génération du HEAD (SSR)
        $headEntries = [];
        $headEntries[] = '<meta charset="utf-8">';
        if ($title) $headEntries[] = "<title>{$title}</title>";
        
        if (!empty($d['description'])) {
            $headEntries[] = $this->tag('meta', ['name' => 'description', 'content' => $d['description']]);
        }
        
        if (!empty($d['url'])) {
            $headEntries[] = $this->tag('link', ['rel' => 'canonical', 'href' => $d['url']]);
        }

        // Harmonisation CSRF
        $csrf = $d['csrfToken'] ?? $d['csrf-token'] ?? null;
        if ($csrf) {
            $headEntries[] = $this->tag('meta', ['name' => 'csrf-token', 'content' => $csrf]);
        }

        // Link Singletons
        foreach ($this->linkSingletons as $key) {
            if (!empty($d[$key])) {
                $href = is_array($d[$key]) ? ($d[$key]['url'] ?? null) : $d[$key];
                if ($href) $headEntries[] = $this->tag('link', ['rel' => $key, 'href' => $href]);
            }
        }

        // Dictionnaires metadatas & properties
        if (!empty($d['metadatas'])) {
            foreach ($d['metadatas'] as $name => $val) {
                $headEntries[] = $this->tag('meta', ['name' => $name, 'content' => $val]);
            }
        }
        if (!empty($d['properties'])) {
            foreach ($d['properties'] as $prop => $val) {
                $headEntries[] = $this->tag('meta', ['property' => $prop, 'content' => $val]);
            }
        }

        // Traductions (SyncL en JS)
        $this->buildRelationLinks($headEntries, $d['translationOfWork'] ?? null, true);
        $this->buildRelationLinks($headEntries, $d['workTranslation'] ?? null, false);

        // Legacy Links
        if (!empty($d['legacyLinks'])) {
            foreach ($d['legacyLinks'] as $l) {
                $attrs = array_merge(['rel' => 'alternate'], $l);
                $headEntries[] = $this->tag('link', $attrs);
            }
        }

        return "\n\t".implode("\n\t", $headEntries);
    }
    
    function renderHtmlHead() {
		return $this->renderDefinitionHtmlHead();	// todo add appearance
	}
    
    private function renderAttributes($attrs) {
        if (empty($attrs) || !is_array($attrs)) return '';
        $html = '';
        foreach ($attrs as $key => $value) {
            $clean_key = preg_replace('/[^a-zA-Z0-9-]/', '', $key);
            $clean_val = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $html .= " {$clean_key}=\"{$clean_val}\"";
        }
        echo $html;
    }
    
    function startTag($attrs = []) {
		$wrapper = '';
		if(!$this->isFragment && $this->expectedHtml)
			$wrapper = "<!DOCTYPE html>\n<html lang="{$lang}" dir="{$dir}">\n<head data-ssr>".$this->renderHtmlHead()."\n</head>\n<body>";
		if($this->isFragment) {
			$wrapper = '<meta http-equiv="refresh" content="0;url=./">'.
				"\n".'<style>aria-ml .aria-ml-fallback {display: none;}</style>'.
				"\n".'<div class="aria-ml-fallback">Loading...</div>';	// = Html support
			
			return $wrapper.'<aria-ml-fragment'.$this->renderAttributes($attrs).'>';
		}
		return $wrapper.'<aria-ml'.$this->renderAttributes($attrs).'>';
	}
	
    function endTag() {
		$wrapper = '';
		if($this->expectedHtml)
			$wrapper = "\n".'<script src="'.$this->polyfillJS.'">'."</script>\n</body>\n</html>";
		if($this->isFragment)
			return '</aria-ml-fragment>'.$wrapper;
		return '</aria-ml>'.$wrapper;
	}
    
    private function _consume($what, $keys = null) {
		$subject = ($what == 'definition') ? $this->definition : $this->appearance;
		if($keys == null)
			$keys = array_keys($subject);

		$d = [];
		foreach($keys as $k)
			if(!in_array($k, $this->consumedKeysOf[$what]) && isset($subject[$k])) {
				$d[$k] = $subject[$k];
				$this->consumedKeysOf[$what] = $k;
			}
		return json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
	
    function consumeDefinition($keys = null) {
		return $this->_consume('definition', $keys);
	}
	
    function consumeAppearance($keys = null) {
		return $this->_consume('appearance', $keys);
	}

    /**
     * Helper pour construire les liens de traduction
     */
    protected function buildRelationLinks(&$entries, $list, $isOriginal) {
        if (!$list) return;
        $items = is_array($list) && isset($list[0]) ? $list : [$list];
        foreach ($items as $t) {
            if (empty($t['url'])) continue;
            $attrs = ['rel' => 'alternate', 'href' => $t['url']];
            if (!empty($t['inLanguage'])) $attrs['hreflang'] = $t['inLanguage'];
            if ($isOriginal) $attrs['class'] = 'translationOfWork';
            $entries[] = $this->tag('link', $attrs);
        }
    }

    /**
     * Helper pour générer une balise HTML propre
     */
    protected function tag(string $name, array $attrs): string {
        $html = "<{$name}";
        foreach ($attrs as $k => $v) {
            if ($v === null || $v === false) continue;
            $html .= " {$k}=\"" . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . "\"";
        }
        return $html . ">";
    }
}
