<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        return view('contacts.index', [
            'contacts' => Contact::query()->orderBy('name')->paginate(20),
        ]);
    }
}
