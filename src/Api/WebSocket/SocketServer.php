<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Okex\Api\WebSocket;

use Lin\Okex\Api\WebSocket\SocketGlobal;
use Lin\Okex\Api\WebSocket\SocketFunction;
use Workerman\Lib\Timer;
use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

class SocketServer
{
    use SocketGlobal;
    use SocketFunction;

    private $worker;

    private $connection=[];
    private $connectionIndex=0;

    public function start(){
        $this->worker = new Worker();
        $this->server();

        $this->worker->onWorkerStart = function() {
            $this->addConnection('public');
        };

        Worker::runAll();
    }

    private function addConnection(string $tag,array $keysecret=[]){
        $this->newConnection()($tag,$keysecret);
    }

    private function newConnection(){
        return function($tag,$keysecret){
            $global=$this->client();

            $this->connection[$this->connectionIndex] = new AsyncTcpConnection('ws://real.okex.com:8443/ws/v3');
            $this->connection[$this->connectionIndex]->transport = 'ssl';

            //自定义属性
            $this->connection[$this->connectionIndex]->tag=$tag;//标记公共连接还是私有连接
            if(!empty($keysecret)) $this->connection[$this->connectionIndex]->tag_keysecret=$keysecret;//标记私有连接

            $this->connection[$this->connectionIndex]->onConnect=$this->onConnect($keysecret);
            $this->connection[$this->connectionIndex]->onMessage=$this->onMessage($global);
            $this->connection[$this->connectionIndex]->onClose=$this->onClose();
            $this->connection[$this->connectionIndex]->onError=$this->onError();

            $this->connect($this->connection[$this->connectionIndex]);
            $this->ping($this->connection[$this->connectionIndex]);
            $this->other($this->connection[$this->connectionIndex],$global);

            $this->connectionIndex++;
        };
    }

    private function onConnect(array $keysecret){
        return function($con) use($keysecret){
            if(empty($keysecret)) return;

            $timestamp=round(microtime(true)*1000)/1000+10;

            $message = $timestamp.'GET/users/self/verify';
            $sign=base64_encode(hash_hmac('sha256', $message, $keysecret['secret'], true));
            $data = json_encode([
                'op' => "login",
                'args' => [$keysecret['key'], $keysecret['passphrase'], $timestamp, $sign]
            ]);

            echo $keysecret['key'].' new connect send'.PHP_EOL;

            $con->send($data);
        };
    }

    private function onMessage($global){
        return function($con,$data) use($global){
            // 解压数据
            $data = gzinflate($data);
            $data=json_decode($data,true);

            if(isset($data['table'])) {
                $table=$data['table'].':'.$this->getInstrumentId($data);
                $table=strtolower($table);
                if($con->tag=='private') $table=$con->tag_keysecret['key'].$table;

                $global->add($table,$data);
                return;
            }

            if(isset($data['event'])) {
                if($data['event']=='error') {

                    print_r($data);
                    echo PHP_EOL;

                    $this->errorMessage($global,$con->tag,$data,isset($con->tag_keysecret)?$con->tag_keysecret:'');

                    return ;
                }
            }

            if(isset($data['success'])) {
                print_r($data);
                echo PHP_EOL;

                //******登陆成功后，keysecret  private 状态
                $keysecret=$con->tag_keysecret;
                $global->keysecretUpdate($keysecret['key'],1);

                return ;
            }
        };
    }

    private function onClose(){
        return function($con){
            //这里连接失败 会轮询 connect
            $con->reConnect(5);
        };
    }

    private function onError(){
        return function($con, $code, $msg){

        };
    }

    private function connect($con){
        $con->connect();
    }

    private function ping($con){
        Timer::add(20, function() use ($con) {
            $con->send("ping");

            echo $con->tag.' ping'.PHP_EOL;
        });
    }

    private function other($con,$global){
        Timer::add(2, function() use($con,$global) {
            $this->subscribe($con,$global);

            $this->unsubscribe($con,$global);

            echo  'listen '.$con->tag.PHP_EOL;
        });
    }

    private function subscribe($con,$global){
        if(empty($global->get('add_sub'))) return;

        if($con->tag=='public'){
            //公共订阅 并 触发私有连接
            $this->subscribePublic($con,$global);
        }else{
            $this->subscribePrivate($con,$global);
        }

        return;
    }

    private function subscribePublic($con,$global){
        $sub=[
            'public'=>[],
            'private'=>[],
        ];

        $temp=$global->get('add_sub');
        foreach ($temp as $v){
            if(count($v)>1) $sub['private'][$v[1]['key']]=$v[1];
            else array_push($sub['public'],$v[0]);
        }

        //有私有频道先去建立新连接 即登陆
        foreach ($sub['private'] as $v){
            $this->login($global,$v);
        }

        //公共连接 标记订阅频道是否有改变。
        if(empty($sub['public'])) {
            echo 'public return '.PHP_EOL;
            return;
        }


        //判断当前是否已经重复订阅。可以无所谓。
        $data=[
            'op' => "subscribe",
            'args' => $sub['public'],
        ];

        $data=json_encode($data);

        print_r($data);
        echo 'public subscribe send'.PHP_EOL;

        $con->send($data);

        //*******订阅成功后，删除add_sub  public 值
        $global->addSubUpdate('public');

        //*******订阅成功后 更改 all_sub  public 值
        $global->allSubUpdate('public',['sub'=>$sub['public']]);
    }

    private function subscribePrivate($con,$global){
        $sub=[];
        $keysecret=$con->tag_keysecret;
        $temp=$global->get('add_sub');
        //判断是否是私有连接，并判断该私有连接是否是  当前用户。
        foreach ($temp as $v){
            $key=$v[1]['key'];
            if(count($v)>1 && $key==$keysecret['key']) $sub[]=$v[0];
        }

        if(empty($sub)) {
            echo 'subscribePrivate return'.PHP_EOL;
            return;
        }

        //**********判断是否已经登陆
        $client_keysecret=$global->get('keysecret');
        $keysecret=$con->tag_keysecret;
        if($client_keysecret[$keysecret['key']]['login']!=1) {
            echo 'subscribePrivate no login return'.$keysecret['key'].PHP_EOL;
            return;
        }

        echo $keysecret['key'].json_encode($sub).PHP_EOL;

        $data=[
            'op' => "subscribe",
            'args' => $sub,
        ];

        $data=json_encode($data);

        print_r($data);
        echo 'private subscribe send '.$keysecret['key'].PHP_EOL;

        $con->send($data);

        //*******订阅成功后，删除add_sub   值
        $global->addSubUpdate('private',['user_key'=>$keysecret['key']]);


        //*******订阅成功后 更改 all_sub   值
        $global->allSubUpdate('private',['sub'=>$temp]);

        return;
    }

    private function unsubscribe($con,$global){
        if(empty($this->get('del_sub'))) return;

        if($con->tag=='public'){
            //公共订阅 并 触发私有连接
            $this->unsubscribePublic($con,$global);
        }else{
            $this->unsubscribePrivate($con,$global);
        }

        return;
    }

    private function unsubscribePublic($con,$global){
        $unsub=[];
        $temp=$this->get('del_sub');
        foreach ($temp as $v){
            if(count($v)==1) $unsub[]=$v[0];
        }

        if(empty($unsub)) {
            echo 'unsubscribePublic return'.PHP_EOL;
            return;
        }

        //判断当前是否已经重复订阅。可以无所谓。
        $data=[
            'op' => "unsubscribe",
            'args' => $unsub,
        ];

        $data=json_encode($data);

        print_r($data);
        echo 'public unsubscribe send'.PHP_EOL;

        $con->send($data);


        //*******订阅成功后，删除del_sub  public 值
        $global->delSubUpdate('public');


        //*******订阅成功后 更改 all_sub  public 值
        $global->unAllSubUpdate('public',['sub'=>$unsub]);

    }

    private function unsubscribePrivate($con,$global){
        $unsub=[];
        $keysecret=$con->tag_keysecret;
        $temp=$global->get('del_sub');
        //判断是否是私有连接，并判断该私有连接是否是  当前用户。
        foreach ($temp as $v){
            $key=$v[1]['key'];
            if(count($v)>1 && $key==$keysecret['key']) $unsub[]=$v[0];
        }

        if(empty($unsub)) {
            echo 'unsubscribePrivate return'.PHP_EOL;
            return;
        }

        echo $keysecret['key'].json_encode($unsub).PHP_EOL;

        $data=[
            'op' => "unsubscribe",
            'args' => $unsub,
        ];

        $data=json_encode($data);

        print_r($data);
        echo 'private unsubscribe send '.$keysecret['key'].PHP_EOL;

        $con->send($data);


        //*******订阅成功后，删除add_sub   值
        $global->delSubUpdate('private',['user_key'=>$keysecret['key']]);

        //*******订阅成功后 更改 all_sub   值
        $global->unAllSubUpdate('private',['sub'=>$temp]);
    }

    private function login($global,$keysecret){
        //判断是否已经登陆
        $old_client_keysecret=$global->get('keysecret');
        if($old_client_keysecret[$keysecret['key']]['login']==1) {
            echo 'private already login return'.$keysecret['key'].PHP_EOL;
            return;
        }

        if($old_client_keysecret[$keysecret['key']]['login']==2) {
            echo 'private doing return'.$keysecret['key'].PHP_EOL;
            return;
        }

        echo 'private new connection'.PHP_EOL;


        //**********如果登陆失败，事件监听会再次 执行轮询 创建新连接，所以必须要有正在登陆中的状态标记
        $global->keysecretUpdate($keysecret['key'],2);

        //当前连接是公共连接才允许建立新的私有连接
        $this->addConnection($keysecret['key'],$keysecret);
    }
}
