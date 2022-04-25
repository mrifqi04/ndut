<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Midtrans\Config;
use Midtrans\Snap;
use Exception;

class MidtransController extends Controller
{
    public function checkout(Request $request) {

        $request->validate([
            'food_id' => 'required|exists:food,id',
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required'
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'payment_url' => ''
        ]);

        // Konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        $transaction = Transaction::with(['food', 'user'])->find($transaction->id);

        // Membuat transaksi midtrans
        $midtrans = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => (int) $transaction->total,
            ], 
            'customer_detail' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email
            ],
            'enable_payments' => [ 'gopay', 'bank_transfer' ],
            'vtweb' => []
        ];

        // Memanggil midtrans
        try {
            // Ambil halaman payment midtrans
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            // Mengembalikan data ke API
            return ResponseFormatter::success($transaction, 'Transaction successfully');

        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 'Transaction failed');
        }
    }
}
