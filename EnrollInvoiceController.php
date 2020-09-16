<?php

namespace App\Http\Controllers\employer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\EnrollInvoice;
use App\Setting;


class EnrollInvoiceController extends Controller
{
    public function invoiceEnroll(){
        $invoice = \App\EnrollInvoice::where('invoice_by', auth()->guard('employer')->user()->employers_id)
        ->with('enrollinvoiceHistory')
        ->orderBy('created_at', 'desc')
        ->paginate(50);

        $user = \App\Employers::where('id', auth()->guard('employer')->user()->employers_id)->first();
        $data['user'] = $user;
        $data['invoice'] = $invoice;
        return view('employer.enroll_invoice',compact('data'));
    }

    public function show(EnrollInvoice $invoice)
    {
        $data['invoice'] = $invoice->load('enrollinvoiceItem', 'enrollinvoiceHistory');

        $setting = Setting::first();
        $data['logo'] = $setting->settingImage['logo'];
        $data['store'] = $setting->name;
        $data['store_address'] = $setting->address;
        $data['store_phone'] = $setting->telephone;
        $data['store_email'] = $setting->email;
        return view('employer.invoice.editform')->with('data', $data);
    }

    public function print(Request $request, EnrollInvoice $invoice)
    {
        $data['invoice'] = $invoice;
        $setting = Setting::first();
        $data['logo'] = $setting->settingImage['logo'];
        $data['store'] = $setting->name;
        $data['store_address'] = $setting->address;
        $data['store_phone'] = $setting->telephone;
        $data['store_email'] = $setting->email;
        return view('employer.invoice.print')->with('data', $data);
    }
}
