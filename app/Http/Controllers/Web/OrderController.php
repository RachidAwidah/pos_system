<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('orders.index', [
            'orders' => Order::query()->with(['customer:id,name', 'user:id,full_name'])->latest('order_date')->paginate(20),
        ]);
    }
}
