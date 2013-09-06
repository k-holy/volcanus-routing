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

	private $script = null;
	private $documentRoot = null;

	public function setUp()
	{
		$this->documentRoot = realpath(__DIR__ . '/RouterTest');
	}

	public function tearDown()
	{
		if (isset($this->script) && file_exists($this->script)) {
			unlink($this->script);
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
		$this->assertEquals('php'  , $router->config('searchExtensions'));
		$this->assertTrue($router->config('overwriteGlobals'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenUnsupportedConfig()
	{
		$router = new Router();
		$router->config('Foo');
	}

	public function testInstanceWithConfiguration()
	{
		$router = new Router(array(
			'parameterDirectoryName'  => '__VAR__',
			'searchExtensions'        => 'php,html',
			'overwriteGlobals'        => false,
			'parameterLeftDelimiter'  => '{%',
			'parameterRightDelimiter' => '%}',
		));
		$this->assertEquals('__VAR__' , $router->config('parameterDirectoryName'));
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
		$router->server('DOCUMENT_ROOT', 'C:\path\to\document\root');
		$this->assertEquals('C:/path/to/document/root', $router->server('DOCUMENT_ROOT'));
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenSetServerVarIsNotAccepted()
	{
		$router = new Router();
		$router->server('REQUEST_METHOD', 'HEAD');
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testRaiseExceptionWhenSetServerVarIsNotString()
	{
		$router = new Router();
		$router->server('REQUEST_URI', array());
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
		$router = new Router();
		$router->importGlobals();
		$this->assertEquals($_SERVER['DOCUMENT_ROOT'  ], $router->server('DOCUMENT_ROOT'));
		$this->assertEquals($_SERVER['REQUEST_URI'    ], $router->server('REQUEST_URI'));
		$this->assertEquals($_SERVER['PATH_INFO'      ], $router->server('PATH_INFO'));
		$this->assertEquals($_SERVER['PATH_TRANSLATED'], $router->server('PATH_TRANSLATED'));
		$this->assertEquals($_SERVER['PHP_SELF'       ], $router->server('PHP_SELF'));
		$this->assertEquals($_SERVER['SCRIPT_NAME'    ], $router->server('SCRIPT_NAME'));
		$this->assertEquals($_SERVER['SCRIPT_FILENAME'], $router->server('SCRIPT_FILENAME'));
	}

	/**
	 * @expectedException \RuntimeException
	 */
	public function testPrepareRaiseExceptionWhenDocumentRootIsNotSet()
	{
		$router = new Router();
		$router->server('REQUEST_URI', '/');
		$router->prepare();
	}

	/**
	 * @expectedException \RuntimeException
	 */
	public function testPrepareRaiseExceptionWhenRequestUriIsNotSet()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->prepare();
	}

	/**
	 * @expectedException \Volcanus\Routing\Exception\NotFoundException
	 */
	public function testPrepareRaiseExceptionWhenIncludeFileCouldNotFound()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/path/could/not/found');
		$router->prepare();
	}

	public function testParameter()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/items/2/');
		$router->prepare();
		$this->assertEquals($router->parameter(0), '1');
		$this->assertEquals($router->parameter(1), '2');
		$this->assertEquals($router->parameters(), array('1', '2'));
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
		$this->assertEquals($router->parameter(0), '1');
		$this->assertEquals($router->parameters(), array('1'));
	}

	public function testParameterWithDelimiterByBuiltInFilterAlpha()
	{
		$router = new Router();
		$router->config('parameterLeftDelimiter', '{%');
		$router->config('parameterRightDelimiter', '%}');
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/users/kholy'); // /users/{%alpha%}
		$router->prepare();
		$this->assertEquals($router->parameter(0), 'kholy');
		$this->assertEquals($router->parameters(), array('kholy'));
	}

	/**
	 * @expectedException \Volcanus\Routing\Exception\InvalidParameterException
	 */
	public function testParameterWithDelimiterByBuiltInFilterRaiseException()
	{
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
		$router->config('parameterFilters', array(
			'profile_id' => function($value) {
				if (strspn($value, '0123456789abcdefghijklmnopqrstuvwxyz_-.') !== strlen($value)) {
					throw new \Volcanus\Routing\Exception\InvalidParameterException('oh...');
				}
				return $value;
			},
			'digit' => function($value) {
				if (!ctype_digit($value)) {
					throw new \Volcanus\Routing\Exception\InvalidParameterException('oh...');
				}
				return intval($value);
			},
		));
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/users/1/profiles/k-holy');
		$router->prepare();
		$this->assertEquals($router->parameter(0), 1);
		$this->assertEquals($router->parameter(1), 'k-holy');
		$this->assertEquals($router->parameters(), array(1, 'k-holy'));
	}

	/**
	 * @expectedException \Volcanus\Routing\Exception\InvalidParameterException
	 */
	public function testParameterWithDelimiterByCustomFilterRaiseExceptionWhenAllFiltersDoesNotReturnValue()
	{
		$router = new Router();
		$router->config('parameterLeftDelimiter', '{%');
		$router->config('parameterRightDelimiter', '%}');
		$router->config('parameterFilters', array(
			'profile_id' => function($value) {
				if (strspn($value, '0123456789abcdefghijklmnopqrstuvwxyz_-.') !== strlen($value)) {
					throw new \Volcanus\Routing\Exception\InvalidParameterException('oh...');
				}
				return $value;
			},
		));
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
		$this->assertEquals($router->translateDirectory(),
			$router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
	}

	public function testIncludeFile()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->includeFile(),
			$router->server('DOCUMENT_ROOT') . '/categories/%VAR%/detail.php');
	}

	public function testExtension()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->extension(), 'json');
	}

	public function testVirtualUri()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->virtualUri(), '/categories/%VAR%/detail.php/foo/bar?foo=bar');
	}

	public function testPhpSelf()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->server('PHP_SELF'), '/categories/1/detail.php/foo/bar');
	}

	public function testScriptName()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->server('SCRIPT_NAME'), '/categories/1/detail.php');
	}

	public function testScriptFileName()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->server('SCRIPT_FILENAME'),
			$router->server('DOCUMENT_ROOT') . '/categories/1/detail.php');
	}

	public function testPathInfo()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->server('PATH_INFO'), '/foo/bar');
	}

	public function testPathTranslated()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/detail.json/foo/bar?foo=bar');
		$router->prepare();
		$this->assertEquals($router->server('PATH_TRANSLATED'),
			$router->server('DOCUMENT_ROOT') . '/foo/bar');
	}

	public function testIncludeDirectoryIndexWhenQueryStringAndFragmentIsSpecified()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/?foo=bar#1');
		$router->prepare();
		$this->assertEquals($router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testRequestUriWithSchemeAndHost()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', 'http://example.com/categories/1/?foo=bar#1');
		$router->prepare();
		$this->assertEquals($router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testRequestUriWithSchemeAndHostAndPort()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', 'http://example.com:8080/categories/1/?foo=bar#1');
		$router->prepare();
		$this->assertEquals($router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($router->virtualUri()             , '/categories/%VAR%/index.php?foo=bar');
	}

	public function testReguralizationOfPathContainingDoubleDot()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/../../categories/1/');
		$router->prepare();
		$this->assertEquals($router->server('PHP_SELF'       ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/categories/1/index.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/categories/1/index.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%/index.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT') . '/categories/%VAR%');
		$this->assertEquals($router->virtualUri()             , '/categories/%VAR%/index.php');
		$this->assertEquals($router->parameter(0), '1');
	}

	public function testIncludeRootDirectoryIndexWhenRequestPathIsOutOfRoot()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/../../../');
		$router->prepare();
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/index.php');
		$this->assertEquals($router->server('PHP_SELF'       ), '/index.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/index.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/index.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT'));
		$this->assertEquals($router->virtualUri()             , '/index.php');
	}

	public function testMultipleSearchExtensions()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/test');
		$router->prepare();
		$this->assertEquals($router->config('searchExtensions', 'html,php')->prepare()->virtualUri(), '/test.html');
		$this->assertEquals($router->config('searchExtensions', 'php,html')->prepare()->virtualUri(), '/test.php');
	}

	public function testDirectoryAndFilenameContainingDot()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/.foo.bar.baz/1/.foo.bar.baz');
		$router->prepare();
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($router->server('PHP_SELF'       ), '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/1/.foo.bar.baz.php');
		$this->assertEquals($router->includeFile()            , $router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%/.foo.bar.baz.php');
		$this->assertEquals($router->translateDirectory()     , $router->server('DOCUMENT_ROOT') . '/.foo.bar.baz/%VAR%');
		$this->assertEquals($router->virtualUri()             , '/.foo.bar.baz/%VAR%/.foo.bar.baz.php');
		$this->assertEquals($router->parameter(0), '1');
	}

	public function testOverwriteGlobals()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
		$router->config('overwriteGlobals', true);
		$_SERVER = array();
		$router->prepare();
		$router->execute();
		$this->assertEquals($router->server('PHP_SELF'       ), '/categories/1/modify.php/foo/bar');
		$this->assertEquals($router->server('SCRIPT_NAME'    ), '/categories/1/modify.php');
		$this->assertEquals($router->server('SCRIPT_FILENAME'), $router->server('DOCUMENT_ROOT') . '/categories/1/modify.php');
		$this->assertEquals($router->server('PATH_INFO'      ), '/foo/bar');
		$this->assertEquals($router->server('PATH_TRANSLATED'), $router->server('DOCUMENT_ROOT') . '/foo/bar');
		$this->assertEquals($_SERVER['PHP_SELF'       ], $router->server('PHP_SELF'       ));
		$this->assertEquals($_SERVER['SCRIPT_NAME'    ], $router->server('SCRIPT_NAME'    ));
		$this->assertEquals($_SERVER['SCRIPT_FILENAME'], $router->server('SCRIPT_FILENAME'));
		$this->assertEquals($_SERVER['PATH_INFO'      ], $router->server('PATH_INFO'      ));
		$this->assertEquals($_SERVER['PATH_TRANSLATED'], $router->server('PATH_TRANSLATED'));
	}

	public function testNotOverwriteGlobals()
	{
		$router = new Router();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/modify/foo/bar?foo=bar#1');
		$router->config('overwriteGlobals', false);
		$_SERVER = array();
		$router->prepare();
		$router->execute();
		$this->assertArrayNotHasKey('PHP_SELF'       , $_SERVER);
		$this->assertArrayNotHasKey('SCRIPT_NAME'    , $_SERVER);
		$this->assertArrayNotHasKey('SCRIPT_FILENAME', $_SERVER);
	}

	public function testScriptPlacedDirectlyUnderOfDocumentRootCanBeInclude()
	{
		$router = new Router();
		$this->script = $this->documentRoot . '/echo-test.php';
		file_put_contents($this->script, '<?php echo "TEST";');
		ob_start();
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/echo-test');
		$router->prepare();
		$router->execute();
		$this->assertEquals('TEST', ob_get_contents());
		ob_end_clean();
	}

	public function testParameterIsNotInitializedWhenGetSecondInstanceWithConfigurations()
	{
		$configurations = array(
			'parameterDirectoryName'  => '__VAR__',
			'searchExtensions'        => 'php,html',
			'overwriteGlobals'        => false,
			'parameterLeftDelimiter'  => null,
			'parameterRightDelimiter' => null,
			'parameterFilters'        => array(),
		);
		$router = Router::instance($configurations);
		$router->server('DOCUMENT_ROOT', $this->documentRoot);
		$router->server('REQUEST_URI', '/categories/1/items/2/');
		$router->prepare();
		$router = Router::instance($configurations);
		$this->assertEquals($router->parameter(0), '1');
		$this->assertEquals($router->parameter(1), '2');
		$this->assertEquals($router->parameters(), array('1', '2'));
	}

}
