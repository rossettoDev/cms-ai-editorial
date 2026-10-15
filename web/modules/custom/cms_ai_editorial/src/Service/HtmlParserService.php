<?php

namespace Drupal\cms_ai_editorial\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Serviço para parsear HTML e extrair título e texto relevante.
 */
class HtmlParserService {

  /**
   * O logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Tamanho máximo do texto extraído (50KB).
   */
  const MAX_EXTRACTED_TEXT_LENGTH = 51200;

  /**
   * Construtor.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('cms_ai_editorial');
  }

  /**
   * Extrai título e texto relevante de HTML.
   *
   * @param string $html
   *   O conteúdo HTML.
   *
   * @return array
   *   Array com as chaves:
   *   - 'success': bool indicando sucesso
   *   - 'title': string com o título (se sucesso)
   *   - 'text': string com o texto extraído (se sucesso)
   *   - 'error': string com a mensagem de erro (se falha)
   */
  public function extractContent($html) {
    if (empty($html)) {
      return [
        'success' => FALSE,
        'error' => 'HTML vazio.',
      ];
    }

    // Criar DOMDocument com tratamento de erros
    libxml_use_internal_errors(TRUE);
    $dom = new \DOMDocument();
    
    // Forçar UTF-8
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    
    if (!@$dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR)) {
      libxml_clear_errors();
      return [
        'success' => FALSE,
        'error' => 'Erro ao fazer parse do HTML.',
      ];
    }
    
    libxml_clear_errors();

    // Extrair título
    $title = $this->extractTitle($dom);

    // Extrair texto relevante
    $text = $this->extractText($dom);

    // Limitar tamanho do texto
    if (strlen($text) > self::MAX_EXTRACTED_TEXT_LENGTH) {
      $text = mb_substr($text, 0, self::MAX_EXTRACTED_TEXT_LENGTH) . '...';
      $this->logger->notice('Texto extraído truncado para tamanho máximo.');
    }

    if (empty($text)) {
      return [
        'success' => FALSE,
        'error' => 'Não foi possível extrair texto relevante da página.',
      ];
    }

    return [
      'success' => TRUE,
      'title' => $title,
      'text' => $text,
    ];
  }

  /**
   * Extrai o título da página.
   */
  protected function extractTitle(\DOMDocument $dom) {
    // Tentar <title>
    $titles = $dom->getElementsByTagName('title');
    if ($titles->length > 0) {
      $title = trim($titles->item(0)->textContent);
      if (!empty($title)) {
        return $title;
      }
    }

    // Tentar meta og:title
    $metas = $dom->getElementsByTagName('meta');
    foreach ($metas as $meta) {
      if ($meta->getAttribute('property') === 'og:title') {
        $title = trim($meta->getAttribute('content'));
        if (!empty($title)) {
          return $title;
        }
      }
    }

    // Tentar primeiro <h1>
    $h1s = $dom->getElementsByTagName('h1');
    if ($h1s->length > 0) {
      $title = trim($h1s->item(0)->textContent);
      if (!empty($title)) {
        return $title;
      }
    }

    return 'Sem título';
  }

  /**
   * Extrai texto relevante do documento.
   */
  protected function extractText(\DOMDocument $dom) {
    // Remover tags que não contêm conteúdo relevante
    $this->removeTags($dom, ['script', 'style', 'nav', 'footer', 'header', 'aside', 'iframe', 'noscript']);

    // Tentar encontrar elemento principal de conteúdo
    $main_content = $this->findMainContent($dom);

    if ($main_content) {
      $text = $this->extractTextFromNode($main_content);
    }
    else {
      // Fallback: extrair de <body>
      $body = $dom->getElementsByTagName('body');
      if ($body->length > 0) {
        $text = $this->extractTextFromNode($body->item(0));
      }
      else {
        $text = $this->extractTextFromNode($dom);
      }
    }

    // Limpar e normalizar
    $text = $this->normalizeText($text);

    return $text;
  }

  /**
   * Remove tags do documento.
   */
  protected function removeTags(\DOMDocument $dom, array $tags) {
    foreach ($tags as $tag) {
      $elements = $dom->getElementsByTagName($tag);
      $to_remove = [];
      foreach ($elements as $element) {
        $to_remove[] = $element;
      }
      foreach ($to_remove as $element) {
        if ($element->parentNode) {
          $element->parentNode->removeChild($element);
        }
      }
    }
  }

  /**
   * Encontra o elemento principal de conteúdo.
   */
  protected function findMainContent(\DOMDocument $dom) {
    // Tentar elementos semânticos HTML5
    $semantic_tags = ['article', 'main'];
    foreach ($semantic_tags as $tag) {
      $elements = $dom->getElementsByTagName($tag);
      if ($elements->length > 0) {
        return $elements->item(0);
      }
    }

    // Tentar por classes/IDs comuns
    $xpath = new \DOMXPath($dom);
    $common_selectors = [
      '//*[@id="content"]',
      '//*[@id="main"]',
      '//*[@id="main-content"]',
      '//*[@class="content"]',
      '//*[@class="main"]',
      '//*[@class="post-content"]',
      '//*[@class="entry-content"]',
      '//*[@class="article-content"]',
    ];

    foreach ($common_selectors as $selector) {
      $nodes = @$xpath->query($selector);
      if ($nodes && $nodes->length > 0) {
        return $nodes->item(0);
      }
    }

    return NULL;
  }

  /**
   * Extrai texto de um nó DOM.
   */
  protected function extractTextFromNode(\DOMNode $node) {
    $text = '';

    if ($node->nodeType === XML_TEXT_NODE) {
      return $node->textContent;
    }

    if ($node->nodeType === XML_ELEMENT_NODE) {
      // Adicionar quebra de linha para elementos de bloco
      $block_elements = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'li', 'blockquote', 'pre', 'br', 'hr',
      ];
      
      $tag_name = strtolower($node->nodeName);
      
      if ($node->hasChildNodes()) {
        foreach ($node->childNodes as $child) {
          $text .= $this->extractTextFromNode($child);
        }
      }
      
      if (in_array($tag_name, $block_elements, TRUE)) {
        $text .= "\n";
      }
    }

    return $text;
  }

  /**
   * Normaliza texto extraído.
   */
  protected function normalizeText($text) {
    // Remover espaços múltiplos
    $text = preg_replace('/[ \t]+/', ' ', $text);
    
    // Remover quebras de linha múltiplas
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    
    // Trimmar linhas
    $lines = explode("\n", $text);
    $lines = array_map('trim', $lines);
    $text = implode("\n", $lines);
    
    // Trim geral
    $text = trim($text);
    
    return $text;
  }

}
