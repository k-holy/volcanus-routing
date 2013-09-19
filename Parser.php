<?php
/**
 * Volcanus libraries for PHP
 *
 * @copyright 2011-2013 k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */
namespace Volcanus\Routing;

use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

/**
 * Parser
 *
 * @package Volcanus\Routing
 * @author k.holy74@gmail.com
 */
class Parser
{

	/**
	 * @var array 設定値
	 */
	private $config;

	/**
	 * @var パース結果
	 */
	private $results;

	public function __construct(array $configurations = array())
	{
		$this->initialize($configurations);
	}

	/**
	 * オブジェクトを初期化します。
	 *
	 * @param array 設定オプション
	 */
	public function initialize(array $configurations = array())
	{
		$this->config = array(
			'documentRoot'            => null,
			'parameterDirectoryName'  => null,
			'parameterLeftDelimiter'  => null,
			'parameterRightDelimiter' => null,
			'searchExtensions'        => null,
			'parameterFilters'        => null,
		);
		$this->results = array(
			'translateDirectory' => null,
			'scriptName'         => null,
			'filename'           => null,
			'pathInfo'           => null,
			'extension'          => null,
			'parameters'         => array(),
		);
		if (!empty($configurations)) {
			$this->config = array_replace($this->config, $configurations);
		}
		return $this;
	}

	/**
	 * 全てのパース結果を配列で返します。
	 *
	 * @return array パース結果の配列
	 */
	public function results()
	{
		return $this->results;
	}

	/**
	 * パスを解析し、ルーティングの実行を準備します。
	 *
	 * @param string requestURI
	 * @return object Router
	 * @throws \RuntimeException
	 * @throws Exception\NotFoundException
	 * @throws Exception\InvalidParameterException
	 */
	public function parse($path)
	{

		$documentRoot = $this->config['documentRoot'];

		$parameterDirectoryName = $this->config['parameterDirectoryName'];

		// パラメータの左デリミタと右デリミタの両方が指定されている場合のみ検索
		$parameterLeftDelimiter = $this->config['parameterLeftDelimiter'];
		$parameterRightDelimiter = $this->config['parameterRightDelimiter'];
		$searchParameter = (isset($parameterLeftDelimiter) && isset($parameterRightDelimiter));

		$searchExtensions = $this->config['searchExtensions'];
		if (is_string($searchExtensions)) {
			$searchExtensions = explode(',', $searchExtensions);
		}

		$translateDirectory = '';
		$scriptName = '';
		$filename = null;
		$fileSegmentIndex = -1;
		$extension = null;
		$parameters = array();

		$segments = $this->parseRequestPath($path);
		$segmentCount = count($segments);

		foreach ($segments as $index => $segment) {

			// セグメント内の . を展開
			$pos = strrpos($segment, '.');
			if ($pos !== false) {
				$filename = $this->findFile($documentRoot . $translateDirectory, $segment);
				if (isset($filename)) {
					$scriptName .= '/' . $filename;
					$fileSegmentIndex = $index;
					break;
				}
				$basename = substr($segment, 0, $pos);
				$ext = substr($segment, $pos + 1);
				if (!empty($searchExtensions) &&
					!in_array($ext, $searchExtensions)
				) {
					$filename = $this->findFile($documentRoot . $translateDirectory,
						$basename, $searchExtensions);
					if (isset($filename)) {
						$scriptName .= '/' . $filename;
						$fileSegmentIndex = $index;
						$extension = $ext;
						break;
					}
				}
			}

			// 実ディレクトリがあれば次のセグメントへ
			if (is_dir($documentRoot . $translateDirectory . '/' . $segment)) {
				$scriptName .= '/' . $segment;
				$translateDirectory .= '/' . $segment;
				continue;
			}

			// 実ファイルがあれば終了
			$filename = $this->findFile($documentRoot . $translateDirectory,
				$segment, $searchExtensions);
			if (isset($filename)) {
				$scriptName .= '/' . $filename;
				$fileSegmentIndex = $index;
				break;
			}

			// パラメータディレクトリがあれば次のセグメントへ
			if (is_dir($documentRoot . $translateDirectory . '/' .
				$parameterDirectoryName)
			) {
				$translateDirectory .= '/' . $parameterDirectoryName;
				$scriptName .= '/' . $segment;
				$parameters[] = $segment;
				continue;
			}

			// デリミタでパラメータディレクトリを検索
			if ($searchParameter) {
				$pattern = $documentRoot . $translateDirectory . '/' .
					sprintf('%s*%s', $parameterLeftDelimiter, $parameterRightDelimiter);
				$dirs = glob($pattern, GLOB_ONLYDIR);
				if (count($dirs) >= 1) {
					$parameterValue = null;
					foreach ($dirs as $dir) {
						$parameterSegment = substr($dir, strrpos($dir, '/') + 1);
						$parameterType = substr($parameterSegment, strlen($parameterLeftDelimiter),
							strlen($parameterSegment) - strlen($parameterLeftDelimiter) - strlen($parameterRightDelimiter)
						);
						$filters = $this->config['parameterFilters'];
						// ユーザフィルタが定義されており、実行結果がNULL以外の場合は妥当なパラメータ値とする
						if (array_key_exists($parameterType, $filters)) {
							$parameterValue = $filters[$parameterType]($segment);
							if (isset($parameterValue)) {
								break;
							}
						// ユーザフィルタが未定義かつCtype関数に合致すれば妥当なパラメータ値とする
						} elseif (is_callable('ctype_' . $parameterType)) {
							$filter = 'ctype_' . $parameterType;
							if (call_user_func($filter, $segment)) {
								$parameterValue = $segment;
								break;
							}
						}
					}
					if (!isset($parameterValue)) {
						throw new InvalidParameterException(
							sprintf('The parameter of the segment in Uri\'s path "%s" is not valid in requestPath "%s".', $segment, $path));
					}
					$translateDirectory .= '/' . $parameterSegment;
					$scriptName .= '/' . $segment;
					$parameters[] = $parameterValue;
					continue;
				}
			}

			throw new NotFoundException(
				sprintf('The file that corresponds to the segment of Uri\'s path "%s" is not found in requestPath "%s".', $segment, $path));
		}

		$translateDirectory = rtrim($translateDirectory, '/');

		// ディレクトリのみでファイル名がない場合
		if (!isset($filename)) {

			$filename = $this->findFile($documentRoot . $translateDirectory,
				'index', array('php', 'html'));
			if (isset($filename)) {
				$fileSegmentIndex = $segmentCount - 1;
				if (isset($segments[$fileSegmentIndex]) && strcmp($segments[$fileSegmentIndex], '') !== 0) {
					$scriptName .= '/' . $filename;
				} else {
					$scriptName .= $filename;
				}
			}
		}

		if (!isset($filename)) {
			throw new NotFoundException(
				sprintf('The file that corresponds to the Uri\'s path "%s" is not found.', $path));
		}

		// @see RFC 3875 Section 4.1. Request Meta-Variables
		$pathInfo = '';
		for ($i = $fileSegmentIndex + 1; $i < $segmentCount; $i++) {
			$pathInfo .= '/' . $segments[$i];
		}

		$this->results['translateDirectory'] = $translateDirectory;
		$this->results['scriptName'] = $scriptName;
		$this->results['filename'] = $filename;
		$this->results['pathInfo'] = $pathInfo;
		$this->results['extension'] = $extension;
		$this->results['parameters'] = $parameters;

		return $this->results;
	}

	/**
	 * パスに含まれる.および..を展開し、ルートからのセグメントの配列を返します。
	 *
	 * @param string パス
	 * @return array セグメントの配列
	 */
	private function parseRequestPath($path)
	{
		$segments = array();
		$count = 0;
		foreach (explode('/', $path) as $segment) {
			if (strcmp($segment, '.') === 0) {
				continue;
			}
			if (strcmp($segment, '..') === 0) {
				if ($count >= 2) {
					array_pop($segments);
					$count--;
				}
				continue;
			}
			$segments[] = $segment;
			$count++;
		}
		array_shift($segments);
		return $segments;
	}

	/**
	 * ディレクトリに、指定された名前および拡張子のファイルがあれば、そのファイル名を返します。
	 *
	 * @param string ディレクトリ
	 * @param string ファイル名
	 * @param array 検索する拡張子のリスト
	 * @return mixed ファイル名またはNULL
	 */
	private function findFile($dir, $filename, $extensions = array())
	{
		if (!empty($extensions)) {
			foreach ($extensions as $extension) {
				$path = $dir . '/' . $filename . '.'. $extension;
				if (file_exists($path) && is_file($path)) {
					return $filename . '.' . $extension;
				}
			}
		}
		$path = $dir . '/' . $filename;
		return (file_exists($path) && is_file($path)) ? $filename : null;
	}

}
