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
     * get response
     *
     * @return Response
     */
    public function getResponse() {
        return $this->response;
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
     * 获取html源码
     *
     * @return string
     */
    public function getHTMLSource() {
        return $this->html_source;
    }

    /**
     * 获取Text源码
     *
     * @return string
     */
    public function getTextSource() {
        return $this->text_source;
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
        // 记录上一次统计的字符数量（text_lines就是去除html标签后的文本，_limitCount是阈值，_depth是我们要分析的深度，sb用于记录正文）
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
                        if (!isset($text_lines[$k])) {
                            continue;
                        }
                        if (strlen(trim($text_lines[$k])) == 0) {
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