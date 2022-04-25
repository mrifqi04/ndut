<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $per_page = 10;
        $order_col = 'user_id';
        $order_asc = 'desc';
        $keyword = $request->get('keyword');

        $query = User::where('type', 'member');
        if ($keyword) {
            $query->where(function($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%");
                $q->orWhere('type', 'like', "%{$keyword}%");
            });
        }


        $users = $query->paginate($per_page);

        return view('admin.pages.users.index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.pages.users.form', [
            'user' => new User,
            'title' => 'Tambah User',
        ]);
    }

    public function store(Request $request)
    {
        $user = new User;
        $request->password = bcrypt($request->password);
        $this->save($user, $request);

        return redirect()
            ->route('admin::users.index')
            ->with('info', "User '{$user->name}' berhasil ditambahkan.");
    }

    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);

        return view('admin.pages.users.show', [
            'user' => $user
        ]);
    }

    public function edit(Request $request, $id)
    {
        $user = User::findOrFail($id);

        return view('admin.pages.users.form', [
            'user' => $user,
            'title' => "Edit User",
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if($request->password != null) {
            $request->password = bcrypt($request->password);
            $this->save($user, $request);
        } else {
            $this->save($user, $request);
        }

        return redirect()
            ->route('admin::users.index')
            ->with('info', "Users '{$users->name}' berhasil di update.");
    }

    public function delete(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return back()
            ->with('info', "User '{$user->name}' telah dihapus.");
    }

    public function save(product $product, Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'cover' => $product->exists ? 'image|mimes:jpeg,png' : 'required|image|mimes:jpeg,png',
            'stock' => 'required|numeric',
            'price' => 'required|numeric',
            'category_ids.*' => 'numeric|exists:categories,category_id',
        ]);

        $cover = $request->file('cover');
        if ($cover) {
            // Hapus cover yg lama
            if ($product->cover) {
                Storage::disk('uploads')->delete("products/{$product->cover}");
            }

            // Simpan cover
            $cover_filename = str_slug($request->get('title')).'.'.$cover->extension();
            $cover->storeAs('products', $cover_filename, 'uploads');

            // Set nilai cover baru
            $product->cover = $cover_filename;
        }

        $product->title = $request->get('title');
        $product->slug = str_slug($product->title);
        $product->description = $request->get('description');
        $product->stock = $request->get('stock');
        $product->price = $request->get('price');
        $product->save();

        $category_ids = $request->get('category_ids');
        $product->categories()->sync($category_ids);
    }
}
