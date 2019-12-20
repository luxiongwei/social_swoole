<?php
namespace App\Util\SocialApi;

/**
 * 封装与杭州进行HTTP操作的类
 *
 * @author Luxw
 */
class HttpClient extends Curl 
{

    public $host;
    public $key;
    public $curlHeaders = array('Content-Type' => 'application/json; charset=utf-8');
    
    public function __construct()
    {
        $this->setHeaders($this->curlHeaders);
    }

    /**
     * POST
     */
    public function post($uri, $params = array())
    {
        $sign = $this->make_sign($params);
        $url = "{$this->host}/" . trim($uri, '/');

        $items['sign'] = $sign;
        $body = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->setRequestBody($body);
        
        $result = parent::post($url, false);

        return $result;
    }

    /**
     * GET
     */
    public function get($uri, $params = array())
    {
        $signData = $items = $params;
        if (isset($signData['r'])){
            unset($signData['r']);
        }
        
        $sign = $this->make_sign($signData);
        $items['sign'] = $sign;
        foreach ($items as $key => $item)
        {
            $items[$key] = "{$key}=$item";
        }

        $url = "{$this->host}/" . trim($uri, '/') . '?' . implode("&", $items);

        $result = parent::get($url, false);

        return $result;
    }

    /**
     * 生成签名
     * 
     * @access protected
     * @param string $data 请求参数
     * @return string 签名字符串
     */
    protected function make_sign($data)
    {
        return ApiSign::MakeSign($data, $this->key);
    }
}
