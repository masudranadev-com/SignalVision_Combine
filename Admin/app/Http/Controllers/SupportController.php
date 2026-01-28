<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupportController extends Controller
{
    //index
    public function index()
    {
        return view("support.index");
    }
}
