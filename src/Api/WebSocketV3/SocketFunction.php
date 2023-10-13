<?php
/**
 * @author lin <465382251@qq.com>
 * */

namespace Lin\Okex\Api\WebSocketV3;


trait SocketFunction
{
    //标记分隔符
    static $USER_DELIMITER='===';

    //对数据轮询 获取当前数据的订阅ID
    protected function getInstrumentId(array $array){
        if(isset($array['currency'])) return $array['currency'];
        if(isset($array['instrument_id'])) return $array['instrument_id'];

        foreach ($array as $v){
            if(is_array($v)) {
                $rlt=$this->getInstrumentId($v);
                if(!empty($rlt)) return $rlt;
            }
        }

        return ;
    }

    /**
     * 标记订阅的频道是否需要有登陆的KEY
     */
    protected function resub(array $sub=[]){
        $new_sub=[];
        $temp1=['account','position','order'];
        foreach ($sub as $v) {
            $temp2=[$v];
            foreach ($temp1 as $tv){
                if(strpos($v, $tv) !== false){
                    array_push($temp2,empty($this->keysecret)? [] : $this->keysecret);
                }
            }
            array_push($new_sub,$temp2);
        }

        return $new_sub;
    }

    /**
     * @param $global
     * @param $tag
     * @param $data
     * @param string $keysecret
     */
    protected function errorMessage($global,$tag,$data,$keysecret=''){
        $all_sub=$global->get('all_sub');
        if(empty($all_sub)) return;

        if($tag=='public') {
            //查询 message 是否包含了关键词。并把错误信息写入频道记录
            foreach ($all_sub as $k=>$v){
                if(is_array($v)) continue;
                $sub=strtolower($v);
                if(stristr(strtolower($data['message']),$v)!==false) $global->save($sub,$data);
            }
        }else{
            //如果是用户单独订阅，则该用户所有相关的订阅都显示该错误
            /*foreach ($all_sub as $k=>$v){
                if(!is_array($v)) continue;
                $sub=strtolower($v[0]);
                $global->add($keysecret['key'].$sub,$data);
            }*/
        }
    }

    protected function log($message){
        if (!is_string($message)) $message=json_encode($message);

        $time=time();
        $tiemdate=date('Y-m-d H:i:s',$time);

        $message=$tiemdate.' '.$message.PHP_EOL;

        if(isset($this->config['log'])){
            if(is_array($this->config['log']) && isset($this->config['log']['filename'])){
                $filename=$this->config['log']['filename'].date('Y-m-d',$time).'.txt';
            }else{
                $filename=date('Y-m-d',$time).'.txt';
            }

            file_put_contents($filename,$message,FILE_APPEND);
        }

        echo $message;
    }

    /**
     * 设置用户key
     * @param $keysecret
     */
    protected function userKey(array $keysecret,string $sub){
        return $keysecret['key'].self::$USER_DELIMITER.$sub;
    }

    /**
     * 重新订阅
     */
    private function reconnection($global,$type='public',array $keysecret=[]){
        $all_sub=$global->get('all_sub');
        if(empty($all_sub)) return;

        if($type=='public'){
            $temp=[];
            foreach ($all_sub as $v){
                if(!is_array($v)) $temp[]=$v;
            }
        }else{
            $this->keysecret=$keysecret;

            $temp=[];
            foreach ($all_sub as $v){
                if(is_array($v) && $keysecret['key']==$v[1]['key']){
                    $temp[]=$v[0];
                }
            }
        }

        $add_sub=$global->get('add_sub');
        if(empty($add_sub)) $global->save('add_sub',$this->resub($temp));
        else $global->save('add_sub',array_merge($this->resub($temp),$add_sub));
    }
}
