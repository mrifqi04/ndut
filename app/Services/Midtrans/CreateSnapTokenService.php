<?php
 
namespace App\Services\Midtrans;
 
use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
 
class CreateSnapTokenService extends Midtrans
{
    protected $order;
 
    public function __construct($order)
    {
        parent::__construct();
 
        $this->order = $order;
    }
 
    public function getSnapToken($gross_amount)
    {
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        $order = Order::with('member')->find($this->order->order_id);

        $params = [
            'transaction_details' => [
                'order_id' => $order->code,
                'gross_amount' => $gross_amount,
            ],
            'customer_details' => [
                'first_name' => $order->member->name,
                'email' => $order->member->email,                
            ]           
        ];
 
        $snapToken = Snap::getSnapToken($params);
 
        return $snapToken;
    }
}