<?php
/**
 * Volcanus libraries for PHP
 *
 * @copyright 2011-2013 k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */
namespace Volcanus\Routing;

use Volcanus\Routing\Router;

/**
 * RouterTest
 *
 * @package Volcanus\Routing
 * @author k.holy74@gmail.com
 */
class RouterTest extends \PHPUnit_Framework_TestCase
{

	private $router = null;
	private $script = null;
	private $documentRoot = null;

	public function setUp()
	{
		$this->documentRoot = realpath(__DIR__ . '/RouterTest');
		$this->router = new Router();
	}

	public function tearDown()
	{
		if (isset($this->script) && file_exists($this->script)) {
			unlink($this->script);
		}
	}

	public function testInitialize()
	{
		$default_config = $this->router->configurations();
		$this->router = new Router(array(
			'parameterDirectoryName' => '__VAR__',
			'searchExtensions'       => 'php,html',
			'overwriteGlobals'       => false,
		));
		$this->router->initialize();
		$this->assertEquals($default_config, $this->router->configurations());
	}

	public function testSingleton()
	{
		$this->assertSame(Router::instance(), Router::instance());
	}

	public function testConfig()
	{
		$this->router->config('parameterDirectoryName', '__VAR__');
		$this->assertEquals('__VAR__', $this->router->config('parameterDirectoryName'));
	}

	public function testDefaultConfiguration()
	{
		$this->assertEquals('%VAR%', $this->router->config('parameterDirectoryName'));
		$this->assertEquals('php'  , $this->router->config('searchExtensions'));
		$this->assertTrue($this->router->config('overwriteGlobals'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenUnsupportedConfig()
	{
		$this->router->config('Foo');
	}

	public function testInstanceWithConfiguration()
	{
		$this->router = new Router(array(
			'parameterDirectoryName' => '__VAR__',
			'searchExtensions'       => 'php,html',
			'overwriteGlobals'       => false,
		));
		$this->assertEquals('__VAR__' , $this->router->config('parameterDirectoryName'));
		$this->assertEquals('php,html', $this->router->config('searchExtensions'));
		$this->assertFalse($this->router->config('overwriteGlobals'));
	}

	public function testSetRequestUri()
	{
		$this->router->server('REQUEST_URI', '/path/to/request.php');
		$this->assertEquals('/path/to/request.php', $this->router->server('REQUEST_URI'));
	}

	public function testSetDocumentRoot()
	{
		$this->router->server('DOCUMENT_ROOT', '/path/to/document/root');
		$this->assertEquals('/path/to/document/root', $this->router->server('DOCUMENT_ROOT'));
	}

	public function testSetDocumentRootNormalizeDirectorySeparator()
	{
		$this->router->server('DOCUMENT_ROOT', 'C:\path\to\document\root');
		$this->assertEquals('C:/path/to/document/root', $this->router->server('DOCUMENT_ROOT'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenSetServerVarIsNotAccepted()
	{
		$this->router->server('REQUEST_METHOD', 'HEAD');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenSetServerVarIsNotString()
	{
		$this->router->server('REQUEST_URI', array());
	}

	public function testImportGlobals()
	{
		$_SERVER['DOCUMENT_ROOT'  ] = '/path/to/document/root';
		$_SERVER['REQUEST_URI'    ] = '/request/uri.php/foo/bar';
		$_SERVER['PATH_INFO'      ] = '/foo/bar';
		$_SERVER['PATH_TRANSLATED'] = '/path/to/document/root/foo/bar';
		$_SERVER['PHP_SELF'       ] = '/request/uri.php/foo/bar';
		$_SERVER['SCRIPT_NAME'    ] = '/request/uri.php';
		$_SERVER['SCRIPT_FILENAME'] = '/path/to/document/root/request/uri.php';
		$this->router->importGlobals();
		$this->assertEquals($_SERVER['DOCUMENT_ROOT'  ], $this->router->server('DOCUMENT_ROOT'));
		$this->assertEquals($_SERVER['REQUEST_URI'    ], $this->router->server('REQUEST_URI'));
		$this->assertEquals($_SERVER['PATH_INFO'      ], $this->router->server('PATH_INFO'));
		$this->assertEquals($_SERVER['PATH_TRANSLATED'], $this->router->server('PATH_TRANSLATED'));
		$this->assertEquals($_SERVER['PHP_SELF'       ], $this->router->server('PHP_SELF'));
		$this->assertEquals($_SERVER['SCRIPT_NAME'    ], $this->router->server('SCRIPT_NAME'));
		$this->assertEquals($_SERVER['SCRIPT_FILENAME'], $this->router->server('SCRIPT_FILENAME'));
	}

	/**
	 * @expectedException \RuntimeException
	 */
	public function testPrepareRaiseExceptionWhenDocumentRootIsNotSet()
	{
		$this->router->server('REQUEST_URI', '/');
		$this->router->prepare();
	}

	/**
	 * @expectedException \RuntimeException
	 */
	public function testPrepareRaiseExceptionWhenRequestUriIsNotSet()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->prepare();
	}

	/**
	 * @expectedException \Volcanus\Routing\Exception\NotFoundException
	 */
	public function testPrepareRaiseExceptionWhenIncludeFileCouldNotFound()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/path/could/not/found');
		$this->router->prepare();
	}

	public function testParameter()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/items/2/');
		$this->router->prepare();
		$this->assertEquals($this->router->parameter(0), '1');
		$this->assertEquals($this->router->parameter(1), '2');
		$this->assertEquals($this->router->parameters(), array('1', '2'));
	}

	public function testEmptyParameter()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/');
		$this->router->prepare();
		$this->assertNull($this->router->parameter(0));
	}

	public function testTranslateDirectory()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->translateDirectory(),
			$this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
	}

	public function testIncludeFile()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->includeFile(),
			$this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%/detail.php');
	}

	public function testExtension()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->extension(), 'json');
	}

	public function testVirtualUri()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->virtualUri(), '/categories/%VAR%/detail.php/foo/bar?foo=bar');
	}

	public function testPhpSelf()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PHP_SELF'), '/categories/1/detail.php/foo/bar');
	}

	public function testScriptName()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->server('SCRIPT_NAME'), '/categories/1/detail.php');
	}

	public function testScriptFileName()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'),
			$this->router->server('DOCUMENT_ROOT') . '/categories/1/detail.php');
	}

	public function testPathInfo()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PATH_INFO'), '/foo/bar');
	}

	public function testPathTranslated()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PATH_TRANSLATED'),
			$this->router->server('DOCUMENT_ROOT') . '/foo/bar');
	}

	public function testIncludeDirectoryIndexWhenQueryStringAndFragmentIsSpecified()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/?foo=bar#1');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($this->router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testRequestUriWithSchemeAndHost()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', 'http://example.com/categories/1/?foo=bar#1');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($this->router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testRequestUriWithSchemeAndHostAndPort()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', 'http://example.com:8080/categories/1/?foo=bar#1');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($this->router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testReguralizationOfPathContainingDoubleDot()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/../../categories/1/');
		$this->router->prepare();
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($this->router->virtualUri()             , '/categories/%VAR%/index.php');
		$this->assertEquals($this->router->parameter(0), '1');
	}

	public function testIncludeRootDirectoryIndexWhenRequestPathIsOutOfRoot()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/../../../');
		$this->router->prepare();
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/index.php');
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/index.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/index.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/index.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT'));
		$this->assertEquals($this->router->virtualUri()             , '/index.php');
	}

	public function testMultipleSearchExtensions()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/test');
		$this->router->prepare();
		$this->assertEquals($this->router->config('searchExtensions', 'html,php')->prepare()->virtualUri(), '/test.html');
		$this->assertEquals($this->router->config('searchExtensions', 'php,html')->prepare()->virtualUri(), '/test.php');
	}

	public function testDirectoryAndFilenameContainingDot()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/.foo.bar.baz/1/.foo.bar.baz');
		$this->router->prepare();
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($this->router->includeFile()            , $this->router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%/.foo.bar.baz.php');
		$this->assertEquals($this->router->translateDirectory()     , $this->router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%');
		$this->assertEquals($this->router->virtualUri()             , '/.foo.bar.baz/%VAR%/.foo.bar.baz.php');
		$this->assertEquals($this->router->parameter(0), '1');
	}

	public function testOverwriteGlobals()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
		$this->router->config('overwriteGlobals', true);
		$_SERVER = array();
		$this->router->prepare();
		$this->router->execute();
		$this->assertEquals($this->router->server('PHP_SELF'       ), '/categories/1/modify.php/foo/bar');
		$this->assertEquals($this->router->server('SCRIPT_NAME'    ), '/categories/1/modify.php');
		$this->assertEquals($this->router->server('SCRIPT_FILENAME'), $this->router->server('DOCUMENT_ROOT') . '/categories/1/modify.php');
		$this->assertEquals($this->router->server('PATH_INFO'      ), '/foo/bar');
		$this->assertEquals($this->router->server('PATH_TRANSLATED'), $this->router->server('DOCUMENT_ROOT') . '/foo/bar');
		$this->assertEquals($_SERVER['PHP_SELF'       ], $this->router->server('PHP_SELF'       ));
		$this->assertEquals($_SERVER['SCRIPT_NAME'    ], $this->router->server('SCRIPT_NAME'    ));
		$this->assertEquals($_SERVER['SCRIPT_FILENAME'], $this->router->server('SCRIPT_FILENAME'));
		$this->assertEquals($_SERVER['PATH_INFO'      ], $this->router->server('PATH_INFO'      ));
		$this->assertEquals($_SERVER['PATH_TRANSLATED'], $this->router->server('PATH_TRANSLATED'));
	}

	public function testNotOverwriteGlobals()
	{
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
		$this->router->config('overwriteGlobals', false);
		$_SERVER = array();
		$this->router->prepare();
		$this->router->execute();
		$this->assertArrayNotHasKey('PHP_SELF'       , $_SERVER);
		$this->assertArrayNotHasKey('SCRIPT_NAME'    , $_SERVER);
		$this->assertArrayNotHasKey('SCRIPT_FILENAME', $_SERVER);
	}

	public function testScriptPlacedDirectlyUnderOfDocumentRootCanBeInclude()
	{
		$this->script = $this->documentRoot . '/echo-test.php';
		file_put_contents($this->script, '<?php echo "TEST";');
		ob_start();
		$this->router->server('DOCUMENT_ROOT', $this->documentRoot);
		$this->router->server('REQUEST_URI', '/echo-test');
		$this->router->prepare();
		$this->router->execute();
		$this->assertEquals('TEST', ob_get_contents());
		ob_end_clean();
	}

}
