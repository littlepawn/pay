<?php
class Wxpay{
	private $wx_appid;
	private $mch_id;
	private $notify_url;

	public function __construct(){
		$this->wx_appid=123;	
		$this->mch_id=123;	
		$this->notify_url="https://example.com/index.php";	
	}

	public function wxpay_unified_order($order_id,$cost){
        $ip=$this->get_real_ip();
       
        if(empty($ip)){
            return false;
        }

        //STEP 1. 构造一个订单。
        $order=array(
            "body" => "昀魔方-活动报名",
            "appid" => $this->wx_appid,
            "device_info" => "WEB",
            "mch_id" => $this->mch_id,
            "nonce_str" => strval(mt_rand()),
            "notify_url" => $this->notify_url,
            "out_trade_no" => strval($order_id),
            "spbill_create_ip" => $ip,
            "total_fee" => intval($cost),//这里的最小单位时分
            "time_start"=>date("YmdHis",time()),
            "trade_type" => "APP"
        );
        ksort($order);
        //STEP 2. 签名
        $sign="";
        foreach ($order as $key => $value) {
            if($value&&$key!="sign"&&$key!="key"){
                $sign.=$key."=".$value."&";
            }
        }
        $sign.="key=".$this->partner_id;
        $sign=strtoupper(md5($sign));

        //STEP 3. 请求服务器
        $xml="<xml>\n";
        foreach ($order as $key => $value) {
            $xml.="<".$key.">".$value."</".$key.">\n";
        }
        $xml.="<sign>".$sign."</sign>\n";
        $xml.="</xml>";
        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: text/xml',
                'content' => $xml
            ),
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents('https://api.mch.weixin.qq.com/pay/unifiedorder', false, $context);
        $result = simplexml_load_string($result,null,LIBXML_NOCDATA);

        if($result->return_code!="SUCCESS"||$result->result_code!="SUCCESS") {
            return false;
        }

        //使用$result->nonce_str和$result->prepay_id。再次签名返回app可以直接打开的链接。
        $input=array(
            "appid" => $this->wx_appid,
            "noncestr"=>"".$result->nonce_str,
            "prepayid"=>"".$result->prepay_id,//上一步请求微信服务器得到nonce_str和prepay_id参数。
            "package"=>"Sign=WXPay",
            "partnerid"=>$this->partner_id,
            "timestamp"=>time(),
        );
        ksort($input);
        $sign="";
        foreach ($input as $key => $value) {
            if($value&&$key!="sign"&&$key!="key"){
                $sign.=$key."=".$value."&";
            }
        }
        $sign.="key=".$this->partner_id;
        $sign=strtoupper(md5($sign));
        $input['sign']=$sign;
        unset($input['appid']);
        return $input;   //app端请求支付需要的参数
    }

    private function get_real_ip(){
	    $ip=false;
	    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
	        $ip=$_SERVER['HTTP_CLIENT_IP'];
	    }
	    if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
	        $ips=explode (', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
	        if($ip){ array_unshift($ips, $ip); $ip=FALSE; }
	        for ($i=0; $i < count($ips); $i++){
	            if(!eregi ('^(10│172.16│192.168).', $ips[$i])){
	                $ip=$ips[$i];
	                break;
	            }
	        }
	    }
	    return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
	}

	/**
     * @param $order_id
     * @return state:
     * SUCCESS—支付成功   REFUND—转入退款  NOTPAY—未支付  CLOSED—已关闭  REVOKED—已撤销（刷卡支付） USERPAYING--用户支付中  PAYERROR--支付失败(其他原因，如银行返回失败)
     */
    //微信订单查询
    public function wx_order_query($order_id){
        $order=array(
            "appid" => $this->wx_appid,
            "mch_id" => $this->mch_id,
            "nonce_str" => mt_rand(),
            "out_trade_no" => strval($order_id),
        );
        ksort($order);
        //STEP 2. 签名
        $sign="";
        foreach ($order as $key => $value) {
            if($value&&$key!="sign"&&$key!="key"){
                $sign.=$key."=".$value."&";
            }
        }
        $sign.="key=".$this->partner_id;
        $sign=strtoupper(md5($sign));
        //STEP 3. 请求服务器
        $xml="<xml>\n";
        foreach ($order as $key => $value) {
            $xml.="<".$key.">".$value."</".$key.">\n";
        }
        $xml.="<sign>".$sign."</sign>\n";
        $xml.="</xml>";
        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: text/xml',
                'content' => $xml
            ),
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents('https://api.mch.weixin.qq.com/pay/orderquery', false, $context);
        $result = simplexml_load_string($result,null,LIBXML_NOCDATA);

        if($result->return_code!="SUCCESS"||$result->result_code!="SUCCESS") {
            return false;
        }

        if($result->trade_state!='SUCCESS')
            return false;
        return true;
    }
}