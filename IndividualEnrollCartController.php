<?php

namespace App\Http\Controllers\employer;

use App\BoothReserve;
use App\EnrollReservation;
use App\Http\Controllers\Controller;
use App\IndividualEnrollCart;
use Illuminate\Http\Request;
use Auth;

class IndividualEnrollCartController extends Controller
{
    public function addToIndividualCart(Request $request)
    {
        Session()->forget('job_id');
        Session()->forget('cart_id');
        Session()->forget('total_amount');

        \App\IndividualEnrollCart::where('employer_id', auth()->guard('employer')->user()->id)->delete();
        $employer_id = Auth::guard("employer")->user()->id;

        $abc = IndividualEnrollCart::create([
            "employer_id" => $employer_id,            
            "reservation_id" => $request['reservation_id'],
            "booth_id" => $request['booth_id'],           
        ]);
        return response()->json(["message" =>"sucessfully inserted"], 200);
    }

    public function showCart()
    {
        $employer_id = Auth::guard("employer")->user()->id;
        $carts = IndividualEnrollCart::where('employer_id', $employer_id)->get();
        $datas = [];
        $datas['total_amount'] = 0;
        $datas['cart'] = [];
        foreach ($carts as $cart) {
            $res = EnrollReservation::where('id', $cart->reservation_id)->first();
            $job_id[] = $cart->id;
            $datas['total_amount'] += $cart->boothreserve->price;
            $datas['cart'][] = [
                'id' => $cart->id,
                'category' => $res->category->title,
                'company' => $res->company_name,
                'reservation_id' => $cart->reservation_id,
                'booth_name' => $cart->boothreserve->booth_name,
                'booth_type' => $cart->boothreserve->booth_type,
                'price' => $cart->boothreserve->price,
            ];



        }
        session(['cart_id' => rand(9999,15), 'total_amount' => $datas['total_amount'], 'job_id' => $job_id]);
        return view('employer.enroll_cart')->with('datas', $datas);
    }
}
