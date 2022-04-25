<?php

namespace App\Http\Controllers;

use App\Libraries\ItemBasedCF;
use App\Libraries\PearsonCorrelation;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Prediction;
use Illuminate\Support\Facades\Artisan;

class DevController extends Controller
{
    public function index(Request $request)
    {
        $members = User::where('type', 'member')->orderBy('user_id')->get();
        $products = Product::orderBy('product_id')->take(10)->get();

        $predictions = Prediction::with(['product', 'user'])->get();

        return view('dev.index', [
            'members' => $members,
            'products' => $products,
            'predictions' => $predictions,
        ]);
    }

    public function pearsonCorrelation(Request $request)
    {
        $productId1 = $request->get('product_id_1');
        $productId2 = $request->get('product_id_2');

        if (is_numeric($productId1) && is_numeric($productId2)) {
            $product1 = Product::findOrFail($productId1);
            $product2 = Product::findOrFail($productId2);

            $product1Ratings = $product1->ratings()->pluck('rating', 'user_id')->toArray();
            $product2Ratings = $product2->ratings()->pluck('rating', 'user_id')->toArray();

            $pearsonCorrelation = new PearsonCorrelation($product1Ratings, $product2Ratings);

            $data['explainer'] = $pearsonCorrelation->explain($product1->title, $product2->title);
        }

        $data['products'] = Product::orderBy('title')->get();

        return view('dev.pearson-correlation', $data);
    }

    public function submitRatings(Request $request)
    {
        $this->validate($request, [
            'ratings.*.user_id' => 'required|exists:users,user_id',
            'ratings.*.product_id' => 'required|exists:products,product_id',
            'ratings.*.rating' => 'required|numeric|min:1|max:5',
        ]);

        $ratings = $request->get('ratings');
        $users = User::whereIn('user_id', collect($ratings)->pluck('user_id')->toArray())->get()->keyBy('user_id');
        $products = Product::whereIn('product_id', collect($ratings)->pluck('product_id')->toArray())->get()->keyBy('product_id');

        foreach ($ratings as $rating) {
            $user = $users[$rating['user_id']];
            $product = $products[$rating['product_id']];
            $user->setRating($product, $rating['rating']);
        }

        Artisan::call('update-predictions');

        return response()->json([
            'status' => 'ok'
        ]);
    }

    public function resetRatings()
    {
        Rating::truncate(); 
        Prediction::truncate();

        return response()->json([
            'status' => 'ok'
        ]);
    }

    public function prediction(Request $request)
    {
        $userId = $request->get('user_id');
        $productId = $request->get('product_id');

        if ($userId && $productId) {
            $user = User::findOrfail($userId);
            $product = Product::findOrfail($productId);

            $cf = new ItemBasedCF($user, $product);

            $data['explainer'] = $cf->explain();
        }

        $data['users'] = User::where('type', User::TYPE_MEMBER)->orderBy('name')->get();
        $data['products'] = Product::orderBy('title')->get();

        return view('dev/prediction', $data);
    }
}
