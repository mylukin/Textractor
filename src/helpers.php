<?php
/**
 * Created by PhpStorm.
 * User: lukin
 * Date: 27/02/2017
 * Time: 21:27
 */
if (function_exists('clear_space')) {
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
}

if (function_exists('mid')) {
    /**
     * 内容截取，支持正则
     *
     * $start,$end,$clear 支持正则表达式，“@”斜杠开头为正则模式
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
}

if (function_exists('close_tags')) {
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
}
