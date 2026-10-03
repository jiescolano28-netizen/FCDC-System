<?php

namespace App\Http\Controllers;

use App\Models\User;



class UserController extends Controller
{
    public function index() {
        return User::all();
    }

    // MANUAL ADDING OF USERS
    public function add() {
        $employee = new User();
        
        $employee->username = 'ceasar';
        $employee->save();

        return 'added successfully';
    }

 
}
