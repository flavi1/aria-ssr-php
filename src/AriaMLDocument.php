<?php
namespace AriaML;

class AriaMLDocument {
    
    public $isFragment = false;
    public $expectedHtml = false;
    public $polyfillJS = 'ariaml/standalone.js';
    protected $definition = [];
    protected $consumedDefinitionKeys = [];
    protected $linkSingletons = ['author', 'license'];
    
    protected $styles = []; 
    protected $groupThemes = [];

    /**
     * @param array $definition Contenu du JSON-LD AriaML
     */
    public function __construct(array $definition = []) {
        if(!isset($definition["@context"]))
            $definition["@context"] = ["[https://schema.org](https://schema.org)", "[https://ariaml.com/ns/](https://ariaml.com/ns/)"];
        if(!isset($definition["@type"]))
            $definition["@type"] = 'WebPage';
        
        $this->definition = $definition;
    }

    /**
     * Associe un thème par défaut à un groupId (nav-slot).
     */
    public function setThemeGroup(string $groupId, string $themeName): self {
        $this->groupThemes[$groupId] = $themeName;
        return $this;
    }

    /**
     * Enregistre un style ou une ressource JSON pour un rendu différé.
     */
    public function addStyle($content, array $attrs = [], ?string $groupId = null): self {
        $this->styles[] = [
            'content' => $content,
            'attrs'   => $attrs,
            'group'   => $groupId
        ];
        return $this;
    }

    /**
     * Rend les styles filtrés par groupe, enveloppés dans un <g>.
     */
    public function renderStyles(?string $groupId = null): string {
        $output = [];
        $filtered = array_filter($this->styles, fn($s) => $s['group'] === $groupId);
        
        if (empty($filtered) && !$groupId) return "";

        foreach ($filtered as $s) {
            $content = $s['content'];
            if (is_array($content)) {
                $content = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $tag = "\n" . $this->tag('style', $s['attrs']);
            if (!isset($s['attrs']['src'])) {
                $tag .= $content;
            }
            $tag .= "</style>";
            $output[] = $tag;
        }

        $html = implode("", $output);
        
        if ($groupId) {
            $attrs = ['nav-slot' => $groupId];
            if (isset($this->groupThemes[$groupId])) {
                $attrs['theme'] = $this->groupThemes[$groupId];
            }
            return "\n" . $this->tag('g', $attrs) . $html . "\n</g>";
        }

        return $html;
    }

    /**
     * Génère le HTML head complet pour le SSR.
     */
    public function renderDefinitionHtmlHead(): string {
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
		foreach ($this->styles as $s) {
			if (isset($s['attrs']['src']) && isset($s['attrs']['preload'])) {
				$href = $s['attrs']['src'];
				// On évite les doublons de preload dans le head
				if (!isset($preloads[$href])) {
					$preloads[$href] = $this->tag('link', [
						'rel' => 'preload',
						'href' => $href,
						'as' => 'style'
					]);
				}
			}
		}
		return !empty($preloads) ? "\n\t" . implode("\n\t", $preloads) : "";
	}

	/**
	 * Version finale du renderHtmlHead incluant le preload des assets critiques.
	 */
	public function renderHtmlHead(): string {
		return $this->renderDefinitionHtmlHead() . $this->renderPreloadStyles();
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

    private function consumeDefinition($keys = null) {
        if($keys == null) $keys = array_keys($this->definition);
        $d = [];
        foreach($keys as $k) {
            if(!in_array($k, $this->consumedDefinitionKeys) && isset($this->definition[$k])) {
                $d[$k] = $this->definition[$k];
                $this->consumedDefinitionKeys[] = $k;
            }
        }
        return json_encode($d, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
