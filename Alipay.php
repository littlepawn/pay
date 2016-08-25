<?php
class Alipay{
	private $ali_appid;
	private $rsa_private_key_path;    //私钥
	private $alipay_rsa_public_key_path;   //支付宝公钥
	private $notify_url;
	private $partner;   //支付宝id 同seller
	private $seller;

	public function __construct(){
		$this->ali_appid=123;	
		$this->partner=123;	
		$this->seller=123;	
		$this->rsa_private_key_path="/path/to";	
		$this->alipay_rsa_public_key_path="/path/to";	
		$this->notify_url="https://example.com/index.php";	
	}

    //根据sdk支付宝构造参数
    public function alipay_unified_orde($order_id,$cost){
        $privateKey=file_get_contents($this->rsa_private_key_path);
        $dataString=sprintf('partner="%s"&seller_id="%s"&out_trade_no="%s"&subject="昀魔方-活动报名"&body="%s"&total_fee="%.2f"&notify_url="%s"&service="mobile.securitypay.pay"&payment_type="1"&_input_charset="utf-8"&it_b_pay="30m"',$this->partner,$this->seller,$order_id,"昀魔方-活动报名",$cost,$this->notify_url);
        //获取签名
        $res = openssl_get_privatekey($privateKey);
        openssl_sign($dataString, $sign, $res);
        openssl_free_key($res);
        $sign = urlencode(base64_encode($sign));
        $dataString.='&sign="'.$sign.'"&sign_type="RSA"';

        return $dataString;
    }

	//支付宝订单查询   此处用了支付宝php的sdk
    public function alipay_order_query($order_id){
        $aop = new AopClient();
        $aop->gatewayUrl='https://openapi.alipay.com/gateway.do';
        $aop->appId=$this->ali_appid;
        $aop->rsaPrivateKeyFilePath=$this->rsa_private_key_path;
        $aop->alipayPublicKey=$this->alipay_rsa_public_key_path;     //此处为开放平台公钥
        $aop->apiVersion = '1.0';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $request = new AlipayTradeQueryRequest();
        $request->setBizContent("{".
            "\"out_trade_no\":\"{$order_id}\"" .
        "}");
        
        $result = $aop->execute($request);
        if(!isset($result->alipay_trade_query_response->trade_status))
            return false;

        if($result->alipay_trade_query_response->trade_status!='TRADE_SUCCESS')
            return false;

        return true;
    }
}