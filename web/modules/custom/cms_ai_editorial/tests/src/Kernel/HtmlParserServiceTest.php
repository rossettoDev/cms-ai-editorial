<?php

namespace Drupal\Tests\cms_ai_editorial\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\cms_ai_editorial\Service\HtmlParserService;

/**
 * Testa o serviço HtmlParserService.
 *
 * @group cms_ai_editorial
 */
class HtmlParserServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cms_ai_editorial'];

  /**
   * O serviço de parser HTML.
   *
   * @var \Drupal\cms_ai_editorial\Service\HtmlParserService
   */
  protected $htmlParserService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->htmlParserService = $this->container->get('cms_ai_editorial.html_parser');
  }

  /**
   * Testa extração de título de <title>.
   */
  public function testExtractsTitleFromTitleTag() {
    $html = '<html><head><title>Título de Teste</title></head><body><p>Conteúdo</p></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertEquals('Título de Teste', $result['title']);
  }

  /**
   * Testa extração de título de meta og:title.
   */
  public function testExtractsTitleFromOgTitle() {
    $html = '<html><head><meta property="og:title" content="Título Open Graph"/></head><body><p>Conteúdo</p></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertEquals('Título Open Graph', $result['title']);
  }

  /**
   * Testa extração de título de <h1>.
   */
  public function testExtractsTitleFromH1() {
    $html = '<html><body><h1>Título H1</h1><p>Conteúdo</p></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertEquals('Título H1', $result['title']);
  }

  /**
   * Testa extração de texto simples.
   */
  public function testExtractsSimpleText() {
    $html = '<html><body><p>Parágrafo 1</p><p>Parágrafo 2</p></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertStringContainsString('Parágrafo 1', $result['text']);
    $this->assertStringContainsString('Parágrafo 2', $result['text']);
  }

  /**
   * Testa remoção de scripts.
   */
  public function testRemovesScripts() {
    $html = '<html><body><p>Texto visível</p><script>alert("XSS")</script></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertStringContainsString('Texto visível', $result['text']);
    $this->assertStringNotContainsString('alert', $result['text']);
  }

  /**
   * Testa remoção de estilos.
   */
  public function testRemovesStyles() {
    $html = '<html><body><p>Texto visível</p><style>body { color: red; }</style></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertStringContainsString('Texto visível', $result['text']);
    $this->assertStringNotContainsString('color', $result['text']);
  }

  /**
   * Testa extração de conteúdo de <article>.
   */
  public function testExtractsFromArticleTag() {
    $html = '
      <html>
        <body>
          <nav>Menu de navegação</nav>
          <article>
            <h1>Artigo Principal</h1>
            <p>Conteúdo do artigo</p>
          </article>
          <footer>Rodapé</footer>
        </body>
      </html>
    ';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertStringContainsString('Conteúdo do artigo', $result['text']);
    // Nav e footer devem ser removidos ou não aparecer
    $this->assertStringNotContainsString('Menu de navegação', $result['text']);
  }

  /**
   * Testa normalização de espaços.
   */
  public function testNormalizesWhitespace() {
    $html = '<html><body><p>Texto   com    múltiplos    espaços</p></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertStringNotContainsString('   ', $result['text']);
  }

  /**
   * Testa limite de tamanho de texto.
   */
  public function testTruncatesLargeText() {
    // Gerar HTML com texto muito grande
    $large_text = str_repeat('A', 60000);
    $html = "<html><body><p>$large_text</p></body></html>";
    
    $result = $this->htmlParserService->extractContent($html);
    
    $this->assertTrue($result['success']);
    $this->assertLessThanOrEqual(51200 + 3, strlen($result['text'])); // +3 para "..."
  }

  /**
   * Testa rejeição de HTML vazio.
   */
  public function testRejectsEmptyHtml() {
    $result = $this->htmlParserService->extractContent('');
    
    $this->assertFalse($result['success']);
    $this->assertEquals('HTML vazio.', $result['error']);
  }

  /**
   * Testa HTML sem conteúdo textual.
   */
  public function testRejectsHtmlWithoutText() {
    $html = '<html><head><title>Título</title></head><body><img src="image.jpg"/></body></html>';
    $result = $this->htmlParserService->extractContent($html);
    
    // Deve falhar porque não há texto relevante
    $this->assertFalse($result['success']);
  }

}
