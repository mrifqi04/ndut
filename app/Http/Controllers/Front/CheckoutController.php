<?php

namespace App\Http\Controllers\Front;

use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Order;
use Midtrans\Notification;
use App\Models\BankAccount;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\CartRepository;
use App\Services\Midtrans\CreateSnapTokenService;

class CheckoutController extends Controller
{
    public function form(Request $request, CartRepository $cart)
    {
        $user = auth()->user();

        return view('front.pages.checkout.form', [
            'items' => $cart->all(),
            'addresses' => $user->member_addresses,
        ]);
    }

    public function submit(Request $request, CartRepository $cart)
    {
        $this->validate($request, [
            'member_address_id' => 'required|exists:member_addresses,member_address_id'
        ]);

        $user = auth()->user();
        $address = $user->member_addresses()->where('member_address_id', $request->get('member_address_id'))->first();
        if (!$address) {
            return back()->with('danger', "Alamat tidak terdaftar.");
        }

        $items = $cart->all();
        if (!count($items)) {
            return back()->with('danger', 'Keranjang belanja anda kosong.');
        }

        $order = new Order;
        $order->code = Order::generateCode();
        $order->member_user_id = $user->user_id;
        $order->member_address_id = $address->member_address_id;
        $order->shipping_cost = $address->shipping_cost ? $address->shipping_cost->cost : 0;
        $order->phone = $address->phone;
        $order->province_id = $address->province_id;
        $order->regency_id = $address->regency_id;
        $order->district_id = $address->district_id;
        $order->subdistrict_id = $address->subdistrict_id;
        $order->address = $address->address;
        $order->status = Order::STATUS_PENDING;
        $order->save();

        foreach ($items as $item) {
            $order_detail = new OrderDetail;
            $order_detail->order_id = $order->order_id;
            $order_detail->product_id = $item['product']->product_id;
            $order_detail->price = $item['product']->price;
            $order_detail->qty = $item['qty'];

            $gross_amount = $item['product']->price * $item['qty'] + $order->shipping_cost;
            $order_detail->save();
        }

        $payment_url = $order->payment_url;
        if (empty($payment_url)) {
            // Jika snap token masih NULL, buat token snap dan simpan ke database

            $midtrans = new CreateSnapTokenService($order);
            $snapToken = $midtrans->getSnapToken($gross_amount);

            $order->payment_url = $snapToken;
            $order->save();

            $cart->clear();

            return redirect()->route('front::checkout.success', ['order_code' => $order->code]);
        }
    }

    public function success(Request $request, $order_code)
    {
        $user = auth()->user();
        $order = $user->orders()->where('code', $order_code)->first();
        if (!$order) {
            return abort(404, "Pesanan tidak ditemukan");
        }

        $bank_accounts = BankAccount::all();

        return view('front.pages.checkout.success', [
            'order' => $order,
            'bank_accounts' => $bank_accounts,
        ]);
    }

    public function callback()
    {        
        // Set konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Buat instance Midtranas notification
        $notification = new Notification;
        
        // Assign ke variabel
        $status = $notification->transaction_status;
        $type = $notification->payment_type;
        $fraud = $notification->fraud_status;
        $order_id = $notification->order_id;

        // Cari transaksi berdasarkan id
        $order = Order::where('code', $order_id)->first();

        // Handle status notifikasi Midtrans
        if ($status == 'capture')
        {
            if ($type == 'credit_card')
            {
                if($fraud == 'challenge')
                {
                    $order->status = 'PENDING';
                }
                else
                {
                    $order->status = 'SUCCESS';
                }
            }
        }
        else if ($status == 'settlement'){
            $order->status = 'processing';
        }
        else if ($status == 'pending')
        {
            $order->status = 'PENDING';
        }
        else if ($status == 'deny')
        {
            $order->status = 'CANCELLED';
        }
        else if ($status == 'expire')
        {
            $order->status = 'CANCELLED';
        }
        else if ($status == 'cancel')
        {
            $order->status = 'CANCELLED';
        }

        // Simpan transaksi
        $order->save();
    }
}
