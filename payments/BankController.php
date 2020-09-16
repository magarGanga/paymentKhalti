<?php

namespace App\Http\Controllers\employer\payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Payments;
use App\Order;
use App\OrderItem;
use App\OrderHistory;
use App\Employers;
use Mail;
use App\Imagetool;
use App\library\myFunctions;
use File;
use Validator;
use App\library\Settings;
use Carbon\Carbon;

class BankController extends Controller
{
     


    public function index()
    {
      $opt = \App\Payments::where('payment_page', 'Bank')->first();
      $setting = json_decode($opt->setting);
      
       
        return view('employer.payments.bank')->with('data', $setting);

    }

    public function success(Request $request)
    {

     
        
      
       
        if (Session()->has('cart_id') && Session()->has('total_amount') && Session()->has('job_id')) {
         
           $order_id =  Session()->get('cart_id');
            $opt = \App\Payments::where('payment_page', 'Bank')->first();
            $setting = json_decode($opt->setting);

          $image = '';
          if ($request->hasFile('file'))  {
            $directory = DIR_IMAGE.'checkout';
            if (!is_dir($directory)) {
              mkdir($directory, 0777, true);
            }
            $this->validate($request,['file'=>'mimes:jpeg,jpg,png,gif|max:10000',]);
            $file = $request->File('file');
            $image = str_random(10).'.'.$file->getClientOriginalExtension();

            $file->move($directory, $image);
          }
           
        $total = Order::where('created_at', 'LIKE', date('Y').'%')->count();
        $new_number = $total + 1;
        $invoice_no = 'INV-'.date('Y').'-'.$new_number;
        $employer = Employers::where('id',auth()->guard('employer')->user()->employers_id)->first();
        $address = $employer->EmployerAddress;
        $order = Order::create([
          'order_by' => $employer->id, 
          'invoice_no' => $invoice_no, 
          'customer_name' => $employer->name,
          'email' => auth()->guard('employer')->user()->email,
          'telephone' => $address->phone,
          'comment' => $request->comment,
          'amount' => Session()->get('total_amount'),
          'order_status' => $setting->order_status,
          'payment_type' => 'Bank Transer'
        ]);

        if(isset($order->id)){
          $job_ids = Session()->get('job_id');
          foreach ($job_ids as $key => $job) {
             $type = '';
            $name = '';
            $cart = \App\Cart::where('id',$job)->first();
            if ($cart->type == 'Jobs') {

             //\App\Jobs::where('id',$cart->jobs_id)->update(['status' => 1]);
             $type = 'Job';
             $name = \App\Jobs::getTitle($cart->jobs_id);
             $product_id = $cart->jobs_id;
            } elseif ($cart->type == 'Tenders') {
              //\App\Tender::where('id',$cart->jobs_id)->update(['status' => 1]);
               $type = 'Tender';
              $name = \App\Tender::getTitle($cart->jobs_id);
              $product_id = $cart->jobs_id;
            }
            elseif ($cart->type == 'JobPackage') {
              $expiry_date = Carbon::now()->addDays(365)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 2,
                'type'          => 'Job'
              ]);
               $type = 'JobPackage';
                $name = 'Job Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }
            elseif ($cart->type == 'ResumePackage') {
              $expiry_date = Carbon::now()->addDays($cart->duration)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 2,
                'type'          => 'Resume'
              ]);
               $type = 'ResumePackage';
                $name = 'Resume Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }
            elseif ($cart->type == 'MemberUpgrade') {
              $expiry_date = Carbon::now()->addMonths($cart->duration)->format('Y-m-d');
              // Employers::where('id',$cart->employers_id)->update(['member_type' => $cart->job_type_id]);
           
                $empp = \App\UpgradeRequest::create([
                  'employers_id' => $cart->employers_id,
                  'member_type_id' => $cart->job_type_id,
                    'status' => '0',
                    'start_date' => date('Y-m-d'),
                    'end_date' => $expiry_date
                ]);
               $type = 'MemberUpgrade';
                $name = 'Member Upgrade('.$cart->job_type.')';
                $product_id = $empp->id;
            }
            
             elseif ($cart->type == 'TenderPackage') {
              $expiry_date = Carbon::now()->addDays(365)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 2,
                'type'          => 'Tender'
              ]);
               $type = 'TenderPackage';
                $name = 'Tender Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }
            
            OrderItem::create([
              'order_id' => $order->id, 
              'product_id' => $product_id, 
              'product_type' => $cart->job_type,
              'name' => $name,
              'type' => $type,
              'duration' => $cart->duration,
              'amount' => $cart->amount
            ]);
          }

           OrderHistory::create([
              'order_id' => $order->id, 
              'order_status' => $setting->order_status, 
              'notify' => 1,
              'comment' => 'Order Placed Successfully',
              'document' => $image
            ]);

           $mydata = array(
                    'to_name' => $employer->name, 
                    'to_email' => auth()->guard('employer')->user()->email,
                    'subject' => 'Order Invoice',
                    'order_detail' => Order::where('id',$order->id)->first(),
                    
                    'from_name' => Settings::getSettings()->name,
                    'logo' => Settings::getImages()->logo,
                    'from_email' => Settings::getSettings()->email,

                     'store_address' => Settings::getSettings()->address,
                     'store_phone' => Settings::getSettings()->telephone,
                                      
                    );
        set_time_limit(600);
         myFunctions::setEmail();
        Mail::send('mail.job_order', ['data' => $mydata], function($mail) use ($mydata){
                  $mail->to($mydata['to_email'],$mydata['to_name'])->from($mydata['from_email'],$mydata['from_name'])->subject($mydata['subject']);
                });
    }


          Session()->forget('job_id');
          Session()->forget('cart_id');
          Session()->forget('total_amount');
          \App\Cart::where('employers_id', auth()->guard('employer')->user()->employers_id)->delete();
          \Session::flash('alert-success','Record have been saved Successfully');
        return redirect('employer/order');

      
   



            # code...
          
        } else{
          \Session::flash('alert-danger','session not found');
          return redirect('employer/order');
        }

        
   

    }

     
     
  
   

}
