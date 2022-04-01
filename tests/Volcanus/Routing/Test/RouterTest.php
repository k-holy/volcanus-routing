<?php
/**
 * Volcanus libraries for PHP
 *
 * @copyright k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */

namespace Volcanus\Routing\Test;

use PHPUnit\Framework\TestCase;
use Volcanus\Routing\Exception\InvalidParameterException;
use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Router;

/**
 * RouterTest
 *
 * @author k.holy74@gmail.com
 */
class RouterTest extends TestCase
{

    private $documentRoot = null;
    private $tempDir = null;

    public function setUp(): void
    {
        $this->documentRoot = realpath(__DIR__ . '/RouterTest');
        $this->tempDir = realpath(__DIR__ . '/RouterTest/temp');
    }

    public function tearDown(): void
    {
        $fi = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($fi as $file) {
            if ($file->isFile() && $file->getBaseName() !== '.gitkeep') {
                unlink($file);
            }
        }
    }

    public function testSingleton()
    {
        $this->assertSame(Router::instance(), Router::instance());
    }

    public function testConfig()
    {
        $router = new Router();
        $router->config('parameterDirectoryName', '__VAR__');
        $this->assertEquals('__VAR__', $router->config('parameterDirectoryName'));
    }

    public function testDefaultConfiguration()
    {
        $router = new Router();
        $this->assertEquals('%VAR%', $router->config('parameterDirectoryName'));
        $this->assertEquals('php', $router->config('searchExtensions'));
        $this->assertTrue($router->config('overwriteGlobals'));
    }

    public function testRaiseExceptionWhenUnsupportedConfig()
    {
        $this->expectException(\InvalidArgumentException::class);
        $router = new Router();
        $router->config('Foo');
    }

    public function testInstanceWithConfiguration()
    {
        $router = new Router([
            'parameterDirectoryName' => '__VAR__',
            'searchExtensions' => 'php,html',
            'overwriteGlobals' => false,
            'parameterLeftDelimiter' => '{%',
            'parameterRightDelimiter' => '%}',
        ]);
        $this->assertEquals('__VAR__', $router->config('parameterDirectoryName'));
        $this->assertEquals('php,html', $router->config('searchExtensions'));
        $this->assertFalse($router->config('overwriteGlobals'));
        $this->assertEquals('{%', $router->config('parameterLeftDelimiter'));
        $this->assertEquals('%}', $router->config('parameterRightDelimiter'));
    }

    public function testSetRequestUri()
    {
        $router = new Router();
        $router->server('REQUEST_URI', '/path/to/request.php');
        $this->assertEquals('/path/to/request.php', $router->server('REQUEST_URI'));
    }

    public function testSetDocumentRoot()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', '/path/to/document/root');
        $this->assertEquals('/path/to/document/root', $router->server('DOCUMENT_ROOT'));
    }

    public function testSetDocumentRootNormalizeDirectorySeparator()
    {
        $router = new Router();
        if (DIRECTORY_SEPARATOR === '/') {
            $this->markTestSkipped();
        }
        $router->server('DOCUMENT_ROOT', 'C:\path\to\document\root');
        $this->assertEquals('C:/path/to/document/root', $router->server('DOCUMENT_ROOT'));
    }

    public function testRaiseExceptionWhenSetServerVarIsNotAccepted()
    {
        $this->expectException(\InvalidArgumentException::class);
        $router = new Router();
        $router->server('REQUEST_METHOD', 'HEAD');
    }

    public function testRaiseExceptionWhenSetServerVarIsNotString()
    {
        $this->expectException(\InvalidArgumentException::class);
        $router = new Router();
        $router->server('REQUEST_URI', []);
    }

    public function testImportGlobals()
    {
        $_SERVER['DOCUMENT_ROOT'] = '/path/to/document/root';
        $_SERVER['REQUEST_URI'] = '/request/uri.php/foo/bar';
        $_SERVER['PATH_INFO'] = '/foo/bar';
        $_SERVER['PATH_TRANSLATED'] = '/path/to/document/root/foo/bar';
        $_SERVER['PHP_SELF'] = '/request/uri.php/foo/bar';
        $_SERVER['SCRIPT_NAME'] = '/request/uri.php';
        $_SERVER['SCRIPT_FILENAME'] = '/path/to/document/root/request/uri.php';
        $router = new Router();
        $router->importGlobals();
        $this->assertEquals($_SERVER['DOCUMENT_ROOT'], $router->server('DOCUMENT_ROOT'));
        $this->assertEquals($_SERVER['REQUEST_URI'], $router->server('REQUEST_URI'));
        $this->assertEquals($_SERVER['PATH_INFO'], $router->server('PATH_INFO'));
        $this->assertEquals($_SERVER['PATH_TRANSLATED'], $router->server('PATH_TRANSLATED'));
        $this->assertEquals($_SERVER['PHP_SELF'], $router->server('PHP_SELF'));
        $this->assertEquals($_SERVER['SCRIPT_NAME'], $router->server('SCRIPT_NAME'));
        $this->assertEquals($_SERVER['SCRIPT_FILENAME'], $router->server('SCRIPT_FILENAME'));
    }

    public function testPrepareRaiseExceptionWhenDocumentRootIsNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $router = new Router();
        $router->server('REQUEST_URI', '/');
        $router->prepare();
    }

    public function testPrepareRaiseExceptionWhenRequestUriIsNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->prepare();
    }

    public function testPrepareRaiseExceptionWhenIncludeFileCouldNotFound()
    {
        $this->expectException(NotFoundException::class);
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/path/could/not/found');
        $router->prepare();
    }

    public function testFallbackScript()
    {
        $router = new Router([
            'fallbackScript' => '/temp/fallback.php',
        ]);
        $script = $this->tempDir . DIRECTORY_SEPARATOR . 'fallback.php';
        touch($script);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/path/could/not/found');
        $router->prepare();
        $this->assertEquals('/temp/fallback.php', $router->server('PHP_SELF'));
        $this->assertEquals('/temp/fallback.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp/fallback.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp/fallback.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp', $router->translateDirectory());
        $this->assertEquals('/temp/fallback.php', $router->virtualUri());
    }

    public function testPrepareRaiseExceptionWhenFallbackScriptCouldNotFound()
    {
        $this->expectException(\RuntimeException::class);
        $router = new Router([
            'fallbackScript' => '/fallback.php',
        ]);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/path/could/not/found');
        $router->prepare();
    }

    public function testFallbackScriptBasename()
    {
        $router = new Router([
            'fallbackScript' => 'fallback.php',
        ]);
        $subDir = $this->tempDir . DIRECTORY_SEPARATOR . 'sub';
        $script = $subDir . DIRECTORY_SEPARATOR . 'fallback.php';
        file_put_contents($script, '<?php echo "TEST";');
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/temp/sub/not-found');
        $router->prepare();
        $this->assertEquals('/temp/sub/fallback.php', $router->server('PHP_SELF'));
        $this->assertEquals('/temp/sub/fallback.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp/sub/fallback.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp/sub/fallback.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/temp/sub', $router->translateDirectory());
        $this->assertEquals('/temp/sub/fallback.php', $router->virtualUri());
        ob_start();
        $router->execute();
        $this->assertEquals('TEST', ob_get_contents());
        ob_end_clean();
    }

    public function testParameter()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/items/2/');
        $router->prepare();
        $this->assertEquals('1', $router->parameter(0));
        $this->assertEquals('2', $router->parameter(1));
        $this->assertEquals(['1', '2'], $router->parameters());
    }

    public function testEmptyParameter()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/');
        $router->prepare();
        $this->assertNull($router->parameter(0));
    }

    public function testParameterWithDelimiterByBuiltInFilterDigit()
    {
        $router = new Router();
        $router->config('parameterLeftDelimiter', '{%');
        $router->config('parameterRightDelimiter', '%}');
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/users/1'); // /users/{%digit%}
        $router->prepare();
        $this->assertEquals('1', $router->parameter(0));
        $this->assertEquals(['1'], $router->parameters());
    }

    public function testParameterWithDelimiterByBuiltInFilterAlpha()
    {
        $router = new Router();
        $router->config('parameterLeftDelimiter', '{%');
        $router->config('parameterRightDelimiter', '%}');
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/users/kholy'); // /users/{%alpha%}
        $router->prepare();
        $this->assertEquals('kholy', $router->parameter(0));
        $this->assertEquals(['kholy'], $router->parameters());
    }

    public function testParameterWithDelimiterByBuiltInFilterRaiseException()
    {
        $this->expectException(InvalidParameterException::class);
        $router = new Router();
        $router->config('parameterLeftDelimiter', '{%');
        $router->config('parameterRightDelimiter', '%}');
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/users/k-holy');
        $router->prepare();
    }

    public function testParameterWithDelimiterByCustomFilter()
    {
        $router = new Router();
        $router->config('parameterLeftDelimiter', '{%');
        $router->config('parameterRightDelimiter', '%}');
        $router->config('parameterFilters', [
            'profile_id' => function ($value) {
                if (strspn($value, '0123456789abcdefghijklmnopqrstuvwxyz_-.') !== strlen($value)) {
                    throw new InvalidParameterException('oh...');
                }
                return $value;
            },
            'digit' => function ($value) {
                if (!ctype_digit($value)) {
                    throw new InvalidParameterException('oh...');
                }
                return intval($value);
            },
        ]);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/users/1/profiles/k-holy');
        $router->prepare();
        $this->assertEquals(1, $router->parameter(0));
        $this->assertEquals('k-holy', $router->parameter(1));
        $this->assertEquals([1, 'k-holy'], $router->parameters());
    }

    public function testParameterWithDelimiterByCustomFilterRaiseExceptionWhenAllFiltersDoesNotReturnValue()
    {
        $this->expectException(InvalidParameterException::class);
        $router = new Router();
        $router->config('parameterLeftDelimiter', '{%');
        $router->config('parameterRightDelimiter', '%}');
        $router->config('parameterFilters', [
            'profile_id' => function ($value) {
                if (strspn($value, '0123456789abcdefghijklmnopqrstuvwxyz_-.') !== strlen($value)) {
                    throw new InvalidParameterException('oh...');
                }
                return $value;
            },
        ]);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/users/1/profiles/invalid@id');
        $router->prepare();
    }

    public function testTranslateDirectory()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            $router->server('DOCUMENT_ROOT') . '/categories/%VAR%',
            $router->translateDirectory()
        );
    }

    public function testIncludeFile()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            $router->server('DOCUMENT_ROOT') . '/categories/%VAR%/detail.php',
            $router->includeFile()
        );
    }

    public function testExtension()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals('json', $router->extension());
    }

    public function testVirtualUri()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            '/categories/%VAR%/detail.php/foo/bar?foo=bar',
            $router->virtualUri()
        );
    }

    public function testPhpSelf()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            '/categories/1/detail.php/foo/bar',
            $router->server('PHP_SELF')
        );
    }

    public function testScriptName()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals('/categories/1/detail.php', $router->server('SCRIPT_NAME'));
    }

    public function testScriptFileName()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            $router->server('DOCUMENT_ROOT') . '/categories/1/detail.php',
            $router->server('SCRIPT_FILENAME')
        );
    }

    public function testPathInfo()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals('/foo/bar', $router->server('PATH_INFO'));
    }

    public function testPathTranslated()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
        $router->prepare();
        $this->assertEquals(
            $router->server('DOCUMENT_ROOT') . '/foo/bar',
            $router->server('PATH_TRANSLATED')
        );
    }

    public function testIncludeDirectoryIndexWhenQueryStringAndFragmentIsSpecified()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/?foo=bar#1');
        $router->prepare();
        $this->assertEquals('/categories/1/index.php', $router->server('PHP_SELF'));
        $this->assertEquals('/categories/1/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/1/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%', $router->translateDirectory());
        $this->assertEquals('/categories/%VAR%/index.php?foo=bar', $router->virtualUri());
    }

    public function testRequestUriWithSchemeAndHost()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', 'http://example.com/categories/1/?foo=bar#1');
        $router->prepare();
        $this->assertEquals('/categories/1/index.php', $router->server('PHP_SELF'));
        $this->assertEquals('/categories/1/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/1/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%', $router->translateDirectory());
        $this->assertEquals('/categories/%VAR%/index.php?foo=bar', $router->virtualUri());
    }

    public function testRequestUriWithSchemeAndHostAndPort()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', 'http://example.com:8080/categories/1/?foo=bar#1');
        $router->prepare();
        $this->assertEquals('/categories/1/index.php', $router->server('PHP_SELF'));
        $this->assertEquals('/categories/1/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/1/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%', $router->translateDirectory());
        $this->assertEquals('/categories/%VAR%/index.php?foo=bar', $router->virtualUri());
    }

    public function testRegularizationOfPathContainingDoubleDot()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/../../categories/1/');
        $router->prepare();
        $this->assertEquals('/categories/1/index.php', $router->server('PHP_SELF'));
        $this->assertEquals('/categories/1/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/1/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/%VAR%', $router->translateDirectory());
        $this->assertEquals('/categories/%VAR%/index.php', $router->virtualUri());
        $this->assertEquals('1', $router->parameter(0));
    }

    public function testIncludeRootDirectoryIndexWhenRequestPathIsOutOfRoot()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/../../../');
        $router->prepare();
        $this->assertEquals('/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals('/index.php', $router->server('PHP_SELF'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT'), $router->translateDirectory());
        $this->assertEquals('/index.php', $router->virtualUri());
    }

    public function testMultipleSearchExtensions()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/test');
        $router->prepare();
        $this->assertEquals('/test.html', $router->config('searchExtensions', 'html,php')->prepare()->virtualUri());
        $this->assertEquals('/test.php', $router->config('searchExtensions', 'php,html')->prepare()->virtualUri());
    }

    public function testDirectoryAndFilenameContainingDot()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/.foo.bar.baz/1/.foo.bar.baz');
        $router->prepare();
        $this->assertEquals('/.foo.bar.baz/1/.foo.bar.baz.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals('/.foo.bar.baz/1/.foo.bar.baz.php', $router->server('PHP_SELF'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/1/.foo.bar.baz.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%/.foo.bar.baz.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%', $router->translateDirectory());
        $this->assertEquals('/.foo.bar.baz/%VAR%/.foo.bar.baz.php', $router->virtualUri());
        $this->assertEquals('1', $router->parameter(0));
    }

    public function testOverwriteGlobals()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
        $router->config('overwriteGlobals', true);
        $_SERVER = [];
        $router->prepare();
        $router->execute();
        $this->assertEquals('/categories/1/modify.php/foo/bar', $router->server('PHP_SELF'));
        $this->assertEquals('/categories/1/modify.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/categories/1/modify.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals('/foo/bar', $router->server('PATH_INFO'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/foo/bar', $router->server('PATH_TRANSLATED'));
        $this->assertEquals($_SERVER['PHP_SELF'], $router->server('PHP_SELF'));
        $this->assertEquals($_SERVER['SCRIPT_NAME'], $router->server('SCRIPT_NAME'));
        $this->assertEquals($_SERVER['SCRIPT_FILENAME'], $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($_SERVER['PATH_INFO'], $router->server('PATH_INFO'));
        $this->assertEquals($_SERVER['PATH_TRANSLATED'], $router->server('PATH_TRANSLATED'));
    }

    public function testNotOverwriteGlobals()
    {
        $router = new Router();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
        $router->config('overwriteGlobals', false);
        $_SERVER = [];
        $router->prepare();
        $router->execute();
        $this->assertArrayNotHasKey('PHP_SELF', $_SERVER);
        $this->assertArrayNotHasKey('SCRIPT_NAME', $_SERVER);
        $this->assertArrayNotHasKey('SCRIPT_FILENAME', $_SERVER);
    }

    public function testScriptPlacedDirectlyUnderOfDocumentRootCanBeInclude()
    {
        $router = new Router();
        $script = $this->documentRoot . DIRECTORY_SEPARATOR . 'echo-test.php';
        file_put_contents($script, '<?php echo "TEST";');
        ob_start();
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/echo-test');
        $router->prepare();
        $router->execute();
        $this->assertEquals('TEST', ob_get_contents());
        unlink($script);
        ob_end_clean();
    }

    public function testParameterIsNotInitializedWhenGetSecondInstanceWithConfigurations()
    {
        $configurations = [
            'parameterDirectoryName' => '__VAR__',
            'searchExtensions' => 'php,html',
            'overwriteGlobals' => false,
            'parameterLeftDelimiter' => null,
            'parameterRightDelimiter' => null,
            'parameterFilters' => [],
        ];
        $router = Router::instance($configurations);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/categories/1/items/2/');
        $router->prepare();
        $router = Router::instance($configurations);
        $this->assertEquals('1', $router->parameter(0));
        $this->assertEquals('2', $router->parameter(1));
        $this->assertEquals(['1', '2'], $router->parameters());
    }

    public function testFallbackScriptAsFileWhenLastSegmentIsParameterDirectory()
    {
        $router = new Router([
            'fallbackScript' => 'index.php',
        ]);
        $router->server('DOCUMENT_ROOT', $this->documentRoot);
        $router->server('REQUEST_URI', '/organizations/registration?foo=bar');
        $router->prepare();
        $this->assertEquals('/organizations/index.php', $router->server('PHP_SELF'));
        $this->assertEquals('/organizations/index.php', $router->server('SCRIPT_NAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/organizations/index.php', $router->server('SCRIPT_FILENAME'));
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/organizations/index.php', $router->includeFile());
        $this->assertEquals($router->server('DOCUMENT_ROOT') . '/organizations', $router->translateDirectory());
        $this->assertEquals('/organizations/index.php?foo=bar', $router->virtualUri());
    }

}
