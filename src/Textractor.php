<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 04/02/2017
 * Time: 13:17
 */

namespace Lukin\Textractor;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\TransferStats;

/**
 * 清除空白
 *
 * @param string $content
 * @return string
 */
function clear_space($content)
{
    if (strlen($content) == 0) return $content;
    $r = $content;
    $r = str_replace(array(chr(9), chr(10), chr(13)), '', $r);
    while (strpos($r, chr(32) . chr(32)) !== false || strpos($r, '&nbsp;') !== false) {
        $r = str_replace(array(
            '&nbsp;',
            chr(32) . chr(32),
        ),
            chr(32),
            $r
        );
    }
    return $r;
}

/**
 * 内容截取，支持正则
 *
 * $start,$end,$clear 支持正则表达式，“/”斜杠开头为正则模式
 * $clear 支持数组
 *
 * @param string $content 内容
 * @param string $start 开始代码
 * @param string $end 结束代码
 * @param string|array $clear 清除内容
 * @return string
 */
function mid($content, $start, $end = null, $clear = null)
{
    if (empty($content) || empty($start)) return null;
    if (strncmp($start, '@', 1) === 0) {
        if (preg_match($start, $content, $args)) {
            $start = $args[0];
        }
    }

    $start_len = strlen($start);
    $result = null;
    // 找到开始的位置
    $start_pos = stripos($content, $start);
    if ($start_pos === false) return null;
    // 获取剩余内容
    $remain_content = substr($content, -(strlen($content) - $start_pos - $start_len));
    if ($end === null) {
        $length = null;
    } else {
        // 正则查找结束符
        if ($end && strncmp($end, '@', 1) === 0) {
            if (preg_match($end, $remain_content, $args, PREG_OFFSET_CAPTURE)) {
                if ($args[0][1] == strlen($remain_content)) {
                    $end = null;
                } else {
                    $end = $args[0][0];
                }
            }
        }
        if ($end == null) {
            $length = null;
        } else {
            $length = stripos($remain_content, $end);
        }
    }

    if ($start_pos !== false) {
        if ($length === null) {
            $result = trim(substr($content, $start_pos + $start_len));
        } else {
            $result = trim(substr($content, $start_pos + $start_len, $length));
        }
    }

    if ($result && $clear) {
        if (is_array($clear)) {
            foreach ($clear as $v) {
                if (strncmp($v, '@', 1) === 0) {
                    $result = preg_replace($v, '', $result);
                } else {
                    if (strpos($result, $v) !== false) {
                        $result = str_replace($v, '', $result);
                    }
                }
            }
        } else {
            if (strncmp($clear, '@', 1) === 0) {
                $result = preg_replace($clear, '', $result);
            } else {
                if (strpos($result, $clear) !== false) {
                    $result = str_replace($clear, '', $result);
                }
            }
        }
    }
    return $result;
}

/**
 * 关闭html标签
 *
 * @param string $html
 * @return mixed|string
 */
function close_tags($html)
{
    if (preg_match_all("/<\/?(\w+)(?:(?:\s+(?:\w|\w[\w-]*\w)(?:\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/i", $html, $matches, PREG_OFFSET_CAPTURE)) {
        $stacks = array();
        foreach ($matches[0] as $i => $match) {
            $tagName = $matches[1][$i][0];
            if ($match[0]{strlen($match[0]) - 2} != '/') {
                // 出栈
                if ($match[0]{1} == '/') {
                    $data = array_pop($stacks);
                    if ($data) {
                        // 出栈要找到自己对应的 tagName
                        while ($tagName != $data[0]) {
                            $data = array_pop($stacks);
                        }
                        // 清理标签内没有内容的标签
                        $start = $data[1];
                        $length = $match[1] - $data[1] + strlen($match[0]);
                        $innerHTML = substr($html, $start, $length);
                        if (!preg_match('@<(
                                img|map|area|audio|embed|input|keygen|object|select|output|progress
                                )\s*@ix', $innerHTML) && strlen(trim(strip_tags($innerHTML))) == 0
                        ) {
                            // 清理标签
                            $html = substr_replace($html, str_repeat(' ', $length), $start, $length);
                        }
                    } else {
                        // 移除烂掉得标签
                        $length = strlen($match[0]);
                        $html = substr_replace($html, str_repeat(' ', $length), $match[1], $length);
                    }
                } else {
                    // 入栈
                    $stacks[] = array($tagName, $match[1], $match[0]);
                }
            }
        }

        // 如果栈里还有内容，则补全标签
        foreach ($stacks as $stack) {
            $html .= '</' . $stack[0] . '>';
        }
    }
    return $html;
}

/**
 * Class Textractor
 * @package Lukin\Textractor
 */
class Textractor
{
    // 按行分析的深度，默认为6 0-6行满足 limit_count
    private $depth = 6;
    // 字符限定数，当分析的文本数量达到限定数则认为进入正文内容
    private $limit_count = 180;
    // 确定文章正文头部时，向上查找，连续的空行到达 head_empty_lines，则停止查找
    private $head_empty_lines = 2;
    // 用于确定文章结束的字符数
    private $end_limit_char_count = 20;
    // 文章追加模式
    private $append_mode = false;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var string
     */
    private $html_source = '';

    /**
     * @var string
     */
    private $text_source = '';

    /**
     * @var Uri
     */
    private $url = '';

    public function __construct($config = null)
    {
        if (!is_null($config)) {
            $this->depth = isset($config['depth']) ? $config['depth'] : $this->depth;
            $this->limit_count = isset($config['limit_count']) ? $config['limit_count'] : $this->limit_count;
            $this->head_empty_lines = isset($config['head_empty_lines']) ? $config['head_empty_lines'] : $this->head_empty_lines;
            $this->end_limit_char_count = isset($config['end_limit_char_count']) ? $config['end_limit_char_count'] : $this->end_limit_char_count;
            $this->append_mode = isset($config['append_mode']) ? $config['append_mode'] : $this->append_mode;
        }
    }

    /**
     * 下载源码
     *
     * @param string $url
     * @param array $options
     * @return $this
     */
    public function download($url, $options = [])
    {
        $defaults = array_merge([
            'debug' => false,
            'cookies' => true,
            'headers' => [
                'Accept-Encoding' => 'gzip, deflate, sdch',
                'Accept-Language' => 'zh-CN,zh;q=0.8,en-US;q=0.6,en;q=0.4',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.98 Safari/537.36',
                'Referer' => $url,
            ],
            'connect_timeout' => 10,
            'timeout' => 10,
        ], $options);

        $client = new Client($defaults);

        try {
            $this->response = $client->get($url, [
                'on_stats' => function (TransferStats $stats) use (&$url) {
                    $url = $stats->getEffectiveUri();
                }
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->response = $e->getResponse();
            } else {
                $this->response = null;
            }
        }

        $this->url = $url;

        return $this;
    }

    /**
     * 解析html
     *
     * @param string $content
     * @return $this
     */
    public function parse($content = null)
    {
        // 转换编码
        if (!$content && $this->response instanceof Response) {
            $content = (string)$this->response->getBody();
            $charset = null;
            $content_type = $this->response->getHeaderLine('Content-Type');
            if (preg_match('@charset=([^\s\,\;]+)@i', $content_type, $matches)) {
                $charset = $matches[1];
            }
            if (!$charset && preg_match('@charset=["\']?([^"\']+)@i', $content, $matches)) {
                $charset = $matches[1];
            }
            // 编码不同，需要转换
            $to = 'UTF-8';
            $from = strtoupper($charset) == 'UTF8' ? 'UTF-8' : $charset;
            if ($from != $to) {
                if (function_exists('iconv')) {
                    $to = substr($to, -8) == '//IGNORE' ? $to : $to . '//IGNORE';
                    $content = iconv($from, $to, $content);
                } elseif (function_exists('mb_convert_encoding')) {
                    $content = mb_convert_encoding($content, $to, $from);
                }
            }
        }

        // 处理压缩过的html
        if (substr_count($content, PHP_EOL) < 10) {
            //$content = str_replace(">", ">\n", $content);
            $content = preg_replace('@</div>@', '$0' . PHP_EOL, $content);
        }
        // 转换html实体
        $content = html_entity_decode($content, ENT_COMPAT, 'utf-8');
        // decimal notation
        $content = preg_replace_callback('/&#(\d+);/m', function ($matches) {
            return chr($matches[1]);
        }, $content);
        // hex notation
        $content = preg_replace_callback('/&#x([a-f0-9]+);/mi', function ($matches) {
            return chr('0x' . $matches[1]);
        }, $content);
        // 清理标签
        $content = preg_replace([
            '@<style[^>]*?>.*?<\/style>@si',
            '@<script[^>]*?>.*?<\/script>@si',
            '@<!--.*?-->@si',
            '@</?iframe[^>]*?>@si',
        ], '', $content);

        $content = preg_replace('@(</[^>]+>)<@', '$1' . PHP_EOL . '<', $content);

        $this->html_source = $content;

        $this->text_source = strip_tags($content);

        return $this;
    }

    /**
     * 解析内容
     *
     * @return array
     */
    private function parse_source()
    {
        $text_result = [];
        $html_result = [];
        // 记录上一次统计的字符数量（lines就是去除html标签后的文本，_limitCount是阈值，_depth是我们要分析的深度，sb用于记录正文）
        $pre_text_len = 0;
        // 记录文章正文的起始位置
        $start_pos = -1;
        // 拆分
        $text_lines = explode(PHP_EOL, $this->text_source);
        $html_lines = explode(PHP_EOL, $this->html_source);
        // 总行数
        $text_lines_count = count($text_lines);
        $html_lines_count = count($html_lines);
        // 处理分割
        for ($i = 0; $i < $text_lines_count - $this->depth; $i++) {
            $len = 0;
            // 字符串长度
            for ($n = 0; $n < $this->depth; $n++) {
                $len += strlen(trim($text_lines[$i + $n]));
            }

            // 还没有找到文章起始位置，需要判断起始位置
            if ($start_pos == -1) {
                if ($pre_text_len > $this->limit_count && $len > 0) {
                    // 如果上次查找的文本数量超过了限定字数，且当前行数字符数不为0，则认为是开始位置
                    // 查找文章起始位置, 如果向上查找，发现2行连续的空行则认为是头部
                    $empty_count = 0;
                    for ($k = $i - 1; $k > 0; $k--) {
                        if (!isset($lines[$k])) {
                            continue;
                        }
                        if (strlen(trim($lines[$k])) == 0) {
                            $empty_count++;
                        } else {
                            $empty_count = 0;
                        }

                        if ($empty_count == $this->head_empty_lines) {
                            $start_pos = $k + $this->head_empty_lines;
                            break;
                        }
                    }

                    // 如果没有定位到文章头，则以当前查找位置作为文章头
                    if ($start_pos == -1) {
                        $start_pos = $i;
                    }
                    // 填充发现的文章起始部分
                    for ($j = $start_pos; $j <= $i; $j++) {
                        $text_result[] = $text_lines[$i];
                        $html_result[] = $html_lines[$i];
                    }
                }
            } else {
                // 当前长度为0，且上一个长度也为0，则认为已经结束
                if ($len <= $this->end_limit_char_count && $pre_text_len < $this->end_limit_char_count) {
                    //追加模式
                    if (!$this->append_mode) {
                        break;
                    }
                    $start_pos = -1;
                }

                $text_result[] = $text_lines[$i];
                $html_result[] = $html_lines[$i];
            }

            $pre_text_len = $len;
        }
        return [
            'text' => implode(PHP_EOL, $text_result),
            'html' => close_tags(implode(PHP_EOL, $html_result)),
        ];
    }

    /**
     * 获取文本
     *
     * @return string
     */
    public function getText()
    {
        return $this->parse_source()['text'];
    }

    /**
     * 获取html
     *
     * @return string
     */
    public function getHTML()
    {
        return $this->parse_source()['html'];
    }

    /**
     * 获取发布日期
     *
     * @return string
     */
    public function getPublishDate()
    {
        $result = null;
        // h1 上下行深度
        $depth = 10;
        // 拆分
        $text_lines = explode(PHP_EOL, $this->text_source);
        $html_lines = explode(PHP_EOL, $this->html_source);
        $html_lines_count = count($html_lines);
        // 处理分割
        for ($i = 0; $i < $html_lines_count; $i++) {
            if (preg_match('@<h1[^>]*>@i', $html_lines[$i])) {
                $wait_lines = [];
                // 先向下找n行
                for ($j = $i + 1; $j <= $i + $depth; $j++) {
                    $wait_lines[] = $j;
                }
                // 再向上找n行
                for ($j = $i - 1; $j >= $i - $depth; $j--) {
                    $wait_lines[] = $j;
                }
                // 开始匹配
                foreach ($wait_lines as $line) {
                    // 读取当前行
                    $text_line = $text_lines[$line];
                    // 匹配日期 2017-02-04 08:39
                    if (preg_match('@\d{4}\-\d{2}\-\d{2}\s+\d{2}\:\d{2}@i', $text_line, $matches)) {
                        $result = $matches[0];
                        break;
                    }
                    // 02月04日 15:46
                    if (preg_match('@\d{2}月\d{2}日\s+\d{2}\:\d{2}@i', $text_line, $matches)) {
                        $result = $matches[0];
                        break;
                    }
                }
                break;
            }
        }
        return $result;
    }

    /**
     * 获取标题
     *
     * @return string
     */
    public function getTitle()
    {
        $html_source = clear_space($this->html_source);
        $title = mid($html_source, '@<h1[^>]*>@i', '@</h1>@i');
        if (!$title) {
            $title = mid($html_source, '@<title[^>]*>@i', '@</title>@i');
        }
        return $title;
    }
}