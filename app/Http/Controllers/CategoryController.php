<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CategoryController extends Controller
{
    public function hello(): View
    {
        return view('hello');
    }
}
