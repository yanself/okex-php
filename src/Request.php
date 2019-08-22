<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Okex;

use GuzzleHttp\Exception\RequestException;
use Lin\Okex\Exceptions\Exception;

class Request
{
    protected $key='';
    
    protected $secret='';
    
    protected $host='';
    
    protected $passphrase='';
    
    
    
    protected $nonce='';
    
    protected $signature='';
    
    protected $headers=[];
    
    protected $type='';
    
    protected $path='';
    
    protected $data=[];
    
    protected $options=[];
    
    public function __construct(array $data)
    {
        $this->key=$data['key'] ?? '';
        $this->secret=$data['secret'] ?? '';
        $this->passphrase = $data['passphrase'] ?? '';
        $this->host=$data['host'] ?? 'https://www.okex.com/';
        
        $this->options=$data['options'] ?? [];
    }
    
    /**
     * 认证
     * */
    protected function auth(){
        $this->nonce();
        
        $this->signature();
        
        $this->headers();
        
        $this->options();
    }
    
    /**
     * 过期时间
     * */
    protected function nonce(){
        $this->nonce=gmdate('Y-m-d\TH:i:s\.000\Z');
    }
    
    /**
     * 签名
     * */
    protected function signature(){
        $body='';
        $path=$this->type.$this->path;
        
        if(!empty($this->data)) {
            if($this->type=='GET') {
                $path.='?'.http_build_query($this->data);
            }else{
                $body=json_encode($this->data);
            }
        }
        
        $this->signature = base64_encode(hash_hmac('sha256', $this->nonce.$path.$body, $this->secret, true));
    }
    
    /**
     * 默认头部信息
     * */
    protected function headers(){
        $this->headers=[
            'Content-Type' => 'application/json',
        ];
        
        if(!empty($this->key) && !empty($this->secret)) {
            $this->headers=array_merge($this->headers,[
                'OK-ACCESS-KEY'=> $this->key,
                'OK-ACCESS-TIMESTAMP'=>$this->nonce,
                'OK-ACCESS-PASSPHRASE' =>$this->passphrase,
                'OK-ACCESS-SIGN' => $this->signature,
            ]);
        }
    }
    
    /**
     * 请求设置
     * */
    protected function options(){
        $this->options=array_merge([
            'headers'=>$this->headers,
            //'verify'=>false   //关闭证书认证
        ],$this->options);
        
        $this->options['timeout'] = $this->options['timeout'] ?? 60;
        
        if(isset($this->options['proxy']) && $this->options['proxy']===true) {
            $this->options['proxy']=[
                'http'  => 'http://127.0.0.1:12333',
                'https' => 'http://127.0.0.1:12333',
                'no'    =>  ['.cn']
            ];
        }
    }
    
    /**
     * 发送http
     * */
    protected function send(){
        $client = new \GuzzleHttp\Client();
        
        $url=$this->host.$this->path;
        
        if(!empty($this->data)) {
            if($this->type=='GET') {
                $url.='?'.http_build_query($this->data);
            }else{
                $this->options['body']=json_encode($this->data);
            }
        }
        
        $response = $client->request($this->type, $url, $this->options);
        
        return $response->getBody()->getContents();
    }
    
    /*
     * 执行流程
     * */
    protected function exec(){
        $this->auth();
        
        //可以记录日志
        try {
            return json_decode($this->send(),true);
        }catch (RequestException $e){
            if(method_exists($e->getResponse(),'getBody')){
                $contents=$e->getResponse()->getBody()->getContents();
                
                $temp=json_decode($contents,true);
                if(!empty($temp)) {
                    $temp['_method']=$this->type;
                    $temp['_url']=$this->host.$this->path;
                }else{
                    $temp['_message']=$e->getMessage();
                }
            }else{
                $temp['_message']=$e->getMessage();
            }
            
            $temp['_httpcode']=$e->getCode();
            
            //TODO  该流程可以记录各种日志
            throw new Exception(json_encode($temp));
        }
    }
}