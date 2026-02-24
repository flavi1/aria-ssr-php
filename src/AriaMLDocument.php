<?php
namespace AriaML;

class AriaMLDocument {
    
    const JSON_TOKENS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
    public $isFragment = false;
    public $expectedHtml = false;
    public $polyfillJS = 'ariaml/standalone.js';
    protected $linkSingletons = ['author', 'license'];
    protected $definition = [];
    protected $appearance = [];
    protected $consumedKeysOf = ['definition' => [], 'appearance' => []];
    
    

    /**
     * @param array $definition Contenu du JSON-LD AriaML
     */
    public function __construct(array $definition = []) {
        if(!isset($definition["@context"]))
            $definition["@context"] = ["https://schema.org", "https://ariaml.com/ns/"];
        if(!isset($definition["@type"]))
            $definition["@type"] = 'WebPage';
        
        $this->definition = $definition;
    }

    /**
     * Enregistre un style ou une ressource JSON pour un rendu différé.
     */
    public function addStyle(array $attrs = [], ?string $key = '0'): self {
		if(!isset($this->appearance[$key])) $this->appearance[$key] = [];
        $this->appearance[$key][] = $attrs;
        return $this;
    }

    /**
     * Génère le HTML head complet pour le SSR.
     */
    public function renderDefinitionInHtmlHead(): string {
        $d = $this->definition;
        $title = htmlspecialchars($d['name'] ?? '');

        $headEntries = [];
        $headEntries[] = '<meta charset="utf-8">';
        if ($title) $headEntries[] = "<title>{$title}</title>";
        
        if (!empty($d['description'])) {
            $headEntries[] = $this->tag('meta', ['name' => 'description', 'content' => $d['description']]);
        }
        
        if (!empty($d['url'])) {
            $headEntries[] = $this->tag('link', ['rel' => 'canonical', 'href' => $d['url']]);
        }

        $csrf = $d['csrfToken'] ?? $d['csrf-token'] ?? null;
        if ($csrf) {
            $headEntries[] = $this->tag('meta', ['name' => 'csrf-token', 'content' => $csrf]);
        }

        foreach ($this->linkSingletons as $key) {
            if (!empty($d[$key])) {
                $href = is_array($d[$key]) ? ($d[$key]['url'] ?? null) : $d[$key];
                if ($href) $headEntries[] = $this->tag('link', ['rel' => $key, 'href' => $href]);
            }
        }

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

        $this->buildRelationLinks($headEntries, $d['translationOfWork'] ?? null, true);
        $this->buildRelationLinks($headEntries, $d['workTranslation'] ?? null, false);

        // Réintégration des Legacy Links (Atom, RSS, alternates divers)
        if (!empty($d['legacyLinks'])) {
            foreach ($d['legacyLinks'] as $l) {
                $attrs = array_merge(['rel' => 'alternate'], $l);
                $headEntries[] = $this->tag('link', $attrs);
            }
        }

        return "\n\t" . implode("\n\t", $headEntries);
    }

	/**
	 * Parcourt les styles enregistrés et génère les balises de preload 
	 * pour les ressources externes ayant l'attribut preload.
	 */
	public function renderPreloadStyles(): string {
		$preloads = [];
		foreach ($this->appearance as $group) {
			foreach ($group as $s) {
				// Dans addStyle, l'asset est directement le tableau d'attributs
				if (isset($s['src']) && isset($s['preload'])) {
					$href = $s['src'];
					if (!isset($preloads[$href])) {
						$preloads[$href] = $this->tag('link', [
							'rel' => 'preload',
							'href' => $href,
							'as' => 'style'
						]);
					}
				}
			}
		}
		return !empty($preloads) ? "\n\t" . implode("\n\t", $preloads) : "";
	}

	/**
	 * Version finale du renderHtmlHead incluant le preload des assets critiques.
	 */
	public function renderHtmlHead(): string {
		return $this->renderInDefinitionHtmlHead() . $this->renderPreloadStyles();
	}

    public function startTag($attrs = []) {
        $lang = $this->definition['inLanguage'] ?? 'en';
        $dir  = $this->definition['direction'] ?? 'ltr';
        $wrapper = '';

        if (!$this->isFragment && $this->expectedHtml) {
            $wrapper = "<!DOCTYPE html>\n<html lang=\"{$lang}\" dir=\"{$dir}\">\n<head data-ssr>" . $this->renderHtmlHead() . "\n</head>\n<body>";
        }

        if ($this->isFragment) {
            $wrapper = '<meta http-equiv="refresh" content="0;url=./">' .
                       "\n" . '<style>aria-ml .aria-ml-fallback {display: none;}</style>' .
                       "\n" . '<div class="aria-ml-fallback">Loading...</div>';
            
            return $wrapper . "\n" . '<aria-ml-fragment' . $this->renderAttributes($attrs) . '>';
        }

        return $wrapper . "\n" . '<aria-ml' . $this->renderAttributes($attrs) . '>';
    }

    public function endTag() {
        $jsonLd = "\n<script type=\"application/ld+json\">\n" . $this->consumeDefinition() . "\n</script>";
        
        $footer = ($this->isFragment ? '</aria-ml-fragment>' : '</aria-ml>') . $jsonLd;

        if ($this->expectedHtml) {
            $footer .= "\n<script src=\"{$this->polyfillJS}\"></script>\n</body>\n</html>";
        }
        return $footer;
    }

    // --- Méthodes utilitaires (set, get, has, remove, tag, buildRelationLinks, consumeDefinition, renderAttributes) ---
    // (Identiques à vos versions précédentes avec correction du consumeDefinitionKeys[] = $k)

    public function set(string $k, $v): self {
        $keys = explode('.', $k);
        $current = &$this->definition;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) $current[$key] = [];
            $current = &$current[$key];
        }
        $current = $v;
        return $this;
    }

    private function _consume($what, $keys = null, $indent) {
		$subject = ($what == 'definition') ? $this->definition : $this->appearance;
		if($keys == null)
			$keys = array_keys($subject);
		if(is_string($keys))
			$keys = [$keys];
		
		$d = [];
		foreach($keys as $k)
			if(!in_array($k, $this->consumedKeysOf[$what]) && isset($subject[$k])) {
				$d[$k] = $subject[$k];
				$this->consumedKeysOf[$what][] = $k;
			}
		$m = 'render'.ucfirst($what, $indent);
		return $this->$m($d);
	}
	
	private function jsonEncode($arr, $tokens, $ident = 0) {
		$json = json_encode($arr, $tokens);
		if ($indent > 0) {
			$spacer = str_repeat("\t", $indent);
			$json = str_replace("\n", "\n" . $spacer, $json);
			$json = $spacer . $json;
		}
		return $json;
	}
	
	function renderDefinition($d, $indent = 0) {
		return $this->jsonEncode($d, self::JSON_TOKENS, $ident);
	}
	
    function consumeDefinition($keys = null, $indent = 0) {
		return $this->_consume('definition', $keys, $indent);
	}
	
	function renderAppearance($d, $indent = 0) {
		$output = [];
		foreach ($d as $group) {
			foreach ($group as $s) {
				$content = '';
				if (isset($s['content'])) {
					$rawContent = $s['content'];
					if (is_array($rawContent)) {
						// Indentation du JSON avec un cran supplémentaire (+1)
						$content = "\n" . $this->jsonEncode($rawContent, self::JSON_TOKENS, $indent + 1) . "\n" . str_repeat("\t", $indent);
					} else {
						$content = $rawContent;
					}
					unset($s['content']); // Point-virgule ajouté
				}

				$tag = "\n" . str_repeat("\t", $indent) . $this->tag('style', $s);
				if (!isset($s['src'])) {
					$tag .= $content;
				}
				$tag .= "</style>";
				$output[] = $tag;
			}
		}
		return implode("", $output);
	}
	
    function consumeAppearance($keys = null, $indent = 0) {
		return $this->_consume('appearance', $keys, $indent);
	}

    private function renderAttributes($attrs) {
        if (empty($attrs) || !is_array($attrs)) return '';
        $html = '';
        foreach ($attrs as $key => $value) {
            $clean_key = preg_replace('/[^a-zA-Z0-9-]/', '', $key);
            $clean_val = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
            $html .= " {$clean_key}=\"{$clean_val}\"";
        }
        return $html;
    }

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

    protected function tag(string $name, array $attrs): string {
        $html = "<{$name}";
        foreach ($attrs as $k => $v) {
            if ($v === null || $v === false) continue;
            $html .= " {$k}=\"" . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . "\"";
        }
        return $html . ">";
    }
}
