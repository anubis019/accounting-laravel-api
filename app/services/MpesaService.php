<?php
namespace App\Services;

use App\Models\MpesaTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MpesaService
{
    private $baseUrl, $consumerKey, $consumerSecret, $shortcode, $passkey, $initiatorName, $securityCredential;
    public function __construct()
    {
        $this->environment = config('mpesa.environment','sandbox');
        $this->baseUrl = $this->environment === 'production' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->shortcode = config('mpesa.shortcode');
        $this->passkey = config('mpesa.passkey');
        $this->initiatorName = config('mpesa.initiator_name');
        $this->securityCredential = config('mpesa.security_credential');
    }

    private function getAccessToken()
    {
        return Cache::remember('mpesa_access_token', 3300, function(){
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get($this->baseUrl.'/oauth/v1/generate?grant_type=client_credentials');
            if($response->successful()) return $response->json()['access_token'];
            Log::error('M-PESA token failed: '.$response->body());
            return null;
        });
    }

    private function formatPhone($phone){
        $phone = preg_replace('/[^0-9]/','',$phone);
        if(substr($phone,0,1)=='0') return '254'.substr($phone,1);
        if(substr($phone,0,3)!='254') return '254'.$phone;
        return $phone;
    }

    private function getTimestamp(){ return date('YmdHis'); }

    public function stkPush($phone, $amount, $accountRef, $desc, $userId=null)
    {
        $token = $this->getAccessToken();
        if(!$token) return ['error'=>'No token'];
        $phone = $this->formatPhone($phone);
        $timestamp = $this->getTimestamp();
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);
        $payload = [
            'BusinessShortCode'=>$this->shortcode,'Password'=>$password,'Timestamp'=>$timestamp,
            'TransactionType'=>'CustomerPayBillOnline','Amount'=>(int)$amount,'PartyA'=>$phone,
            'PartyB'=>$this->shortcode,'PhoneNumber'=>$phone,
            'CallBackURL'=>config('mpesa.callback_url'),
            'AccountReference'=>substr($accountRef,0,12),'TransactionDesc'=>substr($desc,0,13)
        ];
        $response = Http::withToken($token)->post($this->baseUrl.'/mpesa/stkpush/v1/processrequest',$payload);
        if($response->successful()){
            $res = $response->json();
            MpesaTransaction::create([
                'id'=>Str::uuid(),'user_id'=>$userId,'merchant_request_id'=>$res['MerchantRequestID'],
                'checkout_request_id'=>$res['CheckoutRequestID'],'amount'=>$amount,'phone_number'=>$phone,
                'account_reference'=>$accountRef,'status'=>'pending','type'=>'stk_push'
            ]);
            return ['success'=>true,'checkout_request_id'=>$res['CheckoutRequestID'],'response_desc'=>$res['ResponseDescription']];
        }
        return ['error'=>'STK push failed'];
    }

    public function stkPushQuery($checkoutRequestId){
        $token = $this->getAccessToken();
        if(!$token) return ['error'=>'No token'];
        $timestamp = $this->getTimestamp();
        $password = base64_encode($this->shortcode.$this->passkey.$timestamp);
        $payload = ['BusinessShortCode'=>$this->shortcode,'Password'=>$password,'Timestamp'=>$timestamp,'CheckoutRequestID'=>$checkoutRequestId];
        $response = Http::withToken($token)->post($this->baseUrl.'/mpesa/stkpushquery/v1/query',$payload);
        if($response->successful()){
            $res = $response->json();
            MpesaTransaction::where('checkout_request_id',$checkoutRequestId)->update([
                'status'=>$res['ResultCode']==0?'completed':'failed','result_code'=>$res['ResultCode'],'result_desc'=>$res['ResultDesc']??null
            ]);
            return $res;
        }
        return ['error'=>'Query failed'];
    }

    public function handleStkCallback($data){
        $resultCode = $data['Body']['stkCallback']['ResultCode'];
        $checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'];
        $txn = MpesaTransaction::where('checkout_request_id',$checkoutRequestId)->first();
        if(!$txn) return;
        if($resultCode==0){
            $metadata = $data['Body']['stkCallback']['CallbackMetadata']['Item'];
            $amount = $this->getMetadataValue($metadata,'Amount');
            $receipt = $this->getMetadataValue($metadata,'MpesaReceiptNumber');
            $txn->update(['status'=>'completed','mpesa_receipt_number'=>$receipt,'transaction_date'=>now(),'completed_at'=>now()]);
            if($txn->user_id){
                \App\Models\Transaction::create([
                    'id'=>Str::uuid(),'user_id'=>$txn->user_id,
                    'category_id'=>\App\Models\Category::firstOrCreate(['user_id'=>$txn->user_id,'name'=>'M-PESA Payments','type'=>'income'],['color'=>'#00a81f'])->id,
                    'type'=>'income','amount'=>$amount,'description'=>"M-PESA Payment {$txn->account_reference}",
                    'transaction_date'=>now(),'mpesa_receipt'=>$receipt,'is_verified'=>true
                ]);
            }
        } else {
            $txn->update(['status'=>'failed','result_desc'=>$data['Body']['stkCallback']['ResultDesc']]);
        }
    }

    private function getMetadataValue($metadata,$key){
        foreach($metadata as $item) if($item['Name']==$key) return $item['Value'];
        return null;
    }
}