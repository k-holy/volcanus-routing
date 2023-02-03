<?php
/**
 * Volcanus libraries for PHP 8.1~
 *
 * @copyright k-holy <k.holy74@gmail.com>
 * @license The MIT License (MIT)
 */

namespace Volcanus\Routing;

use Volcanus\Routing\Exception\NotFoundException;
use Volcanus\Routing\Exception\InvalidParameterException;

/**
 * Parser
 *
 * @author k.holy74@gmail.com
 */
class Parser
{

    /**
     * @var array 設定値
     */
    private array $config;

    /**
     * @var array パース結果
     */
    private array $results;

    /**
     * constructor
     *
     * @param array $configurations 設定オプション
     */
    public function __construct(array $configurations = [])
    {
        $this->initialize($configurations);
    }

    /**
     * オブジェクトを初期化します。
     *
     * @param array $configurations 設定オプション
     * @return self
     */
    public function initialize(array $configurations = []): self
    {
        $this->config = [
            'documentRoot' => null,
            'parameterDirectoryName' => null,
            'parameterLeftDelimiter' => null,
            'parameterRightDelimiter' => null,
            'searchExtensions' => null,
            'parameterFilters' => null,
            'fallbackScript' => null,
        ];
        $this->results = [
            'translateDirectory' => null,
            'scriptName' => null,
            'filename' => null,
            'pathInfo' => null,
            'extension' => null,
            'parameters' => [],
        ];
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
    public function results(): array
    {
        return $this->results;
    }

    /**
     * パスを解析し、ルーティングの実行を準備します。
     *
     * @param string $path リクエストURI
     * @return array パース結果
     *
     * @throws \RuntimeException
     * @throws NotFoundException
     * @throws InvalidParameterException
     */
    public function parse(string $path): array
    {

        $documentRoot = $this->config['documentRoot'];

        $parameterDirectoryName = $this->config['parameterDirectoryName'];

        // パラメータの左デリミタと右デリミタの両方が指定されている場合のみ検索
        $searchParameter = ($this->config['parameterLeftDelimiter'] !== null && $this->config['parameterRightDelimiter'] !== null);

        $searchExtensions = $this->config['searchExtensions'];
        if (is_string($searchExtensions)) {
            $searchExtensions = explode(',', $searchExtensions);
        }

        $fallbackScript = $this->config['fallbackScript'];

        $translateDirectory = '';
        $scriptName = '';
        $filename = null;
        $fileSegmentIndex = -1;
        $extension = null;
        $parameters = [];

        $segments = $this->parseRequestPath($path);
        $segmentCount = count($segments);

        foreach ($segments as $index => $segment) {

            // セグメント内の . を展開
            $pos = strrpos($segment, '.');
            if ($pos !== false) {
                $filename = $this->findFile($documentRoot . $translateDirectory, $segment);
                if ($filename !== null) {
                    $scriptName .= '/' . $filename;
                    $fileSegmentIndex = $index;
                    break;
                }
                $basename = substr($segment, 0, $pos);
                $ext = substr($segment, $pos + 1);
                if (!empty($searchExtensions) &&
                    !in_array($ext, $searchExtensions)
                ) {
                    $filename = $this->findFile($documentRoot . $translateDirectory, $basename, $searchExtensions);
                    if ($filename !== null) {
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
            $filename = $this->findFile($documentRoot . $translateDirectory, $segment, $searchExtensions);
            if ($filename !== null) {
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
                $values = $this->getParameter($documentRoot . $translateDirectory, $segment);
                if ($values !== false) {
                    if (!isset($values[0]) || !isset($values[1])) {
                        throw new InvalidParameterException(
                            sprintf('The parameter of the segment in Uri\'s path "%s" is not valid in requestPath "%s".', $segment, $path));
                    }
                    $translateDirectory .= '/' . $values[1];
                    $scriptName .= '/' . $segment;
                    $parameters[] = $values[0];
                    continue;
                }
            }

            // fallbackScriptがファイル名で指定されている場合、現在のディレクトリから検索
            if ($fallbackScript !== null && !str_starts_with($fallbackScript, '/')) {
                $filename = $this->findFile($documentRoot . $translateDirectory, $fallbackScript);
                if ($filename !== null) {
                    $scriptName .= '/' . $filename;
                    $fileSegmentIndex = $index;
                    break;
                }
            }

            throw new NotFoundException(
                sprintf('The file that corresponds to the segment of Uri\'s path "%s" is not found in requestPath "%s".', $segment, $path));
        }

        $translateDirectory = rtrim($translateDirectory, '/');
        $scriptName = rtrim($scriptName, '/');

        // ディレクトリのみでファイル名がない場合
        if ($filename === null) {
            $filename = $this->findFile($documentRoot . $translateDirectory, 'index', ['php', 'html']);
            if ($filename !== null) {
                $fileSegmentIndex = $segmentCount - 1;
                $scriptName .= (strrpos($scriptName, '/') === strlen($scriptName)) ? $filename : '/' . $filename;
            }
        }

        if ($filename === null) {
            // fallbackScriptがファイル名で指定され、かつ末尾がパラメータディレクトリの場合、ひとつ前のセグメントから検索
            if ($fallbackScript !== null && !str_starts_with($fallbackScript, '/') && $parameterDirectoryName !== null) {
                $lastSegmentIndex = strrpos($translateDirectory, '/');
                if (substr($translateDirectory, $lastSegmentIndex + 1) === $parameterDirectoryName) {
                    $_translateDirectory = substr($translateDirectory, 0, $lastSegmentIndex);
                    $filename = $this->findFile($documentRoot . $_translateDirectory, $fallbackScript);
                    if ($filename !== null) {
                        $translateDirectory = $_translateDirectory;
                        $fileSegmentIndex = $segmentCount - 1;
                        $scriptName = substr($scriptName, 0, strrpos($scriptName, '/'));
                        $scriptName .= (strrpos($scriptName, '/') === strlen($scriptName)) ? $filename : '/' . $filename;
                    }
                }
            }
        }

        if ($filename === null) {
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
     * @param string $path パス
     * @return array セグメントの配列
     */
    private function parseRequestPath(string $path): array
    {
        $segments = [];
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
     * @param string $dir ディレクトリ
     * @param string $filename ファイル名
     * @param array $extensions 検索する拡張子のリスト
     * @return string|NULL ファイル名またはNULL
     */
    private function findFile(string $dir, string $filename, array $extensions = []): ?string
    {
        if (!empty($extensions)) {
            foreach ($extensions as $extension) {
                $path = $dir . '/' . $filename . '.' . $extension;
                if (file_exists($path) && is_file($path)) {
                    return $filename . '.' . $extension;
                }
            }
        }
        $path = $dir . '/' . $filename;
        return (file_exists($path) && is_file($path)) ? $filename : null;
    }

    /**
     * セグメントからパラメータを取得して返します。
     *
     * @param string $dir ディレクトリ
     * @param string $segment セグメント
     * @return array|false
     */
    private function getParameter(string $dir, string $segment): bool|array
    {
        $pattern = $dir . '/' . sprintf('%s*%s', $this->config['parameterLeftDelimiter'], $this->config['parameterRightDelimiter']);
        $dirs = glob($pattern, GLOB_ONLYDIR);
        if (count($dirs) >= 1) {
            $parameterValue = null;
            $parameterSegment = null;
            foreach ($dirs as $dir) {
                $parameterSegment = substr($dir, strrpos($dir, '/') + 1);
                $parameterType = substr($parameterSegment, strlen($this->config['parameterLeftDelimiter']),
                    strlen($parameterSegment) - strlen($this->config['parameterLeftDelimiter']) - strlen($this->config['parameterRightDelimiter'])
                );
                $filters = $this->config['parameterFilters'];
                // ユーザフィルタが定義されており、実行結果がNULL以外の場合は妥当なパラメータ値とする
                if (array_key_exists($parameterType, $filters)) {
                    $parameterValue = $filters[$parameterType]($segment);
                    if ($parameterValue !== null) {
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
            return [$parameterValue, $parameterSegment];
        }
        return false;
    }

}
